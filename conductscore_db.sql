-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 02, 2025 lúc 07:31 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `conductscore_db`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `classes`
--

CREATE TABLE `classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `faculty_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `homeroom_lecturer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `classes`
--

INSERT INTO `classes` (`id`, `faculty_id`, `code`, `name`, `homeroom_lecturer_id`, `created_at`, `updated_at`) VALUES
(1, 2, 'K70A', 'K70A - Công nghệ thông tin', 1, '2025-10-02 01:28:50', '2025-10-02 01:28:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `criteria`
--

CREATE TABLE `criteria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `max_point` decimal(6,2) DEFAULT NULL,
  `order_no` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `criteria`
--

INSERT INTO `criteria` (`id`, `parent_id`, `name`, `max_point`, `order_no`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Test_11', 30.00, 1, 1, '2025-10-01 16:30:21', '2025-10-02 05:06:57'),
(2, NULL, 'Test_2', 20.00, 50, 1, '2025-10-01 16:30:47', '2025-10-01 16:30:47'),
(3, NULL, 'Test_3', 50.00, 100, 1, '2025-10-01 16:31:05', '2025-10-01 16:31:05'),
(4, 1, 'Test_1.1', 20.00, 2, 1, '2025-10-01 16:31:30', '2025-10-01 16:31:30'),
(5, 1, 'Test_1.2', 10.00, 3, 1, '2025-10-01 16:31:53', '2025-10-01 16:31:53'),
(6, 2, 'Test_2.1', 10.00, 51, 1, '2025-10-01 16:32:26', '2025-10-01 16:32:45'),
(7, 2, 'Test_2.2', 10.00, 52, 1, '2025-10-01 16:33:10', '2025-10-01 16:33:10'),
(8, 3, 'Test_3.1', 10.00, 101, 1, '2025-10-01 16:33:36', '2025-10-01 16:33:36'),
(9, 3, 'Test_3.2', 15.00, 102, 1, '2025-10-01 16:33:57', '2025-10-01 16:33:57'),
(10, 3, 'Test_3.3', 5.00, 103, 1, '2025-10-01 16:34:22', '2025-10-01 16:34:34'),
(11, 3, 'Test_3.4', 20.00, 104, 1, '2025-10-01 16:35:17', '2025-10-01 16:35:17');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `evaluations`
--

CREATE TABLE `evaluations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `term_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('draft','submitted','approved') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `evaluations`
--

INSERT INTO `evaluations` (`id`, `student_id`, `term_id`, `status`, `submitted_at`, `approved_at`, `approved_by`, `note`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'submitted', NULL, NULL, NULL, NULL, '2025-10-02 02:58:12', '2025-10-02 04:36:25'),
(2, 1, 1, '', NULL, NULL, NULL, NULL, '2025-10-02 02:58:33', '2025-10-02 04:35:34'),
(3, 4, 1, '', NULL, NULL, NULL, NULL, '2025-10-02 02:59:04', '2025-10-02 04:38:38');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `evaluation_items`
--

CREATE TABLE `evaluation_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `evaluation_id` bigint(20) UNSIGNED NOT NULL,
  `criterion_id` bigint(20) UNSIGNED NOT NULL,
  `self_score` decimal(6,2) DEFAULT NULL,
  `lecturer_score` decimal(6,2) DEFAULT NULL,
  `note` varchar(300) DEFAULT NULL,
  `evidence_url` varchar(500) DEFAULT NULL,
  `evidence_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Đang đổ dữ liệu cho bảng `evaluation_items`
--

INSERT INTO `evaluation_items` (`id`, `evaluation_id`, `criterion_id`, `self_score`, `lecturer_score`, `note`, `evidence_url`, `evidence_file`, `created_at`, `updated_at`) VALUES
(25, 2, 4, 0.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(26, 2, 5, 0.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(27, 2, 6, 0.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(28, 2, 7, 0.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(29, 2, 8, 0.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(30, 2, 9, 0.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(31, 2, 10, 0.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(32, 2, 11, 20.00, 0.00, '', NULL, NULL, '2025-10-02 03:25:36', '2025-10-02 04:35:34'),
(33, 1, 4, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(34, 1, 5, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(35, 1, 6, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(36, 1, 7, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(37, 1, 8, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(38, 1, 9, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(39, 1, 10, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(40, 1, 11, 0.00, NULL, '', NULL, NULL, '2025-10-02 04:36:25', '2025-10-02 04:36:25'),
(49, 3, 4, 20.00, 20.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38'),
(50, 3, 5, 0.00, 0.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38'),
(51, 3, 6, 0.00, 0.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38'),
(52, 3, 7, 0.00, 0.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38'),
(53, 3, 8, 0.00, 0.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38'),
(54, 3, 9, 0.00, 0.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38'),
(55, 3, 10, 0.00, 0.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38'),
(56, 3, 11, 0.00, 0.00, '', NULL, NULL, '2025-10-02 04:37:24', '2025-10-02 04:38:38');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `faculties`
--

CREATE TABLE `faculties` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `faculties`
--

INSERT INTO `faculties` (`id`, `code`, `name`, `created_at`, `updated_at`) VALUES
(2, 'CNTT', 'Công nghệ thông tin', '2025-10-01 16:51:01', '2025-10-01 16:51:01');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lecturers`
--

CREATE TABLE `lecturers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `lecturers`
--

INSERT INTO `lecturers` (`id`, `full_name`, `email`, `created_at`, `updated_at`, `user_id`) VALUES
(1, 'gv1', 'gv@test.com', '2025-10-02 01:26:15', '2025-10-02 01:26:15', 4),
(2, 'gv2', 'gv2@test.com', '2025-10-02 01:28:11', '2025-10-02 01:28:11', 5),
(4, 'gv3', 'gv3@test.com', '2025-10-02 01:46:25', '2025-10-02 01:46:25', 7);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `student_code` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `students`
--

INSERT INTO `students` (`id`, `class_id`, `student_code`, `full_name`, `email`, `created_at`, `updated_at`, `user_id`) VALUES
(1, 1, '', 'sv1', 'sv1@test.com', '2025-10-02 01:32:41', '2025-10-02 02:03:33', 2),
(3, 1, NULL, 'sv2', 'sv2@test.com', '2025-10-02 01:34:20', '2025-10-02 01:36:29', 3),
(4, 1, NULL, 'sv3', 'sv3@test.com', '2025-10-02 01:35:55', '2025-10-02 04:11:46', 6);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `terms`
--

CREATE TABLE `terms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `term_no` tinyint(3) UNSIGNED NOT NULL,
  `status` enum('upcoming','open','closed') NOT NULL DEFAULT 'upcoming',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Đang đổ dữ liệu cho bảng `terms`
--

INSERT INTO `terms` (`id`, `academic_year`, `term_no`, `status`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, '2024-2025', 3, 'open', '2025-06-01', '2025-08-31', '2025-10-01 16:29:59', '2025-10-01 16:29:59');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('student','lecturer','admin') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'Admin Test', 'admin', 1, NULL, '2025-10-01 02:05:54', '2025-10-01 02:05:54'),
(2, 'sv1@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'sv1', 'student', 1, NULL, '2025-10-01 16:28:30', '2025-10-01 16:28:30'),
(3, 'sv2@test.com', '$2y$10$8MgOlz1GzSPmzbGN/7KVhewK7xtrtTjbvWRpoqQ.oQ41QPwla6mk.', 'sv2', 'student', 1, NULL, '2025-10-01 16:28:47', '2025-10-02 05:25:05'),
(4, 'gv@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'gv1', 'lecturer', 1, NULL, '2025-10-01 16:52:10', '2025-10-01 16:52:10'),
(5, 'gv2@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'gv2', 'lecturer', 1, NULL, '2025-10-02 01:28:11', '2025-10-02 01:28:11'),
(6, 'sv3@test.com', 'e10adc3949ba59abbe56e057f20f883e', 'sv3', 'student', 1, NULL, '2025-10-02 01:35:55', '2025-10-02 01:35:55'),
(7, 'gv3@test.com', '$2y$10$YXa829LT.270ebUWGIku9.VJxLzS/HBWFEaVwcSh.oqNUTtCd2jjW', 'gv3', 'lecturer', 1, NULL, '2025-10-02 01:45:28', '2025-10-02 05:22:10');

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_eval_best`
-- (See below for the actual view)
--
CREATE TABLE `v_eval_best` (
`evaluation_id` bigint(20) unsigned
,`student_id` bigint(20) unsigned
,`term_id` bigint(20) unsigned
,`best_total` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_eval_totals`
-- (See below for the actual view)
--
CREATE TABLE `v_eval_totals` (
`evaluation_id` bigint(20) unsigned
,`student_id` bigint(20) unsigned
,`term_id` bigint(20) unsigned
,`total_self` decimal(28,2)
,`total_lecturer` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_eval_best`
--
DROP TABLE IF EXISTS `v_eval_best`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_eval_best`  AS SELECT `v`.`evaluation_id` AS `evaluation_id`, `v`.`student_id` AS `student_id`, `v`.`term_id` AS `term_id`, coalesce(`v`.`total_lecturer`,`v`.`total_self`) AS `best_total` FROM `v_eval_totals` AS `v` ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_eval_totals`
--
DROP TABLE IF EXISTS `v_eval_totals`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_eval_totals`  AS SELECT `e`.`id` AS `evaluation_id`, `e`.`student_id` AS `student_id`, `e`.`term_id` AS `term_id`, sum(coalesce(`ei`.`self_score`,0)) AS `total_self`, sum(coalesce(`ei`.`lecturer_score`,0)) AS `total_lecturer` FROM (`evaluations` `e` left join `evaluation_items` `ei` on(`ei`.`evaluation_id` = `e`.`id`)) GROUP BY `e`.`id`, `e`.`student_id`, `e`.`term_id` ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_classes_faculty` (`faculty_id`),
  ADD KEY `fk_classes_homeroom` (`homeroom_lecturer_id`);

--
-- Chỉ mục cho bảng `criteria`
--
ALTER TABLE `criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cr_parent` (`parent_id`);

--
-- Chỉ mục cho bảng `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_eval` (`student_id`,`term_id`),
  ADD KEY `fk_ev_term` (`term_id`),
  ADD KEY `fk_ev_approver` (`approved_by`);

--
-- Chỉ mục cho bảng `evaluation_items`
--
ALTER TABLE `evaluation_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_evi` (`evaluation_id`,`criterion_id`),
  ADD KEY `fk_evi_criterion` (`criterion_id`);

--
-- Chỉ mục cho bảng `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Chỉ mục cho bảng `lecturers`
--
ALTER TABLE `lecturers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_code_2` (`student_code`),
  ADD KEY `fk_students_class` (`class_id`);

--
-- Chỉ mục cho bảng `terms`
--
ALTER TABLE `terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_terms` (`academic_year`,`term_no`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `classes`
--
ALTER TABLE `classes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `evaluation_items`
--
ALTER TABLE `evaluation_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `faculties`
--
ALTER TABLE `faculties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `terms`
--
ALTER TABLE `terms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`),
  ADD CONSTRAINT `fk_classes_homeroom` FOREIGN KEY (`homeroom_lecturer_id`) REFERENCES `lecturers` (`id`);

--
-- Các ràng buộc cho bảng `criteria`
--
ALTER TABLE `criteria`
  ADD CONSTRAINT `fk_cr_parent` FOREIGN KEY (`parent_id`) REFERENCES `criteria` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `fk_ev_approver` FOREIGN KEY (`approved_by`) REFERENCES `lecturers` (`id`),
  ADD CONSTRAINT `fk_ev_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `fk_ev_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`);

--
-- Các ràng buộc cho bảng `evaluation_items`
--
ALTER TABLE `evaluation_items`
  ADD CONSTRAINT `fk_evi_criterion` FOREIGN KEY (`criterion_id`) REFERENCES `criteria` (`id`),
  ADD CONSTRAINT `fk_evi_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `lecturers`
--
ALTER TABLE `lecturers`
  ADD CONSTRAINT `fk_lec_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_stu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
