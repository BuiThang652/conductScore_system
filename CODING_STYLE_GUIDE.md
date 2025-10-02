# 🎯 CODING STYLE GUIDE - Hướng dẫn viết code chuẩn

> **File này dành cho AI Copilot và developers**  
> Đọc kỹ trước khi code để đảm bảo consistency trong dự án

## 📌 THÔNG TIN DỰ ÁN

**Dự án:** Hệ thống quản lý điểm rèn luyện  
**Ngôn ngữ:** PHP thuần + MySQL + CSS thuần  
**Đối tượng:** Học sinh mới bắt đầu học PHP  
**Mục tiêu:** Code đơn giản, dễ hiểu, có nhiều comment giải thích

---

## 🔧 QUY TẮC VIẾT CODE

### 1. COMMENT VÀ DOCUMENTATION

#### ✅ BẮT ĐẦU MỖI FILE PHP:
```php
<?php
/**
 * TÊN CHỨC NĂNG FILE - VIẾT HOA, NGẮN GỌN
 * 
 * Mô tả chi tiết chức năng:
 * - Chức năng chính 1
 * - Chức năng chính 2
 * - Lưu ý đặc biệt (nếu có)
 */
```

#### ✅ COMMENT SECTIONS TRONG CODE:
```php
// 1. PHẦN MÔ TẢ CHỨC NĂNG (viết hoa, đánh số thứ tự)
// 2. PHẦN KHÁC (mỗi section quan trọng có số)

// XỬ LÝ FORM ĐĂNG NHẬP (sub-section không cần số)
// TRUY VẤN DATABASE (sub-section)
```

#### ✅ COMMENT INLINE CHO BIẾN QUAN TRỌNG:
```php
$db_host = 'localhost';        // Máy chủ database (localhost = máy tính của bạn)
$db_username = 'root';         // Tên đăng nhập MySQL
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Báo lỗi chi tiết
```

#### ✅ KẾT THÚC MỖI FILE:
```php
/**
 * GIẢI THÍCH CODE CHO NGƯỜI MỚI:
 * 
 * - Khái niệm PHP 1: Giải thích đơn giản, dễ hiểu
 * - Khái niệm PHP 2: Tập trung vào điều người mới cần biết
 * - Khái niệm PHP 3: Tránh thuật ngữ phức tạp
 */
?>
```

### 2. QUY TẮC ĐẶT TÊN

#### ✅ BIẾN VÀ FUNCTION:
```php
// ĐÚNG - snake_case, mô tả chính xác
$user_name = '';
$total_students = 0;
$error_message = '';
$evaluation_items = [];

// SAI - không rõ nghĩa
$n = '';
$data = [];
$temp = '';
```

#### ✅ FILE PHP:
```php
// ĐÚNG - lowercase, gạch dưới nếu cần
config.php
login.php  
students.php
evaluations.php

// SAI - CamelCase hoặc space
Config.php
Login Page.php
```

#### ✅ CSS CLASSES:
```css
/* ĐÚNG - kebab-case */
.student-card
.btn-primary
.nav-menu
.error-message

/* SAI - snake_case hoặc camelCase */
.student_card
.btnPrimary
```

### 3. XỬ LÝ DATABASE

#### ✅ KẾT NỐI DATABASE:
```php
try {
    // PDO là cách an toàn nhất để kết nối MySQL trong PHP
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", 
        $db_username, 
        $db_password
    );
    
    // Thiết lập chế độ báo lỗi (giúp debug dễ hơn)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // Nếu kết nối thất bại thì hiện lỗi
    die("Lỗi kết nối database: " . $e->getMessage());
}
```

#### ✅ PREPARED STATEMENTS (BẮT BUỘC):
```php
// ĐÚNG - An toàn, tránh SQL injection
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// SAI - Dễ bị SQL injection
$sql = "SELECT * FROM users WHERE email = '$email'";
$result = $pdo->query($sql);
```

#### ✅ ERROR HANDLING:
```php
// LUÔN có try/catch cho database operations
try {
    $stmt = $pdo->prepare("SELECT ...");
    $stmt->execute([...]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn database: " . $e->getMessage();
}
```

### 4. BẢO MẬT CƠ BẢN

#### ✅ OUTPUT SECURITY:
```php
// LUÔN dùng htmlspecialchars cho output
echo htmlspecialchars($user_name);
echo htmlspecialchars($student['full_name']);
```

#### ✅ SESSION MANAGEMENT:
```php
// Kiểm tra đăng nhập ở đầu mỗi trang bảo vệ
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
```

#### ✅ FORM VALIDATION:
```php
// Kiểm tra và làm sạch input
$email = trim($_POST['email']);
if (empty($email)) {
    $error_message = 'Email không được để trống';
}
```

### 5. CẤU TRÚC FILE PHP

#### ✅ TEMPLATE CHUẨN:
```php
<?php
/**
 * MÔ TẢ CHỨC NĂNG - FILENAME.PHP
 */

// Bắt đầu session (nếu cần)
session_start();

// Include file kết nối database
require_once 'config.php';

// KIỂM TRA ĐĂNG NHẬP (nếu cần)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// KHAI BÁO BIẾN VÀ KHỞI TẠO
$variable_name = '';
$error_message = '';
$success_message = '';

// XỬ LÝ FORM (nếu có)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Logic xử lý form
        
    } catch (Exception $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// TRUY VẤN DATABASE
try {
    $stmt = $pdo->prepare("SELECT ...");
    $stmt->execute([...]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi database: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tên trang - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header với menu navigation -->
    <!-- Main content -->
    <!-- Footer -->
</body>
</html>

<?php
/**
 * GIẢI THÍCH CODE CHO NGƯỜI MỚI:
 * 
 * - Giải thích các khái niệm PHP quan trọng
 * - Tập trung vào điều người mới cần hiểu
 */
?>
```

