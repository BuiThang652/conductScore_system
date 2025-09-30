<?php
/**
 * FILE NÀY DÙNG ĐỂ KẾT NỐI VỚI DATABASE
 * 
 * Đây là file đầu tiên bạn cần hiểu:
 * - Chứa thông tin kết nối database 
 * - Mỗi lần cần dùng database thì include file này
 */

// 1. THÔNG TIN KẾT NỐI DATABASE (thay đổi theo máy bạn)
$db_host = 'localhost';        // Máy chủ database (localhost = máy tính của bạn)
$db_username = 'root';         // Tên đăng nhập MySQL
$db_password = '';             // Mật khẩu MySQL (mặc định xampp là rỗng)
$db_name = 'ql_drl';           // Tên database

// 2. TẠO KẾT NỐI VỚI DATABASE
try {
    // PDO là cách an toàn nhất để kết nối MySQL trong PHP
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", 
        $db_username, 
        $db_password
    );
    
    // Thiết lập chế độ báo lỗi (giúp debug dễ hơn)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Báo thành công (chỉ để test, sau này bỏ dòng này)
    // echo "Kết nối database thành công!";
    
} catch(PDOException $e) {
    // Nếu kết nối thất bại thì hiện lỗi
    die("Lỗi kết nối database: " . $e->getMessage());
}

/**
 * GIẢI THÍCH DÀNH CHO NGƯỜI MỚI:
 * 
 * - $pdo: đây là "cầu nối" giữa PHP và MySQL
 * - try/catch: để bắt lỗi nếu không kết nối được database
 * - PDO::ATTR_ERRMODE: báo lỗi chi tiết khi có vấn đề
 * - die(): dừng chương trình và hiện thông báo lỗi
 */
?>