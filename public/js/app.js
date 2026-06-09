 // API Configuration
const API_URL = '/api';

// Security utilities (inline for Phase 1 - will be modularized in Phase 4)
const Security = {
    escapeHtml(unsafe) {
        if (unsafe == null) return '';
        return String(unsafe)
            .replace(/&/g, "\u0026amp;")
            .replace(/</g, "\u0026lt;")
            .replace(/>/g, "\u0026gt;")
            .replace(/"/g, "\u0026quot;")
            .replace(/'/g, "\u0026#039;");
    }
};

let currentUser = null;

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    loadMovies();
    loadTheaters();
    loadCities();
    checkAuth();
});

// Auth Functions
function toggleAuth() {
    const modal = new bootstrap.Modal(document.getElementById('authModal'));
    modal.show();
}

function toggleAuthForm() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    if (loginForm?.style.display === 'none') {
        showLoginForm();
    } else {
        showRegisterForm();
    }
}

function setAuthModalTitle(title) {
    const titleEl = document.getElementById('authTitle');
    if (titleEl) titleEl.textContent = title;
}

function hideAuthForms() {
    ['loginForm', 'registerForm', 'forgotPasswordForm', 'resetPasswordForm'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
}

function showLoginForm() {
    hideAuthForms();
    const el = document.getElementById('loginForm');
    if (el) el.style.display = 'block';
    setAuthModalTitle('Đăng Nhập');
}

function showRegisterForm() {
    hideAuthForms();
    const el = document.getElementById('registerForm');
    if (el) el.style.display = 'block';
    setAuthModalTitle('Đăng Ký');
}

function showForgotPasswordForm() {
    hideAuthForms();
    const el = document.getElementById('forgotPasswordForm');
    if (el) el.style.display = 'block';
    setAuthModalTitle('Quên Mật Khẩu');
}

function showResetPasswordForm(email = '') {
    hideAuthForms();
    const el = document.getElementById('resetPasswordForm');
    const emailEl = document.getElementById('resetEmail');
    if (el) el.style.display = 'block';
    if (emailEl && email) emailEl.value = email;
    setAuthModalTitle('Đặt Lại Mật Khẩu');
}

function getAuthHeaders(includeJson = true) {
    const headers = {
        ...(includeJson ? { 'Content-Type': 'application/json' } : {}),
        'Accept': 'application/json'
    };

    // Add CSRF token for state-changing requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    return headers;
}

function persistAuth(user) {
    // Cookies are set automatically by server (HttpOnly)
    currentUser = user;
    // Store user info only (not sensitive token)
    sessionStorage.setItem('currentUser', JSON.stringify(currentUser));
    updateAuthUI();
}

function clearAuth() {
    // Cookies cleared by server
    currentUser = null;
    sessionStorage.removeItem('currentUser');
    updateAuthUI();
}

function handleValidationErrors(errors) {
    if (!errors || typeof errors !== 'object') return false;

    const messages = Object.values(errors)
        .flat()
        .filter(Boolean);

    if (messages.length) {
        const safeMessages = messages.map(message => Security.escapeHtml(message));
        showAlert(safeMessages.join('<br>'), 'danger', true);
        return true;
    }

    return false;
}

async function login() {
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const remember = document.getElementById('loginRemember')?.checked || false;

    try {
        const response = await fetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: getAuthHeaders(false),
            body: JSON.stringify({ email, password, remember })
        });

        const data = await response.json();

        if (data.success) {
            persistAuth(data.data.user);
            bootstrap.Modal.getInstance(document.getElementById('authModal')).hide();
            showAlert('Đăng nhập thành công!', 'success');
        } else {
            handleValidationErrors(data.errors) || showAlert(data.message || 'Đăng nhập thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi: ' + error.message, 'danger');
    }
}

