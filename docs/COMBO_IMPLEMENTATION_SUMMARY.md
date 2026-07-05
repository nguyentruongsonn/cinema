# Tóm Tắt Refactor Combo System

## Thay Đổi Chính

### Từ: Combo trong Products (Thiết kế cũ)
```
products
├── type='food' (Bắp, snack)
├── type='drink' (Coca, nước)
└── type='combo' (Combo Solo, Combo Couple) ← stock=null

combo_items (junction)
├── combo_id → products.id (where type='combo')
└── product_id → products.id
```

### Sang: Combo riêng biệt (Thiết kế mới)
```
combos (bảng mới)
└── Combo Solo, Combo Couple, etc.

products
├── type='food'
└── type='drink' (KHÔNG có type='combo')

combo_items (junction)
├── combo_id → combos.id
└── product_id → products.id
```

## Files Đã Tạo/Sửa

### 1. Migration (2 files)
✅ **`2026_07_05_094404_create_combos_table.php`**
- Tạo bảng `combos` mới
- Columns: id, name, price, image_url, description, status, timestamps, soft deletes

✅ **`2026_07_05_092518_create_combo_items_table.php`** (đã sửa)
- FK `combo_id` giờ trỏ đến `combos.id` (thay vì `products.id`)

### 2. Models (3 files)
✅ **`app/Models/Combo.php`** (mới)
- HasMany comboItems
- Accessor `available_stock` (tính động từ món con)
- Scopes: active(), inStock()

✅ **`app/Models/ComboItem.php`** (đã sửa)
- BelongsTo combo → Combo::class (thay vì Product::class)
- BelongsTo product → Product::class (giữ nguyên)

✅ **`app/Models/Product.php`** (đã sửa)
- Xóa: comboItems() method (không cần nữa)
- Xóa: getAvailableStockAttribute() (không cần nữa)
- Giữ: usedInCombos() method (vẫn hợp lệ)

### 3. Seeders (3 files)
✅ **`database/seeders/ComboSeeder.php`** (mới)
- Seed 4 combo: Solo, Couple, Family, VIP

✅ **`database/seeders/ProductSeeder.php`** (đã sửa)
- Xóa tất cả combo (chỉ còn 8 products: food + drink)

✅ **`database/seeders/ComboItemSeeder.php`** (đã sửa)
- Dùng `Combo::where()` thay vì `Product::where()`
- Error message: "Hãy chạy ComboSeeder trước" (thay vì ProductSeeder)

✅ **`database/seeders/DatabaseSeeder.php`** (đã sửa)
- Thêm `ComboSeeder::class` giữa ProductSeeder và ComboItemSeeder

### 4. Documentation (2 files)
✅ **`docs/COMBO_DATABASE_DESIGN.md`** (đã cập nhật)
- Mô tả thiết kế mới với 3 bảng riêng
- So sánh thiết kế cũ vs mới

✅ **`docs/COMBO_IMPLEMENTATION_SUMMARY.md`** (file này)
- Tóm tắt refactor

## Chạy Migration

### Nếu Database Trống
```bash
php artisan migrate
php artisan db:seed
```

### Nếu Database Đã Có Dữ Liệu Cũ
```bash
# 1. Rollback combo-related migrations
php artisan migrate:rollback --step=1  # rollback combo_items
php artisan migrate:fresh              # hoặc fresh toàn bộ

# 2. Run migrations mới
php artisan migrate

# 3. Seed lại data
php artisan db:seed
```

## Kết Quả

### Combos Table
```
id | name          | price   | status
---|---------------|---------|-------
1  | Combo Solo    | 75000   | 1
2  | Combo Couple  | 129000  | 1
3  | Combo Family  | 199000  | 1
4  | Combo VIP     | 249000  | 1
```

### Products Table (8 món, KHÔNG có combo)
```
id | name              | type  | stock | price
---|-------------------|-------|-------|-------
1  | Bắp rang bơ M     | food  | 200   | 45000
2  | Bắp rang bơ L     | food  | 200   | 55000
3  | Bắp phô mai L     | food  | 150   | 65000
4  | Coca Cola M       | drink | 300   | 30000
5  | Coca Cola L       | drink | 300   | 40000
6  | Sprite M          | drink | 250   | 30000
7  | Nước suối         | drink | 300   | 20000
8  | Snack khoai tây   | food  | 180   | 35000
```

### Combo Items Table
```
combo_id | product_id | quantity | Ý nghĩa
---------|------------|----------|------------------
1        | 1          | 1        | Solo: 1 bắp M
1        | 4          | 1        | Solo: 1 coca M
2        | 2          | 1        | Couple: 1 bắp L
2        | 4          | 2        | Couple: 2 coca M
...
```

## Query Examples

### Lấy tất cả combo
```php
$combos = Combo::active()->get();
```

### Lấy combo với món con
```php
$combo = Combo::with('comboItems.product')->find(1);

foreach ($combo->comboItems as $item) {
    echo "{$item->quantity}x {$item->product->name}\n";
}
```

### Kiểm tra tồn kho combo
```php
$combo = Combo::find(1);
$available = $combo->available_stock; // Tính động
```

### Tìm product được dùng trong combo nào
```php
$product = Product::find(1);
$combos = $product->usedInCombos()->with('combo')->get();
```

## Lợi Ích Refactor

✅ **Tách biệt rõ ràng**: Combo và Product là 2 entity độc lập
✅ **Dễ maintain**: Sửa combo không ảnh hưởng products
✅ **Mở rộng dễ**: Thêm field cho combo (discount, validity, etc.) mà không ảnh hưởng products
✅ **Query đơn giản**: `Combo::all()` thay vì `Product::where('type', 'combo')`
✅ **Type safety**: Không có type='combo' trong products nữa

## Breaking Changes

⚠️ **Code cũ sử dụng `Product::where('type', 'combo')` sẽ BỊ LỖI**

Cần refactor:
```php
// CŨ (sẽ lỗi)
$combos = Product::where('type', 'combo')->get();

// MỚI (đúng)
$combos = Combo::all();
```

⚠️ **ProductController/API cần cập nhật** nếu có endpoint liên quan combo

## Next Steps (Nếu Cần)

1. **Cập nhật Frontend**: Sửa API calls từ `/products?type=combo` sang `/combos`
2. **Cập nhật Controller**: Tạo `ComboController` riêng (nếu cần)
3. **Cập nhật Order Logic**: Nếu có code xử lý combo trong orders, cần refactor
4. **Testing**: Viết tests cho Combo model và relationships

## Tham Khảo

- [COMBO_DATABASE_DESIGN.md](./COMBO_DATABASE_DESIGN.md) - Chi tiết thiết kế database
- Migration files trong `database/migrations/`
- Model files trong `app/Models/`