---

## 🎨 CSS VÀ GIAO DIỆN

### 1. RESPONSIVE DESIGN
```css
/* Mobile-first approach */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Desktop */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
```

### 2. COLOR SCHEME
```css
/* Màu sắc chính - nhẹ nhàng, thân thiện */
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --success-color: #28a745;
    --error-color: #dc3545;
    --background-color: #f8f9fa;
}
```

### 3. TYPOGRAPHY
```css
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    color: #333;
}
```

---

## 🗄️ DATABASE CONVENTIONS

### 1. NAMING
```sql
-- Tên bảng: tiếng Anh, số ít
users, students, evaluations, terms

-- Tên cột: snake_case
user_id, full_name, created_at, updated_at

-- Primary key: luôn là 'id'
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT

-- Foreign key: [table]_id
user_id, student_id, class_id
```

### 2. TIMESTAMPS
```sql
-- Luôn có timestamps
created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### 3. CHARSET
```sql
-- Luôn dùng UTF-8
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

---

## 📝 HTML STRUCTURE

### 1. LAYOUT CƠ BẢN
```html
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tên trang - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="container">
            <h1>Hệ thống quản lý điểm rèn luyện</h1>
            <div class="user-info">
                <!-- User info và logout -->
            </div>
        </div>
    </header>

    <!-- NAVIGATION -->
    <nav class="nav-menu">
        <div class="container">
            <ul class="menu-list">
                <!-- Menu items -->
            </ul>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="container">
            <!-- Page content -->
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Hệ thống quản lý điểm rèn luyện.</p>
        </div>
    </footer>
</body>
</html>
```

---

## 🌟 UX/UI GUIDELINES

### 1. THÔNG BÁO
```php
// Success message
<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        ✅ <?php echo $success_message; ?>
    </div>
<?php endif; ?>

// Error message  
<?php if (!empty($error_message)): ?>
    <div class="alert alert-error">
        ❌ <?php echo $error_message; ?>
    </div>
<?php endif; ?>
```

### 2. EMOJI USAGE
- 🎓 Cho tiêu đề chính
- 📊 Cho thống kê/điểm số
- 👨‍🎓 Cho sinh viên  
- 👨‍🏫 Cho giảng viên
- ✅ Cho thành công
- ❌ Cho lỗi
- 🔍 Cho tìm kiếm
- 📝 Cho form input

### 3. FORM DESIGN
```html
<div class="form-group">
    <label for="field_name">📧 Tên field:</label>
    <input type="text" id="field_name" name="field_name" required 
           placeholder="Placeholder text...">
</div>
```

---

## 🚨 NHỮNG ĐIỀU TUYỆT ĐỐI TRÁNH

### ❌ KHÔNG BAO GIỜ:
1. **Viết SQL trực tiếp** (phải dùng prepared statements)
2. **Quên htmlspecialchars()** khi output
3. **Không có try/catch** cho database
4. **Tên biến không rõ nghĩa** ($a, $temp, $data)
5. **Code không có comment** giải thích
6. **Sử dụng framework phức tạp** (Laravel, Symfony)
7. **OOP trong dự án này** (chỉ dùng procedural PHP)

### ❌ TRÁNH:
1. Thuật ngữ kỹ thuật khó hiểu trong comment
2. Function/class phức tạp
3. Code quá nhiều trong 1 file
4. CSS inline trong HTML
5. JavaScript phức tạp

---

## ✅ CHECKLIST TRƯỚC KHI COMMIT

### Code Quality:
- [ ] Mỗi file có header comment mô tả chức năng
- [ ] Code có comment giải thích các phần quan trọng  
- [ ] Cuối file có khối "GIẢI THÍCH CHO NGƯỜI MỚI"
- [ ] Tên biến rõ ràng, mô tả đúng chức năng
- [ ] Database operations có try/catch
- [ ] Output có htmlspecialchars()

### Security:
- [ ] Dùng prepared statements cho SQL
- [ ] Session được kiểm tra đúng cách
- [ ] Input được validate
- [ ] Error messages thân thiện với user

### UX/UI:
- [ ] Responsive trên mobile
- [ ] Có thông báo success/error  
- [ ] Form validation rõ ràng
- [ ] Giao diện thống nhất với các trang khác

### Documentation:
- [ ] README.md được cập nhật (nếu có thay đổi lớn)
- [ ] Comment code đầy đủ
- [ ] Ví dụ sử dụng trong comment (nếu cần)

---

## 📞 KHI CẦN HỖ TRỢ

1. **Đọc file có sẵn** tương tự để tham khảo pattern
2. **Xem comment** trong code để hiểu logic
3. **Test trên localhost** trước khi commit  
4. **Google error message** cụ thể nếu gặp lỗi
5. **So sánh với template** trong file này

---

## 🎯 MỤC TIÊU CUỐI CÙNG

> **Code tốt là code mà học sinh mới bắt đầu đọc vào cũng hiểu được!**

**Nhớ:** Dự án này dành cho người **mới học PHP**, không phải để khoe kỹ thuật. Ưu tiên **đơn giản và dễ hiểu** hơn **hiệu năng và tối ưu**.

---

*Cập nhật lần cuối: 30/09/2025*  
*Tác giả: GitHub Copilot for conductScore_system*