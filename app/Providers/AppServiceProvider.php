<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Branch;
use App\Models\Combo;
use App\Models\Format;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Screen;
use App\Models\SeatHold;
use App\Models\SeatLayoutTemplate;
use App\Models\Showtime;
use App\Models\Sound;
use App\Models\Theater;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\BannerPolicy;
use App\Policies\BranchPolicy;
use App\Policies\ComboPolicy;
use App\Policies\FormatPolicy;
use App\Policies\MoviePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PostPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\ScreenPolicy;
use App\Policies\SeatLayoutTemplatePolicy;
use App\Policies\ShowtimePolicy;
use App\Policies\SoundPolicy;
use App\Policies\TheaterPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configurePasswordResetNotification();
        $this->registerPolicies();
        $this->configureMorphMap();
        $this->configureSlowQueryLogging();
    }

    private function configurePasswordResetNotification(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
            $expiresIn = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Đặt lại mật khẩu tài khoản CINEMA')
                ->greeting('Xin chào '.$user->name.',')
                ->line('Hệ thống nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.')
                ->action('Đổi mật khẩu', $url)
                ->line("Liên kết này chỉ có hiệu lực trong {$expiresIn} phút và chỉ sử dụng được một lần.")
                ->line('Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email.');
        });
    }

    /**
     * Configure polymorphic morph map for audit logs and other polymorphic relations.
     *
     * This decouples database polymorphic type values from internal class names,
     * allowing class refactoring without breaking existing audit trails.
     */
    private function configureMorphMap(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'role' => Role::class,
            'order' => Order::class,
            'payment' => Payment::class,
            'movie' => Movie::class,
            'showtime' => Showtime::class,
            'screen' => Screen::class,
            'theater' => Theater::class,
            'branch' => Branch::class,
            'product' => Product::class,
            'combo' => Combo::class,
            'promotion' => Promotion::class,
            'banner' => Banner::class,
            'post' => Post::class,
            'seat_layout_template' => SeatLayoutTemplate::class,
            'ticket' => Ticket::class,
            'seat_hold' => SeatHold::class,
        ]);
    }

    /**
     * Configure rate limiters for different endpoint types.
     *
     * Authentication limiters use composite keys (IP + identifier) to prevent
     * both IP-based and account-targeted abuse.
     */
    private function configureRateLimiters(): void
    {
        // Login - strict limit by IP + normalized login identifier
        // Prevents brute-force attacks against specific accounts
        RateLimiter::for('login', function (Request $request) {
            $login = $this->normalizeLoginIdentifier($request);
            $key = $request->ip().'|'.$login;

            return Limit::perMinute(5)
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'message' => 'Quá nhiều lần đăng nhập. Vui lòng thử lại sau.',
                    ], 429);
                });
        });

        // Registration - limit by IP and email separately
        // Prevents mass fake account creation and email bombing
        RateLimiter::for('register', function (Request $request) {
            $email = $this->normalizeEmail($request->input('email'));

            return [
                Limit::perMinute(3)->by($request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' => 'Quá nhiều lần đăng ký. Vui lòng thử lại sau.',
                        ], 429);
                    }),
                Limit::perHour(5)->by('email:'.$email)
                    ->response(function () {
                        return response()->json([
                            'message' => 'Email này đã được sử dụng quá nhiều. Vui lòng thử lại sau.',
                        ], 429);
                    }),
            ];
        });

        // Forgot password - strict limit by IP and email
        // Prevents email bombing and user enumeration through timing
        RateLimiter::for('forgot-password', function (Request $request) {
            $email = $this->normalizeEmail($request->input('email'));

            return [
                Limit::perMinute(2)->by($request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' => 'Quá nhiều yêu cầu đặt lại mật khẩu. Vui lòng thử lại sau.',
                        ], 429);
                    }),
                Limit::perHour(3)->by('forgot:'.$email)
                    ->response(function () {
                        return response()->json([
                            'message' => 'Quá nhiều yêu cầu đặt lại mật khẩu cho email này. Vui lòng thử lại sau.',
                        ], 429);
                    }),
            ];
        });

        // Reset password - limit by IP and email
        // Prevents token brute-force and replay attacks
        RateLimiter::for('reset-password', function (Request $request) {
            $email = $this->normalizeEmail($request->input('email'));
            $key = $request->ip().'|reset:'.$email;

            return Limit::perMinute(3)
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'message' => 'Quá nhiều lần đặt lại mật khẩu. Vui lòng thử lại sau.',
                    ], 429);
                });
        });

        // General authentication endpoints (refresh, verify, etc.)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // General API endpoints
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Order/Booking endpoints - moderate limit
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Ticket viewing endpoints - generous limit for read-only operations
        RateLimiter::for('tickets', function (Request $request) {
            return Limit::perMinute(40)->by($request->user()?->id ?: $request->ip());
        });

        // Payment endpoints - strict limit for financial operations
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Seat lock/unlock - allow rapid seat selection
        RateLimiter::for('seats', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Booking page access - moderate limit to prevent showtime enumeration
        RateLimiter::for('booking', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('pos', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Webhook callbacks - per hour limit by IP
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perHour(100)->by($request->ip());
        });
    }

    /**
     * Normalize login identifier (email or username) for rate limiting.
     *
     * Applies same normalization as LoginRequest::prepareForValidation().
     * IMPORTANT: Keep this synchronized with request normalization logic.
     */
    private function normalizeLoginIdentifier(Request $request): string
    {
        $login = $request->input('login')
            ?? $request->input('email')
            ?? $request->input('username')
            ?? '';

        if (! is_string($login)) {
            return '';
        }

        $login = trim($login);

        // Lowercase if it's an email
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $login = strtolower($login);
        }

        return $login;
    }

    /**
     * Normalize email for rate limiting.
     *
     * Applies same normalization as should be in FormRequest::prepareForValidation().
     * IMPORTANT: Keep this synchronized with request normalization logic.
     */
    private function normalizeEmail(?string $email): string
    {
        if (! is_string($email)) {
            return '';
        }

        return strtolower(trim($email));
    }

    /**
     * Log slow database queries for production observability.
     *
     * Bindings are intentionally not logged because they may contain PII
     * such as emails, phone numbers, tokens, or customer-entered data.
     */
    private function configureSlowQueryLogging(): void
    {
        if (! config('app.slow_query_log_enabled', false)) {
            return;
        }

        $thresholdMs = (float) config('app.slow_query_threshold_ms', 100);

        DB::listen(function ($query) use ($thresholdMs) {
            if ($query->time < $thresholdMs) {
                return;
            }

            Log::warning('Slow database query detected', [
                'time_ms' => round($query->time, 2),
                'connection' => $query->connectionName,
                'sql' => Str::limit($query->sql, 1000),
                'route' => request()->route()?->getName(),
                'method' => request()->method(),
                'path' => request()->path(),
                'user_id' => Auth::id(),
            ]);
        });
    }

    /**
     * Register authorization policies.
     *
     * Policies enforce IDOR protection and proper authorization.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Banner::class, BannerPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Combo::class, ComboPolicy::class);
        Gate::policy(Format::class, FormatPolicy::class);
        Gate::policy(Movie::class, MoviePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(Screen::class, ScreenPolicy::class);
        Gate::policy(SeatLayoutTemplate::class, SeatLayoutTemplatePolicy::class);
        Gate::policy(Showtime::class, ShowtimePolicy::class);
        Gate::policy(Sound::class, SoundPolicy::class);
        Gate::policy(Theater::class, TheaterPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::define('viewDashboardMetrics', fn (User $user): bool => $user->hasPermission('dashboard.view')
        );
    }
}
