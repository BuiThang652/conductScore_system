<?php
/**
 * TRANG ĐĂNG NHẬP - login.php
 * 
 * Trang này cho phép user nhập email và mật khẩu để đăng nhập
 * Đây là trang đầu tiên user sẽ thấy
 */

// Bắt đầu session (để lưu thông tin đăng nhập)
session_start();

// Include file kết nối database
require_once 'config.php';

// Biến để lưu thông báo lỗi
$error_message = '';

// KIỂM TRA NẾU USER ĐÃ ĐĂNG NHẬP RỒI
if (isset($_SESSION['user_id'])) {
    // Nếu đã đăng nhập rồi thì chuyển về trang chủ
    header('Location: index.php');
    exit;
}

// XỬ LÝ KHI USER CLICK BUTTON ĐĂNG NHẬP
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Kiểm tra có nhập đủ thông tin không
    if (empty($email) || empty($password)) {
        $error_message = 'Vui lòng nhập đầy đủ email và mật khẩu!';
    } else {
        // Tìm user trong database
        try {
            $stmt = $pdo->prepare("SELECT id, email, password, full_name, role FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Kiểm tra password (tạm thời dùng plain text cho đơn giản)
            if ($user && $password === $user['password']) {
                // Đăng nhập thành công - lưu thông tin vào session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Chuyển về trang chủ
                header('Location: index.php');
                exit;
            } else {
                $error_message = 'Email hoặc mật khẩu không đúng!';
            }
        } catch (PDOException $e) {
            $error_message = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>🎓 Hệ thống quản lý điểm rèn luyện</h2>
            <h3>Đăng nhập</h3>
            
            <!-- Hiển thị lỗi nếu có -->
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    ❌ <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- FORM ĐĂNG NHẬP -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">📧 Email:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">🔒 Mật khẩu:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-login">Đăng nhập</button>
            </form>
            
            <!-- HƯỚNG DẪN TEST -->
            <div class="test-info">
                <h4>🧪 Tài khoản test (cho học sinh):</h4>
                <p><strong>Email:</strong> admin@test.com</p>
                <p><strong>Mật khẩu:</strong> 123456</p>
                <p><em>Lưu ý: Cần tạo tài khoản này trong database trước!</em></p>
            </div>
        </div>
    </div>
</body>
</html>

<?php
/**
 * GIẢI THÍCH CODE CHO NGƯỜI MỚI:
 * 
 * 1. session_start(): Bắt đầu session để lưu thông tin đăng nhập
 * 2. $_POST: Lấy dữ liệu từ form khi user submit
 * 3. $pdo->prepare(): Cách an toàn để query database (tránh SQL injection)
 * 4. $_SESSION: Lưu thông tin user sau khi đăng nhập thành công
 * 5. header('Location: ...'): Chuyển hướng sang trang khác
 * 6. htmlspecialchars(): Bảo vệ khỏi XSS attack
 */
?>