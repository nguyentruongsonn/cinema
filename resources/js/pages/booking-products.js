class BookingProductRenderer {
    constructor(manager) {
        this.manager = manager;
    }

    icon(name) {
        const normalizedName = String(name || '').toLowerCase();
        if (['bắp', 'popcorn', 'ngô', 'bap', 'combo'].some((term) => normalizedName.includes(term))) return 'bi-cookie';
        if (['nước', 'coke', 'coca', 'sprite', 'pepsi', 'drink', 'suối'].some((term) => normalizedName.includes(term))) return 'bi-cup-straw';
        return 'bi-gift';
    }

    static normalize(products) {
        if (!Array.isArray(products)) return [];

        return products.map((product) => {
            const price = Number(product?.price);
            const maxQuantity = Number.parseInt(product?.max_quantity ?? product?.stock ?? 0, 10);
            const sourceId = Number.parseInt(product?.id, 10);
            const catalogType = product?.catalog_type || product?.type || 'product';
            const catalogKey = product?.catalog_key || `${catalogType}:${sourceId}`;

            return {
                ...product,
                id: catalogKey,
                source_id: sourceId,
                catalog_key: catalogKey,
                catalog_type: catalogType,
                price: Number.isFinite(price) ? price : 0,
                max_quantity: Number.isFinite(maxQuantity) ? Math.max(0, maxQuantity) : 0,
            };
        }).filter((product) => Number.isFinite(product.source_id) && product.catalog_key);
    }

    render() {
        const manager = this.manager;
        if (!manager.productsContainer) return;
        if (!Array.isArray(manager.products)) manager.products = [];

        if (manager.products.length === 0) {
            manager.productsContainer.innerHTML = '<div class="text-center text-muted py-4">Hiện chưa có combo khả dụng.</div>';
            return;
        }

        manager.productsContainer.innerHTML = manager.products.map((product) => {
            const quantity = Number(manager.selectedProducts.get(product.id)) || 0;
            const image = manager.safeImageUrl(product.image_url);
            const total = Number(product.price) * quantity;
            const totalHtml = quantity > 0
                ? `<div class="product-item-total">Tổng: <span class="text-danger">${manager.formatCurrency(total)}</span></div>`
                : '';

            return `<div class="product-card" data-product-id="${manager.escapeHtml(product.id)}">
                <div class="product-image-wrapper">
                    ${image ? `<img src="${manager.escapeHtml(image)}" alt="${manager.escapeHtml(product.name)}" class="product-image">` : ''}
                    <div class="product-image-fallback ${image ? 'd-none' : ''}"><i class="bi ${this.icon(product.name)}"></i></div>
                </div>
                <div class="product-info">
                    <div class="product-name">${manager.escapeHtml(product.name)}</div>
                    <div class="product-description">${manager.escapeHtml(product.description || 'Không có mô tả.')}</div>
                    <div class="product-price">${manager.formatCurrency(product.price)}</div>
                    <div class="product-footer">
                        <div class="quantity-control">
                            <button type="button" class="quantity-btn minus" data-action="decrease" ${quantity <= 0 ? 'disabled' : ''}>−</button>
                            <span class="quantity-value">${quantity}</span>
                            <button type="button" class="quantity-btn plus" data-action="increase" ${quantity >= product.max_quantity ? 'disabled' : ''}>+</button>
                        </div>${totalHtml}
                    </div>
                </div>
            </div>`;
        }).join('');

        manager.productsContainer.querySelectorAll('.product-image').forEach((image) => {
            image.addEventListener('error', () => {
                image.style.display = 'none';
                if (image.nextElementSibling) image.nextElementSibling.style.display = 'flex';
            }, { once: true });
        });

        manager.productsContainer.querySelectorAll('.product-card').forEach((item) => {
            const productId = item.dataset.productId;
            item.querySelector('[data-action="decrease"]')?.addEventListener('click', () => manager.changeProductQuantity(productId, -1));
            item.querySelector('[data-action="increase"]')?.addEventListener('click', () => manager.changeProductQuantity(productId, 1));
        });
    }
}

window.BookingProductRenderer = BookingProductRenderer;
