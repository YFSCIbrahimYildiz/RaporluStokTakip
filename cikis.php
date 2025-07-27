<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Kullanıcı ID'si oturumdan alınıyor
$personel_id = $_SESSION['kullanici_id'] ?? 0;

// LOG: Çıkış (örnek işlem_id: 115, önce islemler tablosuna ekle: 115 - 'Çıkış Yapma')
if ($personel_id) {
    log_ekle($db, $personel_id, 115, 'cikis', 'Kullanıcı çıkış yaptı.', 'başarılı');
}

// Oturumu sonlandır
session_destroy();
header("Location: login.php");
exit;
?>
