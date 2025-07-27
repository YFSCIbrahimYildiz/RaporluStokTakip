<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}   
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=malzeme_listesi_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
    <th>ID</th>
    <th>Malzeme Adı</th>
    <th>Tip</th>
    <th>Birim</th>
    <th>KDV Oranı (%)</th>
    <th>Min. Stok Limiti</th>
    <th>Mevcut Stok</th>
    <th>Açıklama</th>
</tr>";

$sql = "
    SELECT m.id, m.ad, t.tip_adi, m.birim, k.oran AS kdv_orani, m.min_stok_limiti, m.mevcut_stok, m.aciklama
    FROM malzemeler m
    LEFT JOIN malzeme_tipleri t ON m.tip_id = t.id
    LEFT JOIN kdv_oranlari k ON m.kdv_id = k.id
    ORDER BY m.id
";
$stmt = $db->query($sql);

foreach ($stmt as $row) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['ad']) . "</td>";
    echo "<td>" . htmlspecialchars($row['tip_adi']) . "</td>";
    echo "<td>" . htmlspecialchars($row['birim']) . "</td>";
    echo "<td>" . htmlspecialchars($row['kdv_orani']) . "</td>";
    echo "<td>" . htmlspecialchars($row['min_stok_limiti']) . "</td>";
    echo "<td>" . htmlspecialchars($row['mevcut_stok']) . "</td>";
    echo "<td>" . htmlspecialchars($row['aciklama']) . "</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
