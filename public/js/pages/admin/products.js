/**
 * Products / Combos Management - products.js
 * SPA Architecture
 */
(function () {
    'use strict';

    const els = {
        tableBody: document.getElementById('productsTableBody'),
        pagination: document.getElementById('paginationContainer'),
        
        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        typeFilter: document.getElementById('typeFilter'),
        statusFilter: document.getElementById('statusFilter'),

        btnCreate: document.getElementById('btnOpenCreateProduct'),
        modalEl: document.getElementById('productModal'),
        form: document.getElementById('productForm'),
        modalLabel: document.getElementById('productModalLabel'),
        
        formMethod: document.getElementById('productFormMethod'),
        idInput: document.getElementById('productIdInput'),
        
        // Form fields
        name: document.getElementById('productName'),
        type: document.getElementById('productType'),
        price: document.getElementById('productPrice'),
        stock: document.getElementById('productStock'),
        description: document.getElementById('productDescription'),
        status: document.getElementById('productStatus'),
        imageUrl: document.getElementById('productImageUrl'),
        
        imagePreview: document.getElementById('imagePreview'),
        imagePlaceholder: document.getElementById('imagePlaceholder'),
    };

    let currentPage = 1;
    let currentSearch = '';
    let currentType = 'all';
    let currentStatus = 'all';

    function getModalInstance() {
        if (!els.modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1) {
        try {
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;
            
            const url = new URL(window.location.origin + '/api/v1/admin/products');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentType !== 'all') url.searchParams.append('type', currentType);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);

            const res = await window.AdminCore.apiFetch(url.toString());
            if (res && res.ok) {
                const data = await res.json();
                renderTable(data.data.data, data.data.from);
                renderPagination(data.data);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(products, startIndex) {
        if (!products || products.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy sản phẩm nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        products.forEach((prod, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            
            const statusHtml = prod.status 
                ? '<span class="badge bg-success">Đang bán</span>' 
                : '<span class="badge bg-secondary">Ngừng bán</span>';
            
            const priceFmt = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(prod.price);
            
            let typeHtml = '';
            if (prod.type === 'combo') typeHtml = '<span class="badge" style="background:rgba(236,72,153,0.12);color:#ec4899;">Combo</span>';
            else if (prod.type === 'food') typeHtml = '<span class="badge" style="background:rgba(245,158,11,0.12);color:#f59e0b;">Đồ ăn</span>';
            else if (prod.type === 'drink') typeHtml = '<span class="badge" style="background:rgba(96,165,250,0.12);color:#60a5fa;">Đồ uống</span>';
            
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td class="text-center">
                    ${prod.image_url 
                        ? `<img src="${prod.image_url}" alt="Image" style="width: 50px; height: 50px; object-fit: contain; border-radius: 4px; background: rgba(255,255,255,0.1);">` 
                        : `<div style="width: 50px; height: 50px; background: rgba(255,255,255,0.1); border-radius: 4px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-image text-white-50"></i></div>`}
                </td>
                <td>
                    <div class="fw-medium text-white fs-6">${prod.name}</div>
                    ${prod.description ? `<div class="small text-white-50 text-truncate mt-1" style="max-width: 200px;">${prod.description}</div>` : ''}
                </td>
                <td>
                    <div>${typeHtml}</div>
                    <div class="small text-white-50 mt-1">Kho: <strong>${prod.stock}</strong></div>
                </td>
                <td>
                    <div class="fw-medium text-white">${priceFmt}</div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-active-btn m-0" type="checkbox" role="switch"
                            data-id="${prod.id}" ${prod.status ? 'checked' : ''} style="cursor:pointer;" title="Bật/Tắt trạng thái">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-edit-product"
                            style="color: var(--text-secondary); background:rgba(255,255,255,0.05);"
                            data-product='${JSON.stringify(prod).replace(/'/g, "&#39;")}'
                            title="Sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-product"
                            style="color:#ef4444; background:rgba(239,68,68,0.1);" data-id="${prod.id}" title="Xóa">
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
                loadData(currentPage);
            });
        });
    }

    /* ── Forms & Interactions ──────────────────────────────────────── */
    function resetForm() {
        if (!els.form) return;
        els.form.reset();
        els.formMethod.value = 'POST';
        els.idInput.value = '';
        els.imagePreview.src = '';
        els.imagePreview.classList.add('d-none');
        els.imagePlaceholder.classList.remove('d-none');
    }

    if (els.btnCreate) {
        els.btnCreate.addEventListener('click', () => {
            resetForm();
            els.modalLabel.innerHTML = '<i class="bi bi-box-seam me-2"></i>Thêm sản phẩm mới';
            getModalInstance()?.show();
        });
    }

    els.imageUrl.addEventListener('input', (e) => {
        const url = e.target.value;
        if (url) {
            els.imagePreview.src = url;
            els.imagePreview.classList.remove('d-none');
            els.imagePlaceholder.classList.add('d-none');
        } else {
            els.imagePreview.src = '';
            els.imagePreview.classList.add('d-none');
            els.imagePlaceholder.classList.remove('d-none');
        }
    });

    els.tableBody.addEventListener('click', async (e) => {
        // Edit
        const btnEdit = e.target.closest('.btn-edit-product');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';
            
            const prod = JSON.parse(btnEdit.dataset.product);
            els.idInput.value = prod.id;
            els.modalLabel.innerHTML = '<i class="bi bi-box-seam me-2"></i>Cập nhật sản phẩm';
            
            els.name.value = prod.name || '';
            els.type.value = prod.type || 'combo';
            els.price.value = prod.price || '';
            els.stock.value = prod.stock || 0;
            els.description.value = prod.description || '';
            els.status.checked = prod.status === 1 || prod.status === true;
            els.imageUrl.value = prod.image_url || '';

            if (prod.image_url) {
                els.imagePreview.src = prod.image_url;
                els.imagePreview.classList.remove('d-none');
                els.imagePlaceholder.classList.add('d-none');
            }

            getModalInstance()?.show();
            return;
        }

        // Delete
        const btnDel = e.target.closest('.btn-delete-product');
        if (btnDel) {
            if(!confirm('Bạn có chắc muốn xóa sản phẩm này? Thao tác này không thể hoàn tác!')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/products/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) {
                    window.showAdminToast?.('Xóa thành công', 'success');
                    loadData(currentPage);
                } else {
                    const err = await res.json();
                    window.showAdminToast?.(err.message || 'Xóa thất bại', 'error');
                }
            } catch (err) {}
            return;
        }
    });

    els.tableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-active-btn');
        if (toggle) {
            const id = toggle.getAttribute('data-id');
            const isActive = toggle.checked;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/products/${id}/toggle-active`, { method: 'POST' });
                if (!res || !res.ok) throw new Error();
                window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
                loadData(currentPage);
            } catch (error) {
                window.showAdminToast?.('Cập nhật trạng thái thất bại', 'error');
                toggle.checked = !isActive;
            }
        }
    });

    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = els.formMethod.value === 'PUT';
            const id = els.idInput.value;
            const url = isEdit ? `/api/v1/admin/products/${id}` : `/api/v1/admin/products`;
            
            const formData = new FormData(els.form);
            const data = Object.fromEntries(formData.entries());
            data.status = els.status.checked ? 1 : 0;

            try {
                const res = await window.AdminCore.apiFetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(data)
                });
                
                if (res && res.ok) {
                    getModalInstance()?.hide();
                    window.showAdminToast?.(isEdit ? 'Cập nhật thành công!' : 'Thêm sản phẩm thành công!', 'success');
                    loadData(currentPage);
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
            currentType = els.typeFilter.value;
            currentStatus = els.statusFilter.value;
            currentPage = 1;
            loadData(currentPage);
        });

        // Trigger search on select change
        [els.typeFilter, els.statusFilter].forEach(el => {
            if(el) {
                el.addEventListener('change', () => {
                    els.searchForm.dispatchEvent(new Event('submit'));
                });
            }
        });
    }

    /* ── Init ──────────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        loadData(1);
    });

})();
