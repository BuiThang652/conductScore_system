SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';

CREATE TABLE IF NOT EXISTS faculties (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecturers (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(191) NULL UNIQUE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  faculty_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  homeroom_lecturer_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_classes_faculty FOREIGN KEY (faculty_id) REFERENCES faculties(id),
  CONSTRAINT fk_classes_homeroom FOREIGN KEY (homeroom_lecturer_id) REFERENCES lecturers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  class_id BIGINT UNSIGNED NOT NULL,
  student_code VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(191) NULL UNIQUE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS terms (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  academic_year VARCHAR(9) NOT NULL,
  term_no TINYINT UNSIGNED NOT NULL,
  status ENUM('upcoming','open','closed') NOT NULL DEFAULT 'upcoming',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_terms (academic_year, term_no),
  CHECK (start_date <= end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS criteria (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  max_point DECIMAL(6,2) NULL,
  order_no INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cr_parent FOREIGN KEY (parent_id) REFERENCES criteria(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluations (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  term_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME NULL,
  approved_at DATETIME NULL,
  approved_by BIGINT UNSIGNED NULL,
  note VARCHAR(500) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_eval (student_id, term_id),
  CONSTRAINT fk_ev_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_ev_term FOREIGN KEY (term_id) REFERENCES terms(id),
  CONSTRAINT fk_ev_approver FOREIGN KEY (approved_by) REFERENCES lecturers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_items (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  criterion_id BIGINT UNSIGNED NOT NULL,
  self_score DECIMAL(6,2) NULL,
  lecturer_score DECIMAL(6,2) NULL,
  note VARCHAR(300) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_evi (evaluation_id, criterion_id),
  CONSTRAINT fk_evi_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
  CONSTRAINT fk_evi_criterion FOREIGN KEY (criterion_id) REFERENCES criteria(id),
  CHECK (COALESCE(self_score,0) >= 0 AND COALESCE(lecturer_score,0) >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_eval_totals AS
SELECT e.id AS evaluation_id, e.student_id, e.term_id,
       SUM(COALESCE(ei.self_score,0)) AS total_self,
       SUM(COALESCE(ei.lecturer_score,0)) AS total_lecturer
FROM evaluations e
LEFT JOIN evaluation_items ei ON ei.evaluation_id = e.id
GROUP BY e.id, e.student_id, e.term_id;

CREATE OR REPLACE VIEW v_eval_best AS
SELECT v.evaluation_id, v.student_id, v.term_id,
       COALESCE(v.total_lecturer, v.total_self) AS best_total
FROM v_eval_totals v;

-- 1) Tạo bảng người dùng với 3 role cơ bản
CREATE TABLE users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(191) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL, -- mã hóa MD5
  full_name VARCHAR(255) NOT NULL,
  role ENUM('student','lecturer','admin') NOT NULL DEFAULT 'student',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Liên kết tài khoản với giảng viên & sinh viên (nếu cần)
ALTER TABLE lecturers ADD COLUMN user_id BIGINT UNSIGNED NULL UNIQUE,
  ADD CONSTRAINT fk_lec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE students  ADD COLUMN user_id BIGINT UNSIGNED NULL UNIQUE,
  ADD CONSTRAINT fk_stu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Thêm minh chứng
ALTER TABLE evaluation_items
ADD COLUMN evidence_url VARCHAR(500) NULL AFTER note,
ADD COLUMN evidence_file VARCHAR(255) NULL AFTER evidence_url;

-- ===================================================================
-- DỮ LIỆU MẪU - SAMPLE DATA
-- ===================================================================

-- 1) Tạo các khoa
INSERT INTO faculties (code, name) VALUES 
('CNTT', 'Công nghệ thông tin'),
('KT', 'Kinh tế'),
('NN', 'Ngoại ngữ'),
('CK', 'Cơ khí'),
('DT', 'Điện tử');

-- 2) Tạo giảng viên
INSERT INTO lecturers (full_name, email) VALUES 
('TS. Nguyễn Văn Minh', 'nv.minh@university.edu.vn'),
('ThS. Trần Thị Lan', 'tt.lan@university.edu.vn'),
('PGS. Lê Quang Đức', 'lq.duc@university.edu.vn'),
('TS. Phạm Thị Hoa', 'pt.hoa@university.edu.vn'),
('ThS. Hoàng Văn Nam', 'hv.nam@university.edu.vn');

-- 3) Tạo lớp học
INSERT INTO classes (faculty_id, code, name, homeroom_lecturer_id) VALUES 
(1, 'CNTT01', 'Công nghệ thông tin 01', 1),
(1, 'CNTT02', 'Công nghệ thông tin 02', 2),
(2, 'KT01', 'Kinh tế 01', 3),
(2, 'KT02', 'Kinh tế 02', 4),
(3, 'NN01', 'Ngoại ngữ 01', 5);

-- 4) Tạo sinh viên
INSERT INTO students (class_id, student_code, full_name, email) VALUES 
(1, 'CT001', 'Nguyễn Văn An', 'nv.an@student.edu.vn'),
(1, 'CT002', 'Trần Thị Bình', 'tt.binh@student.edu.vn'),
(1, 'CT003', 'Lê Văn Cường', 'lv.cuong@student.edu.vn'),
(2, 'CT004', 'Phạm Thị Dung', 'pt.dung@student.edu.vn'),
(2, 'CT005', 'Hoàng Văn Ê', 'hv.e@student.edu.vn'),
(3, 'KT001', 'Vũ Thị Phương', 'vt.phuong@student.edu.vn'),
(3, 'KT002', 'Đỗ Văn Giang', 'dv.giang@student.edu.vn'),
(4, 'KT003', 'Bùi Thị Hằng', 'bt.hang@student.edu.vn'),
(5, 'NN001', 'Lý Văn Khôi', 'lv.khoi@student.edu.vn'),
(5, 'NN002', 'Mai Thị Linh', 'mt.linh@student.edu.vn');

-- 5) Tạo kỳ học
INSERT INTO terms (academic_year, term_no, status, start_date, end_date) VALUES 
('2023-2024', 1, 'closed', '2023-09-01', '2024-01-15'),
('2023-2024', 2, 'closed', '2024-02-01', '2024-06-15'),
('2024-2025', 1, 'open', '2024-09-01', '2025-01-15'),
('2024-2025', 2, 'upcoming', '2025-02-01', '2025-06-15');

-- 6) Tạo tiêu chí đánh giá
INSERT INTO criteria (parent_id, name, max_point, order_no) VALUES 
-- Tiêu chí chính
(NULL, 'Ý thức học tập', 25.00, 1),
(NULL, 'Ý thức kỷ luật', 25.00, 2),
(NULL, 'Hoạt động tập thể', 20.00, 3),
(NULL, 'Đời sống sinh hoạt', 20.00, 4),
(NULL, 'Các hoạt động khác', 10.00, 5),

-- Tiêu chí con của "Ý thức học tập"
(1, 'Tham gia đầy đủ các hoạt động học tập', 10.00, 11),
(1, 'Hoàn thành tốt các bài tập, đồ án', 8.00, 12),
(1, 'Đạt kết quả học tập tốt', 7.00, 13),

-- Tiêu chí con của "Ý thức kỷ luật"
(2, 'Chấp hành nghiêm chỉnh nội quy nhà trường', 10.00, 21),
(2, 'Không vi phạm các quy định của nhà trường', 10.00, 22),
(2, 'Tham gia đầy đủ các hoạt động bắt buộc', 5.00, 23),

-- Tiêu chí con của "Hoạt động tập thể"
(3, 'Tham gia hoạt động Đoàn, Hội', 8.00, 31),
(3, 'Tham gia hoạt động tình nguyện', 7.00, 32),
(3, 'Tham gia hoạt động văn hóa, thể thao', 5.00, 33),

-- Tiêu chí con của "Đời sống sinh hoạt"
(4, 'Có ý thức tự phục vụ', 8.00, 41),
(4, 'Giữ gìn vệ sinh môi trường', 7.00, 42),
(4, 'Thực hiện tốt nếp sống văn minh', 5.00, 43),

-- Tiêu chí con của "Các hoạt động khác"
(5, 'Tham gia nghiên cứu khoa học', 5.00, 51),
(5, 'Có thành tích đặc biệt khác', 5.00, 52);

-- 7) Tạo tài khoản người dùng với MD5 password
INSERT INTO users (email, password, full_name, role, is_active) VALUES 
-- Admin (password: 123456 -> MD5: e10adc3949ba59abbe56e057f20f883e)
('admin@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'Admin Test', 'admin', 1),
('admin@university.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'Quản trị viên hệ thống', 'admin', 1),

-- Giảng viên (password: 123456 -> MD5: e10adc3949ba59abbe56e057f20f883e)
('nv.minh@university.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'TS. Nguyễn Văn Minh', 'lecturer', 1),
('tt.lan@university.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'ThS. Trần Thị Lan', 'lecturer', 1),
('lq.duc@university.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'PGS. Lê Quang Đức', 'lecturer', 1),
('pt.hoa@university.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'TS. Phạm Thị Hoa', 'lecturer', 1),
('hv.nam@university.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'ThS. Hoàng Văn Nam', 'lecturer', 1),
('lecturer@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'Lecturer Test', 'lecturer', 1),

-- Sinh viên (password: 123456 -> MD5: e10adc3949ba59abbe56e057f20f883e)
('nv.an@student.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'Nguyễn Văn An', 'student', 1),
('tt.binh@student.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'Trần Thị Bình', 'student', 1),
('lv.cuong@student.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'Lê Văn Cường', 'student', 1),
('pt.dung@student.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'Phạm Thị Dung', 'student', 1),
('hv.e@student.edu.vn', 'e10adc3949ba59abbe56e057f20f883e', 'Hoàng Văn Ê', 'student', 1),
('student@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'Student Test', 'student', 1);

-- 8) Liên kết user_id với lecturers và students
UPDATE lecturers SET user_id = (SELECT id FROM users WHERE email = lecturers.email) WHERE email IS NOT NULL;
UPDATE students SET user_id = (SELECT id FROM users WHERE email = students.email) WHERE email IS NOT NULL;

-- 9) Tạo một số đánh giá mẫu
INSERT INTO evaluations (student_id, term_id, status) VALUES 
(1, 3, 'draft'),    -- Nguyễn Văn An - Kỳ 1 2024-2025
(2, 3, 'submitted'), -- Trần Thị Bình - Kỳ 1 2024-2025
(3, 3, 'approved'),  -- Lê Văn Cường - Kỳ 1 2024-2025
(4, 3, 'draft'),    -- Phạm Thị Dung - Kỳ 1 2024-2025
(5, 3, 'draft');    -- Hoàng Văn Ê - Kỳ 1 2024-2025

-- 10) Tạo một số điểm mẫu
INSERT INTO evaluation_items (evaluation_id, criterion_id, self_score, lecturer_score, note) VALUES 
-- Đánh giá của Nguyễn Văn An (evaluation_id = 1)
(1, 6, 9.0, 8.5, 'Tham gia đầy đủ các buổi học'),
(1, 7, 7.0, 7.5, 'Hoàn thành tốt các bài tập'),
(1, 8, 6.0, 6.0, 'Kết quả học tập khá'),
(1, 9, 9.0, 9.0, 'Chấp hành tốt nội quy'),
(1, 10, 8.0, 8.0, 'Không vi phạm quy định'),

-- Đánh giá của Trần Thị Bình (evaluation_id = 2)
(2, 6, 10.0, 9.0, 'Tham gia tích cực các hoạt động học tập'),
(2, 7, 8.0, 8.0, 'Bài tập được làm cẩn thận'),
(2, 8, 7.0, 7.0, 'Kết quả học tập tốt'),
(2, 13, 7.0, 6.0, 'Tham gia hoạt động Đoàn'),
(2, 14, 6.0, 5.0, 'Tham gia tình nguyện');

-- ===================================================================
-- GIẢI THÍCH ROLES VÀ MÃ HÓA PASSWORD
-- ===================================================================

/*
ROLES TRONG HỆ THỐNG (3 ROLE CƠ BẢN):

1. ADMIN (admin):
   - Quản trị viên hệ thống
   - Có quyền cao nhất, quản lý toàn bộ hệ thống
   - Tạo/sửa/xóa users, cấu hình hệ thống
   - Xem tất cả báo cáo và thống kê

2. LECTURER (lecturer):
   - Giảng viên, cố vấn học tập
   - Nhập và đánh giá điểm rèn luyện sinh viên
   - Quản lý lớp được phân công
   - Duyệt điểm rèn luyện

3. STUDENT (student):
   - Sinh viên
   - Tự đánh giá điểm rèn luyện
   - Xem kết quả điểm của bản thân
   - Upload minh chứng (nếu có)

MÃ HÓA PASSWORD:
- Sử dụng MD5 hash
- Password gốc: "123456"
- MD5 hash: "e10adc3949ba59abbe56e057f20f883e"

TÀI KHOẢN TEST:
1. Admin: admin@test.com / 123456
2. Lecturer: lecturer@test.com / 123456  
3. Student: student@test.com / 123456

PHÂN QUYỀN THEO CHỨC NĂNG:
- Tạo/sửa users: admin
- Quản lý sinh viên: admin, lecturer (lớp mình)
- Nhập điểm RL: lecturer, student (tự đánh giá)
- Duyệt điểm RL: admin, lecturer
- Xem báo cáo: admin, lecturer
- Xem điểm cá nhân: student, lecturer (của lớp mình)
*/
