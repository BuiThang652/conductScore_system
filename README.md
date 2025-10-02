# 🎓 HỆ THỐNG QUẢN LÝ ĐIỂM RÈN LUYỆN

## 📖 MÔ TẢ DỰ ÁN

Hệ thống quản lý điểm rèn luyện sinh viên được phát triển bằng **PHP 8** + **MySQL**, hỗ trợ quản lý và đánh giá điểm rèn luyện cho các trường đại học/cao đẳng.

## ✨ TÍNH NĂNG CHÍNH

### 👑 Admin (Quản trị viên)
- ⚙️ Quản lý users (tạo/sửa/xóa/reset password)
- 🏛️ Quản lý khoa, lớp học
- 📅 Quản lý kỳ học
- 📋 Thiết lập tiêu chí đánh giá
- 📊 Xem thống kê tổng quan

### 👨‍🏫 Lecturer (Giảng viên)
- 📝 Đánh giá điểm rèn luyện sinh viên
- 👀 Xem danh sách đánh giá của lớp
- 📈 Theo dõi tiến độ đánh giá
- 📊 Xem báo cáo lớp

### 👨‍🎓 Student (Sinh viên)  
- 📝 Tự đánh giá điểm rèn luyện
- 👀 Xem kết quả đánh giá của bản thân
- 📄 So sánh điểm tự đánh giá vs giảng viên đánh giá
- 📎 Thêm ghi chú, minh chứng

## 🚀 HƯỚNG DẪN CÀI ĐẶT

### ⚡ Cài đặt nhanh (5 phút)
👉 **[Xem QUICK_START.md](QUICK_START.md)**

### 📋 Hướng dẫn chi tiết
👉 **[Xem HUONG_DAN_CAI_DAT.md](HUONG_DAN_CAI_DAT.md)**

## 🔧 YÊU CẦU HỆ THỐNG

- **Web Server:** Apache 2.4+
- **PHP:** 8.0+ (khuyến nghị 8.1+)
- **Database:** MySQL 8.0+ hoặc MariaDB 10.4+
- **Browser:** Chrome, Firefox, Edge (bản mới)

## 🔑 TÀI KHOẢN MẶC ĐỊNH

```
Email: admin@test.com
Password: 123456
Role: Admin
```

## 📁 CẤU TRÚC PROJECT

```
conductScore_system/
├── admin.php              # Trang quản trị hệ thống
├── index.php              # Trang chủ
├── login.php               # Trang đăng nhập
├── students.php            # Trang tự đánh giá sinh viên
├── evaluations.php         # Trang xem kết quả đánh giá
├── lecturer_evaluation.php # Trang đánh giá của giảng viên
├── config.php              # Cấu hình database
├── style.css               # CSS styling
├── drl_super_simple.sql    # Database schema (clean)
├── HUONG_DAN_CAI_DAT.md    # Hướng dẫn cài đặt chi tiết
├── QUICK_START.md          # Hướng dẫn cài đặt nhanh
├── RESET_PASSWORD_GUIDE.md # Hướng dẫn reset password
├── CODING_STYLE_GUIDE.md   # Chuẩn code cho sinh viên
└── README.md               # File này
```

## 💾 DATABASE

### Schema chính:
- `users` - Tài khoản người dùng (admin, lecturer, student)
- `faculties` - Khoa
- `classes` - Lớp học  
- `students` - Sinh viên
- `lecturers` - Giảng viên
- `terms` - Kỳ học
- `criteria` - Tiêu chí đánh giá (có cấu trúc phân cấp)
- `evaluations` - Đánh giá
- `evaluation_items` - Chi tiết điểm từng tiêu chí

### Import database:
```sql
-- Tạo database
CREATE DATABASE conductscore_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema
mysql -u root -p conductscore_db < drl_super_simple.sql
```

## 🛡️ BẢO MẬT

- ✅ **Password hashing:** MD5 (có thể nâng cấp lên bcrypt)
- ✅ **SQL Injection:** Sử dụng Prepared Statements
- ✅ **XSS Protection:** htmlspecialchars() cho output
- ✅ **Session Management:** Secure session handling
- ✅ **Role-based Access:** Phân quyền theo vai trò

## 🔄 QUY TRÌNH SỬ DỤNG

### 1. Thiết lập ban đầu (Admin):
1. Đăng nhập với tài khoản admin
2. Tạo khoa → Tạo users → Tạo lớp học
3. Phân công sinh viên vào lớp
4. Tạo kỳ học → Thiết lập tiêu chí đánh giá

### 2. Chu kỳ đánh giá:
1. **Sinh viên:** Tự đánh giá điểm rèn luyện
2. **Giảng viên:** Xem và đánh giá lại điểm sinh viên
3. **Admin:** Theo dõi và quản lý tổng thể

## 🐛 KHẮC PHỤC SỰ CỐ

### Lỗi thường gặp:
- **Kết nối DB:** Kiểm tra config.php và MySQL service
- **404 Error:** Kiểm tra đường dẫn trong htdocs
- **Login failed:** Dùng đúng email/password mặc định
- **Port conflict:** Đổi Apache port trong XAMPP

👉 **[Xem chi tiết HUONG_DAN_CAI_DAT.md](HUONG_DAN_CAI_DAT.md#6-khắc-phục-sự-cố)**

## 📚 TÀI LIỆU

- **[QUICK_START.md](QUICK_START.md)** - Hướng dẫn cài đặt nhanh 5 phút
- **[HUONG_DAN_CAI_DAT.md](HUONG_DAN_CAI_DAT.md)** - Hướng dẫn chi tiết từng bước
- **[RESET_PASSWORD_GUIDE.md](RESET_PASSWORD_GUIDE.md)** - Hướng dẫn reset password
- **[CODING_STYLE_GUIDE.md](CODING_STYLE_GUIDE.md)** - Chuẩn code giáo dục

## 🚀 PHÁT TRIỂN THÊM

### Tính năng có thể mở rộng:
- 📱 Responsive mobile design
- 📊 Export báo cáo Excel/PDF
- 📎 Upload minh chứng file
- 📧 Email notification
- 🔐 Two-factor authentication
- 📈 Dashboard analytics nâng cao

## 🤝 ĐÓNG GÓP

1. Fork repository
2. Tạo feature branch: `git checkout -b feature/AmazingFeature`
3. Commit changes: `git commit -m 'Add some AmazingFeature'`
4. Push to branch: `git push origin feature/AmazingFeature`
5. Tạo Pull Request

## 📄 LICENSE

Dự án này được phát triển cho mục đích học tập và giáo dục.

## 📞 HỖ TRỢ

- **GitHub Issues:** [Tạo issue mới](https://github.com/BuiThang652/conductScore_system/issues)
- **Email:** buithang652@gmail.com

---

## 🎯 BẮT ĐẦU NGAY

```bash
# 1. Clone repository
git clone https://github.com/BuiThang652/conductScore_system.git

# 2. Copy vào htdocs
cp -r conductScore_system C:/xampp/htdocs/

# 3. Tạo database và import SQL
# 4. Truy cập: http://localhost/conductScore_system
# 5. Đăng nhập: admin@test.com / 123456
```

🎉 **Chúc bạn sử dụng thành công!**

---
*Phát triển bởi: BuiThang652 | Cập nhật: 02/10/2025*