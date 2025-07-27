<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}
// Türkçe karakterler için başlık
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=acil_ihtiyac_listesi.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Excel tablosu başlat
echo "<table border='1'>";
echo "<tr>
        <th>#</th>
        <th>Malzeme Adı</th>
        <th>Birim</th>
        <th>Mevcut Stok</th>
        <th>Acil İhtiyaç (Eksik Adet)</th>
        <th>Açıklama</th>
      </tr>";

// Sorgu: acil ihtiyaçlar ve ilişkili malzeme adı vs.
$query = $db->query("
    SELECT 
        a.id,
        m.ad AS malzeme_adi,
        m.birim,
        m.mevcut_stok,
        a.eksik_adet,
        m.aciklama
    FROM acil_ihtiyaclar a
    JOIN malzemeler m ON a.malzeme_id = m.id
    WHERE a.eksik_adet > 0
    ORDER BY a.id DESC
");

$no = 1;
foreach($query as $row){
    echo "<tr>";
    echo "<td>".$no++."</td>";
    echo "<td>".htmlspecialchars($row['malzeme_adi'])."</td>";
    echo "<td>".htmlspecialchars($row['birim'])."</td>";
    echo "<td>".htmlspecialchars($row['mevcut_stok'])."</td>";
    echo "<td>".htmlspecialchars($row['eksik_adet'])."</td>";
    echo "<td>".htmlspecialchars($row['aciklama'])."</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
