<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=talepler_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Tablo başlıkları
echo "<table border='1'>";
echo "<tr>
    <th>ID</th>
    <th>Personel</th>
    <th>Durum</th>
    <th>Açıklama</th>
    <th>Aciliyet</th>
    <th>Talep Tarihi</th>
    <th>Onay Tarihi</th>
</tr>";

$query = $db->query("
    SELECT t.id, 
           CONCAT(p.ad, ' ', p.soyad) AS personel, 
           t.durum, 
           t.aciklama, 
           t.aciliyet, 
           t.talep_tarihi,
           t.onay_tarihi
    FROM talepler t
    LEFT JOIN personeller p ON t.personel_id = p.id
    ORDER BY t.talep_tarihi DESC
");

foreach ($query as $row) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['personel']}</td>";
    echo "<td>{$row['durum']}</td>";
    echo "<td>{$row['aciklama']}</td>";
    echo "<td>{$row['aciliyet']}</td>";
    echo "<td>{$row['talep_tarihi']}</td>";
    echo "<td>{$row['onay_tarihi']}</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
