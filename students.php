<?php
/**
 * TRANG ĐÁNH GIÁ ĐIỂM RÈN LUYỆN CHO SINH VIÊN - students.php
 * 
 * Chỉ dành cho role sinh viên để tự đánh giá điểm rèn luyện
 */

// Bắt đầu session (nếu chưa có)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// CHỈ CHO PHÉP SINH VIÊN TRUY CẬP
if ($user_role !== 'student') {
    header('Location: index.php?error=access_denied');
    exit;
}

// THÔNG BÁO
$success_message = '';
$error_message = '';

// LẤY THÔNG TIN SINH VIÊN
try {
    $student_stmt = $pdo->prepare("
        SELECT s.*, c.name as class_name, c.code as class_code, f.name as faculty_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN faculties f ON c.faculty_id = f.id
        WHERE s.user_id = ?
    ");
    $student_stmt->execute([$user_id]);
    $current_student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_student) {
        $error_message = "Không tìm thấy thông tin sinh viên tương ứng với tài khoản của bạn.";
    }
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn database: " . $e->getMessage();
}

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

// XỬ LÝ CHỌN KỲ HỌC VÀ HIỂN THỊ FORM ĐÁNH GIÁ
$term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
$current_term = null;
$evaluation = null;
$evaluation_items = [];
$criteria = [];

