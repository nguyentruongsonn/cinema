/**
 * Posts Management - posts.js
 * Simple SPA Architecture (following pattern)
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

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
        imageUploadBox: document.getElementById('postImageUploadBox'),
        imagePreview: document.getElementById('postImagePreview'),
        imagePlaceholder: document.getElementById('postImagePlaceholder'),
        clearImageBtn: document.getElementById('clearPostImageBtn'),
        publishedAt: document.getElementById('postPublishedAt'),
        isPublished: document.getElementById('postIsPublished'),
        statusLabel: document.getElementById('postStatusLabel'),
    };

    const mediaInput = new window.AdminMediaInput({
        root: els.imageUploadBox,
        input: els.image,
        preview: els.imagePreview,
        placeholder: els.imagePlaceholder,
        clearButton: els.clearImageBtn,
    });

    let currentPage = 1, currentSearch = '', currentStatus = 'all', currentCategory = 'all';

    function getModalInstance() {
        return bootstrap.Modal.getOrCreateInstance(els.modalEl);
    }

    async function loadData(page = 1) {
        try {
            if (window.renderAdminTableSkeleton && els.tableBody) {
                window.renderAdminTableSkeleton(els.tableBody, 8, 5, false);
            }

            const url = new URL(window.location.origin + '/api/v1/admin/posts');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentStatus !== 'all') url.searchParams.append('status', currentStatus);
            if (currentCategory !== 'all') url.searchParams.append('category', currentCategory);
            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'posts:list' });
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
            tr.classList.add('admin-table-row');
            const safeCategory = Object.hasOwn(categoryLabels, post.category) ? post.category : 'news';
            const categoryBadge = `<span class="badge-category badge-category-${safeCategory}">${escapeHtml(categoryLabels[post.category] || post.category)}</span>`;
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td>
                    <div class="post-title">${escapeHtml(post.title)}</div>
                    ${post.excerpt ? `<small class="post-excerpt">${escapeHtml(post.excerpt.substring(0, 60))}${post.excerpt.length > 60 ? '...' : ''}</small>` : ''}
                </td>
                <td class="text-center">${categoryBadge}</td>
                <td class="text-center">
                    <span class="author-badge">${escapeHtml(post.author?.name || 'Unknown')}</span>
                </td>
                <td class="text-center text-light">${escapeHtml(post.view_count || 0)}</td>
                <td class="text-center text-light small">${formatDate(post.created_at)}</td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-publish-btn m-0 admin-toggle-pointer" type="checkbox" data-id="${escapeHtml(post.id)}" ${post.is_published ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-edit-post admin-table-action-edit" data-post='${escapeHtml(JSON.stringify(post))}' title="Sửa"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm ms-1 btn-delete-post admin-table-action-delete" data-id="${escapeHtml(post.id)}" title="Xóa"><i class="bi bi-trash"></i></button>
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
        els.isPublished.checked = false;
        els.statusLabel.textContent = 'Xuất bản ngay';
        if (window.jQuery && window.jQuery.fn.summernote && window.jQuery('#summernoteEditor').length) {
            window.jQuery('#summernoteEditor').summernote('code', '');
        }
        els.content.value = '';
        mediaInput.clear();
    }

    function formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    if (els.btnCreate) els.btnCreate.addEventListener('click', () => { resetForm(); els.modalLabel.innerHTML = '<i class="bi bi-file-text me-2 admin-accent-icon"></i>Tạo bài viết mới'; getModalInstance()?.show(); });

    function updatePublicationLabel() {
        if (!els.statusLabel) return;
        if (!els.isPublished.checked) {
            els.statusLabel.textContent = 'Lưu nháp';
            return;
        }
        const scheduledAt = els.publishedAt.value ? new Date(els.publishedAt.value) : null;
        els.statusLabel.textContent = scheduledAt && scheduledAt > new Date() ? 'Xuất bản theo lịch' : 'Xuất bản ngay';
    }

    if (els.isPublished) els.isPublished.addEventListener('change', updatePublicationLabel);
    if (els.publishedAt) els.publishedAt.addEventListener('change', updatePublicationLabel);
    els.tableBody.addEventListener('click', async (e) => {
        const btnEdit = e.target.closest('.btn-edit-post');
        if (btnEdit) {
            resetForm();
            els.formMethod.value = 'PUT';
            const post = JSON.parse(btnEdit.dataset.post);
            els.idInput.value = post.id;
            els.modalLabel.innerHTML = '<i class="bi bi-file-text me-2 admin-accent-icon"></i>Cập nhật bài viết';
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
            updatePublicationLabel();
            if (post.featured_image_url) mediaInput.show(post.featured_image_url);
            getModalInstance()?.show();
            return;
        }

        const btnDel = e.target.closest('.btn-delete-post');
        if (btnDel) {
            if (!await window.AdminDialog.confirm({ message: 'Bạn có chắc muốn xóa bài viết này?', confirmLabel: 'Xóa bài viết', variant: 'danger' })) return;
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
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote && window.jQuery('#summernoteEditor').length) {
                const codeContent = window.jQuery('#summernoteEditor').summernote('code');
                els.content.value = (codeContent === '<p><br></p>' ? '' : codeContent);
            }
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
                const res = await window.AdminCore.apiFetch(url, { method: 'POST', body: formData, skipCache: true });
                if (res && res.ok) { getModalInstance()?.hide(); window.showAdminToast?.(isEdit ? 'Cập nhật bài viết thành công' : 'Tạo bài viết thành công', 'success'); loadData(currentPage); }
                else { const errData = await res.json(); window.showAdminToast?.(window.formatAdminErrors?.(errData.errors || errData.message) || 'Dữ liệu không hợp lệ', 'error'); }
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

    function initSummernote(retries = 10) {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote && window.jQuery('#summernoteEditor').length) {
            window.jQuery('#summernoteEditor').summernote({
                placeholder: 'Viết nội dung bài viết...',
                tabsize: 2,
                height: 280,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                callbacks: {
                    onChange: function(contents) {
                        els.content.value = contents === '<p><br></p>' ? '' : contents;
                    }
                }
            });
        } else if (retries > 0) {
            setTimeout(() => initSummernote(retries - 1), 150);
        }
    }

    window.onAdminPageLoad(() => {
        loadCategories();
        loadData(1);
        initSummernote();
    });
})();
