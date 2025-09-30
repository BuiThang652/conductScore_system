# 🎓 Hệ thống quản lý điểm rèn luyện - Phiên bản học tập

> **Dự án này được thiết kế đặc biệt dành cho học sinh mới bắt đầu học PHP**  
> Code được viết rất đơn giản, có nhiều comment giải thích chi tiết

## 📋 Mục lục
- [Giới thiệu](#-giới-thiệu)
- [Yêu cầu hệ thống](#-yêu-cầu-hệ-thống)
- [Cài đặt](#-cài-đặt)
- [Cấu trúc dự án](#-cấu-trúc-dự-án)
- [Hướng dẫn sử dụng](#-hướng-dẫn-sử-dụng)
- [Giải thích code](#-giải-thích-code-cho-người-mới)
- [Troubleshooting](#-troubleshooting)

## 🎯 Giới thiệu

Đây là một hệ thống quản lý điểm rèn luyện đơn giản được xây dựng bằng **PHP thuần** (không dùng framework). Hệ thống bao gồm:

- ✅ Đăng nhập/đăng xuất
- ✅ Quản lý sinh viên
- ✅ Nhập và xem điểm rèn luyện
- ✅ Giao diện thân thiện, responsive

**Đặc điểm:**
- Code đơn giản, dễ hiểu
- Có nhiều comment giải thích
- Thiết kế theo cấu trúc cơ bản nhất
- Phù hợp cho người mới học PHP

## 💻 Yêu cầu hệ thống

### Phần mềm cần có:
- **XAMPP** (hoặc WAMP/MAMP)
  - PHP 7.4+
  - MySQL 5.7+
  - Apache Server
- **Trình duyệt web** (Chrome, Firefox, Edge...)
- **Text editor** (VS Code khuyến nghị)

### Kiến thức cần có:
- HTML/CSS cơ bản
- PHP cơ bản (biến, mảng, if/else, loop)
- MySQL cơ bản (SELECT, INSERT, UPDATE)

## 🚀 Cài đặt

### Bước 1: Chuẩn bị môi trường
1. **Tải và cài đặt XAMPP:**
   - Tải từ: https://www.apachefriends.org/
   - Cài đặt vào thư mục mặc định: `C:\xampp`

2. **Khởi động XAMPP:**
   - Mở XAMPP Control Panel
   - Start **Apache** và **MySQL**

### Bước 2: Tạo database
1. **Vào phpMyAdmin:**
   - Mở trình duyệt: http://localhost/phpmyadmin
   - Đăng nhập với user: `root`, password: (để trống)

2. **Tạo database:**
   ```sql
   CREATE DATABASE ql_drl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import dữ liệu:**
   - Chọn database `ql_drl`
   - Click tab "Import"
   - Chọn file `drl_super_simple.sql`
   - Click "Go"

### Bước 3: Cài đặt source code
1. **Copy source code:**
   ```
   Copy toàn bộ file vào: C:\xampp\htdocs\conductScore_system\
   ```

2. **Kiểm tra cấu trúc file:**
   ```
   conductScore_system/
   ├── config.php          (Kết nối database)
   ├── login.php           (Trang đăng nhập)
   ├── index.php           (Trang chủ)
   ├── students.php        (Quản lý sinh viên)
   ├── evaluations.php     (Quản lý điểm rèn luyện)
   ├── style.css           (CSS styling)
   ├── drl_super_simple.sql (Database structure)
   └── README.md           (File này)
   ```

### Bước 4: Tạo dữ liệu test
1. **Tạo tài khoản admin:**
   ```sql
   INSERT INTO users (email, password, full_name, role) 
   VALUES ('admin@test.com', '123456', 'Administrator', 'admin');
   ```

2. **Tạo dữ liệu mẫu:** (Tùy chọn)
   ```sql
   -- Tạo khoa
   INSERT INTO faculties (code, name) VALUES 
   ('CNTT', 'Công nghệ thông tin'),
   ('KT', 'Kinh tế');
   
   -- Tạo lớp học
   INSERT INTO classes (faculty_id, code, name) VALUES 
   (1, 'CNTT01', 'Công nghệ thông tin 01'),
   (1, 'CNTT02', 'Công nghệ thông tin 02');
   
   -- Tạo sinh viên
   INSERT INTO students (class_id, student_code, full_name, email) VALUES 
   (1, 'SV001', 'Nguyễn Văn A', 'sv001@example.com'),
   (1, 'SV002', 'Trần Thị B', 'sv002@example.com'),
   (2, 'SV003', 'Lê Văn C', 'sv003@example.com');
   
   -- Tạo kỳ học
   INSERT INTO terms (academic_year, term_no, status, start_date, end_date) VALUES 
   ('2024-2025', 1, 'open', '2024-09-01', '2024-12-31'),
   ('2024-2025', 2, 'upcoming', '2025-01-01', '2025-05-31');
   
   -- Tạo tiêu chí đánh giá
   INSERT INTO criteria (name, max_point, order_no) VALUES 
   ('Ý thức học tập', 25, 1),
   ('Ý thức kỷ luật', 25, 2),
   ('Hoạt động tập thể', 20, 3),
   ('Đời sống sinh hoạt', 20, 4),
   ('Các hoạt động khác', 10, 5);
   ```

### Bước 5: Truy cập hệ thống
1. **Mở trình duyệt:**
   ```
   http://localhost/conductScore_system/login.php
   ```

2. **Đăng nhập:**
   - Email: `admin@test.com`
   - Password: `123456`

## 📁 Cấu trúc dự án

### Files chính:
| File | Mô tả | Độ khó |
|------|-------|--------|
| `config.php` | Kết nối database | ⭐ |
| `login.php` | Xử lý đăng nhập | ⭐⭐ |
| `index.php` | Trang chủ, dashboard | ⭐⭐ |
| `students.php` | Quản lý sinh viên | ⭐⭐⭐ |
| `evaluations.php` | Quản lý điểm rèn luyện | ⭐⭐⭐⭐ |
| `style.css` | Giao diện CSS | ⭐⭐ |

### Database tables:
- `users` - Tài khoản đăng nhập
- `students` - Thông tin sinh viên
- `lecturers` - Thông tin giảng viên
- `classes` - Lớp học
- `faculties` - Khoa
- `terms` - Kỳ học
- `criteria` - Tiêu chí đánh giá
- `evaluations` - Đánh giá điểm rèn luyện
- `evaluation_items` - Chi tiết điểm từng tiêu chí

## 📚 Hướng dẫn sử dụng

### 1. Đăng nhập hệ thống
- Truy cập: `http://localhost/conductScore_system/login.php`
- Nhập email và mật khẩu
- Hệ thống sẽ kiểm tra trong database và tạo session

### 2. Trang chủ (Dashboard)
- Hiển thị thống kê tổng quan
- Menu điều hướng các chức năng
- Thông tin user đang đăng nhập

### 3. Quản lý sinh viên
- Xem danh sách tất cả sinh viên
- Tìm kiếm theo tên, mã sinh viên, email
- Lọc theo lớp học
- Hiển thị thông tin lớp và khoa

### 4. Quản lý điểm rèn luyện
- Chọn kỳ học cần đánh giá
- Tìm và chọn sinh viên
- Nhập điểm cho từng tiêu chí
- Lưu điểm vào database

### 5. Đăng xuất
- Click "Đăng xuất" trên header
- Hệ thống xóa session và về trang login

## 🔍 Giải thích code cho người mới

### 1. Kết nối Database (`config.php`)
```php
// Tạo kết nối PDO
$pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
```
**Giải thích:** PDO là cách an toàn nhất để kết nối MySQL trong PHP.

### 2. Session Management
```php
session_start();                    // Bắt đầu session
$_SESSION['user_id'] = $user_id;    // Lưu thông tin vào session
unset($_SESSION['user_id']);        // Xóa session
```
**Giải thích:** Session giúp lưu thông tin user giữa các trang.

### 3. Prepared Statements
```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
```
**Giải thích:** Prepared statements bảo vệ khỏi SQL injection.

### 4. HTML Form Processing
```php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    // Xử lý dữ liệu form...
}
```
**Giải thích:** Kiểm tra method POST và lấy dữ liệu từ form.

### 5. Include Files
```php
require_once 'config.php';  // Include file bắt buộc
include 'header.php';       // Include file tùy chọn
```
**Giải thích:** Tái sử dụng code bằng cách include file khác.

### 6. Redirects
```php
header('Location: index.php');
exit;
```
**Giải thích:** Chuyển hướng user sang trang khác.

## 🛠 Troubleshooting

### Lỗi thường gặp:

#### 1. "This site can't be reached"
**Nguyên nhân:** Apache chưa start
**Giải pháp:** 
- Mở XAMPP Control Panel
- Click "Start" cho Apache

#### 2. "Access denied for user 'root'"
**Nguyên nhân:** Sai thông tin database
**Giải pháp:**
- Kiểm tra file `config.php`
- Đảm bảo username: `root`, password: rỗng

#### 3. "Table doesn't exist"
**Nguyên nhân:** Chưa import database
**Giải pháp:**
- Vào phpMyAdmin
- Import file `drl_super_simple.sql`

#### 4. "Headers already sent"
**Nguyên nhân:** Có space/text trước `<?php`
**Giải pháp:**
- Kiểm tra file PHP không có ký tự nào trước `<?php`

#### 5. CSS không load
**Nguyên nhân:** Đường dẫn file CSS sai
**Giải pháp:**
- Kiểm tra file `style.css` có trong thư mục không
- Kiểm tra đường dẫn trong HTML

#### 6. "Call to undefined function"
**Nguyên nhân:** Thiếu extension PHP
**Giải pháp:**
- Kiểm tra php.ini
- Enable extension cần thiết (mysqli, pdo_mysql)

## 🎯 Bài tập tự luyện

### Level 1 - Cơ bản:
1. Thay đổi màu sắc giao diện trong `style.css`
2. Thêm field "số điện thoại" cho sinh viên
3. Tạo trang "Thông tin cá nhân"

### Level 2 - Trung bình:
1. Thêm chức năng thêm/sửa/xóa sinh viên
2. Tạo trang báo cáo điểm rèn luyện
3. Thêm validation form

### Level 3 - Nâng cao:
1. Thêm chức năng upload file minh chứng
2. Tạo API trả về JSON
3. Thêm phân quyền chi tiết

## 📞 Hỗ trợ

Nếu gặp khó khăn, bạn có thể:
1. Đọc lại README này
2. Xem comment trong code
3. Google lỗi cụ thể
4. Hỏi thầy cô hoặc bạn bè

## 📝 Ghi chú quan trọng

### Bảo mật:
- **Đây chỉ là dự án học tập**, không dùng cho production
- Password được lưu plain text (không an toàn)
- Cần thêm validation và sanitization

### Cải tiến có thể:
- Hash password bằng `password_hash()`
- Thêm CSRF protection
- Thêm input validation
- Sử dụng framework (Laravel, CodeIgniter)
- Tạo API RESTful

---

**🎉 Chúc bạn học tập tốt và code vui vẻ!**

> Hãy nhớ: "Học lập trình cần kiên nhẫn và thực hành nhiều" 💪