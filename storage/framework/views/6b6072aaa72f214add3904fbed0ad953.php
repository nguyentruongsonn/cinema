<div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cinema-auth-modal">
            
            <div class="auth-required-section d-none">
                <div class="modal-header cinema-auth-header border-0 justify-content-center">
                    <h5 class="modal-title text-center">
                        <i class="bi bi-lock-fill text-danger me-2"></i>
                        Yêu cầu đăng nhập
                    </h5>
                    <button type="button" class="btn-close cinema-auth-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body cinema-auth-body text-center py-5">
                    <p class="text-white mb-4">Bạn cần đăng nhập để đặt vé xem phim.</p>
                    <div class="d-grid gap-3 mx-auto cinema-auth-actions">
                        <button type="button" class="btn cinema-auth-submit" id="showLoginFormBtn">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Đăng nhập ngay
                        </button>
                        <button type="button" class="btn cinema-auth-submit bg-secondary" id="showRegisterFormBtn">
                            <i class="bi bi-person-plus me-2"></i>
                            Đăng ký tài khoản
                        </button>
                    </div>
                </div>
            </div>

            
            <div class="auth-forms-section">
                
                <div class="modal-header cinema-auth-header border-0">
                    <ul class="nav nav-tabs cinema-auth-tabs w-100 border-0" role="tablist">
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link active w-100" id="login-tab" data-bs-toggle="tab"
                            data-bs-target="#loginForm" type="button" role="tab"
                            aria-controls="loginForm" aria-selected="true">
                            Đăng nhập
                        </button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link w-100" id="register-tab" data-bs-toggle="tab"
                            data-bs-target="#registerForm" type="button" role="tab"
                            aria-controls="registerForm" aria-selected="false">
                            Đăng ký
                        </button>
                    </li>
                    </ul>
                    <button type="button" class="btn-close cinema-auth-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body cinema-auth-body">
                <div class="tab-content">
                    
                    <div class="tab-pane fade show active" id="loginForm" role="tabpanel">
                        <div id="loginPanel">
                        <form id="loginFormElement" class="cinema-auth-form" novalidate autocomplete="off">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="loginEmail" class="form-label cinema-auth-label">EMAIL</label>
                                <div class="cinema-auth-input-wrapper">
                                    <i class="bi bi-envelope cinema-auth-icon"></i>
                                    <input type="text" class="form-control cinema-auth-input" id="loginEmail"
                                        name="login" placeholder="email@example.com"
                                        required autocomplete="username" inputmode="email">
                                </div>
                                <div class="invalid-feedback" id="loginEmailError"></div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="loginPassword" class="form-label cinema-auth-label mb-0">MẬT KHẨU</label>
                                    <a href="#" class="cinema-auth-forgot" id="forgotPasswordLink">
                                        Quên mật khẩu?
                                    </a>
                                </div>
                                <div class="cinema-auth-input-wrapper">
                                    <i class="bi bi-lock cinema-auth-icon"></i>
                                    <input type="password" class="form-control cinema-auth-input" id="loginPassword"
                                        name="password" placeholder="••••••••"
                                        required minlength="6" autocomplete="current-password">
                                    <button class="cinema-auth-toggle-password" type="button"
                                        data-target="#loginPassword" aria-label="Hiển thị mật khẩu" aria-pressed="false">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback" id="loginPasswordError"></div>
                            </div>

                            <button type="submit" class="btn cinema-auth-submit w-100" id="loginSubmitBtn">
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                <span class="btn-text">Đăng nhập</span>
                            </button>
                        </form>

                        
                        <div class="cinema-auth-divider">
                            <span>HOẶC TIẾP TỤC VỚI</span>
                        </div>

                        
                        <div class="cinema-auth-social">
                            <button type="button" class="cinema-auth-social-btn" id="googleLoginBtn">
                                <i class="bi bi-google"></i>
                                <span>Google</span>
                            </button>
                            <button type="button" class="cinema-auth-social-btn" id="facebookLoginBtn">
                                <i class="bi bi-facebook"></i>
                                <span>Facebook</span>
                            </button>
                        </div>

                        
                        <div class="alert alert-danger d-none mt-3" id="loginAlert" role="alert"></div>
                        </div>

                        <section id="forgotPasswordPanel" class="d-none" aria-labelledby="forgotPasswordTitle">
                            <button type="button" class="cinema-auth-back" id="backToLoginBtn">
                                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                Quay lại đăng nhập
                            </button>
                            <h3 class="cinema-auth-panel-title" id="forgotPasswordTitle">Khôi phục mật khẩu</h3>
                            <p class="cinema-auth-panel-copy">Nhập email tài khoản. Nếu email tồn tại, hệ thống sẽ gửi liên kết đặt lại mật khẩu có hiệu lực trong 60 phút.</p>

                            <form id="forgotPasswordFormElement" class="cinema-auth-form" novalidate>
                                <?php echo csrf_field(); ?>
                                <div class="mb-4">
                                    <label for="forgotPasswordEmail" class="form-label cinema-auth-label">EMAIL</label>
                                    <div class="cinema-auth-input-wrapper">
                                        <i class="bi bi-envelope cinema-auth-icon" aria-hidden="true"></i>
                                        <input type="email" class="form-control cinema-auth-input" id="forgotPasswordEmail"
                                            name="email" placeholder="email@example.com" required autocomplete="email">
                                    </div>
                                    <div class="invalid-feedback" id="forgotPasswordEmailError"></div>
                                </div>

                                <button type="submit" class="btn cinema-auth-submit w-100" id="forgotPasswordSubmitBtn">
                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    <span class="btn-text">Gửi liên kết đặt lại</span>
                                </button>
                            </form>
                            <div class="alert d-none mt-3" id="forgotPasswordAlert" role="status" aria-live="polite"></div>
                        </section>
                    </div>

                    
                    <div class="tab-pane fade" id="registerForm" role="tabpanel">
                        <form id="registerFormElement" class="cinema-auth-form" novalidate autocomplete="off">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="regName" class="form-label cinema-auth-label">HỌ TÊN</label>
                                <div class="cinema-auth-input-wrapper">
                                    <i class="bi bi-person cinema-auth-icon"></i>
                                    <input type="text" class="form-control cinema-auth-input" id="regName"
                                        name="name" placeholder="Nhập họ tên" required minlength="2">
                                </div>
                                <div class="invalid-feedback" id="regNameError"></div>
                            </div>

                            <div class="mb-3">
                                <label for="regEmail" class="form-label cinema-auth-label">EMAIL</label>
                                <div class="cinema-auth-input-wrapper">
                                    <i class="bi bi-envelope cinema-auth-icon"></i>
                                    <input type="email" class="form-control cinema-auth-input" id="regEmail"
                                        name="email" placeholder="email@example.com" required autocomplete="email">
                                </div>
                                <div class="invalid-feedback" id="regEmailError"></div>
                            </div>

                            <div class="mb-3">
                                <label for="regPhone" class="form-label cinema-auth-label">SỐ ĐIỆN THOẠI</label>
                                <div class="cinema-auth-input-wrapper">
                                    <i class="bi bi-telephone cinema-auth-icon"></i>
                                    <input type="tel" class="form-control cinema-auth-input" id="regPhone"
                                        name="phone" placeholder="Nhập số điện thoại (tùy chọn)" autocomplete="tel">
                                </div>
                                <div class="invalid-feedback" id="regPhoneError"></div>
                            </div>

                            <div class="mb-3">
                                <label for="regPassword" class="form-label cinema-auth-label">MẬT KHẨU</label>
                                <div class="cinema-auth-input-wrapper">
                                    <i class="bi bi-lock cinema-auth-icon"></i>
                                    <input type="password" class="form-control cinema-auth-input" id="regPassword"
                                        name="password" placeholder="Ít nhất 8 ký tự"
                                        required minlength="8" autocomplete="new-password">
                                    <button class="cinema-auth-toggle-password" type="button"
                                        data-target="#regPassword" aria-label="Hiển thị mật khẩu" aria-pressed="false">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback" id="regPasswordError"></div>
                            </div>

                            <div class="mb-4">
                                <label for="regPasswordConfirmation" class="form-label cinema-auth-label">XÁC NHẬN MẬT KHẨU</label>
                                <div class="cinema-auth-input-wrapper">
                                    <i class="bi bi-lock-fill cinema-auth-icon"></i>
                                    <input type="password" class="form-control cinema-auth-input" id="regPasswordConfirmation"
                                        name="password_confirmation" placeholder="Nhập lại mật khẩu"
                                        required minlength="8" autocomplete="new-password">
                                </div>
                                <div class="invalid-feedback" id="regPasswordConfirmationError"></div>
                            </div>

                            <div class="mb-3 form-check cinema-auth-check">
                                <input class="form-check-input" type="checkbox" id="regTerms" name="terms" required>
                                <label class="form-check-label" for="regTerms">
                                    Tôi đồng ý với <a href="#" class="cinema-auth-link">điều khoản sử dụng</a>
                                </label>
                                <div class="invalid-feedback" id="regTermsError"></div>
                            </div>

                            <button type="submit" class="btn cinema-auth-submit w-100" id="registerSubmitBtn">
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                <span class="btn-text">Đăng ký</span>
                            </button>
                        </form>

                        
                        <div class="alert alert-danger d-none mt-3" id="registerAlert" role="alert"></div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/partials/auth-modal.blade.php ENDPATH**/ ?>