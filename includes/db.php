<?php
define('LOG_SECRET_KEY', 'SENIN_COK_GIZLI_LOG_ANAHTARIN!'); // 32 karakter (AES-256 için)
define('LOG_SECRET_IV', 'LOGIV1234567890!'); // 16 karakter IV

try {
    $db = new PDO(
        "mysql:host=localhost;dbname=yildizf2_stokAdmin;charset=utf8mb4",
        "yildizf2_stokTakip",    // Kullanıcı adın
        "HZ6Zbr#Y{mUx"           // Şifren
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>
