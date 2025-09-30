<?php
/**
 * TRANG QUẢN LÝ ĐIỂM RÈN LUYỆN - evaluations.php
 * 
 * Hiển thị và cho phép nhập điểm rèn luyện cho sinh viên
 * Đây là chức năng cốt lõi của hệ thống
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
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// THÔNG BÁO
$success_message = '';
$error_message = '';

try {
    // LẤY DANH SÁCH KỲ HỌC
    $terms_stmt = $pdo->query("
        SELECT id, academic_year, term_no, status, start_date, end_date
        FROM terms 
        ORDER BY academic_year DESC, term_no DESC
    ");
    $terms = $terms_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // NẾU KHÔNG CÓ TERM ID THÌ LẤY KỲ MỚI NHẤT
    if (empty($term_id) && !empty($terms)) {
        $term_id = $terms[0]['id'];
    }
    
    // LẤY THÔNG TIN KỲ HỌC HIỆN TẠI
    $current_term = null;
    if (!empty($term_id)) {
        $term_stmt = $pdo->prepare("SELECT * FROM terms WHERE id = ?");
        $term_stmt->execute([$term_id]);
        $current_term = $term_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // LẤY DANH SÁCH TIÊU CHÍ ĐÁNH GIÁ
    $criteria_stmt = $pdo->query("
        SELECT id, parent_id, name, max_point, order_no
        FROM criteria 
        WHERE is_active = 1
        ORDER BY order_no, id
    ");
    $all_criteria = $criteria_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // PHÂN LOẠI TIÊU CHÍ (PARENT VÀ CHILD)
    $parent_criteria = [];
    $child_criteria = [];
    
    foreach ($all_criteria as $criterion) {
        if (empty($criterion['parent_id'])) {
            $parent_criteria[] = $criterion;
        } else {
            $child_criteria[$criterion['parent_id']][] = $criterion;
        }
    }
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn database: " . $e->getMessage();
}

// XỬ LÝ FORM TÌM KIẾM SINH VIÊN
$students = [];
if (!empty($search_keyword) || !empty($student_id)) {
    try {
        $where_conditions = [];
        $params = [];
        
        if (!empty($search_keyword)) {
            $where_conditions[] = "(s.full_name LIKE ? OR s.student_code LIKE ?)";
            $params[] = "%$search_keyword%";
            $params[] = "%$search_keyword%";
        }
        
        if (!empty($student_id)) {
            $where_conditions[] = "s.id = ?";
            $params[] = $student_id;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $students_stmt = $pdo->prepare("
            SELECT s.id, s.student_code, s.full_name, s.email,
                   c.name as class_name, c.code as class_code
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            $where_clause
            ORDER BY s.student_code
            LIMIT 20
        ");
        $students_stmt->execute($params);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error_message = "Lỗi tìm kiếm sinh viên: " . $e->getMessage();
    }
}

// LẤY THÔNG TIN ĐÁNH GIÁ CỦA SINH VIÊN (NẾU ĐƯỢC CHỌN)
$evaluation = null;
$evaluation_items = [];
if (!empty($student_id) && !empty($term_id)) {
    try {
        // Lấy thông tin sinh viên được chọn
        $student_stmt = $pdo->prepare("
            SELECT s.*, c.name as class_name, c.code as class_code
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE s.id = ?
        ");
        $student_stmt->execute([$student_id]);
        $selected_student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Lấy đánh giá của sinh viên trong kỳ này
        $eval_stmt = $pdo->prepare("
            SELECT * FROM evaluations 
            WHERE student_id = ? AND term_id = ?
        ");
        $eval_stmt->execute([$student_id, $term_id]);
        $evaluation = $eval_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Nếu chưa có đánh giá thì tạo mới
        if (!$evaluation) {
            $create_eval_stmt = $pdo->prepare("
                INSERT INTO evaluations (student_id, term_id, status) 
                VALUES (?, ?, 'draft')
            ");
            $create_eval_stmt->execute([$student_id, $term_id]);
            $evaluation_id = $pdo->lastInsertId();
            
            // Lấy lại thông tin đánh giá vừa tạo
            $eval_stmt->execute([$student_id, $term_id]);
            $evaluation = $eval_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Lấy chi tiết điểm của từng tiêu chí
        if ($evaluation) {
            $items_stmt = $pdo->prepare("
                SELECT ei.*, c.name as criterion_name, c.max_point
                FROM evaluation_items ei
                JOIN criteria c ON ei.criterion_id = c.id
                WHERE ei.evaluation_id = ?
            ");
            $items_stmt->execute([$evaluation['id']]);
            $items_result = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tổ chức dữ liệu theo criterion_id
            foreach ($items_result as $item) {
                $evaluation_items[$item['criterion_id']] = $item;
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi lấy thông tin đánh giá: " . $e->getMessage();
    }
}

// XỬ LÝ CẬP NHẬT ĐIỂM
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_scores'])) {
    try {
        $evaluation_id = (int)$_POST['evaluation_id'];
        $scores = $_POST['scores'] ?? [];
        
        foreach ($scores as $criterion_id => $score_data) {
            $self_score = !empty($score_data['self_score']) ? (float)$score_data['self_score'] : null;
            $lecturer_score = !empty($score_data['lecturer_score']) ? (float)$score_data['lecturer_score'] : null;
            $note = trim($score_data['note'] ?? '');
            
            // Kiểm tra xem đã có record chưa
            $check_stmt = $pdo->prepare("
                SELECT id FROM evaluation_items 
                WHERE evaluation_id = ? AND criterion_id = ?
            ");
            $check_stmt->execute([$evaluation_id, $criterion_id]);
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                // Cập nhật
                $update_stmt = $pdo->prepare("
                    UPDATE evaluation_items 
                    SET self_score = ?, lecturer_score = ?, note = ?
                    WHERE evaluation_id = ? AND criterion_id = ?
                ");
                $update_stmt->execute([$self_score, $lecturer_score, $note, $evaluation_id, $criterion_id]);
            } else {
                // Tạo mới
                $insert_stmt = $pdo->prepare("
                    INSERT INTO evaluation_items (evaluation_id, criterion_id, self_score, lecturer_score, note)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$evaluation_id, $criterion_id, $self_score, $lecturer_score, $note]);
            }
        }
        
        $success_message = "✅ Đã lưu điểm thành công!";
        
        // Reload dữ liệu
        header("Location: evaluations.php?student_id=$student_id&term_id=$term_id&saved=1");
        exit;
        
    } catch (PDOException $e) {
        $error_message = "Lỗi lưu điểm: " . $e->getMessage();
    }
}

// Hiển thị thông báo sau khi redirect
if (isset($_GET['saved'])) {
    $success_message = "✅ Đã lưu điểm thành công!";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Điểm rèn luyện - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="container">
            <h1>🎓 Hệ thống quản lý điểm rèn luyện</h1>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
                <a href="index.php?logout=1" class="btn-logout">🚪 Đăng xuất</a>
            </div>
        </div>
    </header>

    <!-- MENU ĐIỀU HƯỚNG -->
    <nav class="nav-menu">
        <div class="container">
            <ul class="menu-list">
                <li><a href="index.php">🏠 Trang chủ</a></li>
                <li><a href="students.php">👨‍🎓 Sinh viên</a></li>
                <li><a href="evaluations.php" class="active">📊 Điểm rèn luyện</a></li>
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
                <h2>📊 Quản lý điểm rèn luyện</h2>
                <p>Xem và nhập điểm rèn luyện cho sinh viên</p>
            </section>

            <!-- THÔNG BÁO -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- CHỌN KỲ HỌC -->
            <section class="term-selection">
                <h3>📅 Chọn kỳ học</h3>
                <form method="GET" action="" class="term-form">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>">
                    
                    <div class="form-group">
                        <label for="term_id">Kỳ học:</label>
                        <select id="term_id" name="term_id" onchange="this.form.submit()">
                            <option value="">-- Chọn kỳ học --</option>
                            <?php foreach ($terms as $term): ?>
                            <option value="<?php echo $term['id']; ?>" 
                                    <?php echo ($term_id == $term['id']) ? 'selected' : ''; ?>>
                                Năm học <?php echo htmlspecialchars($term['academic_year']); ?> - 
                                Kỳ <?php echo $term['term_no']; ?>
                                (<?php echo ucfirst($term['status']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                
                <?php if ($current_term): ?>
                <div class="current-term-info">
                    <p><strong>Kỳ hiện tại:</strong> 
                       Năm học <?php echo htmlspecialchars($current_term['academic_year']); ?> - 
                       Kỳ <?php echo $current_term['term_no']; ?>
                    </p>
                    <p><strong>Thời gian:</strong> 
                       <?php echo date('d/m/Y', strtotime($current_term['start_date'])); ?> - 
                       <?php echo date('d/m/Y', strtotime($current_term['end_date'])); ?>
                    </p>
                    <p><strong>Trạng thái:</strong> 
                       <span class="status-badge status-<?php echo $current_term['status']; ?>">
                           <?php echo ucfirst($current_term['status']); ?>
                       </span>
                    </p>
                </div>
                <?php endif; ?>
            </section>

            <!-- TÌM KIẾM SINH VIÊN -->
            <section class="student-search">
                <h3>🔍 Tìm kiếm sinh viên</h3>
                <form method="GET" action="" class="search-form">
                    <input type="hidden" name="term_id" value="<?php echo $term_id; ?>">
                    
                    <div class="search-row">
                        <div class="search-field">
                            <label for="search">Tên hoặc mã sinh viên:</label>
                            <input type="text" id="search" name="search" 
                                   placeholder="Nhập tên hoặc mã sinh viên..."
                                   value="<?php echo htmlspecialchars($search_keyword); ?>">
                        </div>
                        <div class="search-buttons">
                            <button type="submit" class="btn-search">🔍 Tìm kiếm</button>
                        </div>
                    </div>
                </form>

                <!-- KẾT QUẢ TÌM KIẾM -->
                <?php if (!empty($students)): ?>
                <div class="search-results">
                    <h4>Kết quả tìm kiếm (<?php echo count($students); ?> sinh viên):</h4>
                    <div class="students-list">
                        <?php foreach ($students as $student): ?>
                        <div class="student-item <?php echo ($student['id'] == $student_id) ? 'selected' : ''; ?>">
                            <div class="student-info">
                                <strong><?php echo htmlspecialchars($student['student_code']); ?></strong> - 
                                <?php echo htmlspecialchars($student['full_name']); ?>
                                <?php if (!empty($student['class_code'])): ?>
                                <br><small>Lớp: <?php echo htmlspecialchars($student['class_code']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="student-actions">
                                <a href="?student_id=<?php echo $student['id']; ?>&term_id=<?php echo $term_id; ?>&search=<?php echo urlencode($search_keyword); ?>" 
                                   class="btn-select">📝 Chọn</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <!-- FORM NHẬP ĐIỂM (CHỈ HIỂN THỊ KHI ĐÃ CHỌN SINH VIÊN) -->
            <?php if (!empty($student_id) && !empty($term_id) && !empty($evaluation) && !empty($selected_student)): ?>
            <section class="evaluation-form">
                <h3>📝 Nhập điểm rèn luyện</h3>
                
                <!-- THÔNG TIN SINH VIÊN -->
                <div class="student-info-box">
                    <h4>👨‍🎓 Thông tin sinh viên</h4>
                    <p><strong>Mã sinh viên:</strong> <?php echo htmlspecialchars($selected_student['student_code']); ?></p>
                    <p><strong>Họ và tên:</strong> <?php echo htmlspecialchars($selected_student['full_name']); ?></p>
                    <p><strong>Lớp:</strong> <?php echo htmlspecialchars($selected_student['class_code'] . ' - ' . $selected_student['class_name']); ?></p>
                    <p><strong>Kỳ đánh giá:</strong> Năm học <?php echo htmlspecialchars($current_term['academic_year']); ?> - Kỳ <?php echo $current_term['term_no']; ?></p>
                </div>

                <!-- FORM ĐIỂM -->
                <form method="POST" action="" class="scores-form">
                    <input type="hidden" name="evaluation_id" value="<?php echo $evaluation['id']; ?>">
                    <input type="hidden" name="save_scores" value="1">
                    
                    <div class="criteria-list">
                        <?php foreach ($parent_criteria as $parent): ?>
                        <div class="criteria-group">
                            <h4 class="criteria-title">
                                <?php echo htmlspecialchars($parent['name']); ?>
                                <?php if (!empty($parent['max_point'])): ?>
                                <span class="max-point">(Tối đa: <?php echo $parent['max_point']; ?> điểm)</span>
                                <?php endif; ?>
                            </h4>
                            
                            <!-- TIÊU CHÍ CON -->
                            <?php if (isset($child_criteria[$parent['id']])): ?>
                                <?php foreach ($child_criteria[$parent['id']] as $child): ?>
                                <div class="criteria-item">
                                    <div class="criteria-name">
                                        <?php echo htmlspecialchars($child['name']); ?>
                                        <?php if (!empty($child['max_point'])): ?>
                                        <span class="max-point">(Tối đa: <?php echo $child['max_point']; ?> điểm)</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="score-inputs">
                                        <div class="score-field">
                                            <label>Sinh viên tự đánh giá:</label>
                                            <input type="number" step="0.01" min="0" max="<?php echo $child['max_point']; ?>"
                                                   name="scores[<?php echo $child['id']; ?>][self_score]"
                                                   value="<?php echo isset($evaluation_items[$child['id']]) ? $evaluation_items[$child['id']]['self_score'] : ''; ?>"
                                                   placeholder="0.00">
                                        </div>
                                        
                                        <div class="score-field">
                                            <label>Giảng viên đánh giá:</label>
                                            <input type="number" step="0.01" min="0" max="<?php echo $child['max_point']; ?>"
                                                   name="scores[<?php echo $child['id']; ?>][lecturer_score]"
                                                   value="<?php echo isset($evaluation_items[$child['id']]) ? $evaluation_items[$child['id']]['lecturer_score'] : ''; ?>"
                                                   placeholder="0.00">
                                        </div>
                                        
                                        <div class="note-field">
                                            <label>Ghi chú:</label>
                                            <textarea name="scores[<?php echo $child['id']; ?>][note]" 
                                                      placeholder="Ghi chú (tùy chọn)"><?php echo isset($evaluation_items[$child['id']]) ? htmlspecialchars($evaluation_items[$child['id']]['note']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- TIÊU CHÍ CHÍNH KHÔNG CÓ CON -->
                                <div class="criteria-item">
                                    <div class="score-inputs">
                                        <div class="score-field">
                                            <label>Sinh viên tự đánh giá:</label>
                                            <input type="number" step="0.01" min="0" max="<?php echo $parent['max_point']; ?>"
                                                   name="scores[<?php echo $parent['id']; ?>][self_score]"
                                                   value="<?php echo isset($evaluation_items[$parent['id']]) ? $evaluation_items[$parent['id']]['self_score'] : ''; ?>"
                                                   placeholder="0.00">
                                        </div>
                                        
                                        <div class="score-field">
                                            <label>Giảng viên đánh giá:</label>
                                            <input type="number" step="0.01" min="0" max="<?php echo $parent['max_point']; ?>"
                                                   name="scores[<?php echo $parent['id']; ?>][lecturer_score]"
                                                   value="<?php echo isset($evaluation_items[$parent['id']]) ? $evaluation_items[$parent['id']]['lecturer_score'] : ''; ?>"
                                                   placeholder="0.00">
                                        </div>
                                        
                                        <div class="note-field">
                                            <label>Ghi chú:</label>
                                            <textarea name="scores[<?php echo $parent['id']; ?>][note]" 
                                                      placeholder="Ghi chú (tùy chọn)"><?php echo isset($evaluation_items[$parent['id']]) ? htmlspecialchars($evaluation_items[$parent['id']]['note']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-save">💾 Lưu điểm</button>
                        <a href="evaluations.php" class="btn-cancel">❌ Hủy</a>
                    </div>
                </form>
            </section>
            <?php endif; ?>

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
 * 1. Trang này có nhiều chức năng: chọn kỳ học, tìm sinh viên, nhập điểm
 * 2. $pdo->lastInsertId(): Lấy ID của record vừa được insert
 * 3. ON DUPLICATE KEY: MySQL syntax để update nếu record đã tồn tại
 * 4. foreach loops: Lặp qua mảng để hiển thị dữ liệu
 * 5. Form validation: Kiểm tra dữ liệu trước khi lưu database
 * 6. header("Location: ..."): Redirect sau khi lưu thành công
 */
?>