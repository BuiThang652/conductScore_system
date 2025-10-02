# 📋 HƯỚNG DẪN CÀI ĐẶT VÀ SỬ DỤNG HỆ THỐNG QUẢN LÝ ĐIỂM RÈN LUYỆN

## 🎯 MỤC LỤC
1. [Cài đặt XAMPP](#1-cài-đặt-xampp)
2. [Thiết lập Database](#2-thiết-lập-database)
3. [Chạy hệ thống](#3-chạy-hệ-thống)
4. [Đăng nhập và sử dụng](#4-đăng-nhập-và-sử-dụng)
5. [Hướng dẫn từng role](#5-hướng-dẫn-từng-role)
6. [Khắc phục sự cố](#6-khắc-phục-sự-cố)

---

## 1. 🔧 CÀI ĐẶT XAMPP

### Bước 1: Tải XAMPP
1. Truy cập: https://www.apachefriends.org/download.html
2. Chọn **Windows** (hoặc OS phù hợp)
3. Tải bản **PHP 8.x** trở lên
4. Chạy file `.exe` vừa tải

### Bước 2: Cài đặt XAMPP
1. **Chạy installer** với quyền Administrator
2. **Chọn components cần thiết:**
   - ✅ Apache
   - ✅ MySQL
   - ✅ PHP
   - ✅ phpMyAdmin
   - ❌ Các thành phần khác (không cần)

3. **Chọn thư mục cài đặt:** `C:\xampp` (mặc định)
4. **Bỏ tick** "Learn more about Bitnami"
5. Click **Next** → **Install** → **Finish**

### Bước 3: Khởi động XAMPP
1. Mở **XAMPP Control Panel**
2. Click **Start** cho:
   - 🟢 **Apache** 
   - 🟢 **MySQL**
3. Đợi đến khi trạng thái chuyển thành màu xanh

```
Apache Running on Port 80
MySQL Running on Port 3306
```

---

## 2. 💾 THIẾT LẬP DATABASE

### Bước 1: Tạo Database
1. Mở trình duyệt → truy cập: http://localhost/phpmyadmin
2. Click tab **"Databases"**
3. Tạo database mới:
   - **Database name:** `conductscore_db`
   - **Collation:** `utf8mb4_unicode_ci`
4. Click **"Create"**

### Bước 2: Import Database Schema
1. Trong phpMyAdmin, chọn database `conductscore_db`
2. Click tab **"Import"**
3. Click **"Choose File"** → chọn file `drl_super_simple.sql`
4. Đảm bảo **Format:** `SQL`
5. Click **"Go"** → đợi import hoàn tất

### Bước 3: Kiểm tra dữ liệu
```sql
-- Kiểm tra bảng đã tạo
SHOW TABLES;

-- Kiểm tra tài khoản admin
SELECT * FROM users WHERE role = 'admin';
```

**Kết quả mong đợi:**
- 11 tables được tạo
- 1 tài khoản admin: `admin@test.com`

---

## 3. 🚀 CHẠY HỆ THỐNG

### Bước 1: Copy source code
1. Copy thư mục `conductScore_system` vào `C:\xampp\htdocs\`
2. Đường dẫn cuối cùng: `C:\xampp\htdocs\conductScore_system\`

### Bước 2: Cấu hình kết nối database
1. Mở file `config.php`
2. Kiểm tra thông số kết nối:

```php
$db_host = 'localhost';        // ✅ Giữ nguyên
$db_username = 'root';         // ✅ Giữ nguyên  
$db_password = '';             // ✅ Giữ nguyên (trống)
$db_name = 'conductscore_db';  // ✅ Tên database vừa tạo
```

### Bước 3: Truy cập hệ thống
1. Mở trình duyệt → truy cập: http://localhost/conductScore_system
2. Nếu thành công → hiện trang đăng nhập
3. Nếu lỗi → xem phần [Khắc phục sự cố](#6-khắc-phục-sự-cố)

---

## 4. 🔐 ĐĂNG NHẬP VÀ SỬ DỤNG

### Tài khoản Admin duy nhất:
```
Email: admin@test.com
Password: 123456
```

### Bước đăng nhập:
1. Truy cập: http://localhost/conductScore_system
2. Nhập email: `admin@test.com`
3. Nhập password: `123456`
4. Click **"Đăng nhập"**

### Sau khi đăng nhập thành công:
- ✅ Hiển thị trang chủ với menu điều hướng
- ✅ Thông tin user: **Admin Test** ở góc phải
- ✅ Menu gồm: Trang chủ | Điểm rèn luyện | Đánh giá sinh viên | Quản trị

---

## 5. 👥 HƯỚNG DẪN TỪNG ROLE

### 🔱 ADMIN - Quản trị viên hệ thống

#### A. Thiết lập ban đầu (BẮT BUỘC):

**Bước 1: Tạo khoa**
1. Vào **"Quản trị"** → tab **"🏛️ Quản lý khoa"**
2. Nhập thông tin khoa:
   - Mã khoa: `CNTT`
   - Tên khoa: `Công nghệ thông tin`
3. Click **"Tạo khoa"**
4. Lặp lại cho các khoa khác

**Bước 2: Tạo tài khoản users**
1. Tab **"👥 Người dùng"**
2. Tạo tài khoản giảng viên:
   - Email: `lecturer@test.com`
   - Password: `123456`
   - Họ tên: `Nguyễn Văn A`
   - Vai trò: `Lecturer`
3. Tạo tài khoản sinh viên:
   - Email: `student@test.com`  
   - Password: `123456`
   - Họ tên: `Trần Thị B`
   - Vai trò: `Student`

**Bước 3: Tạo lớp học**
1. Tab **"🏫 Lớp học"**
2. Tạo lớp:
   - Khoa: Chọn khoa vừa tạo
   - Mã lớp: `CNTT01`
   - Tên lớp: `Công nghệ thông tin 01`
   - GVCN: Chọn giảng viên (tùy chọn)

**Bước 4: Liên kết tài khoản với hồ sơ**
1. Trong **"🏫 Lớp học"** → Click **"👥 Quản lý thành viên"** của lớp
2. Phân công sinh viên vào lớp
3. Liên kết lecturer với giảng viên

**Bước 5: Tạo kỳ học**
1. Tab **"📅 Kỳ học"**
2. Tạo kỳ học:
   - Năm học: `2024-2025`
   - Kỳ: `Kỳ 1`
   - Ngày bắt đầu: `2024-09-01`
   - Ngày kết thúc: `2025-01-15`
   - Trạng thái: `Đang mở`

**Bước 6: Tạo tiêu chí đánh giá**
1. Tab **"📋 Tiêu chí"**
2. Tạo tiêu chí chính:
   - Tên: `Ý thức học tập`
   - Điểm tối đa: `25`
   - Thứ tự: `1`
3. Tạo tiêu chí con:
   - Tiêu chí cha: `Ý thức học tập`
   - Tên: `Tham gia đầy đủ các hoạt động học tập`
   - Điểm tối đa: `10`

#### B. Quản lý hằng ngày:
- 📊 Xem thống kê tổng quan
- 👥 Quản lý users (tạo/sửa/xóa/reset password)
- 🔄 Reset mật khẩu user (nút **"🔑 Reset PW"**)
- 📈 Xem báo cáo đánh giá
- ⚙️ Cấu hình hệ thống

### 👨‍🏫 LECTURER - Giảng viên

#### Đăng nhập:
```
Email: lecturer@test.com (do admin tạo)
Password: 123456
```

#### Chức năng chính:
1. **Xem danh sách đánh giá sinh viên:**
   - Menu **"Điểm rèn luyện"**
   - Lọc theo kỳ học, lớp
   - Tìm kiếm sinh viên

2. **Đánh giá điểm sinh viên:**
   - Menu **"Đánh giá sinh viên"**
   - Chọn kỳ học → chọn sinh viên
   - Nhập điểm cho từng tiêu chí
   - Thêm ghi chú đánh giá
   - Lưu đánh giá

3. **Xem báo cáo lớp:**
   - Tổng hợp điểm lớp
   - So sánh tự đánh giá vs đánh giá GV

### 👨‍🎓 STUDENT - Sinh viên

#### Đăng nhập:
```
Email: student@test.com (do admin tạo)
Password: 123456
```

#### Chức năng chính:
1. **Tự đánh giá điểm rèn luyện:**
   - Menu **"Tự đánh giá"**
   - Chọn kỳ học
   - Nhập điểm cho từng tiêu chí
   - Thêm ghi chú, minh chứng
   - Lưu đánh giá

2. **Xem kết quả đánh giá:**
   - Menu **"Xem kết quả"**
   - So sánh điểm tự đánh giá vs GV đánh giá
   - Xem ghi chú từ giảng viên

---

## 6. 🛠️ KHẮC PHỤC SỰ CỐ

### Lỗi kết nối database:
```
Error: Connection failed: SQLSTATE[HY000] [1049] Unknown database
```
**Giải pháp:**
1. Kiểm tra MySQL đã chạy chưa (XAMPP Control Panel)
2. Kiểm tra tên database trong `config.php`
3. Tạo lại database `conductscore_db`

### Lỗi import SQL:
```
Error: SQL syntax error
```
**Giải pháp:**
1. Sử dụng file `drl_super_simple.sql` (đã được tối ưu)
2. Chọn charset `utf8mb4_unicode_ci`
3. Import từng phần nếu file quá lớn

### Lỗi 404 Not Found:
```
The requested URL was not found
```
**Giải pháp:**
1. Kiểm tra Apache đã chạy chưa
2. Kiểm tra đường dẫn: `C:\xampp\htdocs\conductScore_system\`
3. Truy cập: http://localhost/conductScore_system (không có s cuối)

### Lỗi đăng nhập:
```
Email hoặc mật khẩu không đúng
```
**Giải pháp:**
1. Kiểm tra database đã có user admin chưa:
```sql
SELECT * FROM users WHERE email = 'admin@test.com';
```
2. Reset password nếu cần:
```sql
UPDATE users SET password = 'e10adc3949ba59abbe56e057f20f883e' WHERE email = 'admin@test.com';
```

### Port đã được sử dụng:
```
Port 80 in use by "Unable to open process" with PID 4
```
**Giải pháp:**
1. Đổi port Apache: Config → `httpd.conf` → `Listen 8080`
2. Truy cập: http://localhost:8080/conductScore_system
3. Hoặc tắt các service đang dùng port 80

---

## 📞 HỖ TRỢ

### Thông tin liên hệ:
- **Repository:** https://github.com/BuiThang652/conductScore_system
- **Documentation:** Xem các file `.md` trong project

### Files quan trọng:
- `config.php` - Cấu hình database
- `drl_super_simple.sql` - Database schema
- `RESET_PASSWORD_GUIDE.md` - Hướng dẫn reset password
- `CODING_STYLE_GUIDE.md` - Chuẩn code

### Kiểm tra hệ thống:
```bash
# Kiểm tra PHP version
php -v

# Kiểm tra syntax PHP
php -l index.php

# Kiểm tra MySQL service
net start mysql
```

---

## 🎉 HOÀN TẤT

Sau khi hoàn thành các bước trên, bạn đã có:
- ✅ Hệ thống chạy ổn định trên XAMPP
- ✅ Database được thiết lập đầy đủ
- ✅ Tài khoản admin để quản trị
- ✅ Hiểu cách sử dụng từng chức năng

**Bước tiếp theo:** Bắt đầu tạo dữ liệu thực tế cho trường/tổ chức của bạn!

---
*Phiên bản: 1.0 - Cập nhật: 02/10/2025*