<?php
/**
 * TRANG CHỦ - index.php
 * 
 * Chức năng:
 * - Hiển thị trang chính sau khi user đăng nhập
 * - Hiển thị thông tin user (tên, role)
 * - Hiển thị menu điều hướng theo role
 * - Hiển thị thống kê cơ bản
 */

// 1. BẮT ĐẦU SESSION
session_start();

// 2. KẾT NỐI DATABASE
require_once 'config.php';

// 3. KIỂM TRA ĐĂNG NHẬP
if (!isset($_SESSION['user_id'])) {
    // Nếu chưa đăng nhập thì chuyển về trang login
    header('Location: login.php');
    exit;
}

// 4. LẤY THÔNG TIN USER TỪ SESSION
$user_name = $_SESSION['user_name'];    // Tên user đã đăng nhập
$user_role = $_SESSION['user_role'];    // Role: student, lecturer, admin
$user_email = $_SESSION['user_email'];  // Email của user

// 5. XỬ LÝ ĐĂNG XUẤT
if (isset($_GET['logout'])) {
    // Xóa hết session
    session_destroy();
    // Chuyển về trang login
    header('Location: login.php');
    exit;
}

// LẤY THỐNG KÊ CỞ BẢN TỪ DATABASE
try {
    // Đếm số sinh viên
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM students");
    $total_students = $stmt->fetch()['total_students'];
    
    // Đếm số giảng viên
    $stmt = $pdo->query("SELECT COUNT(*) as total_lecturers FROM lecturers");
    $total_lecturers = $stmt->fetch()['total_lecturers'];
    
    // Đếm số lớp
    $stmt = $pdo->query("SELECT COUNT(*) as total_classes FROM classes");
    $total_classes = $stmt->fetch()['total_classes'];
    
    // Đếm số đánh giá
    $stmt = $pdo->query("SELECT COUNT(*) as total_evaluations FROM evaluations");
    $total_evaluations = $stmt->fetch()['total_evaluations'];
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn database: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="container">
            <h1>Hệ thống quản lý điểm rèn luyện</h1>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
                <a href="?logout=1" class="btn-logout">Đăng xuất</a>
            </div>
        </div>
    </header>

    <!-- MENU ĐIỀU HƯỚNG -->
    <nav class="nav-menu">
        <div class="container">
            <ul class="menu-list">
                <li><a href="index.php" class="active">Trang chủ</a></li>
                <?php if ($user_role === 'student'): ?>
                    <li><a href="students.php">Tự đánh giá</a></li>
                    <li><a href="evaluations.php">Xem kết quả</a></li>
                <?php else: ?>
                    <li><a href="evaluations.php">Điểm rèn luyện</a></li>
                    <li><a href="lecturer_evaluation.php">Đánh giá sinh viên</a></li>
                    <?php if ($user_role === 'admin'): ?>
                        <li><a href="admin.php">Quản trị</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- NỘI DUNG CHÍNH -->
    <main class="main-content">
        <div class="container">
            
            <!-- THÔNG TIN USER -->
            <section class="welcome-section">
                <h2>Chào mừng đến với hệ thống quản lý điểm rèn luyện!</h2>
                <div class="user-details">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?></p>
                    <p><strong>Vai trò:</strong> 
                        <?php 
                        switch($user_role) {
                            case 'admin': echo 'Quản trị viên hệ thống'; break;
                            case 'lecturer': echo 'Giảng viên'; break;
                            case 'student': echo 'Sinh viên'; break;
                            default: echo ucfirst($user_role);
                        }
                        ?>
                    </p>
                    <p><strong>Thời gian đăng nhập:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
                </div>
            </section>

            <!-- THỐNG KÊ TỔNG QUAN -->
            <section class="statistics-section">
                <h3>Thống kê tổng quan</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👨‍🎓</div>
                        <div class="stat-info">
                            <h4>Sinh viên</h4>
                            <p class="stat-number"><?php echo number_format($total_students); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">👨‍🏫</div>
                        <div class="stat-info">
                            <h4>Giảng viên</h4>
                            <p class="stat-number"><?php echo number_format($total_lecturers); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🏫</div>
                        <div class="stat-info">
                            <h4>Lớp học</h4>
                            <p class="stat-number"><?php echo number_format($total_classes); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <h4>Đánh giá</h4>
                            <p class="stat-number"><?php echo number_format($total_evaluations); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- MENU CHỨC NĂNG -->
            <section class="features-section">
                <h3>Chức năng chính</h3>
                <div class="features-grid">
                    <a href="students.php" class="feature-card">
                        <div class="feature-icon">👨‍🎓</div>
                        <h4>Tự đánh giá</h4>
                        <p>Thực hiện tự đánh giá bản thân</p>
                    </a>
                    
                    <a href="evaluations.php" class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h4>Xem kết quả</h4>
                        <p>Xem kết quả đánh giá của bản thân</p>
                    </a>
                    
                    <?php if ($user_role == 'admin'): ?>
                    <a href="admin.php" class="feature-card">
                        <div class="feature-icon">⚙️</div>
                        <h4>Quản trị hệ thống</h4>
                        <p>Quản lý tài khoản, cấu hình hệ thống</p>
                    </a>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Hệ thống quản lý điểm rèn luyện. Được phát triển cho mục đích học tập.</p>
        </div>
    </footer>
</body>
</html>

<?php
/**
 * GIẢI THÍCH CODE CHO NGƯỜI MỚI:
 * 
 * 1. session_start(): Tiếp tục session từ trang login
 * 2. isset($_SESSION['user_id']): Kiểm tra đã đăng nhập chưa
 * 3. COUNT(*): Đếm số lượng record trong database
 * 4. htmlspecialchars(): Bảo vệ output khỏi XSS
 * 5. switch/case: Hiển thị text khác nhau theo role
 * 6. $_GET['logout']: Xử lý đăng xuất qua URL parameter
 */
?>