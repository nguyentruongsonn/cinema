<?php

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
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    /**
     * Register a new user.
     */
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
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Login user by email or username.
     */
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

            $response = $this->successResponse(
                ['user' => $result['user'], 'token_type' => $result['token_type'], 'expires_in' => $result['expires_in']],
                'Login successful'
            );

            return $this->setAuthCookies($response, $result['access_token'], $result['expires_in'], $result['refresh_token'], $result['refresh_expires_in']);
        } catch (\Throwable $e) {
            return $this->errorResponse('Login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Login or register user with Google ID token.
     */
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

            $response = $this->successResponse(
                ['user' => $result['user'], 'token_type' => $result['token_type'], 'expires_in' => $result['expires_in']],
                'Google login successful'
            );

            return $this->setAuthCookies($response, $result['access_token'], $result['expires_in'], $result['refresh_token'], $result['refresh_expires_in']);
        } catch (\Throwable $e) {
            return $this->errorResponse('Google login failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Get current authenticated user.
     */
    public function me(): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->authService->getAuthenticatedUser(),
                'User retrieved successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Logout current user.
     */
    public function logout(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $refreshToken = $request->cookie('refresh_token');
            $this->authService->logout($refreshToken);

            $response = $this->successResponse(null, 'Logout successful');

            return $response
                ->withCookie(cookie()->forget('access_token'))
                ->withCookie(cookie()->forget('refresh_token'));
        } catch (\Throwable $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Refresh access token using refresh token from cookie.
     */
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
            return $this->errorResponse('Token refresh failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Get user profile.
     */
    public function profile(): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->authService->getUserProfile(),
                'Profile retrieved successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->authService->updateProfile($request->validated()),
                'Profile updated successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Change current user's password.
     */
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
            return $this->errorResponse('Failed to change password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authService->sendPasswordResetLink($request->validated('email'));

            if ($status === Password::RESET_LINK_SENT) {
                return $this->successResponse(null, 'Password reset link sent to your email');
            }

            return $this->errorResponse('Failed to send reset link', 400);
        } catch (\Throwable $e) {
            report($e);

            // Do not expose password reset internals or infrastructure failures to clients.
            // This also avoids user enumeration and reset-token leakage in API responses.
            return $this->successResponse(null, 'If the email exists, a password reset link will be sent');
        }
    }

    /**
     * Reset user password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authService->resetPassword($request->validated());

            if ($status === Password::PASSWORD_RESET) {
                return $this->successResponse(null, 'Password reset successfully');
            }

            return $this->errorResponse('Failed to reset password', 400);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to reset password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send email verification notification.
     */
    public function sendVerificationEmail(): JsonResponse
    {
        try {
            $sent = $this->authService->sendEmailVerification();

            if (!$sent) {
                return $this->errorResponse('Email already verified', 400);
            }

            return $this->successResponse(null, 'Verification email sent');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to send verification email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify user email.
     */
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
            return $this->errorResponse('Failed to verify email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Set access and refresh tokens in HttpOnly Secure cookies.
     */
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
                domain: null,
                secure: config('session.secure', true),
                httpOnly: true,
                raw: false,
                sameSite: config('session.same_site', 'lax')
            ))
            ->withCookie(cookie(
                name: 'refresh_token',
                value: $refreshToken,
                minutes: (int) ceil($refreshExpiresIn / 60),
                path: '/',
                domain: null,
                secure: config('session.secure', true),
                httpOnly: true,
                raw: false,
                sameSite: config('session.same_site', 'lax')
            ));
    }
}
