<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}   
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=tedarikciler_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Başlıklar
echo "<table border='1'>";
echo "<tr>
    <th>ID</th>
    <th>Firma Kodu</th>
    <th>Ad</th>
    <th>Telefon</th>
    <th>Yetkili Ad Soyad</th>
    <th>Yetkili Telefon</th>
    <th>E-posta</th>
    <th>VKN</th>
    <th>Adres</th>
    <th>Açıklama</th>
    <th>Durum</th>
</tr>";

$query = $db->query("SELECT * FROM tedarikciler ORDER BY ad");
foreach ($query as $row) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['firma_kodu']}</td>";
    echo "<td>{$row['ad']}</td>";
    echo "<td>" . (function_exists('coz') ? coz($row['telefon']) : $row['telefon']) . "</td>";
    echo "<td>{$row['yetkili_adsoyad']}</td>";
    echo "<td>" . (function_exists('coz') ? coz($row['yetkili_telefon']) : $row['yetkili_telefon']) . "</td>";
    echo "<td>" . (function_exists('coz') ? coz($row['email']) : $row['email']) . "</td>";
    echo "<td>" . (function_exists('coz') ? coz($row['vkn']) : $row['vkn']) . "</td>";
    echo "<td>{$row['adres']}</td>";
    echo "<td>" . (function_exists('coz') ? coz($row['aciklama']) : $row['aciklama']) . "</td>";
    echo "<td>" . ($row['aktif'] ? 'Aktif' : 'Pasif') . "</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
