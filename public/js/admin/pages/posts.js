/**
 * Posts Management - posts.js
 * Simple SPA Architecture (following pattern)
 */
(function () {
    'use strict';

    const els = {
        tableBody: document.getElementById('postsTableBody'),
        pagination: document.getElementById('paginationContainer'),
        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        categoryFilter: document.getElementById('categoryFilter'),
        statusFilter: document.getElementById('statusFilter'),
        btnCreate: document.getElementById('btnCreatePost'),
        modalEl: document.getElementById('postModal'),
        form: document.getElementById('postForm'),
        modalLabel: document.getElementById('postModalLabel'),
        formMethod: document.getElementById('formMethod'),
        idInput: document.getElementById('postIdInput'),
        title: document.getElementById('postTitle'),
        slug: document.getElementById('postSlug'),
        category: document.getElementById('postCategory'),
        excerpt: document.getElementById('postExcerpt'),
        content: document.getElementById('postContent'),
        image: document.getElementById('postImage'),
        publishedAt: document.getElementById('postPublishedAt'),
        isPublished: document.getElementById('postIsPublished'),
        statusLabel: document.getElementById('postStatusLabel'),
    };

    let currentPage = 1, currentSearch = '', currentStatus = 'all', currentCategory = 'all';

    function getModalInstance() {
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    async function loadData(page = 1) {
        try {
            // Skeleton loading is now handled in HTML blade template
            // els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-secondary"></div></td></tr>`;

            const url = new URL(window.location.origin + '/api/v1/admin/posts');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);
            if (currentCategory !== 'all') url.searchParams.append('category', currentCategory);
            const res = await window.AdminCore.apiFetch(url.toString());
            if (res && res.ok) {
                const json = await res.json();
                renderTable(json.data, json.pagination?.from || json.from);
                renderPagination(json.pagination || json);
            } else throw new Error();
        } catch (error) {
            els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-danger">Lỗi tải dữ liệu</td></tr>`;
        }
    }

    function renderTable(posts, startIndex) {
        if (!posts || posts.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">Không tìm thấy bài viết nào</td></tr>`;
            return;
        }

        const categoryLabels = { news: 'Tin tức', blog: 'Blog', announcement: 'Thông báo', event: 'Sự kiện', promotion: 'Khuyến mãi' };
        const formatDate = (d) => { if (!d) return '-'; const date = new Date(d); return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`; };

        els.tableBody.innerHTML = '';
        posts.forEach((post, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            const categoryBadge = `<span class="badge-category badge-category-${post.category}">${categoryLabels[post.category] || post.category}</span>`;
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td>
                    <div class="post-title">${post.title}</div>
                    ${post.excerpt ? `<small class="post-excerpt">${post.excerpt.substring(0, 60)}${post.excerpt.length > 60 ? '...' : ''}</small>` : ''}
                </td>
                <td class="text-center">${categoryBadge}</td>
                <td class="text-center">
                    <span class="author-badge">${post.author?.name || 'Unknown'}</span>
                </td>
                <td class="text-center text-light">${post.view_count || 0}</td>
                <td class="text-center text-light small">${formatDate(post.created_at)}</td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-publish-btn m-0" type="checkbox" data-id="${post.id}" ${post.is_published ? 'checked' : ''} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-edit-post" style="color: var(--text-secondary); background:rgba(255,255,255,0.05);" data-post='${JSON.stringify(post).replace(/'/g, "&#39;")}' title="Sửa"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-post" style="color:#ef4444; background:rgba(239,68,68,0.1);" data-id="${post.id}" title="Xóa"><i class="bi bi-trash"></i></button>
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
        for (let i = 1; i <= meta.last_page; i++) {
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
        els.isPublished.checked = false;
        els.statusLabel.textContent = 'Xuất bản ngay';
        if (window.jQuery && window.jQuery.fn.summernote && window.jQuery('#summernoteEditor').length) {
            window.jQuery('#summernoteEditor').summernote('code', '');
        }
        els.content.value = '';
    }

    function formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    if (els.btnCreate) els.btnCreate.addEventListener('click', () => { resetForm(); els.modalLabel.innerHTML = '<i class="bi bi-file-text me-2"></i>Tạo bài viết mới'; getModalInstance()?.show(); });

    if (els.isPublished && els.statusLabel) els.isPublished.addEventListener('change', () => { els.statusLabel.textContent = els.isPublished.checked ? 'Xuất bản ngay' : 'Lưu nháp'; });

    els.tableBody.addEventListener('click', async (e) => {
        const btnEdit = e.target.closest('.btn-edit-post');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';
            const post = JSON.parse(btnEdit.dataset.post);
            els.idInput.value = post.id;
            els.modalLabel.innerHTML = '<i class="bi bi-file-text me-2"></i>Cập nhật bài viết';
            els.title.value = post.title || '';
            els.slug.value = post.slug || '';
            els.category.value = post.category || '';
            els.excerpt.value = post.excerpt || '';
            els.content.value = post.content || '';
            if (window.jQuery && window.jQuery.fn.summernote && window.jQuery('#summernoteEditor').length) {
                window.jQuery('#summernoteEditor').summernote('code', post.content || '');
            }
            els.publishedAt.value = formatDateTimeLocal(post.published_at);
            els.isPublished.checked = post.is_published === 1 || post.is_published === true;
            els.statusLabel.textContent = els.isPublished.checked ? 'Xuất bản ngay' : 'Lưu nháp';
            getModalInstance()?.show();
            return;
        }

        const btnDel = e.target.closest('.btn-delete-post');
        if (btnDel) {
            if (!confirm('Bạn có chắc muốn xóa bài viết này?')) return;
            try {
                const res = await window.AdminCore.apiFetch(`/api/v1/admin/posts/${btnDel.dataset.id}`, { method: 'DELETE' });
                if (res && res.ok) { window.showAdminToast?.('Xóa bài viết thành công', 'success'); loadData(currentPage); }
                else { const err = await res.json(); window.showAdminToast?.(err.message || 'Xóa thất bại', 'error'); }
            } catch { window.showAdminToast?.('Xóa thất bại', 'error'); }
        }
    });

    els.tableBody.addEventListener('change', async (e) => {
        const toggle = e.target.closest('.toggle-publish-btn');
        if (!toggle) return;
        const id = toggle.getAttribute('data-id'), isPublished = toggle.checked;
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/posts/${id}/toggle-publish`, { method: 'POST' });
            if (!res || !res.ok) throw new Error();
            window.showAdminToast?.('Cập nhật trạng thái thành công', 'success');
            loadData(currentPage);
        } catch { window.showAdminToast?.('Cập nhật thất bại', 'error'); toggle.checked = !isPublished; }
    });

    if (els.form) {
        els.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isEdit = els.formMethod.value === 'PUT', id = els.idInput.value;
            const url = isEdit ? `/api/v1/admin/posts/${id}` : '/api/v1/admin/posts';
            const formData = new FormData();
            formData.append('title', els.title.value.trim());
            if (els.slug.value.trim()) formData.append('slug', els.slug.value.trim());
            formData.append('category', els.category.value);
            if (els.excerpt.value.trim()) formData.append('excerpt', els.excerpt.value.trim());
            formData.append('content', els.content.value.trim());
            if (els.image.files[0]) formData.append('featured_image', els.image.files[0]);
            if (els.publishedAt.value) formData.append('published_at', els.publishedAt.value);
            formData.append('is_published', els.isPublished.checked ? '1' : '0');
            if (isEdit) formData.append('_method', 'PUT');

            try {
                const res = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: formData, credentials: 'same-origin' });
                if (res && res.ok) { getModalInstance()?.hide(); window.showAdminToast?.(isEdit ? 'Cập nhật bài viết thành công' : 'Tạo bài viết thành công', 'success'); loadData(currentPage); }
                else { const errData = await res.json(); alert('Dữ liệu không hợp lệ: ' + JSON.stringify(errData.errors || errData.message)); }
            } catch (error) { console.error('Submit error', error); }
        });
    }

    if (els.searchForm) {
        els.searchForm.addEventListener('submit', (e) => { e.preventDefault(); currentSearch = els.searchInput.value.trim(); currentStatus = els.statusFilter.value; currentCategory = els.categoryFilter.value; currentPage = 1; loadData(currentPage); });
        if (els.statusFilter) els.statusFilter.addEventListener('change', () => els.searchForm.dispatchEvent(new Event('submit')));
        if (els.categoryFilter) els.categoryFilter.addEventListener('change', () => els.searchForm.dispatchEvent(new Event('submit')));
    }

    async function loadCategories() {
        try {
            const res = await window.AdminCore.apiFetch('/api/v1/admin/posts/categories');
            if (res && res.ok) {
                const json = await res.json(), categories = json.data || [];
                const categoryLabels = { news: 'Tin tức', blog: 'Blog', announcement: 'Thông báo', event: 'Sự kiện', promotion: 'Khuyến mãi' };
                if (els.categoryFilter) {
                    els.categoryFilter.innerHTML = '<option value="all">Tất cả danh mục</option>';
                    categories.forEach(cat => { const opt = document.createElement('option'); opt.value = cat; opt.textContent = categoryLabels[cat] || cat; els.categoryFilter.appendChild(opt); });
                }
                if (els.category) {
                    els.category.innerHTML = '';
                    categories.forEach(cat => { const opt = document.createElement('option'); opt.value = cat; opt.textContent = categoryLabels[cat] || cat; els.category.appendChild(opt); });
                }
            }
        } catch (error) { console.error('Error loading categories:', error); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadCategories();
        loadData(1);

        // Initialize Summernote
        if (window.jQuery && window.jQuery.fn.summernote && window.jQuery('#summernoteEditor').length) {
            window.jQuery('#summernoteEditor').summernote({
                placeholder: 'Viết nội dung bài viết...',
                tabsize: 2,
                height: 250,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                callbacks: {
                    onChange: function(contents, $editable) {
                        els.content.value = contents === '<p><br></p>' ? '' : contents;
                    }
                }
            });
        }
    });
})();
