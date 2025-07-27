<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['kullanici_id'])) exit('YETKİ YOK');

$personel_id = $_SESSION['kullanici_id'] ?? 0;
$islem_id    = intval($_POST['islem_id'] ?? 0);
$tip         = trim($_POST['tip'] ?? 'goruntuleme');
$aciklama    = trim($_POST['aciklama'] ?? '');

if ($personel_id && $islem_id && $tip && $aciklama) {
    log_ekle($db, $personel_id, $islem_id, $tip, $aciklama, 'başarılı');
    echo 'OK';
} else {
    echo 'HATA: Eksik veri';
}
?>
