(function () {
    'use strict';

    let heroBanners = [];
    let currentHeroIndex = 0;
    let heroTimer = null;

    let allTheaters = [];
    let currentTheater = null;
    let selectedDate = null;
    let cachedPricing = null;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function init() {
        initDates();
        await Promise.all([
            initHeroSlider(),
            initTheaterManager()
        ]);
    }

    function initDates() {
        const tabsContainer = document.getElementById('showtimeDateTabs');
        if (!tabsContainer) return;

        const dayNames = ['Chủ Nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        const dates = [];
        const today = new Date();

        for (let i = 0; i < 7; i++) {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;
            const labelDay = i === 0 ? 'Hôm nay' : dayNames[d.getDay()];
            const labelDate = `${dd}/${mm}`;
            dates.push({ date: dateStr, labelDay, labelDate });
        }

        selectedDate = dates[0].date;

        tabsContainer.innerHTML = dates.map((item, idx) => `
            <button type="button" class="date-tab-btn ${idx === 0 ? 'active' : ''}" data-date="${item.date}">
                ${escapeHtml(item.labelDay)} (${escapeHtml(item.labelDate)})
            </button>
        `).join('');

        tabsContainer.querySelectorAll('.date-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                tabsContainer.querySelectorAll('.date-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedDate = btn.dataset.date;
                if (currentTheater) {
                    loadShowtimes(currentTheater.id, selectedDate);
                }
            });
        });
    }

    async function initHeroSlider() {
        const slider = document.getElementById('theaterSection') || document.getElementById('theatersHeroSlider');
        const prevBtn = document.getElementById('heroPrevious') || document.getElementById('theatersHeroPrev');
        const nextBtn = document.getElementById('heroNext') || document.getElementById('theatersHeroNext');

        try {
            const res = await window.apiClient.get('/banners?placement=theater');
            if (res.success && Array.isArray(res.data) && res.data.length > 0) {
                heroBanners = res.data;
            } else {
                const fallback = await window.apiClient.get('/banners');
                if (fallback.success && Array.isArray(fallback.data) && fallback.data.length > 0) {
                    heroBanners = fallback.data;
                }
            }
        } catch (e) {
            heroBanners = [];
        }

        if (!heroBanners || heroBanners.length === 0) {
            heroBanners = [
                {
                    title: 'Trải Nghiệm Điện Ảnh Đỉnh Cao',
                    description: 'Hệ thống phòng chiếu chuẩn quốc tế với âm thanh vòm sống động và màn chiếu sắc nét.',
                    image_url: 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=1200&auto=format&fit=crop'
                },
                {
                    title: 'Không Gian Hiện Đại & Sang Trọng',
                    description: 'Ghế ngồi êm ái, bắp nước hảo hạng, dịch vụ tận tâm đón chào quý khán giả.',
                    image_url: 'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=1200&auto=format&fit=crop'
                },
                {
                    title: 'Công Nghệ Chiếu Phim Vượt Trội',
                    description: 'Định dạng IMAX, Dolby Atmos đỉnh cao đưa từng thước phim đến gần bạn hơn bao giờ hết.',
                    image_url: 'https://images.unsplash.com/photo-1440404653325-ab127d49abc1?q=80&w=1200&auto=format&fit=crop'
                }
            ];
        }

        renderHeroSlide(currentHeroIndex);
        renderHeroDots();

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                changeHeroSlide(-1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                changeHeroSlide(1);
            });
        }

        startHeroAutoplay();

        if (slider) {
            slider.addEventListener('mouseenter', stopHeroAutoplay);
            slider.addEventListener('mouseleave', startHeroAutoplay);
        }
    }

    function renderHeroSlide(index) {
        const backdrop = document.getElementById('heroBackdrop') || document.getElementById('theatersHeroBackdrop');
        const titleEl = document.getElementById('heroTitle') || document.getElementById('theatersHeroTitle');
        const descEl = document.getElementById('heroDesc') || document.getElementById('theatersHeroDesc');
        if (!heroBanners.length) return;

        const banner = heroBanners[index];
        const imageUrl = safeImageUrl(banner.image_url || banner.images?.[0]?.url || banner.images?.[0]?.file_url);

        if (backdrop && imageUrl) {
            backdrop.style.setProperty('--hero-backdrop-image', `url("${imageUrl}")`);
            backdrop.style.backgroundImage = `url("${imageUrl}")`;
        }

        if (titleEl && banner.title) {
            titleEl.textContent = banner.title;
        }
        if (descEl) {
            descEl.textContent = banner.description || 'Hệ thống phòng chiếu chuẩn quốc tế với âm thanh vòm sống động và màn chiếu sắc nét.';
        }

        updateHeroDots(index);
    }

    function renderHeroDots() {
        const dotsContainer = document.getElementById('heroDots') || document.getElementById('theatersHeroDots');
        if (!dotsContainer) return;

        if (heroBanners.length <= 1) {
            dotsContainer.innerHTML = '';
            return;
        }

        dotsContainer.innerHTML = heroBanners.map((_, idx) => `
            <button type="button" class="hero-carousel-dot theaters-slider-dot ${idx === currentHeroIndex ? 'active' : ''}" data-index="${idx}" aria-label="Chuyển đến banner ${idx + 1}"></button>
        `).join('');

        dotsContainer.querySelectorAll('.hero-carousel-dot, .theaters-slider-dot').forEach(dot => {
            dot.addEventListener('click', () => {
                const idx = parseInt(dot.dataset.index, 10);
                if (!isNaN(idx)) {
                    currentHeroIndex = idx;
                    renderHeroSlide(currentHeroIndex);
                    restartHeroAutoplay();
                }
            });
        });
    }

    function updateHeroDots(activeIndex) {
        const dotsContainer = document.getElementById('heroDots') || document.getElementById('theatersHeroDots');
        if (!dotsContainer) return;
        const dots = dotsContainer.querySelectorAll('.hero-carousel-dot, .theaters-slider-dot');
        dots.forEach((dot, idx) => {
            dot.classList.toggle('active', idx === activeIndex);
        });
    }

    function changeHeroSlide(direction) {
        if (heroBanners.length <= 1) return;
        currentHeroIndex = (currentHeroIndex + direction + heroBanners.length) % heroBanners.length;
        renderHeroSlide(currentHeroIndex);
        restartHeroAutoplay();
    }

    function startHeroAutoplay() {
        stopHeroAutoplay();
        if (heroBanners.length > 1) {
            heroTimer = window.setInterval(() => {
                changeHeroSlide(1);
            }, 5000);
        }
    }

    function stopHeroAutoplay() {
        if (heroTimer) {
            window.clearInterval(heroTimer);
            heroTimer = null;
        }
    }

    function restartHeroAutoplay() {
        stopHeroAutoplay();
        startHeroAutoplay();
    }

    async function initTheaterManager() {
        const citySelect = document.getElementById('citySelect');
        const theaterSelect = document.getElementById('theaterSelect');

        try {
            const [citiesRes, theatersRes] = await Promise.all([
                window.apiClient.get('/theaters/cities'),
                window.apiClient.get('/theaters?status=active&per_page=50')
            ]);

            const cities = (citiesRes && citiesRes.success && Array.isArray(citiesRes.data)) ? citiesRes.data : [];
            allTheaters = (theatersRes && theatersRes.success && Array.isArray(theatersRes.data)) ? theatersRes.data : [];

            if (citySelect) {
                const cityOptions = ['<option value="">Tất cả khu vực</option>'];
                cities.forEach(c => {
                    const safeC = String(c ?? '').trim();
                    if (safeC) {
                        cityOptions.push(`<option value="${escapeHtml(safeC)}">${escapeHtml(safeC)}</option>`);
                    }
                });
                citySelect.innerHTML = cityOptions.join('');
            }

            const urlParams = new URLSearchParams(window.location.search);
            const targetId = urlParams.get('theater');

            if (targetId && allTheaters.some(t => String(t.id) === String(targetId))) {
                currentTheater = allTheaters.find(t => String(t.id) === String(targetId));
            } else if (allTheaters.length > 0) {
                currentTheater = allTheaters[0];
            }

            if (currentTheater) {
                const currentCity = currentTheater.branch?.name || currentTheater.city || '';
                if (citySelect && currentCity) {
                    citySelect.value = currentCity;
                }
                populateTheaterSelect(currentCity, currentTheater.id);
                selectTheater(currentTheater);
            }

            if (citySelect) {
                citySelect.addEventListener('change', () => {
                    const chosenCity = citySelect.value;
                    const matching = filterTheatersByCity(chosenCity);
                    if (matching.length > 0) {
                        if (!matching.some(t => t.id === currentTheater?.id)) {
                            selectTheater(matching[0]);
                        }
                        populateTheaterSelect(chosenCity, currentTheater?.id);
                    } else {
                        populateTheaterSelect(chosenCity, null);
                    }
                });
            }

            if (theaterSelect) {
                theaterSelect.addEventListener('change', () => {
                    const selId = theaterSelect.value;
                    const matched = allTheaters.find(t => String(t.id) === String(selId));
                    if (matched) {
                        selectTheater(matched);
                    }
                });
            }
        } catch (e) {
            console.error('Failed to init theaters:', e);
        }
    }

    function filterTheatersByCity(city) {
        if (!city) return allTheaters;
        const normalized = city.toLowerCase();
        return allTheaters.filter(t => {
            const bName = String(t.branch?.name ?? '').toLowerCase();
            const cName = String(t.city ?? '').toLowerCase();
            const addr = String(t.address ?? '').toLowerCase();
            return bName === normalized || cName === normalized || addr.includes(normalized);
        });
    }

    function populateTheaterSelect(city, selectedId) {
        const theaterSelect = document.getElementById('theaterSelect');
        if (!theaterSelect) return;

        const filtered = filterTheatersByCity(city);
        if (filtered.length === 0) {
            theaterSelect.innerHTML = '<option value="">Không có rạp tại khu vực này</option>';
            return;
        }

        theaterSelect.innerHTML = filtered.map(t => `
            <option value="${t.id}" ${String(t.id) === String(selectedId) ? 'selected' : ''}>
                ${escapeHtml(t.name)}
            </option>
        `).join('');
    }

    function selectTheater(theater) {
        currentTheater = theater;

        const url = new URL(window.location);
        url.searchParams.set('theater', theater.id);
        window.history.replaceState({}, '', url);

        const nameEl = document.getElementById('currentTheaterName');
        const addrEl = document.getElementById('currentTheaterAddress');
        const phoneEl = document.getElementById('currentTheaterPhone');

        if (nameEl) nameEl.textContent = theater.name || '';
        if (addrEl) {
            addrEl.innerHTML = `<i class="bi bi-geo-alt-fill text-danger me-1"></i> ${escapeHtml(theater.address || 'Đang cập nhật địa chỉ')}`;
        }
        if (phoneEl) {
            phoneEl.innerHTML = `<i class="bi bi-telephone-fill text-danger me-1"></i> Hotline: ${escapeHtml(theater.phone || '1900 2224')}`;
        }

        const detailAddr = document.getElementById('detailAddress');
        const detailPhone = document.getElementById('detailPhone');
        const detailEmail = document.getElementById('detailEmail');
        const detailDesc = document.getElementById('detailDescription');
        const detailAmenities = document.getElementById('detailAmenities');

        if (detailAddr) detailAddr.textContent = theater.address || '--';
        if (detailPhone) detailPhone.textContent = theater.phone || '1900 2224';
        if (detailEmail) detailEmail.textContent = theater.email || 'support@cinema.vn';
        if (detailDesc) {
            detailDesc.innerHTML = `<p class="text-secondary mb-0">${escapeHtml(theater.description || 'Hệ thống rạp chiếu phim chất lượng cao với màn chiếu sắc nét, không gian âm thanh chân thực và các tiện nghi dịch vụ cao cấp.')}</p>`;
        }

        if (detailAmenities) {
            const badges = getTheaterAmenities(theater);
            detailAmenities.innerHTML = badges.map(b => `<span class="amenity-tag">${escapeHtml(b)}</span>`).join('');
        }

        loadPricing(theater.id);
        loadShowtimes(theater.id, selectedDate);
    }

    function getTheaterAmenities(theater) {
        const badges = new Set();
        const screens = Array.isArray(theater.screens) ? theater.screens : [];
        screens.forEach(s => {
            const cap = `${s.name || ''} ${s.format?.name || ''}`.toUpperCase();
            if (cap.includes('IMAX')) badges.add('IMAX');
            if (cap.includes('DOLBY') || cap.includes('ATMOS')) badges.add('Dolby Atmos');
            if (cap.includes('GOLD')) badges.add('Gold Class');
            if (cap.includes('3D')) badges.add('3D Cinema');
            if (cap.includes('LASER')) badges.add('Laser Projection');
        });
        if (badges.size === 0) {
            badges.add('Dolby Atmos');
            badges.add('Laser Projection');
            badges.add('Ghế VIP & Ghế Đôi');
            badges.add('Bắp Nước Đa Dạng');
            badges.add('Bãi Đỗ Xe Tiện Lợi');
        }
        return Array.from(badges);
    }

    async function loadPricing(theaterId) {
        const pricingArea = document.getElementById('pricingContentArea');
        if (!pricingArea) return;

        try {
            if (!cachedPricing) {
                const res = await window.apiClient.get('/prices');
                if (res.success && res.data && Array.isArray(res.data.theaters)) {
                    cachedPricing = res.data.theaters;
                }
            }

            const theaterPricing = (cachedPricing || []).find(p => String(p.theater?.id) === String(theaterId)) || (cachedPricing || [])[0];

            if (!theaterPricing || !theaterPricing.format_tables || theaterPricing.format_tables.length === 0) {
                pricingArea.innerHTML = '<div class="text-center py-4 text-secondary">Chưa có bảng giá cho rạp này.</div>';
                return;
            }

            const tablesHtml = theaterPricing.format_tables.map(fTable => {
                const rowsHtml = (fTable.rows || []).map(row => {
                    const isHappyDay = String(row.title || '').toLowerCase().includes('happy');
                    return `
                        <tr class="${isHappyDay ? 'happy-day-row' : ''}">
                            <td class="text-start ps-3 fw-bold">${escapeHtml(row.title)}</td>
                            <td class="price-val-highlight">${formatPrice(row.adult)}</td>
                            <td>${formatPrice(row.u22)}</td>
                            <td>${formatPrice(row.child)}</td>
                        </tr>
                    `;
                }).join('');

                const noteText = fTable.seat_notes ? `* ${fTable.seat_notes}` : '* Giá vé trên chưa bao gồm phụ thu phòng chiếu đặc biệt hoặc suất chiếu sớm.';

                return `
                    <div class="pricing-format-block mb-4">
                        <h4 class="h6 text-danger fw-bold mb-2">ĐỊNH DẠNG: ${escapeHtml(fTable.format?.name || '2D')}</h4>
                        <div class="pricing-table-scroll">
                            <table class="pricing-table-custom">
                                <thead>
                                    <tr>
                                        <th class="text-start ps-3">THỜI GIAN / LOẠI GHẾ</th>
                                        <th>NGƯỜI LỚN</th>
                                        <th>U22 / HSSV</th>
                                        <th>TRẺ EM & CAO TUỔI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rowsHtml}
                                </tbody>
                            </table>
                        </div>
                        <div class="pricing-notes-box">
                            <i class="bi bi-info-circle me-1"></i> ${escapeHtml(noteText)}
                        </div>
                    </div>
                `;
            }).join('');

            const rulesHtml = `
                <div class="pricing-notes-box mt-3">
                    <div class="fw-bold text-white mb-1">* LƯU Ý KHI ĐẶT VÉ</div>
                    <div class="small text-secondary">
                        - Hệ thống đặt vé trực tuyến mặc định áp dụng giá vé Người lớn.<br>
                        - Khách hàng thuộc đối tượng U22, Học sinh - Sinh viên, Trẻ em, Người cao tuổi vui lòng xuất trình giấy tờ tùy thân hợp lệ tại quầy vé để áp dụng ưu đãi.<br>
                        - Vé Trẻ em dành cho khách hàng cao từ 0.7m đến 1.3m. Trẻ em dưới 0.7m được miễn phí vé khi ngồi cùng người lớn.
                    </div>
                </div>
            `;

            pricingArea.innerHTML = tablesHtml + rulesHtml;
        } catch (e) {
            console.error('Failed to load pricing:', e);
            pricingArea.innerHTML = '<div class="text-center py-4 text-danger">Không thể tải dữ liệu bảng giá lúc này.</div>';
        }
    }

    async function loadShowtimes(theaterId, date) {
        const skeleton = document.getElementById('showtimesSkeleton');
        const grid = document.getElementById('theaterMoviesGrid');
        const empty = document.getElementById('showtimesEmptyState');

        if (skeleton) skeleton.classList.remove('d-none');
        if (grid) grid.classList.add('d-none');
        if (empty) empty.classList.add('d-none');

        try {
            const dateParam = date || new Date().toISOString().split('T')[0];
            const res = await window.apiClient.get(`/showtimes?theater_id=${encodeURIComponent(theaterId)}&date=${encodeURIComponent(dateParam)}&status=all&time_scope=all&per_page=100`);

            if (skeleton) skeleton.classList.add('d-none');

            const showtimes = (res && res.data && (Array.isArray(res.data.data) ? res.data.data : Array.isArray(res.data) ? res.data : [])) || [];

            if (showtimes.length === 0) {
                if (empty) empty.classList.remove('d-none');
                return;
            }

            const movieGroups = new Map();
            showtimes.forEach(st => {
                const movieId = st.movie?.id || st.movie_id;
                if (!movieId) return;
                if (!movieGroups.has(movieId)) {
                    movieGroups.set(movieId, {
                        movie: st.movie,
                        formats: new Map()
                    });
                }
                const mGroup = movieGroups.get(movieId);
                const formatName = st.format?.name || st.screen?.format?.name || '2D Standard';
                if (!mGroup.formats.has(formatName)) {
                    mGroup.formats.set(formatName, []);
                }
                mGroup.formats.get(formatName).push(st);
            });

            if (movieGroups.size === 0) {
                if (empty) empty.classList.remove('d-none');
                return;
            }

            const cardsHtml = Array.from(movieGroups.values()).map(g => {
                const movie = g.movie || {};
                const posterUrl = safeImageUrl(movie.poster_display_url || movie.poster_url || (movie.poster_path ? `/storage/${String(movie.poster_path).replace(/^\/+/, '')}` : ''));
                const safePoster = posterUrl || 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=300&auto=format&fit=crop';
                const ageBadge = movie.age_rating ? `<span class="movie-badge age-rating">${escapeHtml(movie.age_rating)}</span>` : '';
                const durationBadge = movie.duration ? `<span class="movie-badge">${escapeHtml(movie.duration)} phút</span>` : '';

                const formatsHtml = Array.from(g.formats.entries()).map(([fName, slots]) => {
                    const sortedSlots = slots.slice().sort((a, b) => {
                        const tA = new Date(a.scheduled_at || a.start_time).getTime();
                        const tB = new Date(b.scheduled_at || b.start_time).getTime();
                        return tA - tB;
                    });

                    const slotsHtml = sortedSlots.map(slot => {
                        const timeStr = formatShowtime(slot.scheduled_at || slot.start_time);
                        const bookingId = slot.encrypted_id || slot.id;
                        return `<a href="/booking/${bookingId}" class="showtime-slot">${escapeHtml(timeStr)}</a>`;
                    }).join('');

                    return `
                        <div class="format-slot-group mb-3">
                            <div class="small text-secondary fw-bold mb-2">${escapeHtml(fName)}</div>
                            <div class="showtimes-slots-wrap">
                                ${slotsHtml}
                            </div>
                        </div>
                    `;
                }).join('');

                return `
                    <div class="showtime-movie-card">
                        <img src="${safePoster}" alt="${escapeHtml(movie.title || 'Movie')}" class="movie-poster-thumb" loading="lazy">
                        <div class="movie-card-info">
                            <h4 class="movie-card-title">${escapeHtml(movie.title || 'Đang cập nhật')}</h4>
                            <div class="movie-card-badges">
                                ${ageBadge}
                                ${durationBadge}
                            </div>
                            <div class="movie-formats-wrapper">
                                ${formatsHtml}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            if (grid) {
                grid.innerHTML = cardsHtml;
                grid.classList.remove('d-none');
            }
        } catch (e) {
            console.error('Failed to load showtimes:', e);
            if (skeleton) skeleton.classList.add('d-none');
            if (empty) empty.classList.remove('d-none');
        }
    }

    function formatShowtime(dateStr) {
        if (!dateStr) return '--:--';
        try {
            const d = new Date(dateStr);
            if (!isNaN(d.getTime())) {
                const hh = String(d.getHours()).padStart(2, '0');
                const mm = String(d.getMinutes()).padStart(2, '0');
                return `${hh}:${mm}`;
            }
            const parts = String(dateStr).split(' ');
            if (parts[1]) return parts[1].substring(0, 5);
            return dateStr;
        } catch (e) {
            return '--:--';
        }
    }

    function formatPrice(val) {
        const num = Number(val || 0);
        return `${num.toLocaleString('vi-VN')}đ`;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function safeImageUrl(value) {
        const candidate = String(value || '').trim();
        if (!candidate) return '';
        if (/^\/(?!\/)[A-Za-z0-9_./?=&%-]+$/.test(candidate) && !candidate.includes('..')) return candidate;
        if (/^https?:\/\/[^\s"'<>]+$/i.test(candidate)) return candidate.replace(/[()\\]/g, encodeURIComponent);
        return '';
    }
})();
