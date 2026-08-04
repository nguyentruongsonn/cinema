# UI Component Refactor Audit

Ngày cập nhật: 02/08/2026

## Phạm vi

- 43 Blade view, 62 JavaScript file và 58 CSS file.
- Kiểm tra component dùng chung, inline CSS, toast, modal, table, pagination, skeleton, confirm dialog, API client, tabs và trạng thái dữ liệu.
- Tuân theo `FRONTEND_BACKEND_STANDARDS.md` và `FRONTEND_STRUCTURE.md`.

## Đã hoàn thành

### Post section và post card

- Home và bài viết liên quan dùng chung `x-user.content-section-header` và `x-user.post-card`.
- Style thuộc `public/css/users/components/content-posts.css`; card dùng cùng tỷ lệ ảnh, chiều cao, hover, focus và responsive behavior.
- Trang bài viết dùng một hero chung cho mọi filter, data filter không điều hướng URL và có pagination.

### Toast và dialog

- Public UI dùng `Toast`; admin dùng `window.showAdminToast` và `window.formatAdminErrors`.
- Page code không còn native `alert()` hoặc `confirm()`.
- `AdminDialog.confirm()` hỗ trợ Promise, Escape, focus trap, restore focus, backdrop và khóa scroll.
- `Modal.confirmAsync()` được dùng trong booking/payment.

### Inline CSS

- Inline `style=""` trong Blade và HTML sinh từ JavaScript: **0**.
- Thẻ `<style>` trong Blade: **0**; CSS Summernote đã chuyển sang `public/css/admin/pages/posts.css`.
- Runtime `.style.*` giảm từ 112 xuống 74; phần còn lại chủ yếu là geometry, animation, CSS custom property hoặc editor sơ đồ ghế.

### Table, pagination và loading

- Bảng admin dùng `admin-table`, skeleton bảng và `AdminCore.renderAdminPagination`.
- Posts và theaters dùng `CinemaPagination`; theaters không còn pagination riêng.
- `SkeletonLoader` có variant `media-card`, `horizontal-list`, `summary-card`; skeleton chỉ bao vùng dữ liệu.
- Có `x-user.data-state`, `x-admin.data-state` và `DataRegion` cho `loading|empty|error|ready`.

### Media input

- Có `AdminMediaInput`: validate MIME/5MB, drag/drop, clear, revoke object URL và restore preview.
- Products, combos, poster/banner của movies và featured image của posts dùng component chung.
- Banner nhiều ảnh giữ renderer riêng do yêu cầu tối đa 5 ảnh, nhưng không còn CSS dựng bằng JavaScript.

### API và tabs

- Page code gọi `fetch()` trực tiếp: **0**; chỉ transport layer `api-client.js` và `admin-core.js` được phép dùng `fetch()`.
- `APP_CONFIG.apiUrl` dùng path cùng origin, tránh lỗi `localhost`/`127.0.0.1` và CORS.
- Có `CinemaTabs` dùng chung: ARIA selected/tabindex, click, Arrow Left/Right, Home/End và custom event.
- Posts filter và pricing theater tabs đã dùng API tabs chung.

### Component admin

- `x-admin.modal` hỗ trợ form/read-only/custom footer, size, body/dialog/content class, enctype và submit/cancel configuration.
- Branches và banners đã migrate sang `x-admin.modal`.
- `x-admin.filter-bar` dùng markup admin chuẩn và branches đã migrate làm implementation reference.

## Còn tồn tại

### P1 — Migrate modal admin còn lại

- Còn 17 modal viết tay; cần migrate theo từng nhóm để tránh thay đổi form behavior đồng loạt.
- Ưu tiên products, combos, posts, promotions; sau đó screens, showtimes, pricing rules và detail modal orders/users.
- Sau mỗi nhóm phải browser-test Escape, backdrop, submit lỗi, restore focus và SPA navigation cleanup.

### P1 — Data state trên toàn bộ page

- Shared state đã có nhưng mới áp dụng đầy đủ ở theaters và một phần các trang data-heavy.
- Cần migrate home, movies, prices, profile, booking và các CRUD admin khỏi markup empty/error riêng.

### P1 — Multiple media input

- Banner nhiều ảnh vẫn dùng implementation riêng.
- Cần mở rộng `AdminMediaInput` với multiple mode, callback renderer, reorder và revoke object URL trước khi thay thế để không làm mất nghiệp vụ 5 ảnh.

### P2 — Filter bar

- Mới branches dùng `x-admin.filter-bar`; các trang còn lại vẫn dùng markup tương thích nhưng viết tay.
- Migrate screens, showtimes, orders và pricing rules bằng named slots cho select/date/action.

### P2 — CSS ownership

- Một số selector legacy vẫn bị lặp giữa `style.css`, `admin-common.css`, `components/*.css` và page CSS.
- Cần đo coverage theo route trước khi xóa selector; không xóa hàng loạt chỉ dựa trên grep.

### P2 — Runtime style còn lại

- 74 lệnh `.style.*` cần tiếp tục phân loại.
- Geometry động của seat map, ripple, toast position và navigation transition được phép; trạng thái tĩnh phải tiếp tục chuyển sang class/custom property.

## Kiểm chứng

- `node --check`: đạt 37 JavaScript file thay đổi, 0 lỗi.
- `npm run build`: đạt, Vite build 93 modules.
- `php artisan test tests/Feature/ContentManagementTest.php`: đạt 5 tests, 39 assertions.
- Blade render: branches, banners và posts đạt.
- Browser regression bằng Playwright + Microsoft Edge: home, posts, prices, theaters đạt; posts tabs không đổi URL, tabs cập nhật `aria-selected` đúng.
- `npm run lint`: chưa chạy được do dependency cục bộ thiếu `node_modules/@eslint-community/eslint-utils/index.js`, không phải lỗi lint từ source.

## Definition of Done tiếp theo

- 17 modal còn lại chuyển sang `x-admin.modal` và qua browser regression.
- Các data-heavy page dùng `DataRegion` thay markup state riêng.
- Filter bar dùng component chung trên screens/showtimes/orders/pricing rules.
- Multiple media input cho banners dùng API chung.
- Runtime static style về 0; chỉ giữ geometry/animation qua CSS custom property.
- Khôi phục dependency ESLint và đạt build, lint, feature test, browser regression.
