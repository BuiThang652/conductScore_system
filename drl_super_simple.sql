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
-- DỮ LIỆU MẪU - CHỈ TÀI KHOẢN ADMIN
-- ===================================================================

-- Tạo tài khoản admin duy nhất (password: 123456 -> MD5: e10adc3949ba59abbe56e057f20f883e)
INSERT INTO users (email, password, full_name, role, is_active) VALUES 
('admin@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'Admin Test', 'admin', 1);

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

TÀI KHOẢN DUY NHẤT:
- Admin: admin@test.com / 123456

HƯỚNG DẪN SỬ DỤNG:
1. Import file SQL này vào database
2. Đăng nhập với tài khoản admin@test.com / 123456
3. Sử dụng Admin Panel để tạo thêm users, khoa, lớp, sinh viên theo nhu cầu
4. Thiết lập kỳ học và tiêu chí đánh giá
5. Phân quyền và quản lý hệ thống

PHÂN QUYỀN THEO CHỨC NĂNG:
- Tạo/sửa users: admin
- Quản lý sinh viên: admin, lecturer (lớp mình)
- Nhập điểm RL: lecturer, student (tự đánh giá)
- Duyệt điểm RL: admin, lecturer
- Xem báo cáo: admin, lecturer
- Xem điểm cá nhân: student, lecturer (của lớp mình)
*/
