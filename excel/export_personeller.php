<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/encryption.php';
session_start();
if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}   

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=personeller_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
        <th>ID</th>
        <th>Ad Soyad</th>
        <th>Sicil</th>
        <th>Rol</th>
        <th>Telefon</th>
        <th>E-posta</th>
        <th>Durum</th>
      </tr>";

$query = $db->query("
    SELECT p.id, p.ad, p.soyad, p.sicil, r.rol_adi, p.telefon, p.email, p.aktif
    FROM personeller p
    LEFT JOIN roller r ON p.rol_id = r.id
    ORDER BY p.ad, p.soyad
");
foreach ($query as $row) {
    // Telefon ve email şifreli ise çöz
    $telefon = function_exists('coz_akilli') ? coz_akilli($row['telefon']) : $row['telefon'];
    $email = function_exists('coz_akilli') ? coz_akilli($row['email']) : $row['email'];
    $adsoyad = $row['ad'] . ' ' . $row['soyad'];
    $durum = $row['aktif'] == 1 ? 'Aktif' : 'Pasif';

    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$adsoyad}</td>";
    echo "<td>{$row['sicil']}</td>";
    echo "<td>{$row['rol_adi']}</td>";
    echo "<td>{$telefon}</td>";
    echo "<td>{$email}</td>";
    echo "<td>{$durum}</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
