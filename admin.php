<?php
/**
 * TRANG QUẢN TRỊ HỆ THỐNG - admin.php
 * 
 * Chức năng dành cho admin:
 * - Quản lý người dùng (tạo, sửa, xóa)
 * - Quản lý tiêu chí đánh giá
 * - Quản lý kỳ học
 * - Xem thống kê tổng quan
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

// KIỂM TRA ĐĂNG NHẬP VÀ QUYỀN ADMIN
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Lấy thông tin user từ session
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// KHAI BÁO BIẾN VÀ KHỞI TẠO
$error_message = '';
$success_message = '';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// XỬ LÝ CÁC FORM ACTION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_user':
                // Tạo người dùng mới
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                
                if (empty($email) || empty($password) || empty($full_name)) {
                    throw new Exception('Vui lòng điền đầy đủ thông tin!');
                }
                
                // Mã hóa password bằng MD5
                $password_hash = md5($password);
                
                $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$email, $password_hash, $full_name, $role]);
                $user_id = $pdo->lastInsertId();
                
                // Tự động tạo record tương ứng trong bảng lecturers hoặc students
                if ($role === 'lecturer') {
                    $stmt = $pdo->prepare("INSERT INTO lecturers (full_name, email, user_id) VALUES (?, ?, ?)");
                    $stmt->execute([$full_name, $email, $user_id]);
                } elseif ($role === 'student') {
                    $stmt = $pdo->prepare("INSERT INTO students (full_name, email, user_id, class_id, student_code) VALUES (?, ?, ?, NULL, NULL)");
                    $stmt->execute([$full_name, $email, $user_id]);
                }
                
                $success_message = "Tạo người dùng thành công!";
                break;
                
            case 'update_user':
                // Cập nhật người dùng
                $user_id = (int)$_POST['user_id'];
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Lấy thông tin user cũ để so sánh role
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $old_user = $stmt->fetch(PDO::FETCH_ASSOC);
                $old_role = $old_user['role'];
                
                $stmt = $pdo->prepare("UPDATE users SET email = ?, full_name = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$email, $full_name, $role, $is_active, $user_id]);
                
                // Xử lý thay đổi role
                if ($old_role !== $role) {
                    // Xóa record cũ nếu có
                    if ($old_role === 'lecturer') {
                        $pdo->prepare("DELETE FROM lecturers WHERE user_id = ?")->execute([$user_id]);
                    } elseif ($old_role === 'student') {
                        $pdo->prepare("DELETE FROM students WHERE user_id = ?")->execute([$user_id]);
                    }
                    
                    // Tạo record mới
                    if ($role === 'lecturer') {
                        $stmt = $pdo->prepare("INSERT INTO lecturers (full_name, email, user_id) VALUES (?, ?, ?)");
                        $stmt->execute([$full_name, $email, $user_id]);
                    } elseif ($role === 'student') {
                        $stmt = $pdo->prepare("INSERT INTO students (full_name, email, user_id, class_id, student_code) VALUES (?, ?, ?, NULL, NULL)");
                        $stmt->execute([$full_name, $email, $user_id]);
                    }
                } else {
                    // Nếu role không đổi, chỉ cập nhật thông tin
                    if ($role === 'lecturer') {
                        $stmt = $pdo->prepare("UPDATE lecturers SET full_name = ?, email = ? WHERE user_id = ?");
                        $stmt->execute([$full_name, $email, $user_id]);
                    } elseif ($role === 'student') {
                        $stmt = $pdo->prepare("UPDATE students SET full_name = ?, email = ? WHERE user_id = ?");
                        $stmt->execute([$full_name, $email, $user_id]);
                    }
                }
                
                $success_message = "Cập nhật người dùng thành công!";
                break;
                
            case 'delete_user':
                // Xóa người dùng
                $user_id = (int)$_POST['user_id'];
                
                // Không cho phép xóa chính mình
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception('Không thể xóa tài khoản của chính mình!');
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $success_message = "Xóa người dùng thành công!";
                break;
                
            case 'create_faculty':
                // Tạo khoa mới
                $code = strtoupper(trim($_POST['code']));
                $name = trim($_POST['name']);
                
                if (empty($code) || empty($name)) {
                    throw new Exception('Vui lòng điền đầy đủ mã khoa và tên khoa!');
                }
                
                // Kiểm tra mã khoa trùng lặp
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculties WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Mã khoa đã tồn tại!');
                }
                
                $stmt = $pdo->prepare("INSERT INTO faculties (code, name) VALUES (?, ?)");
                $stmt->execute([$code, $name]);
                
                $success_message = "Tạo khoa '{$name}' thành công!";
                break;
                
            case 'update_faculty':
                // Cập nhật khoa
                $faculty_id = (int)$_POST['faculty_id'];
                $code = strtoupper(trim($_POST['code']));
                $name = trim($_POST['name']);
                
                if (empty($code) || empty($name)) {
                    throw new Exception('Vui lòng điền đầy đủ mã khoa và tên khoa!');
                }
                
                // Kiểm tra mã khoa trùng lặp (trừ chính nó)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculties WHERE code = ? AND id != ?");
                $stmt->execute([$code, $faculty_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Mã khoa đã tồn tại!');
                }
                
                $stmt = $pdo->prepare("UPDATE faculties SET code = ?, name = ? WHERE id = ?");
                $stmt->execute([$code, $name, $faculty_id]);
                
                $success_message = "Cập nhật khoa '{$name}' thành công!";
                break;
                
            case 'delete_faculty':
                // Xóa khoa
                $faculty_id = (int)$_POST['faculty_id'];
                
                // Kiểm tra khoa có lớp học nào không
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE faculty_id = ?");
                $stmt->execute([$faculty_id]);
                $class_count = $stmt->fetchColumn();
                
                if ($class_count > 0) {
                    throw new Exception("Không thể xóa khoa này vì còn {$class_count} lớp học đang thuộc khoa!");
                }
                
                // Lấy tên khoa trước khi xóa
                $stmt = $pdo->prepare("SELECT name FROM faculties WHERE id = ?");
                $stmt->execute([$faculty_id]);
                $faculty_name = $stmt->fetchColumn();
                
                if (!$faculty_name) {
                    throw new Exception('Khoa không tồn tại!');
                }
                
                $stmt = $pdo->prepare("DELETE FROM faculties WHERE id = ?");
                $stmt->execute([$faculty_id]);
                
                $success_message = "Xóa khoa '{$faculty_name}' thành công!";
                break;
                
            case 'create_term':
                // Tạo kỳ học mới
                $academic_year = trim($_POST['academic_year']);
                $term_no = (int)$_POST['term_no'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $status = $_POST['status'];
                
                // Validation: Kiểm tra năm học đúng format
                if (!preg_match('/^[0-9]{4}-[0-9]{4}$/', $academic_year)) {
                    throw new Exception('Năm học phải có định dạng YYYY-YYYY (ví dụ: 2024-2025)');
                }
                
                // Validation: Ngày kết thúc phải sau ngày bắt đầu
                if (strtotime($end_date) <= strtotime($start_date)) {
                    throw new Exception('Ngày kết thúc phải sau ngày bắt đầu!');
                }
                
                $stmt = $pdo->prepare("INSERT INTO terms (academic_year, term_no, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$academic_year, $term_no, $start_date, $end_date, $status]);
                
                $success_message = "Tạo kỳ học thành công!";
                break;
                
            case 'update_term':
                // Cập nhật kỳ học
                $term_id = (int)$_POST['term_id'];
                $academic_year = trim($_POST['academic_year']);
                $term_no = (int)$_POST['term_no'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $status = $_POST['status'];
                
                // Validation: Kiểm tra năm học đúng format
                if (!preg_match('/^[0-9]{4}-[0-9]{4}$/', $academic_year)) {
                    throw new Exception('Năm học phải có định dạng YYYY-YYYY');
                }
                
                // Validation: Ngày kết thúc phải sau ngày bắt đầu
                if (strtotime($end_date) <= strtotime($start_date)) {
                    throw new Exception('Ngày kết thúc phải sau ngày bắt đầu!');
                }
                
                $stmt = $pdo->prepare("UPDATE terms SET academic_year = ?, term_no = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
                $stmt->execute([$academic_year, $term_no, $start_date, $end_date, $status, $term_id]);
                
                $success_message = "Cập nhật kỳ học thành công!";
                break;
                
            case 'delete_term':
                // Xóa kỳ học
                $term_id = (int)$_POST['term_id'];
                
                // Kiểm tra xem kỳ học có đang được sử dụng không
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluations WHERE term_id = ?");
                $stmt->execute([$term_id]);
                $evaluation_count = $stmt->fetchColumn();
                
                if ($evaluation_count > 0) {
                    throw new Exception('Không thể xóa kỳ học này vì đã có ' . $evaluation_count . ' đánh giá liên quan!');
                }
                
                $stmt = $pdo->prepare("DELETE FROM terms WHERE id = ?");
                $stmt->execute([$term_id]);
                
                $success_message = "Xóa kỳ học thành công!";
                break;
                
            case 'create_criterion':
                // Tạo tiêu chí mới
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $name = trim($_POST['name']);
                $max_point = !empty($_POST['max_point']) ? (float)$_POST['max_point'] : null;
                $order_no = (int)$_POST['order_no'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation: Tên tiêu chí không được trống
                if (empty($name)) {
                    throw new Exception('Tên tiêu chí không được để trống!');
                }
                
                // Validation: Nếu là tiêu chí con thì kiểm tra parent tồn tại
                if ($parent_id !== null) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM criteria WHERE id = ?");
                    $stmt->execute([$parent_id]);
                    if ($stmt->fetchColumn() == 0) {
                        throw new Exception('Tiêu chí cha không tồn tại!');
                    }
                }
                
                // Validation: Điểm tối đa phải lớn hơn 0 (nếu có)
                if ($max_point !== null && $max_point <= 0) {
                    throw new Exception('Điểm tối đa phải lớn hơn 0!');
                }
                
                $stmt = $pdo->prepare("INSERT INTO criteria (parent_id, name, max_point, order_no, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$parent_id, $name, $max_point, $order_no, $is_active]);
                
                $success_message = "Tạo tiêu chí thành công!";
                break;
                
            case 'update_criterion':
                // Cập nhật tiêu chí
                $criterion_id = (int)$_POST['criterion_id'];
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $name = trim($_POST['name']);
                $max_point = !empty($_POST['max_point']) ? (float)$_POST['max_point'] : null;
                $order_no = (int)$_POST['order_no'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation: Tên tiêu chí không được trống
                if (empty($name)) {
                    throw new Exception('Tên tiêu chí không được để trống!');
                }
                
                // Validation: Không được đặt chính mình làm parent
                if ($parent_id == $criterion_id) {
                    throw new Exception('Tiêu chí không thể làm cha của chính nó!');
                }
                
                // Validation: Kiểm tra parent tồn tại (nếu có)
                if ($parent_id !== null) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM criteria WHERE id = ?");
                    $stmt->execute([$parent_id]);
                    if ($stmt->fetchColumn() == 0) {
                        throw new Exception('Tiêu chí cha không tồn tại!');
                    }
                }
                
                // Validation: Điểm tối đa phải lớn hơn 0 (nếu có)
                if ($max_point !== null && $max_point <= 0) {
                    throw new Exception('Điểm tối đa phải lớn hơn 0!');
                }
                
                $stmt = $pdo->prepare("UPDATE criteria SET parent_id = ?, name = ?, max_point = ?, order_no = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$parent_id, $name, $max_point, $order_no, $is_active, $criterion_id]);
                
                $success_message = "Cập nhật tiêu chí thành công!";
                break;
                
            case 'delete_criterion':
                // Xóa tiêu chí
                $criterion_id = (int)$_POST['criterion_id'];
                
                // Kiểm tra xem có tiêu chí con không
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM criteria WHERE parent_id = ?");
                $stmt->execute([$criterion_id]);
                $child_count = $stmt->fetchColumn();
                
                if ($child_count > 0) {
                    throw new Exception('Không thể xóa tiêu chí này vì còn có ' . $child_count . ' tiêu chí con!');
                }
                
                // Kiểm tra xem có đánh giá nào sử dụng tiêu chí này không
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluation_items WHERE criterion_id = ?");
                $stmt->execute([$criterion_id]);
                $evaluation_count = $stmt->fetchColumn();
                
                if ($evaluation_count > 0) {
                    throw new Exception('Không thể xóa tiêu chí này vì đã có ' . $evaluation_count . ' đánh giá sử dụng!');
                }
                
                $stmt = $pdo->prepare("DELETE FROM criteria WHERE id = ?");
                $stmt->execute([$criterion_id]);
                
                $success_message = "Xóa tiêu chí thành công!";
                break;
                
            case 'create_class':
                // Tạo lớp học mới
                $faculty_id = (int)$_POST['faculty_id'];
                $code = trim($_POST['code']);
                $name = trim($_POST['name']);
                $homeroom_lecturer_id = !empty($_POST['homeroom_lecturer_id']) ? (int)$_POST['homeroom_lecturer_id'] : null;
                
                // Validation: Các trường bắt buộc
                if (empty($code) || empty($name)) {
                    throw new Exception('Mã lớp và tên lớp không được để trống!');
                }
                
                // Validation: Kiểm tra mã lớp đã tồn tại
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Mã lớp đã tồn tại!');
                }
                
                // Validation: Kiểm tra khoa tồn tại
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculties WHERE id = ?");
                $stmt->execute([$faculty_id]);
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception('Khoa không tồn tại!');
                }
                
                // Validation: Kiểm tra giảng viên tồn tại (nếu có)
                if ($homeroom_lecturer_id !== null) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE id = ?");
                    $stmt->execute([$homeroom_lecturer_id]);
                    if ($stmt->fetchColumn() == 0) {
                        throw new Exception('Giảng viên chủ nhiệm không tồn tại!');
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO classes (faculty_id, code, name, homeroom_lecturer_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$faculty_id, $code, $name, $homeroom_lecturer_id]);
                
                $success_message = "Tạo lớp học thành công!";
                break;
                
            case 'update_class':
                // Cập nhật lớp học
                $class_id = (int)$_POST['class_id'];
                $faculty_id = (int)$_POST['faculty_id'];
                $code = trim($_POST['code']);
                $name = trim($_POST['name']);
                $homeroom_lecturer_id = !empty($_POST['homeroom_lecturer_id']) ? (int)$_POST['homeroom_lecturer_id'] : null;
                
                // Validation tương tự như create
                if (empty($code) || empty($name)) {
                    throw new Exception('Mã lớp và tên lớp không được để trống!');
                }
                
                // Kiểm tra mã lớp trùng (trừ chính nó)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE code = ? AND id != ?");
                $stmt->execute([$code, $class_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Mã lớp đã tồn tại!');
                }
                
                $stmt = $pdo->prepare("UPDATE classes SET faculty_id = ?, code = ?, name = ?, homeroom_lecturer_id = ? WHERE id = ?");
                $stmt->execute([$faculty_id, $code, $name, $homeroom_lecturer_id, $class_id]);
                
                $success_message = "Cập nhật lớp học thành công!";
                break;
                
            case 'delete_class':
                // Xóa lớp học
                $class_id = (int)$_POST['class_id'];
                
                // Kiểm tra có sinh viên trong lớp không
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
                $stmt->execute([$class_id]);
                $student_count = $stmt->fetchColumn();
                
                if ($student_count > 0) {
                    throw new Exception('Không thể xóa lớp học này vì còn có ' . $student_count . ' sinh viên!');
                }
                
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$class_id]);
                
                $success_message = "Xóa lớp học thành công!";
                break;
                
            case 'link_user_to_lecturer':
                // Liên kết tài khoản user với lecturer
                $user_id = (int)$_POST['user_id'];
                
                // Kiểm tra user tồn tại và là lecturer
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'lecturer' AND is_active = 1");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    throw new Exception('Tài khoản giảng viên không tồn tại hoặc không hoạt động!');
                }
                
                // Kiểm tra đã có lecturer record chưa
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Tài khoản này đã được liên kết với hồ sơ giảng viên!');
                }
                
                // Tạo lecturer record
                $stmt = $pdo->prepare("INSERT INTO lecturers (user_id, full_name, email) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $user['full_name'], $user['email']]);
                
                $success_message = "Liên kết tài khoản giảng viên thành công!";
                break;
                
            case 'link_user_to_student':
                // Liên kết tài khoản user với student
                $user_id = (int)$_POST['user_id'];
                $student_code = trim($_POST['student_code']);
                $class_id = (int)$_POST['class_id'];
                
                // Kiểm tra user tồn tại và là student
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' AND is_active = 1");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    throw new Exception('Tài khoản sinh viên không tồn tại hoặc không hoạt động!');
                }
                
                // Kiểm tra mã sinh viên trùng
                if (empty($student_code)) {
                    throw new Exception('Mã sinh viên không được để trống!');
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_code = ?");
                $stmt->execute([$student_code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Mã sinh viên đã tồn tại!');
                }
                
                // Kiểm tra đã có student record chưa
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Tài khoản này đã được liên kết với hồ sơ sinh viên!');
                }
                
                // Tạo student record
                $stmt = $pdo->prepare("INSERT INTO students (user_id, class_id, student_code, full_name, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $class_id, $student_code, $user['full_name'], $user['email']]);
                
                $success_message = "Liên kết tài khoản sinh viên thành công!";
                break;
                
            case 'get_class_members':
                try {
                    $class_id = $_POST['class_id'] ?? '';
                    
                    if (empty($class_id)) {
                        echo json_encode(['success' => false, 'error' => 'Thiếu thông tin class_id']);
                        exit;
                    }
                    
                    // Lấy thông tin giáo viên chủ nhiệm
                    $stmt = $pdo->prepare("SELECT l.id, l.full_name FROM classes c 
                                          LEFT JOIN lecturers l ON c.homeroom_lecturer_id = l.id 
                                          WHERE c.id = ?");
                    $stmt->execute([$class_id]);
                    $homeroom_lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Lấy danh sách sinh viên trong lớp
                    $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.student_code as code FROM students s 
                                          WHERE s.class_id = ? ORDER BY s.full_name");
                    $stmt->execute([$class_id]);
                    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Lấy danh sách sinh viên chưa có lớp
                    $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.student_code as code FROM students s 
                                          INNER JOIN users u ON s.user_id = u.id 
                                          WHERE s.class_id IS NULL AND u.is_active = 1 
                                          ORDER BY s.full_name");
                    $stmt->execute();
                    $unassigned_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'homeroom_lecturer' => ($homeroom_lecturer && !empty($homeroom_lecturer['full_name'])) ? $homeroom_lecturer : null,
                        'students' => $students,
                        'unassigned_students' => $unassigned_students
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => 'Lỗi database: ' . $e->getMessage()]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
                }
                exit;
                
            case 'assign_student_to_class':
                // Phân công sinh viên vào lớp
                $student_id = (int)$_POST['student_id'];
                $class_id = (int)$_POST['class_id'];
                
                // Kiểm tra sinh viên tồn tại
                $stmt = $pdo->prepare("SELECT s.*, u.full_name FROM students s INNER JOIN users u ON s.user_id = u.id WHERE s.id = ?");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$student) {
                    throw new Exception('Sinh viên không tồn tại!');
                }
                
                // Kiểm tra lớp tồn tại
                $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
                $stmt->execute([$class_id]);
                $class_name = $stmt->fetchColumn();
                
                if (!$class_name) {
                    throw new Exception('Lớp học không tồn tại!');
                }
                
                // Cập nhật lớp cho sinh viên
                $stmt = $pdo->prepare("UPDATE students SET class_id = ? WHERE id = ?");
                $stmt->execute([$class_id, $student_id]);
                
                $success_message = "Phân công sinh viên {$student['full_name']} vào lớp {$class_name} thành công!";
                break;
                
            case 'bulk_assign_students_to_class':
                try {
                    $class_id = $_POST['class_id'] ?? '';
                    $student_ids = $_POST['student_ids'] ?? '';
                    
                    if (empty($class_id)) {
                        echo json_encode(['success' => false, 'error' => 'Thiếu thông tin class_id']);
                        exit;
                    }
                    
                    if (empty($student_ids)) {
                        echo json_encode(['success' => false, 'error' => 'Vui lòng chọn ít nhất một sinh viên']);
                        exit;
                    }
                    
                    // Parse student IDs từ chuỗi comma-separated
                    $student_id_array = explode(',', $student_ids);
                    $student_id_array = array_map('intval', $student_id_array);
                    $student_id_array = array_filter($student_id_array); // Loại bỏ các giá trị 0
                    
                    if (empty($student_id_array)) {
                        echo json_encode(['success' => false, 'error' => 'Danh sách sinh viên không hợp lệ']);
                        exit;
                    }
                    
                    // Kiểm tra lớp tồn tại
                    $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
                    $stmt->execute([$class_id]);
                    $class_name = $stmt->fetchColumn();
                    
                    if (!$class_name) {
                        echo json_encode(['success' => false, 'error' => 'Lớp học không tồn tại']);
                        exit;
                    }
                    
                    // Bắt đầu transaction để đảm bảo tính nhất quán
                    $pdo->beginTransaction();
                    
                    $assigned_count = 0;
                    $skipped_students = [];
                    
                    foreach ($student_id_array as $student_id) {
                        // Kiểm tra sinh viên tồn tại và chưa có lớp
                        $stmt = $pdo->prepare("SELECT s.*, u.full_name FROM students s 
                                             INNER JOIN users u ON s.user_id = u.id 
                                             WHERE s.id = ? AND u.is_active = 1");
                        $stmt->execute([$student_id]);
                        $student = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$student) {
                            $skipped_students[] = "Sinh viên ID {$student_id} không tồn tại";
                            continue;
                        }
                        
                        if ($student['class_id']) {
                            $skipped_students[] = "{$student['full_name']} đã có lớp";
                            continue;
                        }
                        
                        // Cập nhật lớp cho sinh viên
                        $stmt = $pdo->prepare("UPDATE students SET class_id = ? WHERE id = ?");
                        $stmt->execute([$class_id, $student_id]);
                        $assigned_count++;
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $message = "Đã thêm {$assigned_count} sinh viên vào lớp {$class_name}";
                    if (!empty($skipped_students)) {
                        $message .= ". Bỏ qua: " . implode(', ', $skipped_students);
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => $message,
                        'assigned_count' => $assigned_count,
                        'skipped_count' => count($skipped_students)
                    ]);
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Lỗi database: ' . $e->getMessage()]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
                }
                exit;
                
            case 'remove_student_from_class':
                try {
                    // Loại sinh viên khỏi lớp
                    $student_id = (int)$_POST['student_id'];
                    
                    // Kiểm tra sinh viên tồn tại
                    $stmt = $pdo->prepare("SELECT s.*, u.full_name FROM students s INNER JOIN users u ON s.user_id = u.id WHERE s.id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$student) {
                        echo json_encode(['success' => false, 'error' => 'Sinh viên không tồn tại!']);
                        exit;
                    }
                    
                    // Đặt class_id = NULL
                    $stmt = $pdo->prepare("UPDATE students SET class_id = NULL WHERE id = ?");
                    $stmt->execute([$student_id]);
                    
                    echo json_encode(['success' => true, 'message' => "Loại sinh viên {$student['full_name']} khỏi lớp thành công!"]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => 'Lỗi database: ' . $e->getMessage()]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
                }
                exit;
                
            case 'change_homeroom_lecturer':
                try {
                    $class_id = $_POST['class_id'] ?? '';
                    $lecturer_id = isset($_POST['lecturer_id']) && !empty($_POST['lecturer_id']) ? (int)$_POST['lecturer_id'] : null;
                    
                    if (empty($class_id)) {
                        echo json_encode(['success' => false, 'error' => 'Thiếu thông tin class_id']);
                        exit;
                    }
                    
                    // Kiểm tra lớp tồn tại
                    $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
                    $stmt->execute([$class_id]);
                    $class_name = $stmt->fetchColumn();
                    
                    if (!$class_name) {
                        echo json_encode(['success' => false, 'error' => 'Lớp học không tồn tại']);
                        exit;
                    }
                    
                    // Kiểm tra giảng viên (nếu có)
                    if ($lecturer_id !== null) {
                        $stmt = $pdo->prepare("SELECT l.*, u.full_name FROM lecturers l INNER JOIN users u ON l.user_id = u.id WHERE l.id = ?");
                        $stmt->execute([$lecturer_id]);
                        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$lecturer) {
                            echo json_encode(['success' => false, 'error' => 'Giảng viên không tồn tại']);
                            exit;
                        }
                    }
                    
                    // Cập nhật GVCN
                    $stmt = $pdo->prepare("UPDATE classes SET homeroom_lecturer_id = ? WHERE id = ?");
                    $stmt->execute([$lecturer_id, $class_id]);
                    
                    $message = $lecturer_id ? 'Đặt chủ nhiệm thành công' : 'Bỏ chủ nhiệm thành công';
                    echo json_encode(['success' => true, 'message' => $message]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => 'Lỗi database: ' . $e->getMessage()]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
                }
                exit;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// TRUY VẤN DỮ LIỆU CHO DASHBOARD
try {
    // Thống kê tổng quan
    $stats = [];
    
    // Đếm users theo role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
    $user_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Tổng sinh viên
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $stats['total_students'] = $stmt->fetchColumn();
    
    // Tổng giảng viên
    $stmt = $pdo->query("SELECT COUNT(*) FROM lecturers");
    $stats['total_lecturers'] = $stmt->fetchColumn();
    
    // Tổng lớp học
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes");
    $stats['total_classes'] = $stmt->fetchColumn();
    
    // Tổng đánh giá
    $stmt = $pdo->query("SELECT COUNT(*) FROM evaluations");
    $stats['total_evaluations'] = $stmt->fetchColumn();
    
    // Lấy danh sách users để quản lý
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách kỳ học
    $stmt = $pdo->query("SELECT * FROM terms ORDER BY academic_year DESC, term_no DESC");
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách tiêu chí theo cấu trúc phân cấp
    $stmt = $pdo->query("SELECT c1.*, c2.name as parent_name 
                         FROM criteria c1 
                         LEFT JOIN criteria c2 ON c1.parent_id = c2.id 
                         ORDER BY COALESCE(c1.parent_id, c1.id), c1.order_no, c1.id");
    $criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách tiêu chí cha để làm dropdown
    $stmt = $pdo->query("SELECT * FROM criteria WHERE parent_id IS NULL ORDER BY order_no");
    $parent_criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách khoa
    $stmt = $pdo->query("SELECT * FROM faculties ORDER BY name");
    $faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách lớp học với thông tin khoa và giảng viên chủ nhiệm
    $stmt = $pdo->query("SELECT c.*, f.name as faculty_name, l.full_name as homeroom_lecturer_name 
                         FROM classes c 
                         LEFT JOIN faculties f ON c.faculty_id = f.id
                         LEFT JOIN lecturers l ON c.homeroom_lecturer_id = l.id
                         ORDER BY f.name, c.name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách giảng viên có tài khoản user
    $stmt = $pdo->query("SELECT l.*, u.email as user_email, u.is_active as user_active 
                         FROM lecturers l 
                         INNER JOIN users u ON l.user_id = u.id 
                         WHERE u.role = 'lecturer' AND u.is_active = 1
                         ORDER BY l.full_name");
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách sinh viên có tài khoản user với thông tin lớp
    $stmt = $pdo->query("SELECT s.*, c.name as class_name, c.code as class_code, u.email as user_email, u.is_active as user_active 
                         FROM students s 
                         INNER JOIN users u ON s.user_id = u.id 
                         LEFT JOIN classes c ON s.class_id = c.id 
                         WHERE u.role = 'student' AND u.is_active = 1
                         ORDER BY c.name, s.full_name");
    $students_with_class = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách users chưa có lecturer/student record
    $stmt = $pdo->query("SELECT u.* FROM users u 
                         LEFT JOIN lecturers l ON u.id = l.user_id 
                         WHERE u.role = 'lecturer' AND u.is_active = 1 AND l.user_id IS NULL
                         ORDER BY u.full_name");
    $unassigned_lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT u.* FROM users u 
                         LEFT JOIN students s ON u.id = s.user_id 
                         WHERE u.role = 'student' AND u.is_active = 1 AND s.user_id IS NULL
                         ORDER BY u.full_name");
    $unassigned_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách sinh viên chưa được phân lớp (có student record nhưng class_id = NULL)
    $stmt = $pdo->query("SELECT s.*, u.full_name as user_name, u.email as user_email 
                         FROM students s 
                         INNER JOIN users u ON s.user_id = u.id 
                         WHERE s.class_id IS NULL AND u.is_active = 1
                         ORDER BY s.full_name");
    $unassigned_students_to_class = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn database: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống - Hệ thống quản lý điểm rèn luyện</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS bổ sung cho trang admin */
        .admin-tabs {
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        
        .admin-tabs ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        
        .admin-tabs li a {
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            color: #666;
            border-right: 1px solid #ddd;
        }
        
        .admin-tabs li a.active,
        .admin-tabs li a:hover {
            background-color: #f8f9fa;
            color: #2c3e50;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }
        
        .admin-table th {
            background-color: #f8f9fa;
            font-weight: normal;
        }
        
        .admin-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .role-tag {
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 11px;
            color: white;
        }
        
        .role-admin { background-color: #e74c3c; }
        .role-lecturer { background-color: #3498db; }
        .role-student { background-color: #27ae60; }
        
        .status-active { color: #27ae60; }
        .status-inactive { color: #e74c3c; }
        
        .admin-form {
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 11px;
            margin-right: 5px;
        }
        
        .btn-edit { background-color: #f39c12; color: white; }
        .btn-delete { background-color: #e74c3c; color: white; }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 3px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .modal-header {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .modal-header h4 {
            margin: 0;
            color: #2c3e50;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-buttons {
            text-align: right;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .btn-cancel {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn-cancel:hover {
            background-color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .admin-tabs ul {
                flex-direction: column;
            }
            
            .admin-tabs li a {
                border-right: none;
                border-bottom: 1px solid #ddd;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
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
                <span class="role-badge">Admin</span>
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
                    <li><a href="evaluations.php">Xem kết quả</a></li>
                <?php else: ?>
                    <li><a href="evaluations.php">Điểm rèn luyện</a></li>
                    <li><a href="lecturer_evaluation.php">Đánh giá sinh viên</a></li>
                    <?php if ($user_role === 'admin'): ?>
                        <li><a href="admin.php" class="active">Quản trị</a></li>
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
                <h2>⚙️ Quản trị hệ thống</h2>
                <p>Quản lý người dùng, kỳ học và cấu hình hệ thống</p>
            </section>

            <!-- THÔNG BÁO -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- TABS ĐIỀU HƯỚNG -->
            <div class="admin-tabs">
                <ul>
                    <li><a href="?tab=dashboard" class="<?php echo $current_tab == 'dashboard' ? 'active' : ''; ?>">📊 Tổng quan</a></li>
                    <li><a href="?tab=users" class="<?php echo $current_tab == 'users' ? 'active' : ''; ?>">👥 Người dùng</a></li>
                    <li><a href="?tab=faculties" class="<?php echo $current_tab == 'faculties' ? 'active' : ''; ?>">🏛️ Quản lý khoa</a></li>
                    <li><a href="?tab=classes" class="<?php echo $current_tab == 'classes' ? 'active' : ''; ?>">🏫 Lớp học</a></li>
                    <li><a href="?tab=terms" class="<?php echo $current_tab == 'terms' ? 'active' : ''; ?>">📅 Kỳ học</a></li>
                    <li><a href="?tab=criteria" class="<?php echo $current_tab == 'criteria' ? 'active' : ''; ?>">📋 Tiêu chí</a></li>
                </ul>
            </div>

            <!-- TAB CONTENT -->
            <?php if ($current_tab == 'dashboard'): ?>
                <!-- TỔNG QUAN -->
                <section class="dashboard-section">
                    <h3>📊 Thống kê tổng quan</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($stats['total_students']); ?></div>
                            <div class="stat-label">👨‍🎓 Sinh viên</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($stats['total_lecturers']); ?></div>
                            <div class="stat-label">👨‍🏫 Giảng viên</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($stats['total_classes']); ?></div>
                            <div class="stat-label">🏫 Lớp học</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($stats['total_evaluations']); ?></div>
                            <div class="stat-label">📝 Đánh giá</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($user_stats['admin'] ?? 0); ?></div>
                            <div class="stat-label">👑 Admin</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($user_stats['lecturer'] ?? 0); ?></div>
                            <div class="stat-label">🎓 Lecturer</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($user_stats['student'] ?? 0); ?></div>
                            <div class="stat-label">📚 Student</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format(array_sum($user_stats)); ?></div>
                            <div class="stat-label">👤 Tổng users</div>
                        </div>
                    </div>
                </section>

            <?php elseif ($current_tab == 'users'): ?>
                <!-- QUẢN LÝ NGƯỜI DÙNG -->
                <section class="users-section">
                    <h3>👥 Quản lý người dùng</h3>
                    
                    <div style="background: #e8f4fd; border: 1px solid #b8daff; border-radius: 3px; padding: 15px; margin-bottom: 20px;">
                        <h5 style="margin: 0 0 10px 0; color: #004085;">💡 Hướng dẫn sử dụng</h5>
                        <p style="margin: 0; color: #004085; font-size: 14px;">
                            <strong>Bước 1:</strong> Tạo tài khoản người dùng ở đây với role "lecturer" hoặc "student"<br>
                            <strong>Bước 2:</strong> Vào tab "🏫 Lớp học" để liên kết tài khoản với hồ sơ học tập và phân công lớp
                        </p>
                    </div>
                    
                    <!-- FORM TẠO USER MỚI -->
                    <div class="admin-form">
                        <h4>➕ Tạo người dùng mới</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_user">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="password">Mật khẩu:</label>
                                    <input type="password" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name">Họ và tên:</label>
                                    <input type="text" id="full_name" name="full_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="role">Vai trò:</label>
                                    <select id="role" name="role" required>
                                        <option value="student">Sinh viên</option>
                                        <option value="lecturer">Giảng viên</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn-save">💾 Tạo người dùng</button>
                        </form>
                    </div>

                    <!-- DANH SÁCH USERS -->
                    <div class="users-list">
                        <h4>📋 Danh sách người dùng</h4>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Họ và tên</th>
                                    <th>Vai trò</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td>
                                        <span class="role-tag role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Hoạt động' : 'Tạm khóa'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn-small btn-edit" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>', <?php echo $user['is_active']; ?>)">✏️ Sửa</button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn-small btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">🗑️ Xóa</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            <?php elseif ($current_tab == 'faculties'): ?>
                <!-- QUẢN LÝ KHOA -->
                <section class="faculties-section">
                    <h3>🏛️ Quản lý khoa</h3>
                    
                    <!-- FORM TẠO KHOA MỚI -->
                    <div class="admin-form">
                        <h4>➕ Tạo khoa mới</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_faculty">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="faculty_code">🏷️ Mã khoa:</label>
                                    <input type="text" id="faculty_code" name="code" 
                                           placeholder="Ví dụ: CNTT, KTPM..." 
                                           style="text-transform: uppercase;" 
                                           maxlength="10" required>
                                </div>
                                <div class="form-group">
                                    <label for="faculty_name">📝 Tên khoa:</label>
                                    <input type="text" id="faculty_name" name="name" 
                                           placeholder="Ví dụ: Công nghệ thông tin" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-save">➕ Tạo khoa</button>
                        </form>
                    </div>

                    <!-- DANH SÁCH KHOA -->
                    <div class="admin-table-container">
                        <h4>📋 Danh sách khoa</h4>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Mã khoa</th>
                                    <th>Tên khoa</th>
                                    <th>Số lớp học</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($faculties)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #999; font-style: italic;">
                                        Chưa có khoa nào. Hãy tạo khoa đầu tiên.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($faculties as $faculty): ?>
                                <tr>
                                    <td><?php echo $faculty['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($faculty['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                    <td>
                                        <?php
                                        // Đếm số lớp học thuộc khoa này
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE faculty_id = ?");
                                        $stmt->execute([$faculty['id']]);
                                        $class_count = $stmt->fetchColumn();
                                        echo $class_count > 0 ? 
                                            '<span style="color: #28a745; font-weight: bold;">' . $class_count . ' lớp</span>' : 
                                            '<span style="color: #6c757d;">0 lớp</span>';
                                        ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($faculty['created_at'])); ?></td>
                                    <td>
                                        <button class="btn-small btn-edit" 
                                                onclick="editFaculty(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($faculty['name'], ENT_QUOTES); ?>')">✏️ Sửa</button>
                                        <button class="btn-small btn-delete" 
                                                onclick="deleteFaculty(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['name'], ENT_QUOTES); ?>')">🗑️ Xóa</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            <?php elseif ($current_tab == 'classes'): ?>
                <!-- QUẢN LÝ LỚP HỌC -->
                <section class="classes-section">
                    <h3>🏫 Quản lý lớp học</h3>
                    
                    <!-- FORM TẠO LỚP HỌC MỚI -->
                    <div class="admin-form">
                        <h4>➕ Tạo lớp học mới</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_class">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="faculty_id">🏛️ Khoa:</label>
                                    <select id="faculty_id" name="faculty_id" required>
                                        <option value="">— Chọn khoa —</option>
                                        <?php foreach ($faculties as $faculty): ?>
                                            <option value="<?php echo $faculty['id']; ?>">
                                                <?php echo htmlspecialchars($faculty['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="homeroom_lecturer_id">👨‍🏫 Giảng viên chủ nhiệm:</label>
                                    <select id="homeroom_lecturer_id" name="homeroom_lecturer_id">
                                        <option value="">— Chưa phân công —</option>
                                        <?php foreach ($lecturers as $lecturer): ?>
                                            <option value="<?php echo $lecturer['id']; ?>">
                                                <?php echo htmlspecialchars($lecturer['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="code">🔤 Mã lớp:</label>
                                    <input type="text" id="code" name="code" 
                                           placeholder="Ví dụ: CNTT01, KT02..." required>
                                </div>
                                <div class="form-group">
                                    <label for="name">📚 Tên lớp:</label>
                                    <input type="text" id="name" name="name" 
                                           placeholder="Ví dụ: Công nghệ thông tin 01..." required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-save">💾 Tạo lớp học</button>
                        </form>
                    </div>

                    <!-- DANH SÁCH LỚP HỌC -->
                    <div class="classes-list">
                        <h4>📋 Danh sách lớp học</h4>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Mã lớp</th>
                                    <th>Tên lớp</th>
                                    <th>Khoa</th>
                                    <th>GVCN</th>
                                    <th>Sĩ số</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?php echo $class['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($class['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($class['name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['faculty_name']); ?></td>
                                    <td>
                                        <?php if ($class['homeroom_lecturer_name']): ?>
                                            <?php echo htmlspecialchars($class['homeroom_lecturer_name']); ?>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Chưa phân công</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Đếm số sinh viên trong lớp
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
                                        $stmt->execute([$class['id']]);
                                        $student_count = $stmt->fetchColumn();
                                        
                                        if ($student_count > 0) {
                                            echo '<span style="color: #27ae60; font-weight: bold;">' . $student_count . ' SV</span>';
                                        } else {
                                            echo '<span style="color: #e74c3c;">0 SV</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn-small btn-edit" 
                                                onclick="editClass(<?php echo $class['id']; ?>, <?php echo $class['faculty_id']; ?>, '<?php echo htmlspecialchars($class['code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($class['name'], ENT_QUOTES); ?>', <?php echo $class['homeroom_lecturer_id'] ?? 'null'; ?>)">✏️ Sửa</button>
                                        <button class="btn-small" style="background-color: #17a2b8; color: white;" 
                                                onclick="manageClassMembers(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['name'], ENT_QUOTES); ?>')">👥 Quản lý thành viên</button>
                                        <button class="btn-small btn-delete" 
                                                onclick="deleteClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['name'], ENT_QUOTES); ?>')">🗑️ Xóa</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>


                </section>

                <!-- MODAL SỬA LỚP HỌC -->
                <div id="editClassModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>✏️ Sửa thông tin lớp học</h4>
                            <span class="close" onclick="closeClassModal()">&times;</span>
                        </div>
                        
                        <form method="POST" action="" id="editClassForm">
                            <input type="hidden" name="action" value="update_class">
                            <input type="hidden" name="class_id" id="edit_class_id">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_faculty_id">🏛️ Khoa:</label>
                                    <select id="edit_faculty_id" name="faculty_id" required>
                                        <?php foreach ($faculties as $faculty): ?>
                                            <option value="<?php echo $faculty['id']; ?>">
                                                <?php echo htmlspecialchars($faculty['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit_homeroom_lecturer_id">👨‍🏫 GVCN:</label>
                                    <select id="edit_homeroom_lecturer_id" name="homeroom_lecturer_id">
                                        <option value="">— Chưa phân công —</option>
                                        <?php foreach ($lecturers as $lecturer): ?>
                                            <option value="<?php echo $lecturer['id']; ?>">
                                                <?php echo htmlspecialchars($lecturer['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_code">🔤 Mã lớp:</label>
                                    <input type="text" id="edit_code" name="code" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_name">📚 Tên lớp:</label>
                                    <input type="text" id="edit_name" name="name" required>
                                </div>
                            </div>
                            
                            <div class="modal-buttons">
                                <button type="button" class="btn-cancel" onclick="closeClassModal()">Hủy</button>
                                <button type="submit" class="btn-save">💾 Cập nhật lớp học</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- MODAL XEM THÀNH VIÊN LỚP -->
                <div id="classMembersModal" class="modal">
                    <div class="modal-content" style="max-width: 800px;">
                        <div class="modal-header">
                            <h4>👥 Thành viên lớp <span id="modal_class_name"></span></h4>
                            <span class="close" onclick="closeClassMembersModal()">&times;</span>
                        </div>
                        
                        <div id="class_members_content">
                            <!-- Nội dung sẽ được load bằng JavaScript -->
                        </div>
                    </div>
                </div>

            <?php elseif ($current_tab == 'terms'): ?>
                <!-- QUẢN LÝ KỲ HỌC -->
                <section class="terms-section">
                    <h3>📅 Quản lý kỳ học</h3>
                    
                    <!-- FORM TẠO KỲ HỌC MỚI -->
                    <div class="admin-form">
                        <h4>➕ Tạo kỳ học mới</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_term">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="academic_year">Năm học:</label>
                                    <input type="text" id="academic_year" name="academic_year" 
                                           placeholder="2024-2025" pattern="[0-9]{4}-[0-9]{4}" required>
                                </div>
                                <div class="form-group">
                                    <label for="term_no">Kỳ:</label>
                                    <select id="term_no" name="term_no" required>
                                        <option value="1">Kỳ 1</option>
                                        <option value="2">Kỳ 2</option>
                                        <option value="3">Kỳ hè</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start_date">Ngày bắt đầu:</label>
                                    <input type="date" id="start_date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="end_date">Ngày kết thúc:</label>
                                    <input type="date" id="end_date" name="end_date" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="status">Trạng thái:</label>
                                <select id="status" name="status" required>
                                    <option value="upcoming">Sắp tới</option>
                                    <option value="open">Đang mở</option>
                                    <option value="closed">Đã đóng</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-save">💾 Tạo kỳ học</button>
                        </form>
                    </div>

                    <!-- DANH SÁCH KỲ HỌC -->
                    <div class="terms-list">
                        <h4>📋 Danh sách kỳ học</h4>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Năm học</th>
                                    <th>Kỳ</th>
                                    <th>Thời gian</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($terms as $term): ?>
                                <tr>
                                    <td><?php echo $term['id']; ?></td>
                                    <td><?php echo htmlspecialchars($term['academic_year']); ?></td>
                                    <td>Kỳ <?php echo $term['term_no']; ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($term['start_date'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($term['end_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $term['status']; ?>">
                                            <?php echo ucfirst($term['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-small btn-edit" 
                                                onclick="editTerm(<?php echo $term['id']; ?>, '<?php echo htmlspecialchars($term['academic_year'], ENT_QUOTES); ?>', <?php echo $term['term_no']; ?>, '<?php echo $term['start_date']; ?>', '<?php echo $term['end_date']; ?>', '<?php echo $term['status']; ?>')">✏️ Sửa</button>
                                        <button class="btn-small btn-delete" 
                                                onclick="deleteTerm(<?php echo $term['id']; ?>, '<?php echo htmlspecialchars($term['academic_year'], ENT_QUOTES); ?> - Kỳ <?php echo $term['term_no']; ?>')">🗑️ Xóa</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- MODAL SỬA KỲ HỌC -->
                <div id="editTermModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>✏️ Sửa thông tin kỳ học</h4>
                            <span class="close" onclick="closeTermModal()">&times;</span>
                        </div>
                        
                        <form method="POST" action="" id="editTermForm">
                            <input type="hidden" name="action" value="update_term">
                            <input type="hidden" name="term_id" id="edit_term_id">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_academic_year">📅 Năm học:</label>
                                    <input type="text" id="edit_academic_year" name="academic_year" 
                                           placeholder="2024-2025" pattern="[0-9]{4}-[0-9]{4}" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_term_no">📚 Kỳ:</label>
                                    <select id="edit_term_no" name="term_no" required>
                                        <option value="1">Kỳ 1</option>
                                        <option value="2">Kỳ 2</option>
                                        <option value="3">Kỳ hè</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_start_date">🗓️ Ngày bắt đầu:</label>
                                    <input type="date" id="edit_start_date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_end_date">📅 Ngày kết thúc:</label>
                                    <input type="date" id="edit_end_date" name="end_date" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_status">🔄 Trạng thái:</label>
                                <select id="edit_status" name="status" required>
                                    <option value="upcoming">Sắp tới</option>
                                    <option value="open">Đang mở</option>
                                    <option value="closed">Đã đóng</option>
                                </select>
                            </div>
                            
                            <div class="modal-buttons">
                                <button type="button" class="btn-cancel" onclick="closeTermModal()">Hủy</button>
                                <button type="submit" class="btn-save">💾 Cập nhật kỳ học</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($current_tab == 'criteria'): ?>
                <!-- QUẢN LÝ TIÊU CHÍ -->
                <section class="criteria-section">
                    <h3>📋 Quản lý tiêu chí đánh giá</h3>
                    
                    <!-- FORM TẠO TIÊU CHÍ MỚI -->
                    <div class="admin-form">
                        <h4>➕ Tạo tiêu chí mới</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_criterion">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="parent_id">🌳 Tiêu chí cha:</label>
                                    <select id="parent_id" name="parent_id">
                                        <option value="">— Tiêu chí chính (không có cha) —</option>
                                        <?php foreach ($parent_criteria as $parent): ?>
                                            <option value="<?php echo $parent['id']; ?>">
                                                <?php echo htmlspecialchars($parent['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="order_no">🔢 Thứ tự:</label>
                                    <input type="number" id="order_no" name="order_no" 
                                           value="1" min="1" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="name">📝 Tên tiêu chí:</label>
                                <input type="text" id="name" name="name" 
                                       placeholder="Ví dụ: Ý thức học tập, Tham gia hoạt động..." required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_point">🎯 Điểm tối đa:</label>
                                    <input type="number" id="max_point" name="max_point" 
                                           step="0.01" min="0" placeholder="Ví dụ: 25.00">
                                    <small style="color: #666;">Bỏ trống nếu tiêu chí cha không có điểm</small>
                                </div>
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="is_active" value="1" checked>
                                        ✅ Tiêu chí hoạt động
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-save">💾 Tạo tiêu chí</button>
                        </form>
                    </div>
                    
                    <!-- DANH SÁCH TIÊU CHÍ -->
                    <div class="criteria-list">
                        <h4>📋 Danh sách tiêu chí hiện tại</h4>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên tiêu chí</th>
                                    <th>Tiêu chí cha</th>
                                    <th>Điểm tối đa</th>
                                    <th>Thứ tự</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($criteria as $criterion): ?>
                                <tr>
                                    <td><?php echo $criterion['id']; ?></td>
                                    <td>
                                        <?php 
                                        // Hiển thị phân cấp bằng indent
                                        $indent = $criterion['parent_id'] ? '&nbsp;&nbsp;&nbsp;&nbsp;└─ ' : '';
                                        echo $indent . htmlspecialchars($criterion['name']); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($criterion['parent_name']): ?>
                                            <span style="color: #666; font-size: 12px;">
                                                <?php echo htmlspecialchars($criterion['parent_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Tiêu chí chính</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($criterion['max_point']): ?>
                                            <?php echo number_format($criterion['max_point'], 1); ?> điểm
                                        <?php else: ?>
                                            <span style="color: #999;">Không có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $criterion['order_no']; ?></td>
                                    <td>
                                        <span class="<?php echo $criterion['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $criterion['is_active'] ? 'Hoạt động' : 'Tạm dừng'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-small btn-edit" 
                                                onclick="editCriterion(<?php echo $criterion['id']; ?>, <?php echo $criterion['parent_id'] ?? 'null'; ?>, '<?php echo htmlspecialchars($criterion['name'], ENT_QUOTES); ?>', <?php echo $criterion['max_point'] ?? 'null'; ?>, <?php echo $criterion['order_no']; ?>, <?php echo $criterion['is_active']; ?>)">✏️ Sửa</button>
                                        <button class="btn-small btn-delete" 
                                                onclick="deleteCriterion(<?php echo $criterion['id']; ?>, '<?php echo htmlspecialchars($criterion['name'], ENT_QUOTES); ?>')">🗑️ Xóa</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- MODAL SỬA TIÊU CHÍ -->
                <div id="editCriterionModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4>✏️ Sửa thông tin tiêu chí</h4>
                            <span class="close" onclick="closeCriterionModal()">&times;</span>
                        </div>
                        
                        <form method="POST" action="" id="editCriterionForm">
                            <input type="hidden" name="action" value="update_criterion">
                            <input type="hidden" name="criterion_id" id="edit_criterion_id">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_parent_id">🌳 Tiêu chí cha:</label>
                                    <select id="edit_parent_id" name="parent_id">
                                        <option value="">— Tiêu chí chính (không có cha) —</option>
                                        <?php foreach ($parent_criteria as $parent): ?>
                                            <option value="<?php echo $parent['id']; ?>">
                                                <?php echo htmlspecialchars($parent['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit_order_no">🔢 Thứ tự:</label>
                                    <input type="number" id="edit_order_no" name="order_no" 
                                           min="1" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_name">📝 Tên tiêu chí:</label>
                                <input type="text" id="edit_name" name="name" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_max_point">🎯 Điểm tối đa:</label>
                                    <input type="number" id="edit_max_point" name="max_point" 
                                           step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                                        ✅ Tiêu chí hoạt động
                                    </label>
                                </div>
                            </div>
                            
                            <div class="modal-buttons">
                                <button type="button" class="btn-cancel" onclick="closeCriterionModal()">Hủy</button>
                                <button type="submit" class="btn-save">💾 Cập nhật tiêu chí</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- MODAL SỬA NGƯỜI DÙNG -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>✏️ Sửa thông tin người dùng</h4>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_full_name">Họ và tên:</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Vai trò:</label>
                    <select id="edit_role" name="role" required>
                        <option value="student">Sinh viên</option>
                        <option value="lecturer">Giảng viên</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                        ✅ Tài khoản hoạt động
                    </label>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                    <button type="submit" class="btn-save">💾 Cập nhật</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL SỬA NGƯỜI DÙNG -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>✏️ Sửa thông tin người dùng</h4>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_full_name">Họ và tên:</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Vai trò:</label>
                    <select id="edit_role" name="role" required>
                        <option value="student">Sinh viên</option>
                        <option value="lecturer">Giảng viên</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                        ✅ Tài khoản hoạt động
                    </label>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                    <button type="submit" class="btn-save">💾 Cập nhật</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL SỬA KHOA -->
    <div id="editFacultyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>✏️ Sửa thông tin khoa</h4>
                <span class="close" onclick="closeFacultyModal()">&times;</span>
            </div>
            
            <form method="POST" action="" id="editFacultyForm">
                <input type="hidden" name="action" value="update_faculty">
                <input type="hidden" name="faculty_id" id="edit_faculty_id">
                
                <div class="form-group">
                    <label for="edit_faculty_code">🏷️ Mã khoa:</label>
                    <input type="text" id="edit_faculty_code" name="code" 
                           style="text-transform: uppercase;" 
                           maxlength="10" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_faculty_name">📝 Tên khoa:</label>
                    <input type="text" id="edit_faculty_name" name="name" required>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeFacultyModal()">Hủy</button>
                    <button type="submit" class="btn-save">💾 Cập nhật</button>
                </div>
            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Hệ thống quản lý điểm rèn luyện. Được phát triển cho mục đích học tập.</p>
        </div>
    </footer>

    <script>
        // JavaScript đơn giản cho admin panel
        
        // QUẢN LÝ NGƯỜI DÙNG (User Management Functions)
        function editUser(userId, email, fullName, role, isActive) {
            // Điền dữ liệu vào form sửa user
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_is_active').checked = isActive == 1;
            
            // Hiển thị modal sửa user
            document.getElementById('editUserModal').style.display = 'block';
        }
        
        function closeEditModal() {
            // Đóng modal sửa user
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        function deleteUser(userId) {
            if (confirm('Bạn có chắc chắn muốn xóa người dùng này?')) {
                // Tạo form ẩn để submit xóa user
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                form.appendChild(actionInput);
                
                var userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // QUẢN LÝ KHOA (Faculty Management Functions)
        function editFaculty(facultyId, code, name) {
            // Điền dữ liệu vào form sửa khoa
            document.getElementById('edit_faculty_id').value = facultyId;
            document.getElementById('edit_faculty_code').value = code;
            document.getElementById('edit_faculty_name').value = name;
            
            // Hiển thị modal sửa khoa
            document.getElementById('editFacultyModal').style.display = 'block';
        }
        
        function closeFacultyModal() {
            // Đóng modal sửa khoa
            document.getElementById('editFacultyModal').style.display = 'none';
        }
        
        function deleteFaculty(facultyId, facultyName) {
            if (confirm('Bạn có chắc chắn muốn xóa khoa "' + facultyName + '"?\n\nCảnh báo: Không thể xóa khoa đang có lớp học!')) {
                // Tạo form ẩn để submit xóa khoa
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_faculty';
                form.appendChild(actionInput);
                
                var facultyIdInput = document.createElement('input');
                facultyIdInput.type = 'hidden';
                facultyIdInput.name = 'faculty_id';
                facultyIdInput.value = facultyId;
                form.appendChild(facultyIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // QUẢN LÝ KỲ HỌC (Terms Management Functions)
        function editTerm(termId, academicYear, termNo, startDate, endDate, status) {
            // Điền dữ liệu vào form sửa kỳ học
            document.getElementById('edit_term_id').value = termId;
            document.getElementById('edit_academic_year').value = academicYear;
            document.getElementById('edit_term_no').value = termNo;
            document.getElementById('edit_start_date').value = startDate;
            document.getElementById('edit_end_date').value = endDate;
            document.getElementById('edit_status').value = status;
            
            // Hiển thị modal sửa kỳ học
            document.getElementById('editTermModal').style.display = 'block';
        }
        
        function closeTermModal() {
            // Đóng modal sửa kỳ học
            document.getElementById('editTermModal').style.display = 'none';
        }
        
        function deleteTerm(termId, termName) {
            if (confirm('Bạn có chắc chắn muốn xóa kỳ học "' + termName + '"?\n\nCảnh báo: Thao tác này không thể hoàn tác!')) {
                // Tạo form ẩn để submit xóa kỳ học
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_term';
                form.appendChild(actionInput);
                
                var termIdInput = document.createElement('input');
                termIdInput.type = 'hidden';
                termIdInput.name = 'term_id';
                termIdInput.value = termId;
                form.appendChild(termIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // QUẢN LÝ TIÊU CHÍ (Criteria Management Functions)
        function editCriterion(criterionId, parentId, name, maxPoint, orderNo, isActive) {
            // Điền dữ liệu vào form sửa tiêu chí
            document.getElementById('edit_criterion_id').value = criterionId;
            document.getElementById('edit_parent_id').value = parentId || '';
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_max_point').value = maxPoint || '';
            document.getElementById('edit_order_no').value = orderNo;
            document.getElementById('edit_is_active').checked = isActive == 1;
            
            // Hiển thị modal sửa tiêu chí
            document.getElementById('editCriterionModal').style.display = 'block';
        }
        
        function closeCriterionModal() {
            // Đóng modal sửa tiêu chí
            document.getElementById('editCriterionModal').style.display = 'none';
        }
        
        function deleteCriterion(criterionId, criterionName) {
            if (confirm('Bạn có chắc chắn muốn xóa tiêu chí "' + criterionName + '"?\n\nLưu ý: Không thể xóa nếu:\n- Có tiêu chí con\n- Đã có đánh giá sử dụng\n\nThao tác này không thể hoàn tác!')) {
                // Tạo form ẩn để submit xóa tiêu chí
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_criterion';
                form.appendChild(actionInput);
                
                var criterionIdInput = document.createElement('input');
                criterionIdInput.type = 'hidden';
                criterionIdInput.name = 'criterion_id';
                criterionIdInput.value = criterionId;
                form.appendChild(criterionIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // QUẢN LÝ LỚP HỌC (Classes Management Functions)
        function editClass(classId, facultyId, code, name, homeroomLecturerId) {
            // Điền dữ liệu vào form sửa lớp học
            document.getElementById('edit_class_id').value = classId;
            document.getElementById('edit_faculty_id').value = facultyId;
            document.getElementById('edit_code').value = code;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_homeroom_lecturer_id').value = homeroomLecturerId || '';
            
            // Hiển thị modal sửa lớp học
            document.getElementById('editClassModal').style.display = 'block';
        }
        
        function closeClassModal() {
            // Đóng modal sửa lớp học
            document.getElementById('editClassModal').style.display = 'none';
        }
        
        function deleteClass(classId, className) {
            if (confirm('Bạn có chắc chắn muốn xóa lớp học "' + className + '"?\n\nLưu ý: Không thể xóa nếu lớp còn có sinh viên!\n\nThao tác này không thể hoàn tác!')) {
                // Tạo form ẩn để submit xóa lớp học
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_class';
                form.appendChild(actionInput);
                
                var classIdInput = document.createElement('input');
                classIdInput.type = 'hidden';
                classIdInput.name = 'class_id';
                classIdInput.value = classId;
                form.appendChild(classIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function manageClassMembers(classId, className) {
            // Hiển thị tên lớp trong modal
            document.getElementById('modal_class_name').textContent = className;
            
            // Load thành viên lớp qua AJAX
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_class_members&class_id=' + classId
            })
            .then(response => response.json())
            .then(data => {
                var content = '<div style="padding: 20px;">';
                
                // Hiển thị giáo viên chủ nhiệm
                content += '<h5>👨‍� Giáo viên chủ nhiệm</h5>';
                if (data.homeroom_lecturer) {
                    content += '<div style="background: #e8f5e8; padding: 10px; border-radius: 5px; margin-bottom: 15px;">';
                    content += '<strong>' + data.homeroom_lecturer.full_name + '</strong>';
                    content += '<button class="btn-small btn-delete" style="float: right; margin-left: 10px;" onclick="changeHomeroomLecturer(' + classId + ', null)">Bỏ chủ nhiệm</button>';
                    content += '</div>';
                } else {
                    content += '<p style="color: #999; margin-bottom: 15px;">Chưa có giáo viên chủ nhiệm</p>';
                }
                
                // Danh sách sinh viên trong lớp
                content += '<h5>👨‍🎓 Sinh viên trong lớp (' + data.students.length + ')</h5>';
                if (data.students.length > 0) {
                    content += '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px;">';
                    data.students.forEach(function(student) {
                        content += '<div style="padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">';
                        content += '<span>' + student.full_name + ' (' + student.code + ')</span>';
                        content += '<button class="btn-small btn-delete" onclick="removeStudentFromClass(' + classId + ', ' + student.id + ')">Xóa</button>';
                        content += '</div>';
                    });
                    content += '</div>';
                } else {
                    content += '<p style="color: #999; margin-bottom: 15px;">Chưa có sinh viên nào</p>';
                }
                
                // Form thêm sinh viên
                content += '<h5>➕ Thêm sinh viên vào lớp</h5>';
                if (data.unassigned_students.length > 0) {
                    // Checkbox list cho bulk selection
                    content += '<div style="border: 1px solid #ddd; border-radius: 5px; max-height: 250px; overflow-y: auto; margin-bottom: 15px;">';
                    content += '<div style="background: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 0;">';
                    content += '<label style="font-weight: bold;">';
                    content += '<input type="checkbox" id="select_all_students" onchange="toggleAllStudents()"> ';
                    content += 'Chọn tất cả (' + data.unassigned_students.length + ' sinh viên)';
                    content += '</label>';
                    content += '</div>';
                    
                    data.unassigned_students.forEach(function(student) {
                        content += '<div style="padding: 8px 10px; border-bottom: 1px solid #eee;">';
                        content += '<label style="display: flex; align-items: center; cursor: pointer;">';
                        content += '<input type="checkbox" class="student-checkbox" value="' + student.id + '" onchange="updateSelectAllState()"> ';
                        content += '<span style="margin-left: 8px;">' + student.full_name + ' (' + student.code + ')</span>';
                        content += '</label>';
                        content += '</div>';
                    });
                    content += '</div>';
                    
                    // Buttons
                    content += '<div style="margin-bottom: 15px;">';
                    content += '<button class="btn-small" style="background-color: #28a745; color: white; margin-right: 10px;" onclick="bulkAssignStudentsToClass(' + classId + ')">➕ Thêm các sinh viên đã chọn</button>';
                    content += '<button class="btn-small" style="background-color: #6c757d; color: white;" onclick="clearAllSelections()">🗑️ Bỏ chọn tất cả</button>';
                    content += '</div>';
                    
                    // Legacy single selection (giữ lại cho tương thích)
                    content += '<div style="padding-top: 15px; border-top: 1px solid #ddd;">';
                    content += '<h6>📝 Hoặc thêm từng sinh viên:</h6>';
                    content += '<select id="student_to_assign" style="width: 70%; padding: 8px; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px;">';
                    content += '<option value="">-- Chọn sinh viên --</option>';
                    data.unassigned_students.forEach(function(student) {
                        content += '<option value="' + student.id + '">' + student.full_name + ' (' + student.code + ')</option>';
                    });
                    content += '</select>';
                    content += '<button class="btn-small" style="background-color: #28a745; color: white;" onclick="assignStudentToClass(' + classId + ')">Thêm</button>';
                    content += '</div>';
                } else {
                    content += '<p style="color: #999;">Tất cả sinh viên đã được phân lớp</p>';
                }
                
                content += '<div style="text-align: center; margin-top: 20px;">';
                content += '<button class="btn-cancel" onclick="closeClassMembersModal()">Đóng</button>';
                content += '</div>';
                content += '</div>';
                
                document.getElementById('class_members_content').innerHTML = content;
            })
            .catch(error => {
                console.error('Error:', error);
                var errorMsg = error.message || 'Có lỗi xảy ra khi tải thông tin lớp học.';
                document.getElementById('class_members_content').innerHTML = 
                    '<div style="padding: 20px; text-align: center;">' +
                    '<p style="color: #e74c3c;">❌ ' + errorMsg + '</p>' +
                    '<button class="btn-cancel" onclick="closeClassMembersModal()">Đóng</button>' +
                    '</div>';
            });
            
            // Hiển thị modal thành viên lớp
            document.getElementById('classMembersModal').style.display = 'block';
        }
        
        function assignStudentToClass(classId) {
            var studentId = document.getElementById('student_to_assign').value;
            if (!studentId) {
                alert('Vui lòng chọn sinh viên!');
                return;
            }
            
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=assign_student_to_class&class_id=' + classId + '&student_id=' + studentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Thêm sinh viên vào lớp thành công!');
                    manageClassMembers(classId, document.getElementById('modal_class_name').textContent);
                    // Tải lại tab classes để cập nhật số lượng sinh viên
                    location.href = 'admin.php?tab=classes';
                } else {
                    alert('Lỗi: ' + (data.error || 'Không thể thêm sinh viên vào lớp'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi thêm sinh viên!');
            });
        }
        
        function removeStudentFromClass(classId, studentId) {
            if (!confirm('Bạn có chắc chắn muốn xóa sinh viên này khỏi lớp?')) {
                return;
            }
            
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=remove_student_from_class&class_id=' + classId + '&student_id=' + studentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Xóa sinh viên khỏi lớp thành công!');
                    manageClassMembers(classId, document.getElementById('modal_class_name').textContent);
                    // Tải lại tab classes để cập nhật số lượng sinh viên
                    location.href = 'admin.php?tab=classes';
                } else {
                    alert('Lỗi: ' + (data.error || 'Không thể xóa sinh viên khỏi lớp'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi xóa sinh viên!');
            });
        }
        
        function changeHomeroomLecturer(classId, lecturerId) {
            var action = lecturerId ? 'assign' : 'remove';
            var message = lecturerId ? 'Bạn có chắc chắn muốn đặt giáo viên này làm chủ nhiệm?' : 'Bạn có chắc chắn muốn bỏ chủ nhiệm lớp này?';
            
            if (!confirm(message)) {
                return;
            }
            
            var bodyData = 'action=change_homeroom_lecturer&class_id=' + classId;
            if (lecturerId) {
                bodyData += '&lecturer_id=' + lecturerId;
            }
            // Nếu lecturerId là null/undefined, không gửi lecturer_id parameter
            
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: bodyData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(action === 'assign' ? 'Đặt chủ nhiệm thành công!' : 'Bỏ chủ nhiệm thành công!');
                    manageClassMembers(classId, document.getElementById('modal_class_name').textContent);
                    // Tải lại tab classes để cập nhật thông tin chủ nhiệm
                    location.href = 'admin.php?tab=classes';
                } else {
                    alert('Lỗi: ' + (data.error || 'Không thể thay đổi chủ nhiệm'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi thay đổi chủ nhiệm!');
            });
        }
        
        // BULK ASSIGN FUNCTIONS
        function bulkAssignStudentsToClass(classId) {
            var checkboxes = document.querySelectorAll('.student-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Vui lòng chọn ít nhất một sinh viên!');
                return;
            }
            
            var studentIds = Array.from(checkboxes).map(cb => cb.value).join(',');
            var confirmMsg = 'Bạn có chắc chắn muốn thêm ' + checkboxes.length + ' sinh viên đã chọn vào lớp này?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=bulk_assign_students_to_class&class_id=' + classId + '&student_ids=' + studentIds
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    manageClassMembers(classId, document.getElementById('modal_class_name').textContent);
                    // Tải lại tab classes để cập nhật số lượng sinh viên
                    location.href = 'admin.php?tab=classes';
                } else {
                    alert('❌ Lỗi: ' + (data.error || 'Không thể thêm sinh viên vào lớp'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Có lỗi xảy ra khi thêm sinh viên!');
            });
        }
        
        function toggleAllStudents() {
            var selectAllCheckbox = document.getElementById('select_all_students');
            var studentCheckboxes = document.querySelectorAll('.student-checkbox');
            
            studentCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }
        
        function updateSelectAllState() {
            var selectAllCheckbox = document.getElementById('select_all_students');
            var studentCheckboxes = document.querySelectorAll('.student-checkbox');
            var checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedBoxes.length === studentCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
                selectAllCheckbox.checked = false;
            }
        }
        
        function clearAllSelections() {
            var studentCheckboxes = document.querySelectorAll('.student-checkbox');
            var selectAllCheckbox = document.getElementById('select_all_students');
            
            studentCheckboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
            
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
        
        function closeClassMembersModal() {
            // Đóng modal thành viên lớp
            document.getElementById('classMembersModal').style.display = 'none';
        }
        
        // ĐÓNG MODAL KHI CLICK BÊN NGOÀI (Click Outside to Close Modal)
        window.onclick = function(event) {
            var userModal = document.getElementById('editUserModal');
            var facultyModal = document.getElementById('editFacultyModal');
            var termModal = document.getElementById('editTermModal');
            var criterionModal = document.getElementById('editCriterionModal');
            var classModal = document.getElementById('editClassModal');
            var classMembersModal = document.getElementById('classMembersModal');
            
            // Đóng modal user nếu click bên ngoài
            if (event.target == userModal) {
                userModal.style.display = 'none';
            }
            
            // Đóng modal faculty nếu click bên ngoài
            if (event.target == facultyModal) {
                facultyModal.style.display = 'none';
            }
            
            // Đóng modal term nếu click bên ngoài
            if (event.target == termModal) {
                termModal.style.display = 'none';
            }
            
            // Đóng modal criterion nếu click bên ngoài
            if (event.target == criterionModal) {
                criterionModal.style.display = 'none';
            }
            
            // Đóng modal class nếu click bên ngoài
            if (event.target == classModal) {
                classModal.style.display = 'none';
            }
            
            // Đóng modal class members nếu click bên ngoài
            if (event.target == classMembersModal) {
                classMembersModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>

<?php
/**
 * GIẢI THÍCH CODE CHO NGƯỜI MỚI:
 * 
 * 1. Phân quyền: Chỉ admin mới vào được trang này
 * 2. Tab system: Sử dụng $_GET['tab'] để chuyển đổi nội dung
 * 3. CRUD operations: Create, Read, Update, Delete cho users và terms
 * 4. Form handling: Xử lý nhiều form khác nhau bằng $_POST['action']
 * 5. Security: Kiểm tra quyền admin, validate input, sử dụng prepared statements
 * 6. UI/UX: Thiết kế responsive, thông báo success/error, confirm trước khi xóa
 * 7. Statistics: Hiển thị thống kê tổng quan từ nhiều bảng khác nhau
 */
?>
