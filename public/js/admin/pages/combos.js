/**
 * Combos Management - combos.js
 * SPA Architecture
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeImageUrl(value) {
        const candidate = String(value || '').trim();
        if (/^\/(?!\/)[A-Za-z0-9_./?=&%-]+$/.test(candidate) && !candidate.includes('..')) return candidate;
        if (/^https?:\/\/[^\s"'<>]+$/i.test(candidate)) return candidate;
        return '';
    }

    const els = {
        tableBody: document.getElementById('combosTableBody'),
        pagination: document.getElementById('paginationContainer'),

        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        statusFilter: document.getElementById('statusFilter'),

        btnCreate: document.getElementById('btnOpenCreateCombo'),
        modalEl: document.getElementById('comboModal'),
        form: document.getElementById('comboForm'),
        modalLabel: document.getElementById('comboModalLabel'),

        formMethod: document.getElementById('comboFormMethod'),
        idInput: document.getElementById('comboIdInput'),

        // Form fields
        name: document.getElementById('comboName'),
        price: document.getElementById('comboPrice'),
        description: document.getElementById('comboDescription'),
        status: document.getElementById('comboStatus'),

        imageFile: document.getElementById('comboImageFile'),
        imagePreview: document.getElementById('imagePreview'),
        imagePlaceholder: document.getElementById('imagePlaceholder'),
        clearImageBtn: document.getElementById('clearImageBtn'),
        imageUploadBox: document.getElementById('imageUploadBox'),

        statusLabel: document.getElementById('comboStatusLabel'),

        // Combo items
        availableProducts: document.getElementById('availableProducts'),
        btnAddItem: document.getElementById('btnAddComboItem'),
        comboItemsList: document.getElementById('comboItemsList'),
        emptyComboItems: document.getElementById('emptyComboItems'),
    };

    const pageConfig = window.ADMIN_COMBO_PAGE || {};

    let currentPage = 1;
    let currentSearch = '';
    let currentStatus = 'all';
    let availableProductsData = [];
    let availableProductsLoaded = false;
    let availableProductsPromise = null;
    let comboItems = [];

    function getModalInstance() {
        if (!els.modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1) {
        try {
            if (window.renderAdminTableSkeleton && els.tableBody) {
                window.renderAdminTableSkeleton(els.tableBody, 8, 5, true);
            }

            const url = new URL(window.location.origin + '/api/v1/admin/combos');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);

            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'combos:list' });
            if (res && res.ok) {
                const json = await res.json();
                renderTable(json.data, json.pagination?.from);
                renderPagination(json.pagination);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(combos, startIndex) {
        if (!combos || combos.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy combo nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        combos.forEach((combo, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            const statusHtml = combo.status
                ? '<span class="badge bg-success">Đang bán</span>'
                : '<span class="badge bg-secondary">Ngừng bán</span>';

            const priceFmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(combo.price);

            // Use original_price from database, or calculate as fallback
            const items = combo.items || combo.combo_items || [];
            let originalPrice = combo.original_price || 0;
            if (!originalPrice && items.length > 0) {
                originalPrice = items.reduce((sum, item) => {
                    return sum + ((item.product?.price || 0) * (item.quantity || 0));
                }, 0);
            }
            const originalPriceFmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(originalPrice);

            const stockDisplay = combo.available_stock !== undefined
                ? `<span class="${combo.available_stock > 0 ? 'text-success' : 'text-danger'}">${combo.available_stock}</span>`
                : '<span class="text-muted">N/A</span>';

            const imageUrl = safeImageUrl(combo.image_url);
            const imageHtml = imageUrl
                ? `<img src="${escapeHtml(imageUrl)}" alt="Image" loading="lazy" onerror="this.outerHTML='<i class=\'bi bi-box-seam text-white-50 fs-3\'></i>'">`
                : `<i class="bi bi-box-seam text-white-50 fs-3"></i>`;

            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td class="text-center">
                    <div class="movie-poster-container admin-media-thumb">
                        ${imageHtml}
                    </div>
                </td>
                <td>
                    <div class="fw-medium text-white fs-6">${combo.name}</div>
                    ${combo.description ? `<div class="small text-white-50 text-truncate mt-1 admin-text-truncate-300">${combo.description}</div>` : ''}
                </td>
                <td>
                    <div class="fw-medium">${stockDisplay}</div>
                    ${items.length > 0 ? `<div class="small text-white-50 mt-1">${items.length} sản phẩm</div>` : ''}
                </td>
                <td>
                    <div class="fw-medium text-white-50">${originalPriceFmt}</div>
                </td>
                <td>
                    <div class="fw-medium text-white">${priceFmt}</div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0 admin-toggle-pointer" type="checkbox" role="switch"
                            data-id="${combo.id}" ${combo.status ? 'checked' : ''} title="Bật/Tắt trạng thái">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-combo admin-table-action-edit"

                            data-combo='${JSON.stringify(combo).replace(/'/g, "&#39;")}'
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-combo admin-table-action-delete"
                            data-id="${combo.id}" title="Xóa">
                            <i class="bi bi-trash"></i>
                        </button>
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

    /* ── Image Functions ───────────────────────────────────────────── */
    function showImagePreview(src) {
        if (!src) return;
        if (els.imagePreview) { els.imagePreview.src = src; els.imagePreview.style.display = 'block'; }
        if (els.imagePlaceholder) els.imagePlaceholder.style.display = 'none';
        if (els.clearImageBtn) els.clearImageBtn.classList.remove('d-none');
    }

    function clearImagePreview() {
        if (els.imagePreview) { els.imagePreview.src = ''; els.imagePreview.style.display = 'none'; }
        if (els.imagePlaceholder) els.imagePlaceholder.style.display = 'block';
        if (els.imageFile) els.imageFile.value = '';
        if (els.clearImageBtn) els.clearImageBtn.classList.add('d-none');
    }

    /* ── Combo Items Management ────────────────────────────────────── */
    async function loadAvailableProducts(force = false) {
        if (availableProductsLoaded && !force) {
            renderAvailableProducts();
            return availableProductsData;
        }

        if (availableProductsPromise && !force) {
            return availableProductsPromise;
        }

        availableProductsPromise = (async () => {
            try {
                const res = await window.AdminCore.apiFetch('/api/v1/admin/combos/available-products', {
                    requestKey: 'combos:available-products',
                    cacheTtl: 300000
                });
                if (res && res.ok) {
                    const json = await res.json();
                    availableProductsData = json.data || [];
                    availableProductsLoaded = true;
                    renderAvailableProducts();
                }
            } catch (error) {
                console.error('Error loading products:', error);
            } finally {
                availableProductsPromise = null;
            }

            return availableProductsData;
        })();

        return availableProductsPromise;
    }

    function renderAvailableProducts() {
        if (!els.availableProducts) return;
        els.availableProducts.innerHTML = '<option value="">-- Chọn sản phẩm --</option>';
        availableProductsData.forEach(prod => {
            const option = document.createElement('option');
            option.value = prod.id;
            const priceFmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(prod.price);
            option.textContent = `${prod.name} (${priceFmt}) - ${prod.type === 'food' ? 'Đồ ăn' : 'Đồ uống'} - ${prod.stock} còn`;
            option.dataset.product = JSON.stringify(prod);
            els.availableProducts.appendChild(option);
        });
    }

    function addComboItem(productId) {
        const prod = availableProductsData.find(p => p.id == productId);
        if (!prod) return;

        const existing = comboItems.find(item => item.product_id == productId);
        if (existing) {
            window.showAdminToast?.('Sản phẩm đã có trong combo', 'warning');
            return;
        }

        comboItems.push({
            product_id: prod.id,
            quantity: 1,
            product: prod
        });

        renderComboItems();
    }

    function removeComboItem(productId) {
        comboItems = comboItems.filter(item => item.product_id != productId);
        renderComboItems();
    }

    function updateComboItemQuantity(productId, quantity) {
        const item = comboItems.find(item => item.product_id == productId);
        if (item) {
            item.quantity = Math.max(1, parseInt(quantity) || 1);
            renderComboItems();
        }
    }

    function renderComboItems() {
        if (!els.comboItemsList) return;

        if (comboItems.length === 0) {
            els.comboItemsList.innerHTML = '';
            if (els.emptyComboItems) els.emptyComboItems.style.display = 'block';
            calculateAndUpdatePrice();
            return;
        }

        if (els.emptyComboItems) els.emptyComboItems.style.display = 'none';

        els.comboItemsList.innerHTML = comboItems.map(item => {
            const priceFmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.product.price);
            const subtotal = item.product.price * item.quantity;
            const subtotalFmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(subtotal);

            return `
                <div class="combo-item-card">
                    <div class="combo-item-info">
                        <div class="fw-medium text-white">${item.product.name}</div>
                        <div class="small text-white-50">${item.product.type === 'food' ? 'Đồ ăn' : 'Đồ uống'} • Kho: ${item.product.stock}</div>
                        <div class="small mt-1">
                            <span class="text-info">${priceFmt}</span>
                            ${item.quantity > 1 ? ` × ${item.quantity} = <span class="text-warning fw-medium">${subtotalFmt}</span>` : ''}
                        </div>
                    </div>
                    <input type="number"
                        class="form-control form-control-sm bg-dark text-white border-secondary combo-item-quantity"
                        value="${item.quantity}"
                        min="1"
                        data-product-id="${item.product_id}">
                    <button type="button"
                        class="btn btn-sm btn-danger combo-item-remove"
                        data-product-id="${item.product_id}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `;
        }).join('');

        calculateAndUpdatePrice();
    }

    function calculateAndUpdatePrice() {
        if (!els.price) return;

        if (comboItems.length === 0) {
            els.price.value = '';
            return;
        }

        const total = comboItems.reduce((sum, item) => {
            return sum + (item.product.price * item.quantity);
        }, 0);

        els.price.value = total;
    }

    function resetForm() {
        if (!els.form) return;
        els.form.reset();
        els.formMethod.value = 'POST';
        els.idInput.value = '';
        if (els.status) els.status.checked = true;
        if (els.statusLabel) els.statusLabel.textContent = 'Đang bán';
        clearImagePreview();
        comboItems = [];
        renderComboItems();
    }

    function fillForm(combo) {
        resetForm();
        els.formMethod.value = 'PUT';
        els.idInput.value = combo.id;
        els.modalLabel.innerHTML = `<i class="bi bi-box-seam me-2 admin-accent-icon"></i>${pageConfig.editTitle || 'Cập nhật combo'}`;
        els.name.value = combo.name || '';
        els.price.value = combo.price || '';
        els.description.value = combo.description || '';
        els.status.checked = combo.status === 1 || combo.status === true;
        if (els.statusLabel) els.statusLabel.textContent = els.status.checked ? 'Đang bán' : 'Ngừng bán';

        if (combo.image_url) showImagePreview(combo.image_url);

        const items = combo.items || combo.combo_items || [];
        comboItems = items.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            product: item.product || availableProductsData.find(p => p.id == item.product_id)
        })).filter(item => item.product);

        renderComboItems();
    }

    /* ── Events ────────────────────────────────────────────────────── */
    if (els.btnCreate) {
        els.btnCreate.addEventListener('click', async () => {
            resetForm();
            await loadAvailableProducts();
            els.modalLabel.innerHTML = `<i class="bi bi-box-seam me-2 admin-accent-icon"></i>${pageConfig.createTitle || 'Tạo combo mới'}`;
            getModalInstance()?.show();
        });
    }

    if (els.imageFile) {
        els.imageFile.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) showImagePreview(URL.createObjectURL(file));
        });
    }

    if (els.clearImageBtn) {
        els.clearImageBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            clearImagePreview();
        });
    }

    if (els.imageUploadBox) {
        els.imageUploadBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            els.imageUploadBox.style.borderColor = 'rgba(255,255,255,0.4)';
        });
        els.imageUploadBox.addEventListener('dragleave', () => {
            els.imageUploadBox.style.borderColor = 'rgba(255,255,255,0.15)';
        });
        els.imageUploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            els.imageUploadBox.style.borderColor = 'rgba(255,255,255,0.15)';
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                const dt = new DataTransfer();
                dt.items.add(file);
                els.imageFile.files = dt.files;
                showImagePreview(URL.createObjectURL(file));
            }
        });
    }

    if (els.status && els.statusLabel) {
        els.status.addEventListener('change', () => {
            els.statusLabel.textContent = els.status.checked ? 'Đang bán' : 'Ngừng bán';
        });
    }

    if (els.btnAddItem) {
        els.btnAddItem.addEventListener('click', () => {
            const productId = els.availableProducts.value;
            if (!productId) {
                window.showAdminToast?.('Vui lòng chọn sản phẩm', 'warning');
                return;
            }
            addComboItem(productId);
            els.availableProducts.value = '';
        });
    }

    if (els.comboItemsList) {
        els.comboItemsList.addEventListener('click', (e) => {
            const btnRemove = e.target.closest('.combo-item-remove');
            if (btnRemove) removeComboItem(btnRemove.dataset.productId);
        });

        els.comboItemsList.addEventListener('change', (e) => {
            const input = e.target.closest('.combo-item-quantity');
            if (input) updateComboItemQuantity(input.dataset.productId, input.value);
        });
    }

    els.tableBody.addEventListener('click', async (e) => {
        const btnEdit = e.target.closest('.btn-edit-combo');
        if (btnEdit) {
            await loadAvailableProducts();
            fillForm(JSON.parse(btnEdit.dataset.combo));
            getModalInstance()?.show();
            return;
        }

        const btnDel = e.target.closest('.btn-delete-combo');
        if (btnDel) {
            if (!confirm('Bạn có chắc muốn xóa combo này? Thao tác này không thể hoàn tác!')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/combos/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa combo thành công', 'success');
                    loadData(currentPage);
                    loadAvailableProducts(true);
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Xóa thất bại', 'error');
                }
            } catch (err) {
                window.showAdminToast?.('Xóa thất bại', 'error');
            }
        }
    });

    els.tableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-active-btn');
        if (!toggle) return;

        const id = toggle.getAttribute('data-id');
        const isActive = toggle.checked;
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/combos/${id}/toggle-active`, { method: 'POST' });
            if (!res || !res.ok) throw new Error();
            window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
            loadData(currentPage);
        } catch (error) {
            window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
            toggle.checked = !isActive;
        }
    });

    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (comboItems.length === 0) {
                window.showAdminToast?.('Vui lòng thêm ít nhất 1 sản phẩm vào combo', 'warning');
                return;
            }

            const isEdit = els.formMethod.value === 'PUT';
            const id = els.idInput.value;
            const url = isEdit ? `/api/v1/admin/combos/${id}/update` : '/api/v1/admin/combos';

            const formData = new FormData();
            formData.append('name', els.name.value);
            formData.append('price', els.price.value);
            if (els.description.value) formData.append('description', els.description.value);
            formData.append('status', els.status.checked ? '1' : '0');

            comboItems.forEach((item, index) => {
                formData.append(`items[${index}][product_id]`, item.product_id);
                formData.append(`items[${index}][quantity]`, item.quantity);
            });

            if (els.imageFile?.files[0]) {
                formData.append('image_file', els.imageFile.files[0]);
            }

            try {
                const res = await window.AdminCore.apiFetch(url, { method: 'POST', body: formData });
                if (res && res.ok) {
                    getModalInstance()?.hide();
                    window.showAdminToast?.(isEdit ? (pageConfig.updateSuccess || 'Cập nhật combo thành công!') : (pageConfig.createSuccess || 'Thêm combo thành công!'), 'success');
                    loadData(currentPage);
                    loadAvailableProducts(true);
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
            currentStatus = els.statusFilter.value;
            currentPage = 1;
            loadData(currentPage);
        });

        if (els.statusFilter) {
            els.statusFilter.addEventListener('change', () => {
                els.searchForm.dispatchEvent(new Event('submit'));
            });
        }
    }

    window.onAdminPageLoad(() => {
        loadData(1);
    });
})();
