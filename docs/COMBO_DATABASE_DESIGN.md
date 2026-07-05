# Thiết Kế Database Combo System

## Tổng Quan

Hệ thống combo được thiết kế với **3 bảng riêng biệt**:
- **`combos`**: Lưu thông tin combo (tên, giá, hình ảnh)
- **`products`**: Lưu sản phẩm đơn lẻ (food, drink)
- **`combo_items`**: Bảng trung gian liên kết combo với products + số lượng

## Cấu Trúc Bảng

### 1. Bảng `combos`
Lưu thông tin combo (riêng biệt với products):

```sql
CREATE TABLE combos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL COMMENT 'Tên combo',
    price DECIMAL(10,2) NOT NULL COMMENT 'Giá bán combo',
    image_url VARCHAR(255) NULL COMMENT 'URL hình ảnh combo',
    description TEXT NULL COMMENT 'Mô tả combo',
    status BOOLEAN DEFAULT 1 COMMENT '1: Đang bán, 0: Ngừng bán',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
);
```

### 2. Bảng `products`
Lưu sản phẩm đơn lẻ (food, drink) - KHÔNG chứa combo:

```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    type ENUM('food', 'drink') NOT NULL,  -- Không có 'combo'
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL COMMENT 'Tồn kho thực tế',
    image_url VARCHAR(255) NULL,
    description TEXT NULL,
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### 3. Bảng `combo_items` (Junction Table)
Liên kết combo với products + số lượng:

```sql
CREATE TABLE combo_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    combo_id BIGINT NOT NULL COMMENT 'FK → combos.id',
    product_id BIGINT NOT NULL COMMENT 'FK → products.id',
    quantity INT UNSIGNED DEFAULT 1 COMMENT 'Số lượng món trong combo',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    
    UNIQUE KEY uk_combo_product (combo_id, product_id),
    INDEX idx_combo_id (combo_id),
    INDEX idx_product_id (product_id)
);
```

## Relationships

```
combos (1) ----< combo_items (N) >---- (1) products
   ↑                                         ↑
   |                                         |
Combo Solo                           Bắp M, Coca M, etc.
price: 75k
```

### Ví Dụ Data Flow

**Combo Solo** (75.000đ):
```
combos:
- id: 1, name: 'Combo Solo', price: 75000

combo_items:
- combo_id: 1, product_id: 1 (Bắp M), quantity: 1
- combo_id: 1, product_id: 4 (Coca M), quantity: 1

products:
- id: 1, name: 'Bắp M', type: 'food', stock: 200
- id: 4, name: 'Coca M', type: 'drink', stock: 300
```

## Tính Tồn Kho Combo

Combo KHÔNG có tồn kho cố định. Tính động từ món con:

```php
// Trong Combo model
public function getAvailableStockAttribute(): int
{
    $items = $this->comboItems()->with('product')->get();
    
    $minStock = PHP_INT_MAX;
    foreach ($items as $item) {
        if (!$item->product || $item->product->stock <= 0) {
            return 0;
        }
        $availableCombo = floor($item->product->stock / $item->quantity);
        $minStock = min($minStock, $availableCombo);
    }
    
    return max(0, $minStock);
}
```

**Ví dụ:**
- Bắp M: stock = 200
- Coca M: stock = 300
- Combo Solo cần: 1 bắp M + 1 coca M

→ Tồn kho Combo Solo = min(200/1, 300/1) = 200

## Model Relationships

### Combo Model
```php
class Combo extends Model
{
    public function comboItems(): HasMany
    {
        return $this->hasMany(ComboItem::class, 'combo_id');
    }
}
```

### Product Model
```php
class Product extends Model
{
    public function usedInCombos(): HasMany
    {
        return $this->hasMany(ComboItem::class, 'product_id');
    }
}
```

### ComboItem Model
```php
class ComboItem extends Model
{
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'combo_id');
    }
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
```

## Lợi Ích Thiết Kế

✅ **Tách biệt rõ ràng**: Combo và Product là 2 entity khác nhau
✅ **Dễ quản lý**: Thêm/sửa combo không ảnh hưởng products
✅ **Flexible**: Một product có thể nằm trong nhiều combo
✅ **Tồn kho chính xác**: Combo tự tính từ món con
✅ **Mở rộng dễ**: Thêm metadata cho combo (discount, validity, etc.)

## So Sánh Với Thiết Kế Cũ

| Tiêu chí | Thiết kế cũ (combo trong products) | Thiết kế mới (combos riêng) |
|----------|-----------------------------------|------------------------------|
| Tách biệt | ❌ Combo và product chung bảng | ✅ Combo và product riêng bảng |
| Tồn kho | stock = null cho combo | Tính động từ món con |
| Type | type='combo' / 'food' / 'drink' | Combo không có type |
| Query | Phải filter type | Direct query combos table |
| Mở rộng | Khó (chia sẻ schema) | Dễ (schema riêng) |