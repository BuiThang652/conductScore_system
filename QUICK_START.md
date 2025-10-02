# ⚡ HƯỚNG DẪN NHANH - 5 PHÚT CÀI ĐẶT

## 🎯 CHỈ 5 BƯỚC ĐƠN GIẢN

### 1️⃣ Cài XAMPP
- Tải: https://www.apachefriends.org/download.html
- Cài vào `C:\xampp`
- Khởi động **Apache** + **MySQL**

### 2️⃣ Tạo Database  
- Vào: http://localhost/phpmyadmin
- Tạo database: `conductscore_db`
- Import file: `drl_super_simple.sql`

### 3️⃣ Copy Source Code
- Copy thư mục `conductScore_system` vào `C:\xampp\htdocs\`

### 4️⃣ Truy cập hệ thống
- Vào: http://localhost/conductScore_system
- Đăng nhập: `admin@test.com` / `123456`

### 5️⃣ Thiết lập cơ bản
1. **Tạo khoa** (Quản trị → Quản lý khoa)
2. **Tạo users** (Quản trị → Người dùng)  
3. **Tạo lớp học** (Quản trị → Lớp học)
4. **Tạo kỳ học** (Quản trị → Kỳ học)
5. **Tạo tiêu chí** (Quản trị → Tiêu chí)

## 🔑 TÀI KHOẢN MẶC ĐỊNH

```
Admin: admin@test.com / 123456
```

## 🚨 LỖI THƯỜNG GẶP

| Lỗi | Giải pháp |
|-----|-----------|
| Không kết nối DB | Kiểm tra MySQL chạy chưa |
| 404 Not Found | Kiểm tra đường dẫn `htdocs` |
| Đăng nhập failed | Dùng đúng email/password |
| Port 80 busy | Đổi Apache port 8080 |

## 📋 CHECKLIST HOÀN THÀNH

- [ ] XAMPP cài đặt và chạy
- [ ] Database `conductscore_db` tạo thành công  
- [ ] File SQL import không lỗi
- [ ] Source code trong `htdocs`
- [ ] Đăng nhập admin thành công
- [ ] Tạo được khoa, users, lớp, kỳ học, tiêu chí

✅ **Hoàn tất!** Hệ thống đã sẵn sàng sử dụng!

---
📖 **Hướng dẫn chi tiết:** Xem file `HUONG_DAN_CAI_DAT.md`