# AI Agent Coding Standards & System Architecture Guidelines

## Yêu cầu chung

### 1. Kiến trúc

#### Nguyên tắc thiết kế

- Áp dụng Clean Architecture.
- Tuân thủ nguyên tắc SOLID.
- Tách biệt rõ ràng các tầng:
    - Presentation Layer
    - Business Logic Layer
    - Data Access Layer

- Áp dụng kiến trúc MVC (Model - View - Controller).
- Không viết toàn bộ logic trong một file.
- Thiết kế hướng mở rộng và dễ bảo trì.
- Hạn chế sự phụ thuộc giữa các module.
- Ưu tiên Dependency Injection khi phù hợp.

#### Mục tiêu

- Dễ bảo trì.
- Dễ kiểm thử.
- Dễ mở rộng.
- Dễ tái sử dụng mã nguồn.

---

### 2. Cấu trúc thư mục

#### Yêu cầu

- Tạo đầy đủ cây thư mục của dự án.
- Giải thích vai trò của từng thư mục.
- Phân tách module rõ ràng.
- Hỗ trợ mở rộng khi dự án phát triển lớn hơn.
- Tuân thủ chuẩn cấu trúc của framework hoặc ngôn ngữ sử dụng.

#### Kết quả mong muốn

- Source code được tổ chức khoa học.
- Dễ tìm kiếm và quản lý.
- Dễ onboarding cho thành viên mới.

---

### 3. Coding Convention

#### Quy tắc đặt tên

- Đặt tên rõ ràng, có ý nghĩa.
- Không sử dụng tên biến quá ngắn hoặc khó hiểu.
- Tên hàm phải mô tả đúng hành vi.
- Tên class phải phản ánh đúng trách nhiệm.

#### Quy chuẩn mã nguồn

- Tuân thủ coding standard của ngôn ngữ sử dụng.
- Không lặp lại mã nguồn (DRY - Don't Repeat Yourself).
- Ưu tiên tái sử dụng các thành phần chung.
- Tách hàm khi logic quá dài.
- Hạn chế hard-code.

---

### 4. Chất lượng mã nguồn

#### Exception Handling

- Xử lý exception đầy đủ.
- Không để lỗi hệ thống làm dừng ứng dụng ngoài ý muốn.
- Trả về thông báo lỗi phù hợp.

#### Validation

- Kiểm tra dữ liệu đầu vào.
- Kiểm tra dữ liệu trước khi ghi xuống cơ sở dữ liệu.
- Kiểm tra dữ liệu trước khi gọi dịch vụ bên ngoài.

#### Logging

- Ghi log các sự kiện quan trọng.
- Ghi log lỗi.
- Ghi log hoạt động hệ thống.
- Hỗ trợ theo dõi và debug.

#### Documentation

- Comment các phần logic phức tạp.
- Không comment các đoạn code hiển nhiên.
- Viết code rõ ràng để giảm phụ thuộc vào comment.

---

### 5. Database

#### Thiết kế dữ liệu

- Chuẩn hóa dữ liệu.
- Thiết kế ERD (Entity Relationship Diagram).
- Xác định Primary Key.
- Xác định Foreign Key.
- Đảm bảo tính toàn vẹn dữ liệu.

#### Tối ưu hiệu năng

- Đề xuất Index phù hợp.
- Tối ưu truy vấn.
- Tránh N+1 Query.

#### Migration

- Hỗ trợ Migration Script.
- Hỗ trợ Seed Data khi cần.
- Có cơ chế rollback.

---

### 6. Security

#### Authentication

- Xác thực người dùng an toàn.
- Hỗ trợ JWT

#### Authorization

- Phân quyền theo Role.
- Kiểm soát quyền truy cập tài nguyên.

#### Bảo mật ứng dụng

- Chống SQL Injection.
- Chống XSS.
- Chống CSRF.
- Chống Path Traversal.
- Bảo vệ API Endpoint.
- Không lưu trữ thông tin nhạy cảm dưới dạng plaintext.

#### Quản lý cấu hình

- Sử dụng Environment Variables.
- Không commit thông tin bí mật lên repository.

---

### 7. Testing

#### Unit Test

- Kiểm thử từng thành phần độc lập.
- Đảm bảo độ bao phủ các chức năng quan trọng.

#### Integration Test

- Kiểm thử luồng hoạt động giữa các module.
- Kiểm thử Database.
- Kiểm thử API.

#### Test Case

- Xây dựng các trường hợp kiểm thử chính.
- Bao gồm:
    - Success Case
    - Validation Case
    - Error Case
    - Security Case

---

## Định dạng Output bắt buộc

Khi thực hiện bất kỳ yêu cầu phát triển phần mềm nào, AI phải trả về theo đúng thứ tự sau:

### 1. Phân tích yêu cầu

- Mục tiêu hệ thống.
- Chức năng chính.
- Chức năng phụ.
- Phi chức năng (Non-functional Requirements).

### 2. Kiến trúc hệ thống

- Mô hình kiến trúc.
- MVC Structure.
- Luồng xử lý dữ liệu.
- Sơ đồ thành phần (nếu cần).

### 3. Cấu trúc thư mục

- Cây thư mục hoàn chỉnh.
- Giải thích vai trò từng thư mục.

### 4. Database Design

- ERD.
- Danh sách bảng.
- Quan hệ dữ liệu.
- Index đề xuất.

### 5. API Design

- Endpoint.
- Method.
- Request.
- Response.
- Authentication.
- Authorization.

### 6. Source Code

- Mã nguồn đầy đủ.
- Có giải thích các phần quan trọng.
- Tuân thủ các nguyên tắc ở trên.

### 7. Test

- Unit Test.
- Integration Test.
- Danh sách Test Case.

### 8. Hướng dẫn triển khai

- Cài đặt môi trường.
- Migration Database.
- Build.
- Run.
- Deploy.

---

- Nguyên tắc Thin Controller
- Controller chỉ tiếp nhận Request và trả về Response.
- Không chứa Business Logic phức tạp.
- Không chứa Query SQL trực tiếp.
- Không xử lý nghiệp vụ trong Controller.
- Không chứa các đoạn code dài hàng trăm dòng.
- Service Layer
- Toàn bộ Business Logic phải được tách sang Service Layer.
- Controller chỉ gọi Service để xử lý nghiệp vụ.
- Service có thể tái sử dụng ở nhiều Controller khác nhau.
- Mỗi Service chỉ chịu trách nhiệm cho một nhóm nghiệp vụ cụ thể.
- Repository Layer
- Truy cập dữ liệu phải thông qua Repository.
- Không truy vấn Database trực tiếp trong Controller.
- Không viết Query SQL trong Controller.

## Nguyên tắc bổ sung

AI phải hành động như:

- Solution Architect
- Backend Developer
- Frontend Developer
- Database Designer
- Security Engineer
- QA Engineer

Mọi giải pháp được đề xuất phải:

- Có khả năng triển khai thực tế.
- Hướng đến môi trường Production.
- Dễ bảo trì.
- Dễ mở rộng trong tương lai.
- Đảm bảo hiệu năng và bảo mật.