async function register() {
    const name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const password_confirmation = document.getElementById('registerPasswordConfirm').value;
    const phone = document.getElementById('registerPhone').value;
    const username = document.getElementById('registerUsername')?.value || '';

    if (password !== password_confirmation) {
        showAlert('Mật khẩu không khớp', 'danger');
        return;
    }

    try {
        const response = await fetch(`${API_URL}/auth/register`, {
            method: 'POST',
            headers: getAuthHeaders(false),
            body: JSON.stringify({ name, email, password, password_confirmation, phone, username })
        });

        const data = await response.json();

        if (data.success) {
            persistAuth(data.data.user);
            bootstrap.Modal.getInstance(document.getElementById('authModal')).hide();
            showAlert('Đăng ký thành công!', 'success');
        } else {
            handleValidationErrors(data.errors) || showAlert(data.message || 'Đăng ký thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi: ' + error.message, 'danger');
    }
}

function logout() {
    // Try to invalidate token on server
    fetch(`${API_URL}/auth/logout`, {
        method: 'POST',
        headers: getAuthHeaders(false),
        credentials: 'include'
    }).catch(() => {});

    clearAuth();
    showAlert('Đã đăng xuất', 'info');
}

async function loadUserProfile() {
    if (!currentUser) {
        toggleAuth();
        return;
    }

    try {
        const response = await fetch(`${API_URL}/auth/profile`, {
            headers: getAuthHeaders(false),
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            currentUser = data.data;
            sessionStorage.setItem('currentUser', JSON.stringify(currentUser));
            fillProfileForm(currentUser);
            const profileSection = document.getElementById('profile');
            if (profileSection) profileSection.style.display = 'block';
        } else {
            showAlert(data.message || 'Không thể tải hồ sơ', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi tải hồ sơ: ' + error.message, 'danger');
    }
}

function fillProfileForm(user) {
    const setValue = (id, value = '') => {
        const el = document.getElementById(id);
        if (el) el.value = value || '';
    };

    setValue('profileName', user.name || user.full_name);
    setValue('profileEmail', user.email);
    setValue('profilePhone', user.phone);
    setValue('profileDateOfBirth', user.date_of_birth || user.birth_date);
}

async function updateProfile() {
    if (!currentUser) return;

    const payload = {
        name: document.getElementById('profileName')?.value || '',
        email: document.getElementById('profileEmail')?.value || '',
        phone: document.getElementById('profilePhone')?.value || '',
        date_of_birth: document.getElementById('profileDateOfBirth')?.value || null
    };

    try {
        const response = await fetch(`${API_URL}/auth/profile`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (data.success) {
            currentUser = data.data;
            sessionStorage.setItem('currentUser', JSON.stringify(currentUser));
            updateAuthUI();
            showAlert('Cập nhật hồ sơ thành công!', 'success');
        } else {
            handleValidationErrors(data.errors) || showAlert(data.message || 'Cập nhật hồ sơ thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi cập nhật hồ sơ: ' + error.message, 'danger');
    }
}

async function changePassword() {
    if (!currentUser) return;

    const current_password = document.getElementById('currentPassword')?.value || '';
    const password = document.getElementById('newPassword')?.value || '';
    const password_confirmation = document.getElementById('newPasswordConfirm')?.value || '';

    if (password !== password_confirmation) {
        showAlert('Mật khẩu mới không khớp', 'danger');
        return;
    }

    try {
        const response = await fetch(`${API_URL}/auth/change-password`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'include',
            body: JSON.stringify({ current_password, password, password_confirmation })
        });
        const data = await response.json();

        if (data.success) {
            document.getElementById('changePasswordForm')?.reset();
            showAlert('Đổi mật khẩu thành công!', 'success');
        } else {
            handleValidationErrors(data.errors) || showAlert(data.message || 'Đổi mật khẩu thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi đổi mật khẩu: ' + error.message, 'danger');
    }
}

async function forgotPassword() {
    const email = document.getElementById('forgotPasswordEmail')?.value || '';

    try {
        const response = await fetch(`${API_URL}/auth/forgot-password`, {
            method: 'POST',
            headers: getAuthHeaders(false),
            body: JSON.stringify({ email })
        });
        const data = await response.json();

        if (data.success) {
            showAlert(data.message || 'Đã gửi hướng dẫn đặt lại mật khẩu.', 'success');
            showResetPasswordForm(email);
        } else {
            handleValidationErrors(data.errors) || showAlert(data.message || 'Gửi yêu cầu thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi gửi yêu cầu: ' + error.message, 'danger');
    }
}

async function resetPassword() {
    const email = document.getElementById('resetEmail')?.value || '';
    const token = document.getElementById('resetToken')?.value || '';
    const password = document.getElementById('resetPassword')?.value || '';
    const password_confirmation = document.getElementById('resetPasswordConfirm')?.value || '';

    if (password !== password_confirmation) {
        showAlert('Mật khẩu xác nhận không khớp', 'danger');
        return;
    }

    try {
        const response = await fetch(`${API_URL}/auth/reset-password`, {
            method: 'POST',
            headers: getAuthHeaders(false),
            body: JSON.stringify({ email, token, password, password_confirmation })
        });
        const data = await response.json();

        if (data.success) {
            document.getElementById('authForm')?.reset();
            showLoginForm();
            showAlert(data.message || 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập.', 'success');
        } else {
            handleValidationErrors(data.errors) || showAlert(data.message || 'Đặt lại mật khẩu thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi đặt lại mật khẩu: ' + error.message, 'danger');
    }
}

async function sendVerificationEmail() {
    if (!currentUser) return;

    try {
        const response = await fetch(`${API_URL}/auth/send-verification-email`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            showAlert(data.message || 'Đã gửi email xác thực.', 'success');
        } else {
            showAlert(data.message || 'Gửi email xác thực thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi gửi email xác thực: ' + error.message, 'danger');
    }
}

function checkAuth() {
    // Check via API (cookies sent automatically)
    fetch(`${API_URL}/auth/me`, {
        headers: getAuthHeaders(false),
        credentials: 'include'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            currentUser = data.data;
            sessionStorage.setItem('currentUser', JSON.stringify(currentUser));
            updateAuthUI();
        }
    })
    .catch(() => {
        // Not authenticated or error
        const stored = sessionStorage.getItem('currentUser');
        if (stored) {
            currentUser = JSON.parse(stored);
            updateAuthUI();
        }
    });
}

function updateAuthUI() {
    const authBtn = document.getElementById('authBtn');
    const userMenu = document.getElementById('userMenu');
    const profileMenu = document.getElementById('profileMenu');
    const adminMenu = document.getElementById('adminMenu');
    const ordersSection = document.getElementById('orders');
    const profileSection = document.getElementById('profile');

    if (currentUser) {
        if (authBtn) {
            const userName = Security.escapeHtml(currentUser.name || currentUser.full_name);
            authBtn.innerHTML = `<i class="bi bi-person-circle"></i> ${userName} (Đăng Xuất)`;
            authBtn.onclick = logout;
        }
        if (userMenu) userMenu.style.display = 'block';
        if (profileMenu) profileMenu.style.display = 'block';
        if (adminMenu) adminMenu.style.display = isAdminUser() ? 'block' : 'none';
        if (profileSection) fillProfileForm(currentUser);
        if (ordersSection) loadUserOrders();
        if (isAdminUser()) loadAdminDashboard();
    } else {
        if (authBtn) {
            authBtn.textContent = 'Đăng Nhập';
            authBtn.onclick = toggleAuth;
        }
        if (userMenu) userMenu.style.display = 'none';
        if (profileMenu) profileMenu.style.display = 'none';
        if (adminMenu) adminMenu.style.display = 'none';
        if (ordersSection) ordersSection.style.display = 'none';
        if (profileSection) profileSection.style.display = 'none';
    }
}

function isAdminUser() {
    const roles = currentUser?.roles || [];
    const roleNames = Array.isArray(roles)
        ? roles.map(role => typeof role === 'string' ? role : role.name)
        : [];

    return roleNames.some(role => ['admin', 'super-admin', 'manager', 'staff'].includes(role))
        || ['admin', 'super-admin', 'manager', 'staff'].includes(currentUser?.role);
}

// Movies
let currentMoviePage = 1;

async function loadMovies(page = 1) {
    currentMoviePage = page;

    try {
        const searchInput = document.getElementById('movieSearchInput');
        const statusFilter = document.getElementById('movieStatusFilter');
        const sortFilter = document.getElementById('movieSortFilter');

        const [sortBy = 'release_date', sortDir = 'desc'] = (sortFilter?.value || 'release_date:desc').split(':');
        const params = new URLSearchParams({
            per_page: '12',
            page: String(page),
            status: statusFilter?.value || 'now_showing',
            sort_by: sortBy,
            sort_dir: sortDir
        });

        if (searchInput?.value.trim()) {
            params.set('q', searchInput.value.trim());
        }

        const response = await fetch(`${API_URL}/movies?${params.toString()}`);
        const data = await response.json();

        if (data.success) {
            renderMovies(data.data || []);
            renderMoviesPagination(data.pagination);
        } else {
            showAlert(data.message || 'Không thể tải danh sách phim', 'danger');
        }
    } catch (error) {
        console.error('Error loading movies:', error);
        showAlert('Lỗi tải phim: ' + error.message, 'danger');
    }
}

function renderMovies(movies) {
    const moviesList = document.getElementById('moviesList');
    if (!moviesList) return;

    if (!movies.length) {
        moviesList.innerHTML = '<div class="col-12"><p class="text-center text-muted">Không tìm thấy phim phù hợp.</p></div>';
        return;
    }

    moviesList.innerHTML = movies.map(movie => {
        const categories = (movie.categories || []).map(category => category.name).join(', ');
        const releaseDate = movie.release_date ? new Date(movie.release_date).toLocaleDateString('vi-VN') : 'N/A';

        return `
            <div class="col-md-6 col-lg-4">
                <div class="movie-card h-100">
                    <div class="movie-poster">
                        ${movie.poster_url ? `<img src="${movie.poster_url}" alt="${movie.title}" class="img-fluid">` : '🎬'}
                        ${movie.is_hot ? '<span class="badge bg-danger movie-hot-badge">HOT</span>' : ''}
                    </div>
                    <div class="movie-info">
                        <div class="movie-title">${movie.title}</div>
                        <div class="movie-genre">${categories || movie.age_rating || ''}</div>
                        <div class="movie-rating">⏱️ ${movie.duration || 'N/A'} phút · 📅 ${releaseDate}</div>
                        <div class="movie-rating">🎭 ${movie.director || 'Đang cập nhật'}</div>
                        <button class="btn-book" onclick="selectMovie(${movie.id})">Đặt Vé</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderMoviesPagination(pagination) {
    const paginationEl = document.getElementById('moviesPagination');
    if (!paginationEl || !pagination || pagination.last_page <= 1) {
        if (paginationEl) paginationEl.innerHTML = '';
        return;
    }

    const prevDisabled = pagination.current_page <= 1 ? 'disabled' : '';
    const nextDisabled = pagination.current_page >= pagination.last_page ? 'disabled' : '';

    paginationEl.innerHTML = `
        <nav aria-label="Movie pagination">
            <ul class="pagination">
                <li class="page-item ${prevDisabled}">
                    <button class="page-link" onclick="loadMovies(${pagination.current_page - 1})" ${prevDisabled}>Trước</button>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">Trang ${pagination.current_page}/${pagination.last_page}</span>
                </li>
                <li class="page-item ${nextDisabled}">
                    <button class="page-link" onclick="loadMovies(${pagination.current_page + 1})" ${nextDisabled}>Sau</button>
                </li>
            </ul>
        </nav>
    `;
}

function selectMovie(movieId) {
    if (!currentUser) {
        showAlert('Vui lòng đăng nhập để đặt vé', 'info');
        toggleAuth();
        return;
    }
    loadShowtimes(movieId);
}

// Theaters
let currentTheaterPage = 1;

async function loadCities() {
    try {
        const response = await fetch(`${API_URL}/theaters/cities`);
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('theaterCityFilter');
            if (!select) return;
            data.data.forEach(city => {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                select.appendChild(opt);
            });
        }
    } catch (error) {
        console.error('Error loading cities:', error);
    }
}

async function loadTheaters(page = 1) {
    currentTheaterPage = page;

    try {
        const searchInput = document.getElementById('theaterSearchInput');
        const cityFilter = document.getElementById('theaterCityFilter');

        const params = new URLSearchParams({
            per_page: '12',
            page: String(page),
            status: 'active',
            sort_by: 'name',
            sort_dir: 'asc'
        });

        if (searchInput?.value.trim()) {
            params.set('q', searchInput.value.trim());
        }
        if (cityFilter?.value) {
            params.set('city', cityFilter.value);
        }

        const response = await fetch(`${API_URL}/theaters?${params.toString()}`);
        const data = await response.json();

        if (data.success) {
            const theatersList = document.getElementById('theatersList');
            if (!theatersList) return;
            theatersList.innerHTML = (data.data || []).map(theater => {
                const screens = theater.screens || [];
                return `
                <div class="col-md-6 col-lg-4">
                    <div class="theater-card h-100">
                        <div class="theater-name">${theater.name}</div>
                        <div class="theater-info">📍 ${theater.address || ''}</div>
                        <div class="theater-info">🏙️ ${theater.city || ''}</div>
                        <div class="theater-info">📞 ${theater.phone || 'N/A'}</div>
                        <div class="theater-info">🎬 ${screens.length} phòng chiếu</div>
                        <button class="btn btn-outline-primary btn-sm mt-2 w-100" onclick="viewTheater(${theater.id})">
                            Xem Chi Tiết
                        </button>
                    </div>
                </div>
            `}).join('') || '<div class="col-12"><p class="text-center text-muted">Không tìm thấy rạp phù hợp.</p></div>';

            renderTheatersPagination(data.pagination);
        }
    } catch (error) {
        console.error('Error loading theaters:', error);
    }
}

function renderTheatersPagination(pagination) {
    const paginationEl = document.getElementById('theatersPagination');
    if (!paginationEl || !pagination || pagination.last_page <= 1) {
        if (paginationEl) paginationEl.innerHTML = '';
        return;
    }

    const prevDisabled = pagination.current_page <= 1 ? 'disabled' : '';
    const nextDisabled = pagination.current_page >= pagination.last_page ? 'disabled' : '';

    paginationEl.innerHTML = `
        <nav aria-label="Theater pagination">
            <ul class="pagination">
                <li class="page-item ${prevDisabled}">
                    <button class="page-link" onclick="loadTheaters(${pagination.current_page - 1})" ${prevDisabled}>Trước</button>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">Trang ${pagination.current_page}/${pagination.last_page}</span>
                </li>
                <li class="page-item ${nextDisabled}">
                    <button class="page-link" onclick="loadTheaters(${pagination.current_page + 1})" ${nextDisabled}>Sau</button>
                </li>
            </ul>
        </nav>
    `;
}

async function viewTheater(theaterId) {
    try {
        const response = await fetch(`${API_URL}/theaters/${theaterId}?per_page=50`);
        const data = await response.json();

        if (data.success) {
            const theater = data.data;
            const screens = theater.screens || [];

            const existingModal = document.getElementById('theaterDetailModal');
            if (existingModal) existingModal.remove();

            const html = `
                <div class="modal fade" id="theaterDetailModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${theater.name}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <p><strong>📍 Địa chỉ:</strong> ${theater.address || 'N/A'}</p>
                                        <p><strong>🏙️ Thành phố:</strong> ${theater.city || 'N/A'}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>📞 Điện thoại:</strong> ${theater.phone || 'N/A'}</p>
                                        <p><strong>📧 Email:</strong> ${theater.email || 'N/A'}</p>
                                    </div>
                                </div>
                                <h6 class="mb-3">🎬 Phòng Chiếu (${screens.length})</h6>
                                ${screens.length === 0 ? '<p class="text-muted">Chưa có phòng chiếu nào.</p>' : `
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Mã</th>
                                                <th>Tên</th>
                                                <th>Loại</th>
                                                <th>Định dạng</th>
                                                <th>Âm thanh</th>
                                                <th>Sức chứa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${screens.map(screen => `
                                                <tr>
                                                    <td>${screen.code || '-'}</td>
                                                    <td>${screen.name}</td>
                                                    <td>${screen.screen_type || 'Standard'}</td>
                                                    <td>${screen.format?.name || '-'}</td>
                                                    <td>${screen.sound?.name || '-'}</td>
                                                    <td>${screen.capacity}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById('theaterDetailModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading theater detail:', error);
        showAlert('Lỗi tải thông tin rạp: ' + error.message, 'danger');
    }
}

// Showtimes
let selectedSeats = [];
let currentShowtimeId = null;
let currentShowtimePrice = 0;
let currentSelectedMovieId = null;
let currentSeatMap = new Map();
let currentSeatHoldId = null;
let currentSeatHoldExpiresAt = null;
let seatHoldCountdownTimer = null;
let seatStatusPollingTimer = null;

async function loadShowtimes(movieId) {
    currentSelectedMovieId = movieId;

    // Load movie detail for the modal header
    let movieTitle = 'Đang tải...';
    try {
        const movieRes = await fetch(`${API_URL}/movies/${movieId}`);
        const movieData = await movieRes.json();
        if (movieData.success) {
            movieTitle = movieData.data.title;
        }
    } catch (e) {}

    // Build today & tomorrow dates for default filter
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().split('T')[0];

    const existingModal = document.getElementById('showtimeModal');
    if (existingModal) existingModal.remove();

    const html = `
        <div class="modal fade" id="showtimeModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">🎬 ${movieTitle} - Chọn Suất Chiếu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-4" id="showtimeFilterForm" onchange="fetchShowtimes()">
                            <div class="col-md-5">
                                <select class="form-select" id="showtimeTheaterFilter">
                                    <option value="">Tất cả rạp</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="showtimeDateFilter">
                                    <option value="${todayStr}">Hôm nay (${today.toLocaleDateString('vi-VN')})</option>
                                    <option value="${tomorrowStr}">Ngày mai (${tomorrow.toLocaleDateString('vi-VN')})</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="showtimeFormatFilter">
                                    <option value="">Tất cả định dạng</option>
                                </select>
                            </div>
                        </div>
                        <div id="showtimesList">
                            <div class="text-center py-4">
                                <div class="spinner"></div>
                                <p class="mt-2 text-muted">Đang tải suất chiếu...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);
    const modal = new bootstrap.Modal(document.getElementById('showtimeModal'));
    modal.show();

    // Load filter options then fetch showtimes
    await Promise.all([
        loadShowtimeTheaters(movieId),
        loadShowtimeFormats()
    ]);
    await fetchShowtimes();
}

async function loadShowtimeTheaters(movieId) {
    try {
        // Get all unique theaters that show this movie
        const res = await fetch(`${API_URL}/showtimes?per_page=200&movie_id=${movieId}&status=active`);
        const data = await res.json();
        if (!data.success) return;

        const theaterMap = new Map();
        (data.data || []).forEach(st => {
            const theater = st.screen?.theater;
            if (theater && !theaterMap.has(theater.id)) {
                theaterMap.set(theater.id, theater);
            }
        });

        const select = document.getElementById('showtimeTheaterFilter');
        if (!select) return;

        // Keep "Tất cả rạp" option
        select.innerHTML = '<option value="">Tất cả rạp</option>';

        // Convert to sorted array
        const sorted = Array.from(theaterMap.values()).sort((a, b) => a.name.localeCompare(b.name));
        sorted.forEach(theater => {
            const opt = document.createElement('option');
            opt.value = theater.id;
            opt.textContent = `${theater.name} (${theater.city || ''})`;
            select.appendChild(opt);
        });
    } catch (e) {
        console.error('Error loading showtime theaters:', e);
    }
}

async function loadShowtimeFormats() {
    try {
        const res = await fetch(`${API_URL}/showtimes?per_page=200&status=active`);
        const data = await res.json();
        if (!data.success) return;

        const formatSet = new Set();
        (data.data || []).forEach(st => {
            if (st.format?.name) {
                formatSet.add(JSON.stringify({ id: st.format_id, name: st.format.name }));
            }
        });

        const select = document.getElementById('showtimeFormatFilter');
        if (!select) return;

        Array.from(formatSet)
            .map(f => JSON.parse(f))
            .sort((a, b) => a.name.localeCompare(b.name))
            .forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id;
                opt.textContent = f.name;
                select.appendChild(opt);
            });
    } catch (e) {
        console.error('Error loading formats:', e);
    }
}

async function fetchShowtimes() {
    if (!currentSelectedMovieId) return;

    const theaterId = document.getElementById('showtimeTheaterFilter')?.value || '';
    const date = document.getElementById('showtimeDateFilter')?.value || '';
    const formatId = document.getElementById('showtimeFormatFilter')?.value || '';

    const params = new URLSearchParams({
        movie_id: String(currentSelectedMovieId),
        per_page: '100',
        status: 'active',
        upcoming: 'true',
        sort_by: 'scheduled_at',
        sort_dir: 'asc'
    });

    if (theaterId) params.set('theater_id', theaterId);
    if (date) params.set('date', date);
    if (formatId) params.set('format_id', formatId);

    try {
        const response = await fetch(`${API_URL}/showtimes?${params.toString()}`);
        const data = await response.json();

        const container = document.getElementById('showtimesList');
        if (!container) return;

        if (data.success && data.data.length > 0) {
            const showtimes = data.data;

            // Group by theater
            const grouped = {};
            showtimes.forEach(st => {
                const theaterId = st.screen?.theater?.id || 'unknown';
                if (!grouped[theaterId]) {
                    grouped[theaterId] = {
                        theater: st.screen?.theater || { id: 'unknown', name: 'Không xác định' },
                        showtimes: []
                    };
                }
                grouped[theaterId].showtimes.push(st);
            });

            container.innerHTML = Object.values(grouped).map(group => `
                <div class="mb-4">
                    <h6 class="text-danger mb-3 border-bottom pb-2">
                        🏢 ${group.theater.name}
                        <small class="text-muted ms-2">${group.theater.address || ''}</small>
                    </h6>
                    <div class="row g-2">
                        ${group.showtimes.map(st => {
                            const startTime = st.start_time || st.scheduled_at;
                            const timeFormatted = startTime ? new Date(startTime).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '--:--';
                            const dateFormatted = startTime ? new Date(startTime).toLocaleDateString('vi-VN') : '';
                            const screenName = st.screen?.name || '';
                            const formatName = st.format?.name || '2D';
                            return `
                                <div class="col-6 col-md-4 col-lg-3">
                                    <button class="btn btn-outline-danger w-100 text-start p-3 showtime-btn"
                                            onclick="selectShowtime(${st.id}, ${st.price})"
                                            title="${screenName} - ${formatName}">
                                        <div class="fw-bold">${timeFormatted}</div>
                                        <small class="text-muted d-block">${dateFormatted}</small>
                                        <small class="d-block">
                                            <span class="badge bg-dark">${formatName}</span>
                                            <span class="badge bg-secondary">${screenName}</span>
                                        </small>
                                        <div class="fw-bold mt-1 text-danger">
                                            ${new Intl.NumberFormat('vi-VN').format(st.price)}₫
                                        </div>
                                    </button>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="text-center py-5">
                    <h4 class="text-muted">😕 Không có suất chiếu phù hợp</h4>
                    <p class="text-muted">Thử thay đổi bộ lọc hoặc chọn ngày khác.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error fetching showtimes:', error);
        const container = document.getElementById('showtimesList');
        if (container) {
            container.innerHTML = '<div class="text-center py-4 text-danger">Lỗi tải suất chiếu. Vui lòng thử lại.</div>';
        }
    }
}

function clearSeatHoldTimers() {
    if (seatHoldCountdownTimer) {
        clearInterval(seatHoldCountdownTimer);
        seatHoldCountdownTimer = null;
    }

    if (seatStatusPollingTimer) {
        clearInterval(seatStatusPollingTimer);
        seatStatusPollingTimer = null;
    }
}

async function releaseCurrentSeatHold() {
    if (!currentSeatHoldId) return;

    const holdId = currentSeatHoldId;
    currentSeatHoldId = null;
    currentSeatHoldExpiresAt = null;
    clearSeatHoldTimers();

    try {
        await fetch(`${API_URL}/seats/unlock/${holdId}`, {
            method: 'DELETE',
            headers: getAuthHeaders(false),
            credentials: 'include'
        });
    } catch (error) {
        console.warn('Failed to release seat hold:', error);
    }
}

function startSeatHoldCountdown(heldUntil) {
    currentSeatHoldExpiresAt = heldUntil;
    const countdownEl = document.getElementById('seatHoldCountdown');

    clearSeatHoldTimers();

    const render = () => {
        if (!currentSeatHoldExpiresAt || !countdownEl) return;

        const remainingSeconds = Math.max(0, Math.floor((new Date(currentSeatHoldExpiresAt).getTime() - Date.now()) / 1000));
        const minutes = String(Math.floor(remainingSeconds / 60)).padStart(2, '0');
        const seconds = String(remainingSeconds % 60).padStart(2, '0');

        countdownEl.textContent = `${minutes}:${seconds}`;

        if (remainingSeconds <= 0) {
            clearSeatHoldTimers();
            currentSeatHoldId = null;
            currentSeatHoldExpiresAt = null;
            selectedSeats = [];
            updateTotalPrice();
            refreshSeatStatuses();
            showAlert('Thời gian giữ ghế đã hết. Vui lòng chọn lại ghế.', 'warning');
        }
    };

    render();
    seatHoldCountdownTimer = setInterval(render, 1000);
    seatStatusPollingTimer = setInterval(refreshSeatStatuses, 15000);
}

async function refreshSeatStatuses() {
    if (!currentShowtimeId) return;

    try {
        const response = await fetch(`${API_URL}/seats/showtime/${currentShowtimeId}`, {
            headers: getAuthHeaders(false),
            credentials: 'include'
        });
        const data = await response.json();

        if (!data.success) return;

        currentSeatMap = new Map((data.data || []).map(seat => [seat.id, seat]));

        document.querySelectorAll('[data-seat-id]').forEach(el => {
            const seatId = Number(el.dataset.seatId);
            const seat = currentSeatMap.get(seatId);
            if (!seat) return;

            const isSelected = selectedSeats.includes(seatId);
            el.className = 'seat';
            el.onclick = null;

            if (isSelected && ['available', 'held_by_me'].includes(seat.status)) {
                el.classList.add('seat-selected');
                el.onclick = () => toggleSeat(seatId, seat.status);
            } else if (seat.status === 'booked' || seat.status === 'locked') {
                el.classList.add('seat-booked');
                if (!isSelected) {
                    selectedSeats = selectedSeats.filter(id => id !== seatId);
                }
            } else {
                el.classList.add('seat-available');
                if (Number(seat.surcharge || 0) > 0) el.classList.add('seat-premium');
                el.onclick = () => toggleSeat(seatId, seat.status || 'available');
            }
        });

        updateTotalPrice();
    } catch (error) {
        console.warn('Failed to refresh seat statuses:', error);
    }
}

async function holdSelectedSeats() {
    if (selectedSeats.length === 0) {
        await releaseCurrentSeatHold();
        return true;
    }

    const response = await fetch(`${API_URL}/seats/lock`, {
        method: 'POST',
        headers: getAuthHeaders(),
        credentials: 'include',
        body: JSON.stringify({
            showtime_id: currentShowtimeId,
            seat_ids: selectedSeats
        })
    });

    const data = await response.json();

    if (!data.success) {
        showAlert(data.message || 'Không thể giữ ghế đã chọn', 'danger');
        await refreshSeatStatuses();
        return false;
    }

    currentSeatHoldId = data.data.hold_id;
    startSeatHoldCountdown(data.data.held_until);
    return true;
}

async function selectShowtime(showtimeId, price) {
    try {
        await releaseCurrentSeatHold();

        const response = await fetch(`${API_URL}/seats/showtime/${showtimeId}`, {
            headers: getAuthHeaders(false),
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            currentShowtimePrice = price;
            currentShowtimeId = showtimeId;
            const modal = bootstrap.Modal.getInstance(document.getElementById('showtimeModal'));
            if (modal) modal.hide();
            showSeatsSelection(showtimeId, data.data);
        }
    } catch (error) {
        showAlert('Lỗi: ' + error.message, 'danger');
    }
}

function showSeatsSelection(showtimeId, seats) {
    selectedSeats = [];
    currentSeatMap = new Map((seats || []).map(seat => [seat.id, seat]));
    document.getElementById('totalPrice').textContent = '0';

    const seatsContainer = document.getElementById('seatsContainer');
    if (!seatsContainer) return;

    if (!seats || seats.length === 0) {
        seatsContainer.innerHTML = '<p class="text-center text-muted py-4">Không có thông tin ghế cho suất chiếu này.</p>';
    } else {
        seatsContainer.innerHTML = `
            <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                <span class="badge bg-success">Còn trống</span>
                <span class="badge bg-danger">Đã đặt/đang giữ</span>
                <span class="badge bg-warning text-dark">Đã chọn</span>
                <span class="badge bg-info text-dark">VIP/Phụ thu</span>
                <span class="badge bg-secondary">Giữ ghế: <span id="seatHoldCountdown">--:--</span></span>
            </div>
            <div class="screen-indicator text-center mb-3">
                <span class="badge bg-dark w-100 py-2">MÀN HÌNH</span>
            </div>
            <div class="seats-grid">
                ${seats.map(seat => {
                    const label = seat.label || `${seat.row}${seat.column || seat.number || ''}`;
                    const seatPrice = Number(seat.price || currentShowtimePrice);
                    const surcharge = Number(seat.surcharge || 0);
                    let statusClass = surcharge > 0 ? 'seat-available seat-premium' : 'seat-available';
                    let clickable = `onclick="toggleSeat(${seat.id}, 'available')"`;
                    if (seat.status === 'booked' || seat.status === 'locked') {
                        statusClass = 'seat-booked';
                        clickable = '';
                    } else if (seat.status === 'held_by_me') {
                        statusClass = 'seat-selected';
                    }
                    return `
                        <div class="seat ${statusClass}"
                             ${clickable}
                             data-seat-id="${seat.id}"
                             title="${label} - ${new Intl.NumberFormat('vi-VN').format(seatPrice)}₫">
                            ${label}
                        </div>
                    `;
                }).join('')}
            </div>
            <div id="selectedSeatsInfo" class="text-center text-muted mt-3 small">
                Chưa chọn ghế nào.
            </div>
        `;
    }

    const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
    bookingModal.show();
}

async function toggleSeat(seatId, status) {
    if (status === 'booked' || status === 'locked') return;

    const seatElement = document.querySelector(`[data-seat-id="${seatId}"]`);
    if (!seatElement) return;

    const index = selectedSeats.indexOf(seatId);

    if (index > -1) {
        selectedSeats.splice(index, 1);
        seatElement.classList.remove('seat-selected');
        seatElement.classList.add('seat-available');
    } else {
        selectedSeats.push(seatId);
        seatElement.classList.remove('seat-available');
        seatElement.classList.add('seat-selected');
    }

    updateTotalPrice();
    await holdSelectedSeats();
}

function calculateSelectedSeatsTotal() {
    return selectedSeats.reduce((total, seatId) => {
        const seat = currentSeatMap.get(seatId);
        return total + Number(seat?.price || currentShowtimePrice);
    }, 0);
}

function updateTotalPrice() {
    const totalPrice = calculateSelectedSeatsTotal();
    const el = document.getElementById('totalPrice');
    const infoEl = document.getElementById('selectedSeatsInfo');

    if (el) el.textContent = new Intl.NumberFormat('vi-VN').format(totalPrice);

    if (infoEl) {
        if (selectedSeats.length === 0) {
            infoEl.textContent = 'Chưa chọn ghế nào.';
        } else {
            const labels = selectedSeats.map(seatId => {
                const seat = currentSeatMap.get(seatId);
                return seat?.label || `${seat?.row || ''}${seat?.column || seat?.number || ''}`;
            }).join(', ');
            infoEl.textContent = `Ghế đã chọn: ${labels}`;
        }
    }
}

async function proceedToPayment() {
    if (selectedSeats.length === 0) {
        showAlert('Vui lòng chọn ít nhất một ghế', 'warning');
        return;
    }

    try {
        const totalPrice = calculateSelectedSeatsTotal();

        const response = await fetch(`${API_URL}/orders`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'include',
            body: JSON.stringify({
                showtime_id: currentShowtimeId,
                seat_ids: selectedSeats,
                seat_hold_id: currentSeatHoldId
            })
        });

        const data = await response.json();

        if (data.success) {
            const orderId = data.data.id;
            const orderTotal = Number(data.data.total_amount || data.data.total_price || totalPrice);
            const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
            if (modal) modal.hide();
            currentSeatHoldId = null;
            currentSeatHoldExpiresAt = null;
            clearSeatHoldTimers();
            showPaymentModal(orderId, orderTotal);
        } else {
            showAlert(data.message || 'Tạo đơn hàng thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi: ' + error.message, 'danger');
    }
}

function showPaymentModal(orderId, totalPrice) {
    const existingModal = document.getElementById('paymentModal');
    if (existingModal) existingModal.remove();

    const html = `
        <div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Thanh Toán</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            Đơn hàng đã được giữ trong thời gian ngắn. Vui lòng hoàn tất thanh toán để xác nhận vé.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mã đơn hàng</label>
                            <input type="text" class="form-control" value="#${orderId}" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tổng Tiền</label>
                            <input type="text" class="form-control" value="${new Intl.NumberFormat('vi-VN').format(totalPrice)} VNĐ" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phương Thức Thanh Toán</label>
                            <select class="form-control" id="paymentMethod">
                                <option value="credit_card">Thẻ Tín Dụng</option>
                                <option value="debit_card">Thẻ Ghi Nợ</option>
                                <option value="bank_transfer">Chuyển Khoản Ngân Hàng</option>
                                <option value="e_wallet">Ví Điện Tử</option>
                            </select>
                        </div>
                        <div id="paymentFeedback" class="small text-muted mb-3"></div>
                        <button id="payButton" class="btn btn-success w-100" onclick="processPayment(${orderId}, ${totalPrice})">
                            Thanh Toán
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

async function processPayment(orderId, totalPrice) {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const payButton = document.getElementById('payButton');
    const feedback = document.getElementById('paymentFeedback');

    if (payButton) {
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo thanh toán...';
    }
    if (feedback) feedback.textContent = 'Đang tạo giao dịch thanh toán...';

    try {
        const response = await fetch(`${API_URL}/payments`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'include',
            body: JSON.stringify({
                order_id: orderId,
                payment_method: paymentMethod,
                amount: totalPrice
            })
        });

        const data = await response.json();

        if (data.success) {
            const paymentId = data.data.id;
            if (feedback) feedback.textContent = `Đã tạo giao dịch ${data.data.payment_code || data.data.transaction_code || ''}. Đang xác nhận...`;
            await verifyPayment(paymentId);
        } else {
            showAlert(data.message || 'Tạo thanh toán thất bại', 'danger');
            if (feedback) feedback.textContent = data.message || 'Tạo thanh toán thất bại.';
        }
    } catch (error) {
        showAlert('Lỗi: ' + error.message, 'danger');
        if (feedback) feedback.textContent = error.message;
    } finally {
        if (payButton) {
            payButton.disabled = false;
            payButton.innerHTML = 'Thanh Toán';
        }
    }
}

async function verifyPayment(paymentId) {
    try {
        const response = await fetch(`${API_URL}/payments/${paymentId}/verify`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'include',
            body: JSON.stringify({
                status: 'completed'
            })
        });

        const data = await response.json();

        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            if (modal) modal.hide();
            showAlert('Thanh toán thành công! Vé của bạn đã được xác nhận.', 'success');
            selectedSeats = [];
            updateTotalPrice();
            loadUserOrders();
            if (currentShowtimeId) refreshSeatStatuses();
        } else {
            showAlert(data.message || 'Xác nhận thanh toán thất bại', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi: ' + error.message, 'danger');
    }
}

// Admin Dashboard
async function loadAdminDashboard() {
    if (!currentUser || !isAdminUser()) return;

    const section = document.getElementById('adminDashboard');
    const cardsEl = document.getElementById('adminStatsCards');
    const recentEl = document.getElementById('adminRecentOrders');
    const topMoviesEl = document.getElementById('adminTopMovies');
    const revenueEl = document.getElementById('adminRevenueByDay');

    if (section) section.style.display = 'block';
    if (cardsEl) {
        cardsEl.innerHTML = `
            <div class="col-12 text-center py-4">
                <div class="spinner"></div>
                <p class="text-muted mt-2">Đang tải dashboard...</p>
            </div>
        `;
    }

    try {
        const response = await fetch(`${API_URL}/admin/dashboard/stats`, {
            headers: getAuthHeaders(false),
            credentials: 'include'
        });

        const data = await response.json();

        if (!data.success) {
            showAlert(data.message || 'Không thể tải Admin Dashboard', 'danger');
            return;
        }

        const dashboard = data.data || {};
        renderAdminCards(dashboard.cards || {});
        renderAdminRecentOrders(dashboard.recent_orders || []);
        renderAdminTopMovies(dashboard.top_movies || []);
        renderAdminRevenueByDay(dashboard.revenue_by_day || []);
    } catch (error) {
        showAlert('Lỗi tải Admin Dashboard: ' + error.message, 'danger');
        if (cardsEl) cardsEl.innerHTML = '';
        if (recentEl) recentEl.innerHTML = '';
        if (topMoviesEl) topMoviesEl.innerHTML = '';
        if (revenueEl) revenueEl.innerHTML = '';
    }
}

function renderAdminCards(cards) {
    const cardsEl = document.getElementById('adminStatsCards');
    if (!cardsEl) return;

    const items = [
        { label: 'Phim', value: cards.movies || 0, icon: '🎬', color: 'primary' },
        { label: 'Rạp', value: cards.theaters || 0, icon: '🏢', color: 'success' },
        { label: 'Suất chiếu', value: cards.showtimes || 0, icon: '🕒', color: 'info' },
        { label: 'Người dùng', value: cards.users || 0, icon: '👥', color: 'secondary' },
        { label: 'Đơn chờ', value: cards.pending_orders || 0, icon: '⏳', color: 'warning' },
        { label: 'Đơn xác nhận', value: cards.confirmed_orders || 0, icon: '✅', color: 'success' },
        { label: 'Hôm nay', value: formatCurrency(cards.today_revenue || 0), icon: '💵', color: 'danger' },
        { label: 'Tháng này', value: formatCurrency(cards.monthly_revenue || 0), icon: '📈', color: 'dark' },
    ];

    cardsEl.innerHTML = items.map(item => `
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">${item.label}</div>
                            <div class="h5 mb-0">${item.value}</div>
                        </div>
                        <span class="badge bg-${item.color} fs-6">${item.icon}</span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function renderAdminRecentOrders(orders) {
    const el = document.getElementById('adminRecentOrders');
    if (!el) return;

    if (!orders.length) {
        el.innerHTML = '<p class="text-muted mb-0">Chưa có đơn hàng.</p>';
        return;
    }

    el.innerHTML = `
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Khách</th>
                    <th>Phim</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Tổng</th>
                </tr>
            </thead>
            <tbody>
                ${orders.map(order => `
                    <tr>
                        <td>${order.code || order.id}</td>
                        <td>${order.customer || 'N/A'}</td>
                        <td>${order.movie || 'N/A'}</td>
                        <td><span class="badge bg-${adminStatusColor(order.status)}">${getStatusLabel(order.status)}</span></td>
                        <td class="text-end">${formatCurrency(order.total_amount || 0)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderAdminTopMovies(movies) {
    const el = document.getElementById('adminTopMovies');
    if (!el) return;

    if (!movies.length) {
        el.innerHTML = '<p class="text-muted mb-0">Chưa có dữ liệu doanh thu phim.</p>';
        return;
    }

    el.innerHTML = movies.map((movie, index) => `
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <div>
                <div class="fw-bold">${index + 1}. ${movie.title || 'N/A'}</div>
                <small class="text-muted">${movie.orders_count || 0} đơn</small>
            </div>
            <div class="text-end fw-bold text-danger">${formatCurrency(movie.revenue || 0)}</div>
        </div>
    `).join('');
}

function renderAdminRevenueByDay(rows) {
    const el = document.getElementById('adminRevenueByDay');
    if (!el) return;

    if (!rows.length) {
        el.innerHTML = '<p class="text-muted mb-0">Chưa có dữ liệu doanh thu.</p>';
        return;
    }

    el.innerHTML = `
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Số đơn</th>
                    <th class="text-end">Doanh thu</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map(row => `
                    <tr>
                        <td>${row.date ? new Date(row.date).toLocaleDateString('vi-VN') : 'N/A'}</td>
                        <td>${row.orders_count || 0}</td>
                        <td class="text-end fw-bold">${formatCurrency(row.revenue || 0)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function adminStatusColor(status) {
    return {
        pending: 'warning',
        confirmed: 'success',
        paid: 'success',
        completed: 'primary',
        cancelled: 'secondary',
        expired: 'dark',
        failed: 'danger',
    }[status] || 'secondary';
}

function getStatusLabel(status) {
    return {
        pending: 'Chờ xử lý',
        confirmed: 'Đã xác nhận',
        paid: 'Đã thanh toán',
        completed: 'Hoàn tất',
        cancelled: 'Đã hủy',
        expired: 'Hết hạn',
        failed: 'Thất bại',
    }[status] || status || 'N/A';
}

async function loadUserOrders(page = 1) {
    if (!currentUser) {
        toggleAuth();
        return;
    }

    const ordersSection = document.getElementById('orders');
    const ordersList = document.getElementById('ordersList');
    if (ordersSection) ordersSection.style.display = 'block';
    if (ordersList) {
        ordersList.innerHTML = `
            <div class="col-12 text-center py-4">
                <div class="spinner"></div>
                <p class="text-muted mt-2">Đang tải đơn hàng...</p>
            </div>
        `;
    }

    try {
        const response = await fetch(`${API_URL}/orders/user/me?page=${page}&per_page=6`, {
            headers: getAuthHeaders(false)
        });
        const data = await response.json();

        if (!data.success) {
            showAlert(data.message || 'Không thể tải đơn hàng', 'danger');
            if (ordersList) ordersList.innerHTML = '';
            return;
        }

        const payload = data.data || {};
        renderUserOrders(payload.items || []);
        renderOrdersPagination(payload.pagination || {});
    } catch (error) {
        showAlert('Lỗi tải đơn hàng: ' + error.message, 'danger');
        if (ordersList) ordersList.innerHTML = '';
    }
}

function renderUserOrders(orders) {
    const ordersList = document.getElementById('ordersList');
    if (!ordersList) return;

    if (!orders.length) {
        ordersList.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    Bạn chưa có đơn hàng nào. Hãy chọn phim và đặt vé ngay!
                </div>
            </div>
        `;
        return;
    }

    ordersList.innerHTML = orders.map(order => {
        const showtime = order.showtime || {};
        const movie = showtime.movie || {};
        const screen = showtime.screen || {};
        const theater = screen.theater || {};
        const seats = getOrderSeatLabels(order);
        const isConfirmed = ['confirmed', 'paid', 'completed'].includes(order.status) || Number(order.status_code) === 2;

        return `
            <div class="col-12 col-lg-6">
                <div class="order-card h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="small text-muted">Mã đơn</div>
                            <h5 class="mb-1">${escapeHtml(order.code || order.order_code || ('#' + order.id))}</h5>
                        </div>
                        <span class="badge bg-${adminStatusColor(order.status)}">${getStatusLabel(order.status)}</span>
                    </div>
                    <div class="order-meta">
                        <div><strong>Phim:</strong> ${escapeHtml(movie.title || 'N/A')}</div>
                        <div><strong>Rạp:</strong> ${escapeHtml(theater.name || 'N/A')}</div>
                        <div><strong>Phòng:</strong> ${escapeHtml(screen.name || screen.screen_name || 'N/A')}</div>
                        <div><strong>Suất chiếu:</strong> ${formatDateTime(showtime.start_time || showtime.show_time || showtime.started_at)}</div>
                        <div><strong>Ghế:</strong> ${escapeHtml(seats.join(', ') || 'N/A')}</div>
                        <div><strong>Thanh toán:</strong> ${escapeHtml(order.payment_status || 'N/A')}</div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3 pt-3 border-top">
                        <div class="fw-bold text-danger">${formatCurrency(order.total_amount || order.total_price || order.total || 0)}</div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="showTicket(${order.id})">
                                ${isConfirmed ? 'Mở vé' : 'Chi tiết'}
                            </button>
                            ${order.status === 'pending' ? `<button class="btn btn-sm btn-outline-danger" onclick="cancelOrder(${order.id})">Hủy</button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderOrdersPagination(pagination) {
    const el = document.getElementById('ordersPagination');
    if (!el) return;

    const current = Number(pagination.current_page || 1);
    const last = Number(pagination.last_page || 1);

    if (last <= 1) {
        el.innerHTML = '';
        return;
    }

    const buttons = [];
    for (let page = 1; page <= last; page++) {
        buttons.push(`
            <button class="btn btn-sm ${page === current ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadUserOrders(${page})">
                ${page}
            </button>
        `);
    }

    el.innerHTML = `<div class="btn-group flex-wrap" role="group">${buttons.join('')}</div>`;
}

async function showTicket(orderId) {
    if (!currentUser) {
        toggleAuth();
        return;
    }

    const ticketContent = document.getElementById('ticketContent');
    if (ticketContent) {
        ticketContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner"></div>
                <p class="text-muted mt-2">Đang tải vé...</p>
            </div>
        `;
    }

    const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
    modal.show();

    try {
        const response = await fetch(`${API_URL}/orders/${orderId}`, {
            headers: getAuthHeaders(false)
        });
        const data = await response.json();

        if (!data.success) {
            if (ticketContent) ticketContent.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data.message || 'Không thể tải vé')}</div>`;
            return;
        }

        renderTicket(data.data || {});
    } catch (error) {
        if (ticketContent) ticketContent.innerHTML = `<div class="alert alert-danger mb-0">Lỗi tải vé: ${escapeHtml(error.message)}</div>`;
    }
}

function renderTicket(order) {
    const ticketContent = document.getElementById('ticketContent');
    if (!ticketContent) return;

    const showtime = order.showtime || {};
    const movie = showtime.movie || {};
    const screen = showtime.screen || {};
    const theater = screen.theater || {};
    const seats = getOrderSeatLabels(order);
    const isConfirmed = ['confirmed', 'paid', 'completed'].includes(order.status) || Number(order.status_code) === 2;
    const qrText = encodeURIComponent(`${order.code || order.id}|${movie.title || ''}|${seats.join(',')}`);

    ticketContent.innerHTML = `
        <div class="ticket ${isConfirmed ? 'ticket-valid' : 'ticket-pending'}">
            <div class="ticket-header">
                <div>
                    <div class="ticket-brand">🎬 Cinema</div>
                    <div class="ticket-code">${escapeHtml(order.code || order.order_code || ('#' + order.id))}</div>
                </div>
                <span class="badge bg-${adminStatusColor(order.status)}">${getStatusLabel(order.status)}</span>
            </div>
            <div class="ticket-body">
                <div class="ticket-main">
                    <h4 class="ticket-movie">${escapeHtml(movie.title || 'N/A')}</h4>
                    <div class="row g-3">
                        <div class="col-md-6"><span>Rạp</span><strong>${escapeHtml(theater.name || 'N/A')}</strong></div>
                        <div class="col-md-6"><span>Phòng</span><strong>${escapeHtml(screen.name || screen.screen_name || 'N/A')}</strong></div>
                        <div class="col-md-6"><span>Suất chiếu</span><strong>${formatDateTime(showtime.start_time || showtime.show_time || showtime.started_at)}</strong></div>
                        <div class="col-md-6"><span>Ghế</span><strong>${escapeHtml(seats.join(', ') || 'N/A')}</strong></div>
                        <div class="col-md-6"><span>Thanh toán</span><strong>${escapeHtml(order.payment_status || 'N/A')}</strong></div>
                        <div class="col-md-6"><span>Tổng tiền</span><strong>${formatCurrency(order.total_amount || order.total_price || order.total || 0)}</strong></div>
                    </div>
                </div>
                <div class="ticket-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrText}" alt="QR vé">
                    <small>Quét mã để soát vé</small>
                </div>
            </div>
            <div class="ticket-note">
                ${isConfirmed ? 'Vé hợp lệ khi đơn hàng đã được xác nhận/thanh toán.' : 'Đơn hàng chưa được xác nhận. Vui lòng hoàn tất thanh toán trước khi sử dụng vé.'}
            </div>
        </div>
    `;
}

async function cancelOrder(orderId) {
    if (!confirm('Bạn chắc chắn muốn hủy đơn hàng này?')) return;

    try {
        const response = await fetch(`${API_URL}/orders/${orderId}`, {
            method: 'DELETE',
            headers: getAuthHeaders(false)
        });
        const data = await response.json();

        if (data.success) {
            showAlert('Đã hủy đơn hàng', 'success');
            loadUserOrders();
        } else {
            showAlert(data.message || 'Không thể hủy đơn hàng', 'danger');
        }
    } catch (error) {
        showAlert('Lỗi hủy đơn hàng: ' + error.message, 'danger');
    }
}

function getOrderSeatLabels(order) {
    const items = order.items || order.order_items || [];
    return items
        .map(item => item.metadata?.seat_label || item.seat_label || item.label || item.name)
        .filter(Boolean);
}

function printTicket() {
    const content = document.getElementById('ticketContent')?.innerHTML;
    if (!content) return;

    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write(`
        <html>
            <head>
                <title>In vé điện tử</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="css/style.css">
            </head>
            <body class="p-4">${content}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 300);
}

function formatDateTime(value) {
    if (!value) return 'N/A';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function formatCurrency(amount) {
    return Number(amount || 0).toLocaleString('vi-VN') + ' VNĐ';
}

function showAlert(message, type = 'info', trusted = false) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3 shadow`;
    alert.style.zIndex = '9999';

    const safeMessage = trusted ? message : Security.escapeHtml(message);
    alert.innerHTML = `
        <span>${safeMessage}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(alert);

    setTimeout(() => {
        const instance = bootstrap.Alert.getOrCreateInstance(alert);
        instance.close();
    }, 5000);
}
