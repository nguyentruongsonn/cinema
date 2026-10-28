<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    // Đăng ký tài khoản và cấp cặp token JWT (access + refresh)
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register(
                $request->validated(),
                $request->ip(),
                $request->userAgent()
            );

            $response = $this->successResponse(
                ['user' => $result['user'], 'token_type' => $result['token_type'], 'expires_in' => $result['expires_in']],
                'User registered successfully',
                201
            );

            return $this->setAuthCookies($response, $result['access_token'], $result['expires_in'], $result['refresh_token'], $result['refresh_expires_in']);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Registration failed', 500);
        }
    }

    // Đăng nhập: kiểm tra thông tin, giới hạn thử và trả về JWT token
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated(),
                $request->ip(),
                $request->userAgent()
            );

            if (!$result) {
                return $this->errorResponse('Invalid credentials or account inactive', 401);
            }

            $user = $result['user'];
            $redirectUrl = '/';

            if ($user->canAccessAdminPanel()) {
                $redirectUrl = route($user->adminLandingRouteName(), [], false);
            }

            $response = $this->successResponse(
                [
                    'user' => $user,
                    'token_type' => $result['token_type'],
                    'expires_in' => $result['expires_in'],
                    'redirect_url' => $redirectUrl,
                ],
                'Login successful'
            );

            return $this->setAuthCookies($response, $result['access_token'], $result['expires_in'], $result['refresh_token'], $result['refresh_expires_in']);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Login failed', 500);
        }
    }

    // Đăng nhập nhanh bằng Google qua id_token
    public function googleLogin(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $result = $this->authService->loginWithGoogle(
                $validated['id_token'],
                $request->ip(),
                $request->userAgent()
            );

            $user = $result['user'];
            $redirectUrl = '/';

            if ($user->canAccessAdminPanel()) {
                $redirectUrl = route($user->adminLandingRouteName(), [], false);
            }

            $response = $this->successResponse(
                [
                    'user' => $user,
                    'token_type' => $result['token_type'],
                    'expires_in' => $result['expires_in'],
                    'redirect_url' => $redirectUrl,
                ],
                'Google login successful'
            );

            return $this->setAuthCookies($response, $result['access_token'], $result['expires_in'], $result['refresh_token'], $result['refresh_expires_in']);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Google login failed', 401);
        }
    }

    public function me(): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->authService->getAuthenticatedUser(),
                'User retrieved successfully'
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to retrieve user', 500);
        }
    }

    // Đăng xuất: thu hồi refresh token và xóa sạch cookie xác thực
    public function logout(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $refreshToken = $request->cookie('refresh_token');
            $this->authService->logout($refreshToken);

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $response = $this->successResponse(null, 'Logout successful');

            return $response
                ->withCookie(cookie()->forget('access_token', '/', config('session.domain')))
                ->withCookie(cookie()->forget('refresh_token', '/', config('session.domain')))
                ->withCookie(cookie()->forget(config('session.cookie'), '/', config('session.domain')));
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Logout failed', 500);
        }
    }

    // Cấp access token mới từ refresh token còn hạn
    public function refresh(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $refreshToken = $request->cookie('refresh_token');

            if (!$refreshToken) {
                return $this->errorResponse('Refresh token not found', 401);
            }

            $result = $this->authService->refreshAccessToken(
                $refreshToken,
                $request->ip(),
                $request->userAgent()
            );

            $response = $this->successResponse(
                ['user' => $result['user'], 'token_type' => $result['token_type'], 'expires_in' => $result['expires_in']],
                'Token refreshed successfully'
            );

            return $this->setAuthCookies($response, $result['access_token'], $result['expires_in'], $result['refresh_token'], $result['refresh_expires_in']);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Token refresh failed', 401);
        }
    }

    public function profile(): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->authService->getUserProfile(),
                'Profile retrieved successfully'
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to retrieve profile', 500);
        }
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->authService->updateProfile($request->validated()),
                'Profile updated successfully'
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to update profile', 500);
        }
    }

    // Đổi mật khẩu: xác minh mật khẩu hiện tại và hủy toàn bộ phiên cũ
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $changed = $this->authService->changePassword(
                $request->validated('current_password'),
                $request->validated('new_password')
            );

            if (!$changed) {
                return $this->errorResponse('Current password is incorrect', 400);
            }

            return $this->successResponse(null, 'Password changed successfully');
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to change password', 500);
        }
    }

    // Gửi link đặt lại mật khẩu qua email
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->sendPasswordResetLink($request->validated('email'));
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->successResponse(null, 'If the email exists, a password reset link will be sent');
    }

    // Đặt lại mật khẩu mới từ link xác thực email
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authService->resetPassword($request->validated());

            if ($status === Password::PASSWORD_RESET) {
                return $this->successResponse(null, 'Password reset successfully');
            }

            return $this->errorResponse('Failed to reset password', 400);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to reset password', 500);
        }
    }

    // Gửi link xác thực tài khoản qua email
    public function sendVerificationEmail(): JsonResponse
    {
        try {
            $sent = $this->authService->sendEmailVerification();

            if (!$sent) {
                return $this->errorResponse('Email already verified', 400);
            }

            return $this->successResponse(null, 'Verification email sent');
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to send verification email', 500);
        }
    }

    // Xác minh email người dùng hợp lệ
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        try {
            $verified = $this->authService->verifyEmail(
                (int) $request->validated('id'),
                (string) $request->validated('hash')
            );

            if (!$verified) {
                return $this->errorResponse('Invalid verification link or email already verified', 400);
            }

            return $this->successResponse(null, 'Email verified successfully');
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to verify email', 500);
        }
    }

    private function setAuthCookies(
        JsonResponse $response,
        string $accessToken,
        int $accessExpiresIn,
        string $refreshToken,
        int $refreshExpiresIn
    ): JsonResponse {
        return $response
            ->withCookie(cookie(
                name: 'access_token',
                value: $accessToken,
                minutes: (int) ceil($accessExpiresIn / 60),
                path: '/',
                domain: config('session.domain'),
                secure: config('session.secure'),
                httpOnly: true,
                raw: false,
                sameSite: config('session.same_site', 'lax')
            ))
            ->withCookie(cookie(
                name: 'refresh_token',
                value: $refreshToken,
                minutes: (int) ceil($refreshExpiresIn / 60),
                path: '/',
                domain: config('session.domain'),
                secure: config('session.secure'),
                httpOnly: true,
                raw: false,
                sameSite: config('session.same_site', 'lax')
            ));
    }
}
