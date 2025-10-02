<?php
/**
 * TRANG XEM KẾT QUẢ ĐÁNH GIÁ ĐIỂM RÈN LUYỆN - evaluations.php
 * 
 * Chức năng:
 * - Sinh viên: Xem kết quả đánh giá của bản thân
 * - Giáo viên: Xem danh sách và kết quả đánh giá của sinh viên
 * - Admin: Xem tất cả đánh giá trong hệ thống
 */

// Bắt đầu session
session_start();

// Include file kết nối database
require_once 'config.php';

// XỬ LÝ LOGOUT (phải đặt trước kiểm tra đăng nhập)
if (isset($_GET['logout'])) {
    // Xóa tất cả session variables
    $_SESSION = array();
    
    // Xóa session cookie nếu có
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hủy session
    session_destroy();
    
    // Chuyển hướng về trang login
    header('Location: login.php?message=logout_success');
    exit;
}

// KIỂM TRA ĐĂNG NHẬP
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lấy thông tin user từ session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// THÔNG BÁO
$success_message = '';
$error_message = '';

// LẤY DANH SÁCH KỲ HỌC
$terms = [];
try {
    $terms_stmt = $pdo->query("
        SELECT id, academic_year, term_no, status, start_date, end_date
        FROM terms 
        ORDER BY academic_year DESC, term_no DESC
    ");
    $terms = $terms_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Lỗi lấy danh sách kỳ học: " . $e->getMessage();
}

// XỬ LÝ THEO ROLE
$evaluations = [];
$current_student = null;
$selected_term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : (count($terms) > 0 ? $terms[0]['id'] : 0);

if ($user_role === 'student') {
    // SINH VIÊN - CHỈ XEM ĐÁNH GIÁ CỦA BẢN THÂN
    try {
        // Lấy thông tin sinh viên
        $student_stmt = $pdo->prepare("
            SELECT s.*, c.name as class_name, c.code as class_code, f.name as faculty_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id 
            LEFT JOIN faculties f ON c.faculty_id = f.id
            WHERE s.user_id = ?
        ");
        $student_stmt->execute([$user_id]);
        $current_student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_student) {
            // Lấy tất cả đánh giá của sinh viên
            $eval_stmt = $pdo->prepare("
                SELECT e.*, t.academic_year, t.term_no, t.status as term_status,
                       SUM(COALESCE(ei.self_score, 0)) as total_self_score,
                       SUM(COALESCE(ei.lecturer_score, 0)) as total_lecturer_score,
                       COUNT(ei.id) as total_items
                FROM evaluations e
                JOIN terms t ON e.term_id = t.id
                LEFT JOIN evaluation_items ei ON e.id = ei.evaluation_id
                WHERE e.student_id = ?
                GROUP BY e.id, t.id
                ORDER BY t.academic_year DESC, t.term_no DESC
            ");
            $eval_stmt->execute([$current_student['id']]);
            $evaluations = $eval_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi lấy thông tin đánh giá: " . $e->getMessage();
    }
    
} else {
    // GIÁO VIÊN/ADMIN - XEM DANH SÁCH ĐÁNH GIÁ
    $search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
    $class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    
    try {
        // Lấy danh sách lớp (cho admin) hoặc lớp của giáo viên
        $classes = [];
        if ($user_role === 'admin') {
            $class_stmt = $pdo->query("SELECT id, code, name FROM classes ORDER BY code");
            $classes = $class_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else if ($user_role === 'lecturer') {
            // Lấy lớp mà giáo viên làm chủ nhiệm
            $lecturer_stmt = $pdo->prepare("SELECT id FROM lecturers WHERE user_id = ?");
            $lecturer_stmt->execute([$user_id]);
            $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lecturer) {
                $class_stmt = $pdo->prepare("SELECT id, code, name FROM classes WHERE homeroom_lecturer_id = ? ORDER BY code");
                $class_stmt->execute([$lecturer['id']]);
                $classes = $class_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // Xây dựng query tìm kiếm đánh giá
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($selected_term_id)) {
            $where_conditions[] = "e.term_id = ?";
            $params[] = $selected_term_id;
        }
        
        if (!empty($search_keyword)) {
            $where_conditions[] = "(s.full_name LIKE ? OR s.student_code LIKE ?)";
            $params[] = "%$search_keyword%";
            $params[] = "%$search_keyword%";
        }
        
        if (!empty($class_filter)) {
            $where_conditions[] = "s.class_id = ?";
            $params[] = $class_filter;
        }
        
        // Nếu là giáo viên, chỉ xem sinh viên trong lớp mình làm chủ nhiệm
        if ($user_role === 'lecturer' && !empty($classes)) {
            $class_ids = array_column($classes, 'id');
            if (!empty($class_ids)) {
                $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';
                $where_conditions[] = "s.class_id IN ($placeholders)";
                $params = array_merge($params, $class_ids);
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $eval_stmt = $pdo->prepare("
            SELECT e.*, s.student_code, s.full_name as student_name,
                   c.code as class_code, c.name as class_name,
                   t.academic_year, t.term_no,
                   SUM(COALESCE(ei.self_score, 0)) as total_self_score,
                   SUM(COALESCE(ei.lecturer_score, 0)) as total_lecturer_score,
                   COUNT(ei.id) as total_items
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            JOIN terms t ON e.term_id = t.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN evaluation_items ei ON e.id = ei.evaluation_id
            WHERE $where_clause
            GROUP BY e.id
            ORDER BY t.academic_year DESC, t.term_no DESC, s.student_code
            LIMIT 100
        ");
        $eval_stmt->execute($params);
        $evaluations = $eval_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error_message = "Lỗi tìm kiếm đánh giá: " . $e->getMessage();
    }
}
// LẤY CHI TIẾT ĐÁNH GIÁ CỤ THỂ (KHI CLICK VÀO MỘT ĐÁNH GIÁ)
$evaluation_detail = null;
$evaluation_items = [];
$criteria = [];

$detail_eval_id = isset($_GET['eval_id']) ? (int)$_GET['eval_id'] : 0;
if (!empty($detail_eval_id)) {
    try {
        // Lấy thông tin đánh giá
        $detail_stmt = $pdo->prepare("
            SELECT e.*, s.student_code, s.full_name as student_name,
                   c.code as class_code, c.name as class_name,
                   t.academic_year, t.term_no, t.status as term_status
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            JOIN terms t ON e.term_id = t.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE e.id = ?
        ");
        $detail_stmt->execute([$detail_eval_id]);
        $evaluation_detail = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($evaluation_detail) {
            // Kiểm tra quyền xem (sinh viên chỉ xem của mình)
            if ($user_role === 'student' && $current_student && $evaluation_detail['student_id'] != $current_student['id']) {
                $error_message = "Bạn không có quyền xem đánh giá này!";
                $evaluation_detail = null;
            } else {
                // Lấy danh sách tiêu chí
                $criteria_stmt = $pdo->query("
                    SELECT id, parent_id, name, max_point, order_no
                    FROM criteria 
                    WHERE is_active = 1
                    ORDER BY order_no, id
                ");
                $all_criteria = $criteria_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Phân loại tiêu chí
                $parent_criteria = [];
                $child_criteria = [];
                
                foreach ($all_criteria as $criterion) {
                    if (empty($criterion['parent_id'])) {
                        $parent_criteria[] = $criterion;
                    } else {
                        $child_criteria[$criterion['parent_id']][] = $criterion;
                    }
                }
                
                $criteria = ['parent' => $parent_criteria, 'child' => $child_criteria];
                
                // Lấy chi tiết điểm
                $items_stmt = $pdo->prepare("
                    SELECT ei.*, c.name as criterion_name, c.max_point, c.parent_id
                    FROM evaluation_items ei
                    JOIN criteria c ON ei.criterion_id = c.id
                    WHERE ei.evaluation_id = ?
                    ORDER BY c.order_no, c.id
                ");
                $items_stmt->execute([$detail_eval_id]);
                $items_result = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Tổ chức dữ liệu theo criterion_id
                foreach ($items_result as $item) {
                    $evaluation_items[$item['criterion_id']] = $item;
                }
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi lấy chi tiết đánh giá: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả đánh giá - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS bổ sung cho trang kết quả đánh giá */
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .evaluation-table th,
        .evaluation-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .evaluation-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .evaluation-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-draft {
            background: #ffeaa7;
            color: #fdcb6e;
        }
        
        .status-submitted {
            background: #74b9ff;
            color: white;
        }
        
        .status-approved {
            background: #00b894;
            color: white;
        }
        
        .score-comparison {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .score-box {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            flex: 1;
        }
        
        .score-box.self {
            border-left: 4px solid #74b9ff;
        }
        
        .score-box.lecturer {
            border-left: 4px solid #00b894;
        }
        
        .score-number {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .score-label {
            color: #666;
            font-size: 14px;
        }
        
        .criteria-detail {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .criteria-header {
            background: #667eea;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
        }
        
        .criteria-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .criteria-item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .item-scores {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr;
            gap: 15px;
            align-items: start;
        }
        
        .score-item {
            text-align: center;
        }
        
        .score-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        
        .score-max {
            display: block;
            font-size: 12px;
            color: #999;
        }
        
        .item-note {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-style: italic;
            color: #666;
        }
        
        .filter-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .no-evaluations {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .btn-view {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
        }
        
        .btn-view:hover {
            background: #5a6fd8;
        }
        
        @media (max-width: 768px) {
            .score-comparison {
                flex-direction: column;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .item-scores {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .evaluation-table {
                font-size: 12px;
            }
            
            .evaluation-table th,
            .evaluation-table td {
                padding: 8px 4px;
            }
        }
    </style>
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
                <li><a href="index.php">Trang chủ</a></li>
                <?php if ($user_role === 'student'): ?>
                    <li><a href="students.php">Tự đánh giá</a></li>
                    <li><a href="evaluations.php" class="active">Xem kết quả</a></li>
                <?php else: ?>
                    <li><a href="evaluations.php" class="active">Điểm rèn luyện</a></li>
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
            
            <?php if (!empty($evaluation_detail)): ?>
                <!-- CHI TIẾT ĐÁNH GIÁ -->
                <a href="evaluations.php" class="back-link">← Quay lại danh sách</a>
                
                <section class="evaluation-detail">
                    <div class="page-header">
                        <h2>📊 Chi tiết đánh giá điểm rèn luyện</h2>
                        <p>
                            <strong><?php echo htmlspecialchars($evaluation_detail['student_name']); ?></strong> 
                            (<?php echo htmlspecialchars($evaluation_detail['student_code']); ?>) - 
                            Lớp <?php echo htmlspecialchars($evaluation_detail['class_code']); ?> - 
                            Năm học <?php echo htmlspecialchars($evaluation_detail['academic_year']); ?> 
                            Kỳ <?php echo $evaluation_detail['term_no']; ?>
                        </p>
                    </div>

                    <!-- THÔNG BÁO -->
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-error">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <!-- SO SÁNH ĐIỂM -->
                    <?php
                    $total_self = 0;
                    $total_lecturer = 0;
                    $total_max = 0;
                    
                    foreach ($evaluation_items as $item) {
                        $total_self += $item['self_score'] ?? 0;
                        $total_lecturer += $item['lecturer_score'] ?? 0;
                        $total_max += $item['max_point'] ?? 0;
                    }
                    ?>
                    
                    <div class="score-comparison">
                        <div class="score-box self">
                            <div class="score-number"><?php echo number_format($total_self, 1); ?></div>
                            <div class="score-label">Điểm tự đánh giá</div>
                        </div>
                        <div class="score-box lecturer">
                            <div class="score-number"><?php echo number_format($total_lecturer, 1); ?></div>
                            <div class="score-label">Điểm giảng viên</div>
                        </div>
                        <div class="score-box">
                            <div class="score-number"><?php echo number_format($total_max, 1); ?></div>
                            <div class="score-label">Điểm tối đa</div>
                        </div>
                    </div>

                    <!-- CHI TIẾT TỪNG TIÊU CHÍ -->
                    <?php foreach ($criteria['parent'] as $parent): ?>
                        <div class="criteria-detail">
                            <div class="criteria-header">
                                <?php echo htmlspecialchars($parent['name']); ?>
                                <?php if ($parent['max_point']): ?>
                                    (Tối đa: <?php echo $parent['max_point']; ?> điểm)
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($criteria['child'][$parent['id']])): ?>
                                <?php foreach ($criteria['child'][$parent['id']] as $child): ?>
                                    <?php if (isset($evaluation_items[$child['id']])): ?>
                                        <?php $item = $evaluation_items[$child['id']]; ?>
                                        <div class="criteria-item">
                                            <div class="item-name"><?php echo htmlspecialchars($child['name']); ?></div>
                                            <div class="item-scores">
                                                <div class="score-item">
                                                    <span class="score-value"><?php echo number_format($item['self_score'] ?? 0, 1); ?></span>
                                                    <span class="score-max">Tự đánh giá</span>
                                                </div>
                                                <div class="score-item">
                                                    <span class="score-value" style="color: #00b894;">
                                                        <?php echo number_format($item['lecturer_score'] ?? 0, 1); ?>
                                                    </span>
                                                    <span class="score-max">Giảng viên</span>
                                                </div>
                                                <div class="item-note">
                                                    <?php if (!empty($item['note'])): ?>
                                                        <?php echo htmlspecialchars($item['note']); ?>
                                                    <?php else: ?>
                                                        <em>Không có ghi chú</em>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if (isset($evaluation_items[$parent['id']])): ?>
                                    <?php $item = $evaluation_items[$parent['id']]; ?>
                                    <div class="criteria-item">
                                        <div class="item-scores">
                                            <div class="score-item">
                                                <span class="score-value"><?php echo number_format($item['self_score'] ?? 0, 1); ?></span>
                                                <span class="score-max">Tự đánh giá</span>
                                            </div>
                                            <div class="score-item">
                                                <span class="score-value" style="color: #00b894;">
                                                    <?php echo number_format($item['lecturer_score'] ?? 0, 1); ?>
                                                </span>
                                                <span class="score-max">Giảng viên</span>
                                            </div>
                                            <div class="item-note">
                                                <?php if (!empty($item['note'])): ?>
                                                    <?php echo htmlspecialchars($item['note']); ?>
                                                <?php else: ?>
                                                    <em>Không có ghi chú</em>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>

            <?php else: ?>
                <!-- DANH SÁCH ĐÁNH GIÁ -->
                <section class="page-header">
                    <h2>📊 Kết quả đánh giá điểm rèn luyện</h2>
                    <?php if ($user_role === 'student'): ?>
                        <p>Xem kết quả đánh giá điểm rèn luyện của bạn</p>
                    <?php else: ?>
                        <p>Quản lý và xem kết quả đánh giá điểm rèn luyện</p>
                    <?php endif; ?>
                </section>

                <!-- THÔNG BÁO -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($user_role === 'student' && !$current_student): ?>
                    <!-- SINH VIÊN CHƯA CÓ THÔNG TIN -->
                    <div class="alert alert-warning">
                        <h3>⚠️ Không tìm thấy thông tin sinh viên</h3>
                        <p>Vui lòng liên hệ quản trị viên để được hỗ trợ.</p>
                    </div>
                    
                <?php elseif ($user_role !== 'student'): ?>
                    <!-- BỘ LỌC CHO GIÁO VIÊN/ADMIN -->
                    <section class="filter-section">
                        <h3>🔍 Tìm kiếm và lọc</h3>
                        <form method="GET" action="">
                            <div class="filter-row">
                                <div class="form-group">
                                    <label for="term_id">Kỳ học:</label>
                                    <select id="term_id" name="term_id">
                                        <option value="">-- Tất cả kỳ --</option>
                                        <?php foreach ($terms as $term): ?>
                                            <option value="<?php echo $term['id']; ?>" 
                                                    <?php echo ($selected_term_id == $term['id']) ? 'selected' : ''; ?>>
                                                <?php echo $term['academic_year'] . ' - Kỳ ' . $term['term_no']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if (!empty($classes)): ?>
                                <div class="form-group">
                                    <label for="class_id">Lớp:</label>
                                    <select id="class_id" name="class_id">
                                        <option value="">-- Tất cả lớp --</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" 
                                                    <?php echo ($class_filter == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo $class['code'] . ' - ' . $class['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label for="search">Tìm sinh viên:</label>
                                    <input type="text" id="search" name="search" 
                                           placeholder="Tên hoặc mã sinh viên..."
                                           value="<?php echo htmlspecialchars($search_keyword); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn-search">🔍 Tìm kiếm</button>
                                </div>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- DANH SÁCH ĐÁNH GIÁ -->
                <?php if (!empty($evaluations)): ?>
                    <section class="evaluations-list">
                        <h3>
                            📋 Danh sách đánh giá 
                            (<?php echo count($evaluations); ?> <?php echo $user_role === 'student' ? 'kỳ' : 'bản ghi'; ?>)
                        </h3>
                        
                        <table class="evaluation-table">
                            <thead>
                                <tr>
                                    <?php if ($user_role !== 'student'): ?>
                                        <th>Mã SV</th>
                                        <th>Sinh viên</th>
                                        <th>Lớp</th>
                                    <?php endif; ?>
                                    <th>Kỳ học</th>
                                    <th>Trạng thái</th>
                                    <th>Tự đánh giá</th>
                                    <th>Giáo viên</th>
                                    <th>Ngày cập nhật</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evaluations as $eval): ?>
                                <tr>
                                    <?php if ($user_role !== 'student'): ?>
                                        <td><?php echo htmlspecialchars($eval['student_code']); ?></td>
                                        <td><?php echo htmlspecialchars($eval['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($eval['class_code']); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php echo $eval['academic_year'] . ' - Kỳ ' . $eval['term_no']; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $eval['status']; ?>">
                                            <?php 
                                            switch($eval['status']) {
                                                case 'draft': echo 'Nháp'; break;
                                                case 'submitted': echo 'Đã nộp'; break;
                                                case 'approved': echo 'Đã duyệt'; break;
                                                default: echo ucfirst($eval['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($eval['total_self_score'], 1); ?></strong> điểm
                                    </td>
                                    <td>
                                        <?php if ($eval['total_lecturer_score'] > 0): ?>
                                            <strong style="color: #00b894;">
                                                <?php echo number_format($eval['total_lecturer_score'], 1); ?>
                                            </strong> điểm
                                        <?php else: ?>
                                            <span style="color: #999;">Chưa có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($eval['updated_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="?eval_id=<?php echo $eval['id']; ?>" class="btn-view">
                                            👁️ Xem chi tiết
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                    
                <?php else: ?>
                    <div class="no-evaluations">
                        <h3>📋 Chưa có đánh giá nào</h3>
                        <?php if ($user_role === 'student'): ?>
                            <p>Bạn chưa có đánh giá điểm rèn luyện nào.</p>
                            <a href="students.php" class="btn-primary">📝 Tự đánh giá ngay</a>
                        <?php else: ?>
                            <p>Chưa có đánh giá nào phù hợp với tiêu chí tìm kiếm.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Hệ thống quản lý điểm rèn luyện. Được phát triển cho mục đích học tập.</p>
        </div>
    </footer>

    <script>
        // Tự động tải lại trang nếu có filter thay đổi
        document.addEventListener('DOMContentLoaded', function() {
            const termSelect = document.getElementById('term_id');
            const classSelect = document.getElementById('class_id');
            
            if (termSelect) {
                termSelect.addEventListener('change', function() {
                    if (this.value) {
                        this.form.submit();
                    }
                });
            }
            
            if (classSelect) {
                classSelect.addEventListener('change', function() {
                    this.form.submit();
                });
            }
            
            // Tự động ẩn thông báo sau 5 giây
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
            
            // Highlight row khi hover
            const tableRows = document.querySelectorAll('.evaluation-table tbody tr');
            tableRows.forEach(function(row) {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>

<?php
/**
 * GIẢI THÍCH CODE CHO NGƯỜI MỚI:
 * 
 * 1. Trang này có nhiều chức năng: chọn kỳ học, tìm sinh viên, nhập điểm
 * 2. $pdo->lastInsertId(): Lấy ID của record vừa được insert
 * 3. ON DUPLICATE KEY: MySQL syntax để update nếu record đã tồn tại
 * 4. foreach loops: Lặp qua mảng để hiển thị dữ liệu
 * 5. Form validation: Kiểm tra dữ liệu trước khi lưu database
 * 6. header("Location: ..."): Redirect sau khi lưu thành công
 */
?>