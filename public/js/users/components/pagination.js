/**
 * Shared accessible pagination renderer for client-rendered data regions.
 */
class CinemaPagination {
    static render(options = {}) {
        const container = this.getElement(options.container);
        const pagination = options.pagination || {};
        const currentPage = Number(pagination.current_page || 1);
        const lastPage = Number(pagination.last_page || 1);
        const perPage = Number(pagination.per_page || 0);
        const total = Number(pagination.total || 0);

        if (!container) return;
        container.innerHTML = '';
        container.classList.add('cinema-pagination');

        if (!lastPage || lastPage <= 1) return;

        if (options.summary !== false && total > 0 && perPage > 0) {
            const firstItem = ((currentPage - 1) * perPage) + 1;
            const lastItem = Math.min(currentPage * perPage, total);
            const summary = document.createElement('div');
            summary.className = 'cinema-pagination__summary';
            summary.textContent = `Hiển thị ${firstItem}-${lastItem} trong ${total} ${options.itemLabel || 'mục'}`;
            container.appendChild(summary);
        }

        container.appendChild(this.createButton({
            page: currentPage - 1,
            label: '‹',
            ariaLabel: options.previousLabel || 'Trang trước',
            disabled: currentPage <= 1,
            onPageChange: options.onPageChange,
        }));

        this.visiblePages(currentPage, lastPage).forEach(item => {
            if (item === 'ellipsis') {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'cinema-pagination__ellipsis';
                ellipsis.textContent = '...';
                ellipsis.setAttribute('aria-hidden', 'true');
                container.appendChild(ellipsis);
                return;
            }

            container.appendChild(this.createButton({
                page: item,
                label: String(item),
                ariaLabel: `Trang ${item}`,
                active: item === currentPage,
                onPageChange: options.onPageChange,
            }));
        });

        container.appendChild(this.createButton({
            page: currentPage + 1,
            label: '›',
            ariaLabel: options.nextLabel || 'Trang tiếp theo',
            disabled: currentPage >= lastPage,
            onPageChange: options.onPageChange,
        }));
    }

    static createButton({ page, label, ariaLabel, disabled = false, active = false, onPageChange }) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cinema-pagination__button';
        button.dataset.page = String(page);
        button.textContent = label;
        button.setAttribute('aria-label', ariaLabel);

        if (active) {
            button.classList.add('is-active');
            button.setAttribute('aria-current', 'page');
        }

        if (disabled) {
            button.disabled = true;
        } else {
            button.addEventListener('click', () => {
                if (typeof onPageChange === 'function') onPageChange(page);
            });
        }

        return button;
    }

    static visiblePages(currentPage, lastPage) {
        const pages = [];
        let previousWasGap = false;

        for (let page = 1; page <= lastPage; page += 1) {
            const isVisible = page === 1 || page === lastPage || Math.abs(page - currentPage) <= 1;

            if (isVisible) {
                pages.push(page);
                previousWasGap = false;
            } else if (!previousWasGap) {
                pages.push('ellipsis');
                previousWasGap = true;
            }
        }

        return pages;
    }

    static getElement(target) {
        if (!target) return null;
        return typeof target === 'string' ? document.querySelector(target) : target;
    }
}

if (typeof window !== 'undefined') {
    window.CinemaPagination = CinemaPagination;
}

export default CinemaPagination;
