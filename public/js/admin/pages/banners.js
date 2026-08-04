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
        link: document.getElementById('bannerLink'),
        startDate: document.getElementById('bannerStartDate'),
        endDate: document.getElementById('bannerEndDate'),
        isActive: document.getElementById('bannerIsActive'),
        statusLabel: document.getElementById('bannerStatusLabel'),
        previewContainer: document.getElementById('imagePreviewContainer'),
        previewWrap: document.querySelector('.preview-wrap'),
    };

    let currentPage = 1, currentSearch = '', currentStatus = 'all';
    let selectedFiles = [];
    const MAX_BANNER_IMAGES = 5;
    const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024;

    function getModalInstance() {
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    async function loadData(page = 1) {
        try {
            if (window.renderAdminTableSkeleton && els.tableBody) {
                window.renderAdminTableSkeleton(els.tableBody, 9, 5, true);
            }
            const url = new URL(window.location.origin + '/api/v1/admin/banners');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);
            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'banners:list' });
            if (res && res.ok) {
                const json = await res.json();
                renderTable(json.data, json.pagination?.from || json.from);
                renderPagination(json.pagination || json);
            } else throw new Error();
        } catch (error) {
            if (error.name === 'AbortError') return;
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-danger">Lỗi tải dữ liệu</td></tr>`;
        }
    }

    function renderTable(banners, startIndex) {
        if (!banners || banners.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">Không tìm thấy banner nào</td></tr>`;
            return;
        }

        const formatDate = (d) => { if (!d) return '-'; const date = new Date(d); return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}`; };
        els.tableBody.innerHTML = '';
        banners.forEach((banner, index) => {
            const tr = document.createElement('tr');
            tr.classList.add('admin-table-row');
            const safeTitle = escapeHtml(String(banner.title || ''));
            const description = String(banner.description || '');
            const safeDescription = escapeHtml(description.substring(0, 50));
            const images = Array.isArray(banner.images) ? banner.images : [];
            const imagesHtml = images.length
                ? images.map((image, imageIndex) => `
                    <img src="${escapeHtml(image.image_url || storageImageUrl(image.image_path))}"
                         alt="${safeTitle} - ${imageIndex + 1}"
                         class="banner-image-preview admin-banner-thumb">
                `).join('')
                : '<span class="text-white-50">Chưa có ảnh</span>';
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td><div class="banner-images-row">${imagesHtml}</div></td>
                <td>
                    <div class="banner-title">${safeTitle}</div>
                    ${description ? `<small class="banner-description">${safeDescription}${description.length > 50 ? '...' : ''}</small>` : ''}
                </td>
                <td class="text-center small">${formatDate(banner.start_date)}</td>
                <td class="text-center small">${formatDate(banner.end_date)}</td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0 admin-toggle-pointer" type="checkbox" data-id="${escapeHtml(banner.id)}" ${banner.is_active ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-edit-banner admin-table-action-edit" data-banner='${escapeHtml(JSON.stringify(banner))}' title="Sửa"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-banner admin-table-action-delete" data-id="${escapeHtml(banner.id)}" title="Xóa"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            `;
            els.tableBody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        window.AdminCore.renderAdminPagination(els.pagination, meta, (page) => {
            currentPage = page; loadData(page);
        });
    }

    function resetForm() {
        els.form.reset();
        els.formMethod.value = 'POST';
        els.idInput.value = '';
        els.isActive.checked = true;
        els.statusLabel.textContent = 'Đang hoạt động';
        els.image.required = true;
        selectedFiles = [];
        els.previewWrap?.replaceChildren();
        els.previewContainer?.classList.add('d-none');
    }

    function formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    if (els.btnCreate) els.btnCreate.addEventListener('click', () => {
        resetForm();
        selectedFiles = [];
        els.modalLabel.innerHTML = '<i class="bi bi-image me-2 admin-accent-icon"></i>Tạo banner mới';
        getModalInstance()?.show();
    });

    if (els.isActive && els.statusLabel) els.isActive.addEventListener('change', () => { els.statusLabel.textContent = els.isActive.checked ? 'Đang hoạt động' : 'Tạm dừng'; });

    function renderPreviews() {
        els.previewContainer.classList.add('d-none');
        els.previewWrap.innerHTML = '';

        if (selectedFiles.length > 0) {
            els.previewContainer.classList.remove('d-none');
            els.previewWrap.classList.add('banner-preview-list');

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.addEventListener('load', function (e) {
                    const wrap = document.createElement('div');
                    wrap.className = 'banner-preview-item';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'banner-preview-image';

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-x';
                    btn.appendChild(icon);
                    btn.className = 'banner-preview-remove';
                    btn.setAttribute('aria-label', `Xóa ảnh ${index + 1}`);
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
            const incomingCount = this.files.length;
            const previousCount = selectedFiles.length;
            const validFiles = Array.from(this.files).filter(file => file.size <= MAX_FILE_SIZE_BYTES);
            if (validFiles.length !== incomingCount) {
                window.showAdminToast?.('Ảnh banner không được vượt quá 5MB', 'warning');
            }
            // Append new files to existing selection
            validFiles.forEach(f => {
                if (selectedFiles.length < MAX_BANNER_IMAGES) selectedFiles.push(f);
            });
            if (previousCount + validFiles.length > MAX_BANNER_IMAGES) {
                window.showAdminToast?.('Mỗi lần tạo tối đa 5 ảnh banner', 'warning');
            }
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
            els.modalLabel.innerHTML = '<i class="bi bi-image me-2 admin-accent-icon"></i>Cập nhật banner';
            els.title.value = banner.title || '';
            els.description.value = banner.description || '';

            // Show all current images on one row.
            els.previewWrap.replaceChildren();
            (banner.images || []).forEach((image) => {
                const preview = document.createElement('img');
                preview.src = image.image_url || storageImageUrl(image.image_path);
                preview.alt = '';
                preview.className = 'banner-preview-image';
                els.previewWrap.appendChild(preview);
            });
            els.previewContainer.classList.toggle('d-none', !banner.images?.length);
            selectedFiles = [];

            els.link.value = banner.link_url || '';
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
            if (!await window.AdminDialog.confirm({ message: 'Bạn có chắc muốn xóa banner này?', confirmLabel: 'Xóa banner', variant: 'danger' })) return;
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

            selectedFiles.forEach(file => {
                formData.append('image_paths[]', file);
            });

            if (els.link.value.trim()) formData.append('link_url', els.link.value.trim());
            if (els.startDate.value) formData.append('start_date', els.startDate.value);
            if (els.endDate.value) formData.append('end_date', els.endDate.value);
            formData.append('is_active', els.isActive.checked ? '1' : '0');
            if (isEdit) formData.append('_method', 'PUT');

            try {
                const res = await window.AdminCore.apiFetch(url, { method: 'POST', body: formData, skipCache: true });
                if (res && res.ok) { getModalInstance()?.hide(); window.showAdminToast?.(isEdit ? 'Cập nhật banner thành công' : 'Tạo banner thành công', 'success'); loadData(currentPage); }
                else { const errData = await res.json(); window.showAdminToast?.(window.formatAdminErrors?.(errData.errors || errData.message) || 'Dữ liệu không hợp lệ', 'error'); }
            } catch (error) { console.error('Submit error', error); }
        });
    }

    if (els.searchForm) {
        els.searchForm.addEventListener('submit', (e) => { e.preventDefault(); currentSearch = els.searchInput.value.trim(); currentStatus = els.statusFilter.value; currentPage = 1; loadData(currentPage); });
        if (els.statusFilter) els.statusFilter.addEventListener('change', () => els.searchForm.dispatchEvent(new Event('submit')));
    }

    const registerPageLoad = (callback) => {
        if (typeof window.onAdminPageLoad === 'function') {
            window.onAdminPageLoad(callback);
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            queueMicrotask(callback);
        }
    };

    registerPageLoad(() => loadData(1));
})();
