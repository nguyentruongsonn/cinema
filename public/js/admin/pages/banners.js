/**
 * Banners Management - banners.js
 * Simple SPA Architecture (following pattern)
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function safeImageUrl(value, fallback = '/images/placeholder.png') {
        const candidate = String(value || '').trim();
        if (/^data:image\/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+$/i.test(candidate)) return candidate;
        if (/^\/(?!\/)[A-Za-z0-9_./?=&%-]+$/.test(candidate) && !candidate.includes('..')) return candidate;
        if (/^https?:\/\/[^\s"'<>]+$/i.test(candidate)) return candidate;
        return fallback;
    }

    function storageImageUrl(path) {
        const cleanPath = String(path || '').replace(/^\/+/, '');
        return safeImageUrl(`/storage/${cleanPath}`);
    }

    const els = {
        tableBody: document.getElementById('bannersTableBody'),
        pagination: document.getElementById('paginationContainer'),
        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        positionFilter: document.getElementById('positionFilter'),
        statusFilter: document.getElementById('statusFilter'),
        btnCreate: document.getElementById('btnCreateBanner'),
        modalEl: document.getElementById('bannerModal'),
        form: document.getElementById('bannerForm'),
        modalLabel: document.getElementById('bannerModalLabel'),
        formMethod: document.getElementById('formMethod'),
        idInput: document.getElementById('bannerIdInput'),
        title: document.getElementById('bannerTitle'),
        description: document.getElementById('bannerDescription'),
        image: document.getElementById('bannerImage'),
        position: document.getElementById('bannerPosition'),
        link: document.getElementById('bannerLink'),
        order: document.getElementById('bannerDisplayOrder'),
        startDate: document.getElementById('bannerStartDate'),
        endDate: document.getElementById('bannerEndDate'),
        isActive: document.getElementById('bannerIsActive'),
        statusLabel: document.getElementById('bannerStatusLabel'),
        previewContainer: document.getElementById('imagePreviewContainer'),
        previewWrap: document.querySelector('.preview-wrap'),
    };

    let currentPage = 1, currentSearch = '', currentStatus = 'all', currentPosition = 'all';
    let selectedFiles = [];

    function getModalInstance() {
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    async function loadData(page = 1) {
        try {
            // Skeleton loading is now handled in HTML blade template
            // els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-secondary"></div></td></tr>`;
            const url = new URL(window.location.origin + '/api/v1/admin/banners');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);
            if (currentPosition !== 'all') url.searchParams.append('position', currentPosition);
            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'banners:list' });
            if (res && res.ok) {
                const json = await res.json();
                renderTable(json.data, json.pagination?.from || json.from);
                renderPagination(json.pagination || json);
            } else throw new Error();
        } catch (error) {
            if (error.name === 'AbortError') return;
            els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">Lỗi tải dữ liệu</td></tr>`;
        }
    }

    function renderTable(banners, startIndex) {
        if (!banners || banners.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">Không tìm thấy banner nào</td></tr>`;
            return;
        }

        const positionLabels = { home_slider: 'Slider trang chủ', sidebar: 'Sidebar', popup: 'Popup', top_bar: 'Thanh trên', footer: 'Footer' };
        const formatDate = (d) => { if (!d) return '-'; const date = new Date(d); return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}`; };
        const getDateRange = (start, end) => {
            const now = new Date();
            const s = start ? new Date(start) : null, e = end ? new Date(end) : null;
            if (!s && !e) return '<span class="date-range">Không giới hạn</span>';
            if (s && e) {
                const active = now >= s && now <= e;
                const expired = e < now;
                const cls = expired ? 'expired' : (active ? 'active' : '');
                return `<span class="date-range ${cls}">${formatDate(start)} - ${formatDate(end)}</span>`;
            }
            if (s) return `<span class="date-range">Từ ${formatDate(start)}</span>`;
            return `<span class="date-range">Đến ${formatDate(end)}</span>`;
        };

        els.tableBody.innerHTML = '';
        banners.forEach((banner, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            const safePosition = Object.hasOwn(positionLabels, banner.position) ? banner.position : 'sidebar';
            const positionBadge = `<span class="badge-position badge-position-${safePosition}">${escapeHtml(positionLabels[banner.position] || banner.position)}</span>`;
            const imgSrc = banner.image_path ? storageImageUrl(banner.image_path) : '/images/placeholder.png';
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td><img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(banner.title)}" class="banner-image-preview"></td>
                <td>
                    <div class="banner-title">${escapeHtml(banner.title)}</div>
                    ${banner.description ? `<small class="banner-description">${escapeHtml(banner.description.substring(0, 50))}${banner.description.length > 50 ? '...' : ''}</small>` : ''}
                </td>
                <td class="text-center">${positionBadge}</td>
                <td class="text-center"><span class="order-badge">${escapeHtml(banner.display_order || 0)}</span></td>
                <td class="text-center small">${getDateRange(banner.start_date, banner.end_date)}</td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0" type="checkbox" data-id="${escapeHtml(banner.id)}" ${banner.is_active ? 'checked' : ''} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-edit-banner" style="color: var(--text-secondary); background:rgba(255,255,255,0.05);" data-banner='${escapeHtml(JSON.stringify(banner))}' title="Sửa"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-banner" style="color:#ef4444; background:rgba(239,68,68,0.1);" data-id="${escapeHtml(banner.id)}" title="Xóa"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            `;
            els.tableBody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        if (!meta || meta.last_page <= 1) { els.pagination.innerHTML = ''; return; }
        let html = '<ul class="pagination pagination-sm m-0">';
        html += meta.current_page > 1 ? `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page - 1}">&laquo;</a></li>` : `<li class="page-item disabled"><span class="page-link">&laquo;</span></li>`;
        for (const i of window.AdminCore.paginationPages(meta)) {
            html += i === meta.current_page ? `<li class="page-item active"><span class="page-link">${i}</span></li>` : `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }
        html += meta.current_page < meta.last_page ? `<li class="page-item"><a class="page-link" href="#" data-page="${meta.current_page + 1}">&raquo;</a></li>` : `<li class="page-item disabled"><span class="page-link">&raquo;</span></li>`;
        html += '</ul>';
        els.pagination.innerHTML = html;
        els.pagination.querySelectorAll('a.page-link').forEach(a => a.addEventListener('click', (e) => { e.preventDefault(); currentPage = parseInt(a.getAttribute('data-page')); loadData(currentPage); }));
    }

    function resetForm() {
        els.form.reset();
        els.formMethod.value = 'POST';
        els.idInput.value = '';
        els.isActive.checked = true;
        els.statusLabel.textContent = 'Đang hoạt động';
        els.image.required = true;
    }

    function formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    if (els.btnCreate) els.btnCreate.addEventListener('click', () => {
        resetForm();
        selectedFiles = [];
        els.modalLabel.innerHTML = '<i class="bi bi-image me-2"></i>Tạo banner mới';
        getModalInstance()?.show();
    });

    if (els.isActive && els.statusLabel) els.isActive.addEventListener('change', () => { els.statusLabel.textContent = els.isActive.checked ? 'Đang hoạt động' : 'Tạm dừng'; });

    function renderPreviews() {
        els.previewContainer.style.display = 'none';
        els.previewWrap.innerHTML = '';

        if (selectedFiles.length > 0) {
            els.previewContainer.style.display = 'block';
            els.previewWrap.style.display = 'flex';
            els.previewWrap.style.flexWrap = 'wrap';
            els.previewWrap.style.gap = '10px';

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.addEventListener('load', function (e) {
                    const wrap = document.createElement('div');
                    wrap.style.position = 'relative';
                    wrap.style.display = 'inline-block';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.height = '80px';
                    img.style.width = '120px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '6px';
                    img.style.border = '1px solid rgba(255,255,255,0.1)';

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-x';
                    btn.appendChild(icon);
                    btn.style.position = 'absolute';
                    btn.style.top = '-6px';
                    btn.style.right = '-6px';
                    btn.style.background = '#ef4444';
                    btn.style.color = 'white';
                    btn.style.border = 'none';
                    btn.style.borderRadius = '50%';
                    btn.style.width = '20px';
                    btn.style.height = '20px';
                    btn.style.lineHeight = '1';
                    btn.style.padding = '0';
                    btn.style.fontSize = '14px';
                    btn.style.cursor = 'pointer';
                    btn.addEventListener('click', function() {
                        selectedFiles.splice(index, 1);
                        renderPreviews();
                        // Reset file input if empty
                        if(selectedFiles.length === 0) els.image.value = '';
                    });

                    wrap.appendChild(img);
                    wrap.appendChild(btn);
                    els.previewWrap.appendChild(wrap);
                }, { once: true });
                reader.readAsDataURL(file);
            });
        }
    }

    els.image.addEventListener('change', function () {
        if (this.files && this.files.length > 0) {
            // Append new files to existing selection
            Array.from(this.files).forEach(f => selectedFiles.push(f));
            renderPreviews();
        }
    });

    els.tableBody.addEventListener('click', async (e) => {
        const btnEdit = e.target.closest('.btn-edit-banner');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';
            const banner = JSON.parse(btnEdit.dataset.banner);
            els.idInput.value = banner.id;
            els.modalLabel.innerHTML = '<i class="bi bi-image me-2"></i>Cập nhật banner';
            els.title.value = banner.title || '';
            els.description.value = banner.description || '';

            // Show single image for edit
            els.previewWrap.replaceChildren();
            const preview = document.createElement('img');
            preview.src = storageImageUrl(banner.image_path);
            preview.alt = '';
            preview.style.cssText = 'height:80px;width:120px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.1);';
            els.previewWrap.appendChild(preview);
            els.previewContainer.style.display = 'block';
            selectedFiles = [];

            els.position.value = banner.position || '';
            els.link.value = banner.link_url || '';
            els.order.value = banner.display_order || 0;
            els.startDate.value = formatDateTimeLocal(banner.start_date);
            els.endDate.value = formatDateTimeLocal(banner.end_date);
            els.isActive.checked = banner.is_active === 1 || banner.is_active === true;
            els.statusLabel.textContent = els.isActive.checked ? 'Đang hoạt động' : 'Tạm dừng';
            els.image.required = false;
            getModalInstance()?.show();
            return;
        }

        const btnDel = e.target.closest('.btn-delete-banner');
        if (btnDel) {
            if (!confirm('Bạn có chắc muốn xóa banner này?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/banners/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) { window.showAdminToast?.('Xóa banner thành công', 'success'); loadData(currentPage); }
                else { const err = await res.json(); window.showAdminToast?.(err.message || 'Xóa thất bại', 'error'); }
            } catch { window.showAdminToast?.('Xóa thất bại', 'error'); }
        }
    });

    els.tableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-active-btn');
        if (!toggle) return;
        const id = toggle.getAttribute('data-id'), isActive = toggle.checked;
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/banners/${id}/toggle-active`, { method: 'POST' });
            if (!res || !res.ok) throw new Error();
            window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
            loadData(currentPage);
        } catch { window.showAdminToast?.('Cập nhật thất bại', 'error'); toggle.checked = !isActive; }
    });

    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = els.formMethod.value === 'PUT', id = els.idInput.value;
            const url = isEdit ? `/api/v1/admin/banners/${id}` : '/api/v1/admin/banners';
            const formData = new FormData();
            formData.append('title', els.title.value.trim());
            if (els.description.value.trim()) formData.append('description', els.description.value.trim());

            if (isEdit) {
                if (els.image.files[0]) formData.append('image_path', els.image.files[0]);
                else if (selectedFiles.length > 0) formData.append('image_path', selectedFiles[selectedFiles.length - 1]);
            } else {
                selectedFiles.forEach(file => {
                    formData.append('image_paths[]', file);
                });
            }

            formData.append('position', els.position.value);
            if (els.link.value.trim()) formData.append('link_url', els.link.value.trim());
            formData.append('display_order', els.order.value || 0);
            if (els.startDate.value) formData.append('start_date', els.startDate.value);
            if (els.endDate.value) formData.append('end_date', els.endDate.value);
            formData.append('is_active', els.isActive.checked ? '1' : '0');
            if (isEdit) formData.append('_method', 'PUT');

            try {
                const res = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: formData, credentials: 'same-origin' });
                if (res && res.ok) { getModalInstance()?.hide(); window.showAdminToast?.(isEdit ? 'Cập nhật banner thành công' : 'Tạo banner thành công', 'success'); loadData(currentPage); }
                else { const errData = await res.json(); alert('Dữ liệu không hợp lệ: ' + JSON.stringify(errData.errors || errData.message)); }
            } catch (error) { console.error('Submit error', error); }
        });
    }

    if (els.searchForm) {
        els.searchForm.addEventListener('submit', (e) => { e.preventDefault(); currentSearch = els.searchInput.value.trim(); currentStatus = els.statusFilter.value; currentPosition = els.positionFilter.value; currentPage = 1; loadData(currentPage); });
        if (els.statusFilter) els.statusFilter.addEventListener('change', () => els.searchForm.dispatchEvent(new Event('submit')));
        if (els.positionFilter) els.positionFilter.addEventListener('change', () => els.searchForm.dispatchEvent(new Event('submit')));
    }

    async function loadPositions() {
        try {
            const res = await window.AdminCore.apiFetch('/api/v1/admin/banners/positions');
            if (res && res.ok) {
                const json = await res.json(), positions = json.data || [];
                const positionLabels = { home_slider: 'Slider trang chủ', sidebar: 'Sidebar', popup: 'Popup', top_bar: 'Thanh trên', footer: 'Footer' };
                if (els.positionFilter) {
                    els.positionFilter.innerHTML = '<option value="all">Tất cả vị trí</option>';
                    positions.forEach(pos => { const opt = document.createElement('option'); opt.value = pos; opt.textContent = positionLabels[pos] || pos; els.positionFilter.appendChild(opt); });
                }
                if (els.position) {
                    els.position.innerHTML = '';
                    positions.forEach(pos => { const opt = document.createElement('option'); opt.value = pos; opt.textContent = positionLabels[pos] || pos; els.position.appendChild(opt); });
                }
            }
        } catch (error) { console.error('Error loading positions:', error); }
    }

    window.onAdminPageLoad(() => { loadPositions(); loadData(1); });
})();
