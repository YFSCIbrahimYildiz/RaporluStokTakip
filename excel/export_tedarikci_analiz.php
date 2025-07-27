<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=tedarikci_analiz_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Başlıklar
echo "<table border='1'>";
echo "<tr>
    <th>Tedarikçi ID</th>
    <th>Firma Adı</th>
    <th>Toplam Sipariş Sayısı</th>
    <th>Toplam Tutar</th>
</tr>";

$query = $db->query("
    SELECT t.id, t.ad AS firma_adi, 
        COUNT(s.id) AS toplam_siparis, 
        SUM(s.toplam_tutar) AS toplam_tutar
    FROM tedarikciler t
    LEFT JOIN siparisler s ON s.tedarikci_id = t.id
    GROUP BY t.id, t.ad
    ORDER BY toplam_tutar DESC
");
foreach ($query as $row) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['firma_adi']}</td>";
    echo "<td>{$row['toplam_siparis']}</td>";
    echo "<td>{$row['toplam_tutar']}</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
