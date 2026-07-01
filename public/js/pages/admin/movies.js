/**
 * Movies Management - movies.js
 * SPA Architecture
 */
(function () {
    'use strict';

    const els = {
        tableBody: document.getElementById('moviesTableBody'),
        pagination: document.getElementById('paginationContainer'),
        
        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        movieTabs: document.getElementById('movieTabs'),

        btnCreate: document.getElementById('btnOpenCreateMovie'),
        modalEl: document.getElementById('movieModal'),
        form: document.getElementById('movieForm'),
        modalLabel: document.getElementById('movieModalLabel'),
        
        formMethod: document.getElementById('movieFormMethod'),
        idInput: document.getElementById('movieIdInput'),
        
        // Form fields
        title: document.getElementById('movieTitle'),
        originalTitle: document.getElementById('movieOriginalTitle'),
        duration: document.getElementById('movieDuration'),
        releaseDate: document.getElementById('movieReleaseDate'),
        endDate: document.getElementById('movieEndDate'),
        ageRating: document.getElementById('movieAgeRating'),
        surcharge: document.getElementById('movieSurcharge'),
        trailerUrl: document.getElementById('movieTrailer'),
        description: document.getElementById('movieDescription'),
        director: document.getElementById('movieDirector'),
        cast: document.getElementById('movieCast'),
        statusToggle: document.getElementById('movieStatusToggle'),
        statusLabel: document.getElementById('movieStatusLabel'),
        isHidden: document.getElementById('movieIsHidden'),
        posterUrl: document.getElementById('moviePosterUrl'),
        bannerUrl: document.getElementById('movieBannerUrl'),
        isHot: document.getElementById('movieIsHot'),
        
        posterPreview: document.getElementById('posterPreview'),
        posterPlaceholder: document.getElementById('posterPlaceholder'),
        posterFile: document.getElementById('moviePosterFile'),
        clearPosterBtn: document.getElementById('clearPosterBtn'),
        posterUploadBox: document.getElementById('posterUploadBox'),

        bannerPreview: document.getElementById('bannerPreview'),
        bannerPlaceholder: document.getElementById('bannerPlaceholder'),
        bannerFile: document.getElementById('movieBannerFile'),
        clearBannerBtn: document.getElementById('clearBannerBtn'),
    };

    let currentPage = 1;
    let currentSearch = '';
    let currentStatus = 'all';

    function getModalInstance() {
        if (!els.modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1, search = '', status = 'all') {
        try {
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;
            
            const url = new URL(window.location.origin + '/api/v1/movies');
            url.searchParams.append('page', page);
            if (search) url.searchParams.append('q', search);
            if (status !== 'all') url.searchParams.append('status', status);

            const res = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (res.ok) {
                const json = await res.json();
                renderTable(json.data, json.pagination?.from);
                renderPagination(json.pagination);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(movies, startIndex) {
        if (!movies || movies.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy bộ phim nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        const now = new Date();
        movies.forEach((movie, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            
            const rDateObj = new Date(movie.release_date);
            const rDate = rDateObj.toLocaleDateString('vi-VN');
            
            // Computed status
            let statusHtml = '';
            if (!movie.status) {
                statusHtml = '<span class="badge bg-secondary">Nháp</span>';
            } else {
                if (rDateObj > now) {
                    statusHtml = '<span class="badge bg-warning text-dark">Sắp chiếu</span>';
                } else {
                    statusHtml = '<span class="badge bg-success">Đã xuất bản</span>';
                }
            }
            
            const isHotChecked = movie.is_hot ? 'checked' : '';
            const isActiveChecked = movie.status ? 'checked' : '';
            const posterSrc = movie.poster_display_url || movie.poster_url || '';
            
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td class="text-center">
                    ${posterSrc 
                        ? `<img src="${posterSrc}" alt="Poster" style="width: 50px; height: 75px; object-fit: cover; border-radius: 4px;">` 
                        : `<div style="width: 50px; height: 75px; background: rgba(255,255,255,0.1); border-radius: 4px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-image text-white-50"></i></div>`}
                </td>
                <td>
                    <div class="fw-medium text-white fs-6">${movie.title}</div>
                    <div class="small text-white-50 mt-1">Đạo diễn: ${movie.director || 'N/A'} • ${movie.duration} phút</div>
                    <div class="small text-white-50 mt-1"><i class="bi bi-calendar3 me-1"></i> ${rDate} <span class="badge bg-dark border border-secondary ms-2">${movie.age_rating || 'P'}</span></div>
                </td>
                <td class="text-center">${statusHtml}</td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input toggle-active-btn" type="checkbox" role="switch"
                            data-id="${movie.id}" ${isActiveChecked} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input toggle-hot-btn" type="checkbox" role="switch"
                            data-id="${movie.id}" ${isHotChecked} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-movie"
                            style="color: var(--text-secondary); background:rgba(255,255,255,0.05);"
                            data-movie='${JSON.stringify(movie).replace(/'/g, "&#39;")}'
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-movie"
                            style="color:#ef4444; background:rgba(239,68,68,0.1);" data-id="${movie.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            els.tableBody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        if (!meta || meta.last_page <= 1) {
            els.pagination.innerHTML = '';
            return;
        }
        
        let html = '<ul class="pagination pagination-sm m-0">';
        if (meta.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page - 1}">&laquo;</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">&laquo;</span></li>`;
        }

        for (let i = 1; i <= meta.last_page; i++) {
            if (i === meta.current_page) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }
        }

        if (meta.current_page < meta.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page + 1}">&raquo;</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">&raquo;</span></li>`;
        }
        html += '</ul>';
        
        els.pagination.innerHTML = html;
        els.pagination.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                currentPage = parseInt(a.getAttribute('data-page'));
                loadData(currentPage, currentSearch, currentStatus);
            });
        });
    }

    /* ── Forms & Interactions ──────────────────────────────────────── */
    function resetForm() {
        if (!els.form) return;
        els.form.reset();
        els.formMethod.value = 'POST';
        els.idInput.value = '';
        // Reset status label
        if (els.statusLabel) els.statusLabel.textContent = 'Nháp';
        // Uncheck all category checkboxes
        document.querySelectorAll('.category-cb').forEach(cb => cb.checked = false);
        // Reset poster
        clearPosterPreview();
        clearBannerPreview();
    }

    function showPosterPreview(src) {
        if (!src) return;
        els.posterPreview.src = src;
        els.posterPreview.style.display = 'block';
        if (els.posterPlaceholder) els.posterPlaceholder.style.display = 'none';
        if (els.clearPosterBtn) els.clearPosterBtn.classList.remove('d-none');
    }

    function clearPosterPreview() {
        if (els.posterPreview) { els.posterPreview.src = ''; els.posterPreview.style.display = 'none'; }
        if (els.posterPlaceholder) els.posterPlaceholder.style.display = 'flex';
        if (els.posterFile) els.posterFile.value = '';
        if (els.clearPosterBtn) els.clearPosterBtn.classList.add('d-none');
    }

    function showBannerPreview(src) {
        if (!src) return;
        els.bannerPreview.src = src;
        els.bannerPreview.style.display = 'block';
        if (els.bannerPlaceholder) els.bannerPlaceholder.style.display = 'none';
        if (els.clearBannerBtn) els.clearBannerBtn.classList.remove('d-none');
    }

    function clearBannerPreview() {
        if (els.bannerPreview) { els.bannerPreview.src = ''; els.bannerPreview.style.display = 'none'; }
        if (els.bannerPlaceholder) els.bannerPlaceholder.style.display = 'flex';
        if (els.bannerFile) els.bannerFile.value = '';
        if (els.clearBannerBtn) els.clearBannerBtn.classList.add('d-none');
    }

    if (els.btnCreate) {
        els.btnCreate.addEventListener('click', () => {
            resetForm();
            els.modalLabel.innerHTML = '<i class="bi bi-film me-2"></i>Thêm phim mới';
            getModalInstance()?.show();
        });
    }

    // File input preview events
    if (els.posterFile) {
        els.posterFile.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) showPosterPreview(URL.createObjectURL(file));
        });
    }
    if (els.clearPosterBtn) {
        els.clearPosterBtn.addEventListener('click', (e) => { e.stopPropagation(); clearPosterPreview(); });
    }
    if (els.posterUploadBox) {
        els.posterUploadBox.addEventListener('dragover', (e) => { e.preventDefault(); els.posterUploadBox.style.borderColor = 'rgba(255,255,255,0.4)'; });
        els.posterUploadBox.addEventListener('dragleave', () => { els.posterUploadBox.style.borderColor = 'rgba(255,255,255,0.15)'; });
        els.posterUploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            els.posterUploadBox.style.borderColor = 'rgba(255,255,255,0.15)';
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                const dt = new DataTransfer();
                dt.items.add(file);
                els.posterFile.files = dt.files;
                showPosterPreview(URL.createObjectURL(file));
            }
        });
    }
    if (els.bannerFile) {
        els.bannerFile.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) showBannerPreview(URL.createObjectURL(file));
        });
    }
    if (els.clearBannerBtn) {
        els.clearBannerBtn.addEventListener('click', (e) => { e.stopPropagation(); clearBannerPreview(); });
    }

    els.tableBody.addEventListener('click', async (e) => {
        // Edit
        const btnEdit = e.target.closest('.btn-edit-movie');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';
            
            const movie = JSON.parse(btnEdit.dataset.movie);
            els.idInput.value = movie.id;
            els.modalLabel.innerHTML = '<i class="bi bi-film me-2"></i>Cập nhật phim';
            
            // Điền các field cơ bản
            els.title.value = movie.title || '';
            if (els.originalTitle) els.originalTitle.value = movie.original_title || '';
            els.duration.value = movie.duration || '';
            els.releaseDate.value = movie.release_date ? movie.release_date.split('T')[0] : '';
            if (els.endDate) els.endDate.value = movie.end_date ? movie.end_date.split('T')[0] : '';
            els.ageRating.value = movie.age_rating || 'P';
            if (els.surcharge) els.surcharge.value = movie.surcharge || '';
            els.trailerUrl.value = movie.trailer_url || '';
            if (els.description) els.description.value = movie.description || '';
            els.director.value = movie.director || '';
            els.cast.value = movie.cast || '';
            els.isHot.checked = !!movie.is_hot;
            if (els.isHidden) els.isHidden.checked = !!movie.is_hidden;

            // Trạng thái xuất bản (boolean)
            if (els.statusToggle) {
                els.statusToggle.checked = !!movie.status;
                if (els.statusLabel) els.statusLabel.textContent = movie.status ? 'Đã xuất bản' : 'Nháp';
            }

            // Tick thể loại
            if (movie.categories && movie.categories.length) {
                const catIds = movie.categories.map(c => c.id);
                document.querySelectorAll('.category-cb').forEach(cb => {
                    cb.checked = catIds.includes(parseInt(cb.value));
                });
            }

            // Hiển thị poster/banner hiện tại
            const posterSrc = movie.poster_display_url || movie.poster_url || '';
            if (posterSrc) showPosterPreview(posterSrc);
            else clearPosterPreview();

            const bannerSrc = movie.banner_display_url || '';
            if (bannerSrc) showBannerPreview(bannerSrc);
            else clearBannerPreview();

            getModalInstance()?.show();
            return;
        }

        // Delete
        const btnDel = e.target.closest('.btn-delete-movie');
        if (btnDel) {
            if(!confirm('Bạn có chắc muốn xóa phim này? Thao tác này sẽ xóa mọi dữ liệu lịch chiếu liên quan!')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/movies/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa thành công', 'success');
                    loadData(currentPage, currentSearch, currentStatus);
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Xóa thất bại', 'error');
                }
            } catch (err) {}
            return;
        }
    });

    els.tableBody.addEventListener('change', async (e) => {
        // Toggle Active
        const toggleActive = e.target.closest('.toggle-active-btn');
        if (toggleActive) {
            const id = toggleActive.getAttribute('data-id');
            const isActive = toggleActive.checked;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/movies/${id}/toggle-active`, { method: 'POST' });
                if (!res || !res.ok) throw new Error();
                window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
                loadData(currentPage, currentSearch, currentStatus);
            } catch (error) {
                window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
                toggleActive.checked = !isActive;
            }
            return;
        }

        // Toggle Hot
        const toggleHot = e.target.closest('.toggle-hot-btn');
        if (toggleHot) {
            const id = toggleHot.getAttribute('data-id');
            const isHot = toggleHot.checked;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/movies/${id}/toggle-hot`, { method: 'POST' });
                if (!res || !res.ok) throw new Error();
                window.showAdminToast?.('Cập nhật trạng thái Hot thành công', 'success');
                loadData(currentPage, currentSearch, currentStatus);
            } catch (error) {
                window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
                toggleHot.checked = !isHot;
            }
            return;
        }
    });

    // Update status label khi toggle
    if (els.statusToggle) {
        els.statusToggle.addEventListener('change', () => {
            if (els.statusLabel) {
                els.statusLabel.textContent = els.statusToggle.checked ? 'Đã xuất bản' : 'Nháp';
            }
        });
    }

    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = els.formMethod.value === 'PUT';
            const id = els.idInput.value;
            // Edit dùng route POST /admin/movies/{id}/update (hỗ trợ file upload)
            const url = isEdit
                ? `/api/v1/admin/movies/${id}/update`
                : `/api/v1/admin/movies`;

            // Build FormData (hỗ trợ file upload)
            const formData = new FormData();

            // Thêm các field text
            const textFields = [
                'title','original_title','duration','release_date','end_date',
                'age_rating','surcharge','trailer_url','description','director','cast'
            ];
            textFields.forEach(key => {
                const el = els.form.querySelector(`[name="${key}"]`);
                if (el && el.value !== '') formData.append(key, el.value);
            });

            // Booleans
            formData.append('status', els.statusToggle?.checked ? '1' : '0');
            formData.append('is_hot', els.isHot.checked ? '1' : '0');
            formData.append('is_hidden', els.isHidden?.checked ? '1' : '0');

            // Category IDs
            document.querySelectorAll('.category-cb:checked').forEach(cb => {
                formData.append('category_ids[]', cb.value);
            });

            // File uploads
            if (els.posterFile?.files[0]) formData.append('poster_file', els.posterFile.files[0]);
            if (els.bannerFile?.files[0]) formData.append('banner_file', els.bannerFile.files[0]);

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: 'POST',
                    body: formData,
                });

                if (res && res.ok) {
                    getModalInstance()?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật thành công!' : 'Thêm phim thành công!', 'success');
                    loadData(currentPage, currentSearch, currentStatus);
                } else {
                    const errData = await res.json();
                    alert('Dữ liệu không hợp lệ: ' + JSON.stringify(errData.errors || errData.message));
                }
            } catch (error) {
                console.error('Submit form error', error);
            }
        });
    }

    if (els.searchForm) {
        els.searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            currentSearch = els.searchInput.value.trim();
            currentPage = 1;
            loadData(currentPage, currentSearch, currentStatus);
        });
    }
    
    if (els.movieTabs) {
        els.movieTabs.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON') {
                els.movieTabs.querySelectorAll('button').forEach(btn => {
                    btn.classList.remove('active');
                });
                e.target.classList.add('active');
                
                currentStatus = e.target.getAttribute('data-status');
                currentPage = 1;
                loadData(currentPage, currentSearch, currentStatus);
            }
        });
    }

    /* ── Init ──────────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        loadData(1, '', 'all');
    });

})();
