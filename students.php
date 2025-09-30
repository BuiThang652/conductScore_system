<?php
/**
 * TRANG QUẢN LÝ SINH VIÊN - students.php
 * 
 * Hiển thị danh sách sinh viên từ database
 * Có chức năng tìm kiếm đơn giản
 */

// Bắt đầu session
session_start();

// Include file kết nối database
require_once 'config.php';

// KIỂM TRA ĐĂNG NHẬP
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lấy thông tin user từ session
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// THAM SỐ TÌM KIẾM
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$class_filter = isset($_GET['class_id']) ? $_GET['class_id'] : '';

// XÂY DỰNG QUERY TÌM SINH VIÊN
$where_conditions = [];
$params = [];

if (!empty($search_keyword)) {
    $where_conditions[] = "(s.full_name LIKE ? OR s.student_code LIKE ? OR s.email LIKE ?)";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
}

if (!empty($class_filter)) {
    $where_conditions[] = "s.class_id = ?";
    $params[] = $class_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // QUERY LẤY DANH SÁCH SINH VIÊN VỚI THÔNG TIN LỚP
    $sql = "
        SELECT 
            s.id,
            s.student_code,
            s.full_name,
            s.email,
            s.created_at,
            c.name as class_name,
            c.code as class_code,
            f.name as faculty_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN faculties f ON c.faculty_id = f.id
        $where_clause
        ORDER BY s.student_code ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ĐẾM TỔNG SỐ SINH VIÊN
    $count_sql = "
        SELECT COUNT(*) as total
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN faculties f ON c.faculty_id = f.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_students = $count_stmt->fetch()['total'];
    
    // LẤY DANH SÁCH LỚP HỌC CHO DROPDOWN
    $classes_stmt = $pdo->query("
        SELECT c.id, c.name, c.code, f.name as faculty_name 
        FROM classes c 
        LEFT JOIN faculties f ON c.faculty_id = f.id 
        ORDER BY c.code
    ");
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn database: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sinh viên - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="container">
            <h1>🎓 Hệ thống quản lý điểm rèn luyện</h1>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
                <a href="?logout=1" class="btn-logout">🚪 Đăng xuất</a>
            </div>
        </div>
    </header>

    <!-- MENU ĐIỀU HƯỚNG -->
    <nav class="nav-menu">
        <div class="container">
            <ul class="menu-list">
                <li><a href="index.php">🏠 Trang chủ</a></li>
                <li><a href="students.php" class="active">👨‍🎓 Sinh viên</a></li>
                <li><a href="evaluations.php">📊 Điểm rèn luyện</a></li>
                <?php if ($user_role == 'admin'): ?>
                <li><a href="admin.php">⚙️ Quản trị</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- NỘI DUNG CHÍNH -->
    <main class="main-content">
        <div class="container">
            
            <!-- TIÊU ĐỀ -->
            <section class="page-header">
                <h2>👨‍🎓 Quản lý sinh viên</h2>
                <p>Danh sách tất cả sinh viên trong hệ thống</p>
            </section>

            <!-- BỘ LỌC TÌM KIẾM -->
            <section class="search-section">
                <form method="GET" action="" class="search-form">
                    <div class="search-row">
                        <div class="search-field">
                            <label for="search">🔍 Tìm kiếm:</label>
                            <input type="text" id="search" name="search" 
                                   placeholder="Nhập tên, mã sinh viên hoặc email..."
                                   value="<?php echo htmlspecialchars($search_keyword); ?>">
                        </div>
                        
                        <div class="search-field">
                            <label for="class_id">🏫 Lớp học:</label>
                            <select id="class_id" name="class_id">
                                <option value="">-- Tất cả lớp --</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" 
                                        <?php echo ($class_filter == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['code'] . ' - ' . $class['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="search-buttons">
                            <button type="submit" class="btn-search">🔍 Tìm kiếm</button>
                            <a href="students.php" class="btn-reset">🔄 Làm mới</a>
                        </div>
                    </div>
                </form>
            </section>

            <!-- KÉT QUẢ TÌM KIẾM -->
            <section class="results-section">
                <div class="results-header">
                    <p>📊 Tìm thấy <strong><?php echo number_format($total_students); ?></strong> sinh viên</p>
                    <?php if (!empty($search_keyword) || !empty($class_filter)): ?>
                    <p class="search-info">
                        🔍 Đang lọc: 
                        <?php if (!empty($search_keyword)): ?>
                            <span class="filter-tag">Từ khóa: "<?php echo htmlspecialchars($search_keyword); ?>"</span>
                        <?php endif; ?>
                        <?php if (!empty($class_filter)): ?>
                            <?php 
                            $selected_class = array_filter($classes, function($c) use ($class_filter) {
                                return $c['id'] == $class_filter;
                            });
                            $selected_class = reset($selected_class);
                            ?>
                            <span class="filter-tag">Lớp: <?php echo htmlspecialchars($selected_class['code']); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- BẢNG DANH SÁCH SINH VIÊN -->
                <?php if (empty($students)): ?>
                    <div class="no-results">
                        <p>😔 Không tìm thấy sinh viên nào phù hợp với điều kiện tìm kiếm.</p>
                        <a href="students.php" class="btn-reset">🔄 Xem tất cả sinh viên</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Mã sinh viên</th>
                                    <th>Họ và tên</th>
                                    <th>Email</th>
                                    <th>Lớp học</th>
                                    <th>Khoa</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td class="student-code">
                                        <strong><?php echo htmlspecialchars($student['student_code']); ?></strong>
                                    </td>
                                    <td class="student-name">
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                    </td>
                                    <td class="student-email">
                                        <?php if (!empty($student['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>">
                                                <?php echo htmlspecialchars($student['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <em>Chưa có email</em>
                                        <?php endif; ?>
                                    </td>
                                    <td class="student-class">
                                        <?php if (!empty($student['class_name'])): ?>
                                            <span class="class-tag">
                                                <?php echo htmlspecialchars($student['class_code']); ?>
                                            </span>
                                            <br>
                                            <small><?php echo htmlspecialchars($student['class_name']); ?></small>
                                        <?php else: ?>
                                            <em>Chưa có lớp</em>
                                        <?php endif; ?>
                                    </td>
                                    <td class="student-faculty">
                                        <?php echo !empty($student['faculty_name']) ? htmlspecialchars($student['faculty_name']) : '-'; ?>
                                    </td>
                                    <td class="created-date">
                                        <?php echo date('d/m/Y', strtotime($student['created_at'])); ?>
                                    </td>
                                    <td class="actions">
                                        <a href="evaluations.php?student_id=<?php echo $student['id']; ?>" 
                                           class="btn-view" title="Xem điểm rèn luyện">
                                            📊 Điểm RL
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
 * 1. LEFT JOIN: Nối bảng để lấy thông tin lớp và khoa của sinh viên
 * 2. LIKE: Tìm kiếm gần đúng (ví dụ: "Nguyên" sẽ tìm ra "Nguyễn Văn A")
 * 3. $_GET: Lấy tham số từ URL (ví dụ: ?search=abc&class_id=1)
 * 4. implode(): Nối các string thành một chuỗi
 * 5. array_filter(): Lọc mảng theo điều kiện
 * 6. htmlspecialchars(): Bảo vệ output khỏi XSS
 */
?>