if (!empty($term_id) && $current_student) {
    try {
        // Lấy thông tin kỳ học
        $term_stmt = $pdo->prepare("SELECT * FROM terms WHERE id = ?");
        $term_stmt->execute([$term_id]);
        $current_term = $term_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Lấy danh sách tiêu chí đánh giá
        $criteria_stmt = $pdo->query("
            SELECT id, parent_id, name, max_point, order_no
            FROM criteria 
            WHERE is_active = 1
            ORDER BY order_no, id
        ");
        $all_criteria = $criteria_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Phân loại tiêu chí thành 2 nhóm: tiêu chí chính và tiêu chí con
        $main_criteria_list = array();      // Danh sách tiêu chí chính (không có parent)
        $sub_criteria_list = array();       // Danh sách tiêu chí con (có parent)
        
        foreach ($all_criteria as $single_criterion) {
            if (empty($single_criterion['parent_id'])) {
                // Đây là tiêu chí chính (không có parent)
                $main_criteria_list[] = $single_criterion;
            } else {
                // Đây là tiêu chí con (có parent), nhóm theo parent_id
                $sub_criteria_list[$single_criterion['parent_id']][] = $single_criterion;
            }
        }
        
        // Tạo mảng tổng hợp để dễ sử dụng trong template
        $criteria = array('parent' => $main_criteria_list, 'child' => $sub_criteria_list);
        
        // Lấy đánh giá của sinh viên trong kỳ này
        $eval_stmt = $pdo->prepare("
            SELECT * FROM evaluations 
            WHERE student_id = ? AND term_id = ?
        ");
        $eval_stmt->execute([$current_student['id'], $term_id]);
        $evaluation = $eval_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Nếu chưa có đánh giá thì tạo mới
        if (!$evaluation) {
            $create_eval_stmt = $pdo->prepare("
                INSERT INTO evaluations (student_id, term_id, status) 
                VALUES (?, ?, 'draft')
            ");
            $create_eval_stmt->execute([$current_student['id'], $term_id]);
            
            // Lấy lại thông tin đánh giá vừa tạo
            $eval_stmt->execute([$current_student['id'], $term_id]);
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

/**
 * HÀM XÓA ĐIỂM ĐÁNH GIÁ CŨ
 * Xóa tất cả điểm cũ của một đánh giá để chuẩn bị lưu điểm mới
 */
function deleteOldEvaluationScores($pdo, $evaluation_id) {
    $delete_stmt = $pdo->prepare("DELETE FROM evaluation_items WHERE evaluation_id = ?");
    return $delete_stmt->execute([$evaluation_id]);
}

/**
 * HÀM LƯU ĐIỂM ĐÁNH GIÁ MỚI
 * Lưu từng điểm đánh giá của sinh viên vào database
 */
function saveNewEvaluationScores($pdo, $evaluation_id, $scores_data, $notes_data) {
    $insert_stmt = $pdo->prepare("
        INSERT INTO evaluation_items (evaluation_id, criterion_id, self_score, note) 
        VALUES (?, ?, ?, ?)
    ");
    
    // Lặp qua từng tiêu chí để lưu điểm
    foreach ($scores_data as $criterion_id => $score_value) {
        $score_value = (float)$score_value;  // Chuyển thành số thực
        $note_text = isset($notes_data[$criterion_id]) ? trim($notes_data[$criterion_id]) : '';
        
        // Thực hiện lưu từng record
        $insert_stmt->execute([$evaluation_id, $criterion_id, $score_value, $note_text]);
    }
    
    return true;
}

/**
 * HÀM CẬP NHẬT TRẠNG THÁI ĐÁNH GIÁ
 * Đổi status từ 'draft' thành 'submitted' và cập nhật thời gian
 */
function updateEvaluationStatus($pdo, $evaluation_id, $new_status = 'submitted') {
    $update_eval_stmt = $pdo->prepare("
        UPDATE evaluations 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    return $update_eval_stmt->execute([$new_status, $evaluation_id]);
}

// XỬ LÝ LƯU ĐIỂM ĐÁNH GIÁ (LOGIC CHÍNH)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation']) && $evaluation) {
    try {
        // BƯỚC 1: Bắt đầu transaction (để rollback nếu có lỗi)
        $pdo->beginTransaction();
        
        // BƯỚC 2: Xóa điểm cũ (nếu có)
        deleteOldEvaluationScores($pdo, $evaluation['id']);
        
        // BƯỚC 3: Lưu điểm mới từ form
        saveNewEvaluationScores($pdo, $evaluation['id'], $_POST['scores'], $_POST['notes']);
        
        // BƯỚC 4: Cập nhật trạng thái đánh giá
        updateEvaluationStatus($pdo, $evaluation['id'], 'submitted');
        
        // BƯỚC 5: Commit transaction (lưu thay đổi vào database)
        $pdo->commit();
        $success_message = "✅ Đã lưu điểm tự đánh giá thành công!";
        
        // BƯỚC 6: Reload trang để hiển thị dữ liệu mới
        header("Location: students.php?term_id=$term_id&saved=1");
        exit;
        
    } catch (PDOException $e) {
        // Nếu có lỗi thì rollback (hủy tất cả thay đổi)
        $pdo->rollback();
        $error_message = "Lỗi lưu điểm: " . $e->getMessage();
    }
}

// Hiển thị thông báo sau khi redirect
if (isset($_GET['saved'])) {
    $success_message = "✅ Đã lưu điểm tự đánh giá thành công!";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá điểm rèn luyện - Hệ thống quản lý điểm rèn luyện</title>
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
                <li><a href="index.php">Trang chủ</a></li>
                <?php if ($user_role === 'student'): ?>
                    <li><a href="students.php" class="active">Tự đánh giá</a></li>
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
            
            <!-- TIÊU ĐỀ -->
            <section class="page-header">
                <h2>📝 Đánh giá điểm rèn luyện</h2>
                <p>Tự đánh giá điểm rèn luyện của bản thân theo các tiêu chí</p>
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

            <?php if ($current_student): ?>
                <!-- THÔNG TIN SINH VIÊN -->
                <section class="student-info-section">
                    <h3>👨‍🎓 Thông tin của bạn</h3>
                    <div class="student-info-card">
                        <p><strong>Họ và tên:</strong> <?php echo htmlspecialchars($current_student['full_name']); ?></p>
                        <p><strong>Lớp:</strong> 
                            <?php if (!empty($current_student['class_code'])): ?>
                                <?php echo htmlspecialchars($current_student['class_code'] . ' - ' . $current_student['class_name']); ?>
                            <?php else: ?>
                                <em>Chưa được phân lớp</em>
                            <?php endif; ?>
                        </p>
                        <p><strong>Khoa:</strong> <?php echo htmlspecialchars($current_student['faculty_name'] ?? 'Chưa xác định'); ?></p>
                    </div>
                </section>

                <?php if (!empty($current_student['class_id'])): ?>
                    <!-- CHỌN KỲ HỌC -->
                    <section class="term-selection-section">
                        <h3>📅 Chọn kỳ học để đánh giá</h3>
                        <form method="GET" class="term-form">
                            <div class="form-group">
                                <select name="term_id" id="term_id" onchange="this.form.submit()">
                                    <option value="">-- Chọn kỳ học --</option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?php echo $term['id']; ?>" 
                                                <?php echo ($term_id == $term['id']) ? 'selected' : ''; ?>>
                                            <?php echo $term['academic_year'] . ' - Kỳ ' . $term['term_no']; ?>
                                            <?php if ($term['status'] !== 'active'): ?>
                                                (<?php echo ucfirst($term['status']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </section>

                    <!-- FORM ĐÁNH GIÁ -->
                    <?php if ($current_term && !empty($criteria['parent'])): ?>
                    <section class="evaluation-section">
                        <h3>📋 Đánh giá điểm rèn luyện - <?php echo $current_term['academic_year'] . ' Kỳ ' . $current_term['term_no']; ?></h3>
                        
                        <form method="POST" class="evaluation-form" id="evaluationForm">
                            <input type="hidden" name="save_evaluation" value="1">
                            
                            <!-- THÔNG TIN TỔNG ĐIỂM -->
                            <div class="score-summary">
                                <div class="total-score-display">
                                    <span class="label">📊 Tổng điểm tự đánh giá:</span>
                                    <span class="score" id="totalScore">0</span>
                                    <span class="max-score">/ <span id="maxTotalScore">100</span> điểm</span>
                                </div>
                            </div>
                            
                            <?php 
                            $total_max_score = 0;
                            // Lặp qua từng tiêu chí chính để hiển thị form
                            foreach ($criteria['parent'] as $main_criterion): 
                                $main_criterion_max_score = 0;
                                // Tính tổng điểm tối đa của nhóm này bằng cách cộng điểm các tiêu chí con
                                if (isset($criteria['child'][$main_criterion['id']])) {
                                    foreach ($criteria['child'][$main_criterion['id']] as $sub_criterion) {
                                        $main_criterion_max_score += $sub_criterion['max_point'];
                                    }
                                }
                                $total_max_score += $main_criterion_max_score;
                            ?>
                                <div class="criteria-group">
                                    <div class="criteria-header">
                                        <h4><?php echo htmlspecialchars($main_criterion['name']); ?></h4>
                                        <div class="group-score">
                                            <span class="current-group-score">0</span> / <span class="max-group-score"><?php echo $main_criterion_max_score; ?></span> điểm
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($criteria['child'][$main_criterion['id']])): ?>
                                        <?php foreach ($criteria['child'][$main_criterion['id']] as $sub_criterion): ?>
                                            <div class="criteria-item">
                                                <div class="criteria-info">
                                                    <label for="score_<?php echo $sub_criterion['id']; ?>">
                                                        <?php echo htmlspecialchars($sub_criterion['name']); ?>
                                                        <span class="max-point">Max: <?php echo $sub_criterion['max_point']; ?> điểm</span>
                                                    </label>
                                                </div>
                                                <div class="input-group">
                                                    <div class="score-input">
                                                        <input type="number" 
                                                               id="score_<?php echo $sub_criterion['id']; ?>"
                                                               name="scores[<?php echo $sub_criterion['id']; ?>]" 
                                                               class="score-field"
                                                               data-max="<?php echo $sub_criterion['max_point']; ?>"
                                                               min="0" 
                                                               max="<?php echo $sub_criterion['max_point']; ?>" 
                                                               step="0.1"
                                                               value="<?php echo isset($evaluation_items[$sub_criterion['id']]) ? $evaluation_items[$sub_criterion['id']]['self_score'] : '0'; ?>"
                                                               placeholder="0"
                                                               oninput="updateScores()"
                                                               required>
                                                        <span class="input-suffix">điểm</span>
                                                    </div>
                                                    <textarea name="notes[<?php echo $sub_criterion['id']; ?>]" 
                                                              class="note-field"
                                                              placeholder="📝 Ghi chú, minh chứng, hoạt động cụ thể..."><?php echo isset($evaluation_items[$sub_criterion['id']]) ? htmlspecialchars($evaluation_items[$sub_criterion['id']]['note']) : ''; ?></textarea>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="form-actions">
                                <div class="action-buttons">
                                    <button type="submit" class="btn-primary" id="saveBtn">
                                        💾 Lưu điểm tự đánh giá
                                    </button>
                                    <button type="button" class="btn-secondary" onclick="resetForm()">
                                        🔄 Reset form
                                    </button>
                                    <a href="students.php" class="btn-neutral">
                                        ↩️ Quay lại chọn kỳ
                                    </a>
                                </div>
                                <div class="form-note">
                                    <p><strong>📌 Lưu ý:</strong> Hãy đánh giá trung thực và có căn cứ. Điểm tự đánh giá sẽ được giảng viên xem xét và điều chỉnh nếu cần.</p>
                                </div>
                            </div>
                        </form>
                        
                        <script>
                            // Khởi tạo tổng điểm tối đa
                            document.getElementById('maxTotalScore').textContent = '<?php echo $total_max_score; ?>';
                        </script>
                        
                        <!-- JavaScript cho form đánh giá -->
                        <script>
                            // Hàm cập nhật tổng điểm
                            function updateScores() {
                                let totalScore = 0;
                                const scoreFields = document.querySelectorAll('.score-field');
                                
                                // Tính tổng điểm
                                scoreFields.forEach(field => {
                                    const value = parseFloat(field.value) || 0;
                                    const max = parseFloat(field.getAttribute('data-max'));
                                    
                                    // Kiểm tra không vượt quá điểm tối đa
                                    if (value > max) {
                                        field.value = max;
                                        alert(`Điểm không được vượt quá ${max}!`);
                                    }
                                    
                                    totalScore += parseFloat(field.value) || 0;
                                });
                                
                                // Cập nhật hiển thị tổng điểm
                                document.getElementById('totalScore').textContent = totalScore.toFixed(1);
                                
                                // Cập nhật điểm từng nhóm
                                updateGroupScores();
                                
                                // Thay đổi màu sắc dựa trên tỷ lệ điểm
                                const maxTotal = parseFloat(document.getElementById('maxTotalScore').textContent);
                                const percentage = (totalScore / maxTotal) * 100;
                                const scoreElement = document.getElementById('totalScore');
                                
                                scoreElement.className = 'score';
                                if (percentage >= 90) {
                                    scoreElement.classList.add('excellent');
                                } else if (percentage >= 70) {
                                    scoreElement.classList.add('good');
                                } else if (percentage >= 50) {
                                    scoreElement.classList.add('average');
                                } else {
                                    scoreElement.classList.add('poor');
                                }
                            }
                            
                            // Hàm cập nhật điểm từng nhóm
                            function updateGroupScores() {
                                const groups = document.querySelectorAll('.criteria-group');
                                
                                groups.forEach(group => {
                                    const scoreFields = group.querySelectorAll('.score-field');
                                    let groupTotal = 0;
                                    
                                    scoreFields.forEach(field => {
                                        groupTotal += parseFloat(field.value) || 0;
                                    });
                                    
                                    const currentScoreSpan = group.querySelector('.current-group-score');
                                    if (currentScoreSpan) {
                                        currentScoreSpan.textContent = groupTotal.toFixed(1);
                                    }
                                });
                            }
                            
                            // Hàm reset form
                            function resetForm() {
                                if (confirm('Bạn có chắc muốn reset tất cả điểm về 0?')) {
                                    document.querySelectorAll('.score-field').forEach(field => {
                                        field.value = 0;
                                    });
                                    document.querySelectorAll('.note-field').forEach(field => {
                                        field.value = '';
                                    });
                                    updateScores();
                                }
                            }
                            
                            // Validation trước khi submit
                            document.getElementById('evaluationForm').addEventListener('submit', function(e) {
                                const totalScore = parseFloat(document.getElementById('totalScore').textContent);
                                const maxScore = parseFloat(document.getElementById('maxTotalScore').textContent);
                                
                                if (totalScore === 0) {
                                    if (!confirm('Tổng điểm của bạn là 0. Bạn có chắc muốn lưu?')) {
                                        e.preventDefault();
                                        return;
                                    }
                                }
                                
                                if (totalScore > maxScore) {
                                    alert('Tổng điểm vượt quá điểm tối đa. Vui lòng kiểm tra lại!');
                                    e.preventDefault();
                                    return;
                                }
                                
                                // Hiển thị loading
                                document.getElementById('saveBtn').innerHTML = '⏳ Đang lưu...';
                                document.getElementById('saveBtn').disabled = true;
                            });
                            
                            // Khởi tạo tính điểm khi load trang
                            document.addEventListener('DOMContentLoaded', function() {
                                updateScores();
                            });
                        </script>
                    </section>
                <?php elseif ($current_term): ?>
                    <section class="no-criteria-section">
                        <div class="alert alert-warning">
                            <p>⚠️ Chưa có tiêu chí đánh giá nào được thiết lập cho kỳ học này.</p>
                        </div>
                    </section>
                <?php endif; ?>

                <?php else: ?>
                    <!-- SINH VIÊN CHƯA CÓ LỚP -->
                    <section class="no-class-section">
                        <div class="alert alert-warning">
                            <h3>⚠️ Chưa được phân lớp</h3>
                            <p>Bạn chưa được phân lớp học nên không thể thực hiện đánh giá điểm rèn luyện.</p>
                            <p>Vui lòng liên hệ phòng đào tạo hoặc quản trị viên để được hỗ trợ phân lớp.</p>
                        </div>
                    </section>
                <?php endif; ?>

            <?php else: ?>
                <!-- KHÔNG TÌM THẤY SINH VIÊN -->
                <section class="error-section">
                    <div class="alert alert-error">
                        <h3>❌ Không tìm thấy thông tin sinh viên</h3>
                        <p>Vui lòng liên hệ quản trị viên để được hỗ trợ.</p>
                    </div>
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
 * 1. session_start(): Tiếp tục session đăng nhập
 * 2. PDO::prepare(): Chuẩn bị câu lệnh SQL an toàn (tránh SQL injection)
 * 3. fetchAll(PDO::FETCH_ASSOC): Lấy dữ liệu dạng mảng associative
 * 4. transaction (beginTransaction/commit/rollback): Đảm bảo tính toàn vẹn dữ liệu
 * 5. htmlspecialchars(): Bảo vệ khỏi XSS khi hiển thị dữ liệu
 * 6. isset(): Kiểm tra biến có tồn tại không
 * 7. $_POST/$_GET: Nhận dữ liệu từ form và URL
 * 8. JavaScript validation: Kiểm tra dữ liệu trước khi submit
 * 9. prepared statements: Sử dụng placeholder (?) để bảo mật
 * 10. foreign key: Liên kết giữa các bảng (student_id, term_id, criterion_id)
 */
?>