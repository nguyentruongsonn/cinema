@extends('layouts.app')

@section('title', 'Đặt lại mật khẩu')

@section('content')
<section class="cinema-auth-page py-5" aria-labelledby="resetPasswordTitle">
    <div class="container">
        <div class="cinema-auth-page-card mx-auto">
            <div class="cinema-auth-page-header">
                <span class="cinema-auth-page-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                <div>
                    <p class="cinema-auth-page-eyebrow">Bảo mật tài khoản</p>
                    <h1 id="resetPasswordTitle">Đặt lại mật khẩu</h1>
                </div>
            </div>

            <p class="cinema-auth-panel-copy mb-4">Tạo mật khẩu mới gồm ít nhất 8 ký tự, có chữ và số.</p>

            <form id="resetPasswordFormElement" class="cinema-auth-form" novalidate>
                <input type="hidden" name="token" value="{{ request()->route('token') }}">
                <input type="hidden" name="email" value="{{ request('email') }}">

                <div class="mb-3">
                    <label for="resetPassword" class="form-label cinema-auth-label">MẬT KHẨU MỚI</label>
                    <div class="cinema-auth-input-wrapper">
                        <i class="bi bi-lock cinema-auth-icon" aria-hidden="true"></i>
                        <input type="password" class="form-control cinema-auth-input" id="resetPassword"
                            name="password" required minlength="8" autocomplete="new-password">
                        <button class="cinema-auth-toggle-password" type="button" data-reset-password-toggle="#resetPassword"
                            aria-label="Hiển thị mật khẩu" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="resetPasswordConfirmation" class="form-label cinema-auth-label">XÁC NHẬN MẬT KHẨU</label>
                    <div class="cinema-auth-input-wrapper">
                        <i class="bi bi-lock-check cinema-auth-icon" aria-hidden="true"></i>
                        <input type="password" class="form-control cinema-auth-input" id="resetPasswordConfirmation"
                            name="password_confirmation" required minlength="8" autocomplete="new-password">
                        <button class="cinema-auth-toggle-password" type="button" data-reset-password-toggle="#resetPasswordConfirmation"
                            aria-label="Hiển thị mật khẩu" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    </div>
                </div>

                <button type="submit" class="btn cinema-auth-submit w-100" id="resetPasswordSubmitBtn">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Cập nhật mật khẩu</span>
                </button>
            </form>

            <div class="alert d-none mt-3" id="resetPasswordAlert" role="status" aria-live="polite"></div>
            <a href="{{ route('home') }}" class="cinema-auth-back mt-4"><i class="bi bi-arrow-left"></i> Quay về trang chủ</a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/users/pages/reset-password.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
