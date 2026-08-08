<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 10;
    private const LOCKOUT_SECONDS = 900;

    public function register(array $data, string $ipAddress, ?string $userAgent = null): array
    {
        $data['email'] = $this->normalizeEmail($data['email']);
        $rawPhone = $data['phone'] ?? null;
        $normalizedPhone = null;

        if ($rawPhone) {
            $normalizedPhone = preg_replace('/\D/', '', (string) $rawPhone);
            if (str_starts_with($normalizedPhone, '84')) {
                $normalizedPhone = '0' . substr($normalizedPhone, 2);
            }
        }

        // Check if there is an unclaimed POS customer account with this phone number
        $unclaimedUser = null;
        if ($normalizedPhone) {
            $unclaimedUser = User::query()
                ->where('account_status', 'unclaimed')
                ->where(function ($q) use ($normalizedPhone) {
                    $q->where('phone', $normalizedPhone)
                      ->orWhere('phone', ltrim($normalizedPhone, '0'))
                      ->orWhere('phone', '0' . ltrim($normalizedPhone, '0'));
                })
                ->first();
        }

        if ($unclaimedUser) {
            // Claim existing POS account: set name, email, password, username, account_status = claimed
            // Keeps all accumulated loyalty_points!
            $unclaimedUser->forceFill([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'username'       => $data['username'] ?? null,
                'phone'          => $normalizedPhone ?: $rawPhone,
                'password'       => Hash::make($data['password']),
                'status'         => 1,
                'account_status' => 'claimed',
            ])->save();

            $user = $unclaimedUser;

            Log::info('POS unclaimed user account claimed via online registration', [
                'user_id'         => $user->id,
                'phone'           => $normalizedPhone,
                'retained_points' => $user->loyalty_points,
            ]);
        } else {
            $user = User::create([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'username'       => $data['username'] ?? null,
                'phone'          => $normalizedPhone ?: $rawPhone,
                'password'       => Hash::make($data['password']),
                'status'         => 1,
                'account_status' => 'claimed',
            ]);
        }

        $this->assignDefaultRole($user);

        $accessToken = JWTAuth::fromUser($user);
        $refreshTokenData = RefreshToken::generate(
            $user->id,
            $data['device_name'] ?? null,
            $ipAddress,
            $userAgent
        );

        $user->load('role.permissions');

        Log::info('User registered successfully', ['user_id' => $user->id]);

        return $this->dualTokenPayload($user, $accessToken, $refreshTokenData['plain_token']);
    }

    public function login(array $credentials, string $ipAddress, ?string $userAgent = null): ?array
    {
        $login = trim((string) ($credentials['login'] ?? $credentials['email'] ?? ''));
        $rateKey = $this->loginRateKey($login, $ipAddress);

        if ($this->isRateLimited($rateKey)) {
            throw new RuntimeException('Tài khoản/IP đăng nhập quá nhiều lần. Vui lòng thử lại sau 15 phút.');
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if ($field === 'email') {
            $login = $this->normalizeEmail($login);
        }
        $loginMethod = $field === 'email' ? 'email' : 'username';

        $user = User::where($field, $login)->first();

        $loginCredentials = [
            $field => $login,
            'password' => $credentials['password'],
            'status' => 1,
        ];

        $accessToken = JWTAuth::attempt($loginCredentials);

        if (!$accessToken) {
            $this->hitRateLimit($rateKey);

            if ($user) {
                LoginHistory::record(
                    $user->id,
                    $ipAddress,
                    $userAgent,
                    $loginMethod,
                    false,
                    'Invalid credentials or inactive account'
                );
            }

            Log::warning('Login failed', ['login' => $login, 'ip' => $ipAddress]);

            return null;
        }

        Cache::forget($rateKey);

        /** @var User $user */
        $user = auth()->user();
        $sessionToken = hash('sha256', $accessToken);

        $this->updateLastLogin($user, $ipAddress);

        LoginHistory::record(
            $user->id,
            $ipAddress,
            $userAgent,
            $loginMethod,
            true,
            null,
            $sessionToken
        );

        $refreshTokenData = RefreshToken::generate(
            $user->id,
            $credentials['device_name'] ?? null,
            $ipAddress,
            $userAgent
        );

        $user->load('role.permissions');

        Log::info('User logged in successfully', ['user_id' => $user->id]);

        return $this->dualTokenPayload($user, $accessToken, $refreshTokenData['plain_token']);
    }

    public function loginWithGoogle(string $idToken, string $ipAddress, ?string $userAgent = null): array
    {
        $googleUser = $this->verifyGoogleIdToken($idToken);

        $email = isset($googleUser['email']) ? $this->normalizeEmail($googleUser['email']) : null;

        if (!$email) {
            throw new RuntimeException('Google token không hợp lệ.');
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser['name'] ?? Str::before($email, '@'),
                'username' => $this->uniqueUsername(Str::slug(Str::before($email, '@'), '_')),
                'avatar_url' => $googleUser['picture'] ?? null,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
                'status' => 1,
            ]
        );

        if (!$user->status) {
            throw new RuntimeException('Tài khoản đã bị khóa.');
        }

        $this->assignDefaultRole($user);
        $this->updateLastLogin($user, $ipAddress);

        $accessToken = JWTAuth::fromUser($user);
        $sessionToken = hash('sha256', $accessToken);

        LoginHistory::record(
            $user->id,
            $ipAddress,
            $userAgent,
            'google',
            true,
            null,
            $sessionToken
        );

        $refreshTokenData = RefreshToken::generate(
            $user->id,
            null,
            $ipAddress,
            $userAgent
        );

        $user->load('role.permissions');

        return $this->dualTokenPayload($user, $accessToken, $refreshTokenData['plain_token']);
    }

    public function getAuthenticatedUser(): User
    {
        return auth()->user()->load('role.permissions');
    }

    public function getUserProfile(): User
    {
        return auth()->user()->load('role.permissions', 'orders', 'loginHistories');
    }

    public function updateProfile(array $data): User
    {
        $user = auth()->user();
        $user->update($data);

        Log::info('User profile updated', ['user_id' => $user->id]);

        return $user->fresh()->load('role.permissions');
    }

    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        $user = auth()->user();

        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        RefreshToken::revokeAllForUser($user->id);

        Log::info('Password changed successfully and refresh tokens revoked', ['user_id' => $user->id]);

        return true;
    }

    public function sendPasswordResetLink(string $email): string
    {
        $email = $this->normalizeEmail($email);
        $status = Password::sendResetLink(['email' => $email]);

        Log::info('Password reset link sent', ['email' => $email, 'status' => $status]);

        return $status;
    }

    public function resetPassword(array $data): string
    {
        return Password::reset(
            $data,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                RefreshToken::revokeAllForUser($user->id);

                event(new PasswordReset($user));

                Log::info('Password reset successfully and refresh tokens revoked', ['user_id' => $user->id]);
            }
        );
    }

    public function sendEmailVerification(): bool
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            return false;
        }

        VerifyEmail::createUrlUsing(function (User $notifiable): string {
            return URL::to('/api/v1/auth/verify-email') . '?' . http_build_query([
                'id' => $notifiable->getKey(),
                'hash' => $this->createEmailVerificationToken($notifiable),
            ]);
        });

        $user->notify(new VerifyEmail());

        Log::info('Email verification sent', ['user_id' => $user->id]);

        return true;
    }

    public function verifyEmail(int $userId, string $hash): bool
    {
        $user = User::findOrFail($userId);

        if (!$this->isValidEmailVerificationToken($user, $hash)) {
            Log::warning('Invalid email verification hash', ['user_id' => $userId]);
            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();

        Log::info('Email verified successfully', ['user_id' => $user->id]);

        return true;
    }

    public function logout(?string $plainRefreshToken = null): void
    {
        $token = JWTAuth::getToken();
        $userId = auth()->id();

        if ($token && $userId) {
            LoginHistory::where('user_id', $userId)
                ->where('session_token', hash('sha256', (string) $token))
                ->whereNull('logged_out_at')
                ->latest()
                ->first()
                ?->markLoggedOut();

            JWTAuth::setToken($token)->invalidate();
        }

        if ($plainRefreshToken) {
            RefreshToken::findByPlainToken($plainRefreshToken)?->revoke();
        }

        Log::info('User logged out', ['user_id' => $userId]);
    }

    public function refreshAccessToken(string $plainRefreshToken, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        return DB::transaction(function () use ($plainRefreshToken, $ipAddress, $userAgent): array {
            $refreshToken = RefreshToken::query()
                ->where('token', hash('sha256', $plainRefreshToken))
                ->lockForUpdate()
                ->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            throw new RuntimeException('Refresh token không hợp lệ hoặc đã hết hạn.');
        }

        $user = $refreshToken->user;

        if (!$user || !$user->status) {
            $refreshToken->revoke();
            throw new RuntimeException('Tài khoản không tồn tại hoặc đã bị khóa.');
        }

        $refreshToken->markAsUsed();
        $refreshToken->revoke();

        $accessToken = JWTAuth::fromUser($user);
        $newRefreshTokenData = RefreshToken::generate(
            $user->id,
            $refreshToken->device_name,
            $ipAddress ?? $refreshToken->ip_address,
            $userAgent ?? $refreshToken->user_agent
        );

        $user->load('role.permissions');

        Log::info('Access token refreshed with refresh token rotation', ['user_id' => $user->id]);

            return $this->dualTokenPayload($user, $accessToken, $newRefreshTokenData['plain_token']);
        }, 3);
    }

    public function createEmailVerificationToken(User $user, ?int $expiresAt = null): string
    {
        $expiresAt ??= now()->addMinutes((int) config('auth.passwords.users.expire', 60))->getTimestamp();
        $payload = $user->getKey() . '|' . $this->normalizeEmail($user->getEmailForVerification()) . '|' . $expiresAt;

        return $expiresAt . '.' . hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    private function assignDefaultRole(User $user): void
    {
        $userRole = Role::whereIn('slug', ['customer', 'user'])
            ->orderByRaw("CASE WHEN slug = 'customer' THEN 0 ELSE 1 END")
            ->first();

        if ($userRole) {
            $user->role()->associate($userRole);
            $user->save();
        }
    }
    private function updateLastLogin(User $user, string $ipAddress): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

    private function dualTokenPayload(User $user, string $accessToken, string $refreshToken): array
    {
        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'refresh_expires_in' => config('auth.refresh_token_ttl', 30) * 24 * 60 * 60,
        ];
    }

    private function loginRateKey(string $login, string $ipAddress): string
    {
        return 'login_attempts:' . sha1(Str::lower($login) . '|' . $ipAddress);
    }

    private function isRateLimited(string $key): bool
    {
        return (int) Cache::get($key, 0) >= self::MAX_LOGIN_ATTEMPTS;
    }

    private function hitRateLimit(string $key): void
    {
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, self::LOCKOUT_SECONDS);
    }

    private function verifyGoogleIdToken(string $idToken): array
    {
        $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Không thể xác thực Google token.');
        }

        $payload = $response->json();

        if (($payload['aud'] ?? null) !== config('services.google.client_id')) {
            throw new RuntimeException('Google Client ID không hợp lệ.');
        }

        if (($payload['email_verified'] ?? 'false') !== 'true') {
            throw new RuntimeException('Email Google chưa được xác thực.');
        }

        return $payload;
    }

    private function uniqueUsername(string $base): string
    {
        $base = $base ?: 'user';
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . $counter++;
        }

        return $username;
    }

    private function isValidEmailVerificationToken(User $user, string $token): bool
    {
        if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $matches)) {
            return false;
        }

        $expiresAt = (int) $matches[1];
        if ($expiresAt < now()->getTimestamp()) {
            return false;
        }

        return hash_equals($this->createEmailVerificationToken($user, $expiresAt), $token);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
