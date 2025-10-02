<?php
/**
 * TRANG ĐÁNH GIÁ CHO GIẢNG VIÊN - lecturer_evaluation.php
 * 
 * Chức năng:
 * - Hiển thị danh sách sinh viên đã tự đánh giá
 * - Cho phép giảng viên xem điểm tự đánh giá của sinh viên
 * - Giảng viên nhập điểm đánh giá và ghi chú
 * - Lưu kết quả đánh giá vào database
 */

// Bắt đầu session
session_start();

// Include file kết nối database
require_once 'config.php';

// XỬ LÝ LOGOUT
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit();
}

// KIỂM TRA ĐĂNG NHẬP
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

// KIỂM TRA QUYỀN - Chỉ giảng viên và admin mới được truy cập
if ($_SESSION['user_role'] === 'student') {
    header("Location: students.php");
    exit();
}

// Lấy thông tin user
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Biến thông báo
$success_message = '';
$error_message = '';

try {
    // XỬ LÝ LƯU ĐIỂM ĐÁNH GIÁ
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation'])) {
        $evaluation_id = (int)$_POST['evaluation_id'];
        $scores = $_POST['scores'] ?? [];
        
        if (empty($scores)) {
            $error_message = "Vui lòng nhập ít nhất một điểm đánh giá.";
        } else {
            $pdo->beginTransaction();
            
            try {
                // Cập nhật hoặc tạo mới evaluation_items
                foreach ($scores as $criterion_id => $score_data) {
                    $lecturer_score = isset($score_data['lecturer_score']) ? (float)$score_data['lecturer_score'] : 0;
                    $note = isset($score_data['note']) ? trim($score_data['note']) : '';
                    
                    // Validation: Kiểm tra điểm không vượt quá giới hạn
                    $stmt_check = $pdo->prepare("SELECT max_point FROM criteria WHERE id = :criterion_id");
                    $stmt_check->execute([':criterion_id' => $criterion_id]);
                    $criterion = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($criterion && $lecturer_score > $criterion['max_point']) {
                        throw new Exception("Lỗi: Điểm nhập ({$lecturer_score}) vượt quá điểm tối đa ({$criterion['max_point']}) cho tiêu chí này.");
                    }
                    
                    // Cập nhật hoặc tạo mới evaluation_items
                    $stmt = $pdo->prepare("
                        UPDATE evaluation_items 
                        SET lecturer_score = :lecturer_score, 
                            note = :note,
                            updated_at = NOW()
                        WHERE evaluation_id = :evaluation_id 
                        AND criterion_id = :criterion_id
                    ");
                    
                    $stmt->execute([
                        ':lecturer_score' => $lecturer_score,
                        ':note' => $note,
                        ':evaluation_id' => $evaluation_id,
                        ':criterion_id' => $criterion_id
                    ]);
                }
                
                // Cập nhật trạng thái đánh giá thành 'evaluated' (đã được giảng viên đánh giá)
                $stmt = $pdo->prepare("
                    UPDATE evaluations 
                    SET status = 'evaluated', 
                        updated_at = NOW() 
                    WHERE id = :evaluation_id
                ");
                $stmt->execute([':evaluation_id' => $evaluation_id]);
                
                $pdo->commit();
                $success_message = "Đã lưu đánh giá thành công!";
                
                // Redirect để tránh submit lại khi refresh
                header("Location: ?success=1");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollback();
                $error_message = "Lỗi khi lưu đánh giá: " . $e->getMessage();
            }
        }
    }
    
    // Hiển thị thông báo success từ URL parameter
    if (isset($_GET['success'])) {
        $success_message = "Đã lưu đánh giá thành công!";
    }
    
    // LẤY DANH SÁCH KỲ HỌC
    $stmt = $pdo->prepare("
        SELECT id, academic_year, term_no, start_date, end_date, status 
        FROM terms 
        ORDER BY academic_year DESC, term_no DESC
    ");
    $stmt->execute();
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy kỳ học được chọn
    $selected_term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
    $current_term = null;
    
    if ($selected_term_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM terms WHERE id = :term_id");
        $stmt->execute([':term_id' => $selected_term_id]);
        $current_term = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // LẤY THÔNG TIN ĐÁNH GIÁ CỤ THỂ (khi giảng viên chọn đánh giá)
    $evaluation_detail = null;
    $evaluation_items = [];
    $criteria = ['parent' => [], 'child' => []];
    
    if (isset($_GET['eval_id']) && $selected_term_id > 0) {
        $eval_id = (int)$_GET['eval_id'];
        
        // Lấy thông tin đánh giá
        $stmt = $pdo->prepare("
            SELECT e.*, s.student_code, s.full_name as student_name,
                   c.code as class_code, c.name as class_name,
                   t.academic_year, t.term_no
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            JOIN terms t ON e.term_id = t.id
            WHERE e.id = :eval_id AND e.term_id = :term_id
            AND e.status IN ('submitted', 'evaluated')
        ");
        $stmt->execute([':eval_id' => $eval_id, ':term_id' => $selected_term_id]);
        $evaluation_detail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($evaluation_detail) {
            // Lấy các items đánh giá
            $stmt = $pdo->prepare("
                SELECT ei.*, c.name as criteria_name, c.max_point, c.parent_id
                FROM evaluation_items ei
                JOIN criteria c ON ei.criterion_id = c.id
                WHERE ei.evaluation_id = :eval_id
                ORDER BY c.order_no, c.id
            ");
            $stmt->execute([':eval_id' => $eval_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $evaluation_items[$item['criterion_id']] = $item;
            }
            
            // Lấy cấu trúc criteria
            $stmt = $pdo->prepare("
                SELECT * FROM criteria 
                WHERE is_active = 1 
                ORDER BY order_no, id
            ");
            $stmt->execute();
            $all_criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($all_criteria as $criterion) {
                if ($criterion['parent_id'] == 0) {
                    $criteria['parent'][] = $criterion;
                } else {
                    $criteria['child'][$criterion['parent_id']][] = $criterion;
                }
            }
        }
    }
    
    // LẤY DANH SÁCH ĐÁNH GIÁ CẦN XEM XÉT (đã được sinh viên nộp)
    $evaluations = [];
    if ($selected_term_id > 0) {
        $stmt = $pdo->prepare("
            SELECT e.id, e.status, e.created_at, e.updated_at,
                   s.student_code, s.full_name as student_name,
                   c.code as class_code, c.name as class_name,
                   t.academic_year, t.term_no,
                   SUM(COALESCE(ei.self_score, 0)) as total_self_score,
                   SUM(COALESCE(ei.lecturer_score, 0)) as total_lecturer_score
            FROM evaluations e
            JOIN students s ON e.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            JOIN terms t ON e.term_id = t.id
            LEFT JOIN evaluation_items ei ON e.id = ei.evaluation_id
            WHERE e.term_id = :term_id 
            AND e.status IN ('submitted', 'evaluated')
            GROUP BY e.id, e.status, e.created_at, e.updated_at,
                     s.student_code, s.full_name, c.code, c.name,
                     t.academic_year, t.term_no
            ORDER BY e.updated_at DESC
        ");
        $stmt->execute([':term_id' => $selected_term_id]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = "Lỗi database: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá sinh viên - Hệ thống quản lý điểm rèn luyện</title>
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
                <li><a href="evaluations.php">Điểm rèn luyện</a></li>
                <li><a href="lecturer_evaluation.php" class="active">Đánh giá sinh viên</a></li>
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="admin.php">Quản trị</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- NỘI DUNG CHÍNH -->
    <main class="main-content">
        <div class="container">
            
            <?php if (!empty($evaluation_detail)): ?>
                <!-- CHI TIẾT ĐÁNH GIÁ SINH VIÊN -->
                <a href="lecturer_evaluation.php?term_id=<?php echo $selected_term_id; ?>" class="back-link">← Quay lại danh sách</a>
                
                <section class="evaluation-detail">
                    <div class="page-header">
                        <h2>Đánh giá điểm rèn luyện sinh viên</h2>
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

                    <!-- FORM ĐÁNH GIÁ -->
                    <form method="POST" action="" class="lecturer-evaluation-form">
                        <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_detail['id']; ?>">
                        <input type="hidden" name="save_evaluation" value="1">
                        
                        <?php foreach ($criteria['parent'] as $parent): ?>
                            <div class="criteria-group">
                                <div class="criteria-header">
                                    <h4><?php echo htmlspecialchars($parent['name']); ?></h4>
                                    <?php if ($parent['max_point']): ?>
                                        <span class="max-point">Tối đa: <?php echo $parent['max_point']; ?> điểm</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (isset($criteria['child'][$parent['id']])): ?>
                                    <?php foreach ($criteria['child'][$parent['id']] as $child): ?>
                                        <?php if (isset($evaluation_items[$child['id']])): ?>
                                            <?php $item = $evaluation_items[$child['id']]; ?>
                                            <div class="criteria-item">
                                                <div class="criteria-info">
                                                    <label><?php echo htmlspecialchars($child['name']); ?></label>
                                                    <span class="max-point">Tối đa: <?php echo $child['max_point']; ?> điểm</span>
                                                </div>
                                                
                                                <div class="score-comparison">
                                                    <div class="score-input-group">
                                                        <label>Sinh viên tự đánh giá:</label>
                                                        <input type="number" value="<?php echo isset($item['self_score']) && $item['self_score'] !== null ? number_format($item['self_score'], 1) : '0.0'; ?>" 
                                                               readonly class="score-readonly" title="Điểm sinh viên đã tự đánh giá">
                                                        <span>điểm</span>
                                                        <?php if (!isset($item['self_score']) || $item['self_score'] === null): ?>
                                                            <small style="color: #dc3545; font-style: italic;">(Sinh viên chưa đánh giá)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="score-input-group">
                                                        <label>Giảng viên đánh giá:</label>
                                                        <input type="number" step="0.1" min="0" max="<?php echo $child['max_point']; ?>"
                                                               name="scores[<?php echo $child['id']; ?>][lecturer_score]"
                                                               value="<?php echo $item['lecturer_score'] ?? ''; ?>"
                                                               placeholder="0.0" class="score-input">
                                                        <span>điểm</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="note-section">
                                                    <label>Ghi chú đánh giá:</label>
                                                    <textarea name="scores[<?php echo $child['id']; ?>][note]" 
                                                              placeholder="Nhập ghi chú, lý do điều chỉnh điểm (nếu có)..."
                                                              class="note-textarea"><?php echo htmlspecialchars($item['note'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php if (isset($evaluation_items[$parent['id']])): ?>
                                        <?php $item = $evaluation_items[$parent['id']]; ?>
                                        <div class="criteria-item">
                                            <div class="score-comparison">
                                                <div class="score-input-group">
                                                    <label>Sinh viên tự đánh giá:</label>
                                                    <input type="number" value="<?php echo isset($item['self_score']) && $item['self_score'] !== null ? number_format($item['self_score'], 1) : '0.0'; ?>" 
                                                           readonly class="score-readonly" title="Điểm sinh viên đã tự đánh giá">
                                                    <span>điểm</span>
                                                    <?php if (!isset($item['self_score']) || $item['self_score'] === null): ?>
                                                        <small style="color: #dc3545; font-style: italic;">(Sinh viên chưa đánh giá)</small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="score-input-group">
                                                    <label>Giảng viên đánh giá:</label>
                                                    <input type="number" step="0.1" min="0" max="<?php echo $parent['max_point']; ?>"
                                                           name="scores[<?php echo $parent['id']; ?>][lecturer_score]"
                                                           value="<?php echo $item['lecturer_score'] ?? ''; ?>"
                                                           placeholder="0.0" class="score-input">
                                                    <span>điểm</span>
                                                </div>
                                            </div>
                                            
                                            <div class="note-section">
                                                <label>Ghi chú đánh giá:</label>
                                                <textarea name="scores[<?php echo $parent['id']; ?>][note]" 
                                                          placeholder="Nhập ghi chú, lý do điều chỉnh điểm (nếu có)..."
                                                          class="note-textarea"><?php echo htmlspecialchars($item['note'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-save">Lưu đánh giá</button>
                            <a href="lecturer_evaluation.php?term_id=<?php echo $selected_term_id; ?>" class="btn-cancel">Hủy</a>
                        </div>
                    </form>
                </section>

            <?php else: ?>
                <!-- DANH SÁCH ĐÁNH GIÁ CẦN XEM XÉT -->
                <section class="page-header">
                    <h2>Đánh giá điểm rèn luyện sinh viên</h2>
                    <p>Xem xét và đánh giá lại điểm rèn luyện mà sinh viên đã tự đánh giá</p>
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

                <!-- CHỌN KỲ HỌC -->
                <section class="term-selection">
                    <h3>Chọn kỳ học</h3>
                    <form method="GET" action="" class="term-form">
                        <div class="form-group">
                            <label for="term_id">Kỳ học:</label>
                            <select id="term_id" name="term_id" onchange="this.form.submit()">
                                <option value="">-- Chọn kỳ học --</option>
                                <?php foreach ($terms as $term): ?>
                                <option value="<?php echo $term['id']; ?>" 
                                        <?php echo ($selected_term_id == $term['id']) ? 'selected' : ''; ?>>
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

                <!-- DANH SÁCH ĐÁNH GIÁ -->
                <?php if (!empty($evaluations)): ?>
                    <section class="evaluations-list">
                        <h3>Danh sách đánh giá cần xem xét (<?php echo count($evaluations); ?> bản ghi)</h3>
                        
                        <table class="evaluation-table">
                            <thead>
                                <tr>
                                    <th>Mã SV</th>
                                    <th>Sinh viên</th>
                                    <th>Lớp</th>
                                    <th>Trạng thái</th>
                                    <th>Điểm tự đánh giá</th>
                                    <th>Điểm giảng viên</th>
                                    <th>Ngày nộp</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evaluations as $eval): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($eval['student_code']); ?></td>
                                    <td><?php echo htmlspecialchars($eval['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($eval['class_code']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $eval['status']; ?>">
                                            <?php 
                                            switch($eval['status']) {
                                                case 'submitted': echo 'Chờ đánh giá'; break;
                                                case 'evaluated': echo 'Đã đánh giá'; break;
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
                                            <span style="color: #999;">Chưa đánh giá</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($eval['updated_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="?term_id=<?php echo $selected_term_id; ?>&eval_id=<?php echo $eval['id']; ?>" 
                                           class="btn-evaluate">
                                            <?php echo ($eval['status'] === 'evaluated') ? 'Xem/Sửa' : 'Đánh giá'; ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                    
                <?php elseif ($selected_term_id > 0): ?>
                    <div class="no-evaluations">
                        <h3>Chưa có đánh giá nào</h3>
                        <p>Chưa có sinh viên nào nộp đánh giá cho kỳ học này.</p>
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
        // Tự động ẩn thông báo sau 5 giây
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
            
            // Validation cho input điểm
            const scoreInputs = document.querySelectorAll('.score-input');
            scoreInputs.forEach(function(input) {
                input.addEventListener('blur', function() {
                    const value = parseFloat(this.value);
                    const max = parseFloat(this.getAttribute('max'));
                    
                    if (value > max) {
                        alert('Điểm nhập (' + value + ') vượt quá điểm tối đa (' + max + ')');
                        this.value = max;
                        this.focus();
                    }
                });
                
                input.addEventListener('input', function() {
                    const value = parseFloat(this.value);
                    const max = parseFloat(this.getAttribute('max'));
                    
                    if (value > max) {
                        this.style.borderColor = '#dc3545';
                        this.style.backgroundColor = '#fff5f5';
                    } else {
                        this.style.borderColor = '#ced4da';
                        this.style.backgroundColor = 'white';
                    }
                });
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
            
            // Validation trước khi submit form
            const form = document.querySelector('.lecturer-evaluation-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let hasError = false;
                    
                    scoreInputs.forEach(function(input) {
                        const value = parseFloat(input.value);
                        const max = parseFloat(input.getAttribute('max'));
                        
                        if (value > max) {
                            hasError = true;
                            input.style.borderColor = '#dc3545';
                            input.style.backgroundColor = '#fff5f5';
                        }
                    });
                    
                    if (hasError) {
                        e.preventDefault();
                        alert('Vui lòng kiểm tra lại các điểm nhập không vượt quá giới hạn!');
                        return false;
                    }
                });
            }
        });
    </script>

</body>
</html>

<?php
/**
 * GIẢI THÍCH CODE CHO NGƯỜI MỚI:
 * 
 * 1. Trang này cho phép giảng viên xem và đánh giá lại điểm sinh viên đã tự đánh giá
 * 2. Có 2 giao diện chính: danh sách đánh giá và form đánh giá chi tiết
 * 3. Giảng viên có thể nhập điểm và ghi chú cho từng tiêu chí
 * 4. Hệ thống cập nhật status đánh giá thành 'evaluated' sau khi lưu
 * 5. Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
 */
?>