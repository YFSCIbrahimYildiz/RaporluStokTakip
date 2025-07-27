<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=stok_gecmisi_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
        <th>ID</th>
        <th>Malzeme Adı</th>
        <th>İşlem Tipi</th>
        <th>Miktar</th>
        <th>Açıklama</th>
        <th>İşlem Tarihi</th>
      </tr>";

$query = $db->query("
    SELECT sh.id,
           m.ad AS malzeme_adi,
           sh.hareket_tip,
           sh.miktar,
           sh.aciklama,
           sh.islem_tarihi
      FROM stok_hareketleri sh
      LEFT JOIN malzemeler m ON sh.malzeme_id = m.id
      ORDER BY sh.islem_tarihi DESC
");

foreach ($query as $row) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['malzeme_adi']}</td>";
    echo "<td>{$row['hareket_tip']}</td>";
    echo "<td>{$row['miktar']}</td>";
    echo "<td>{$row['aciklama']}</td>";
    echo "<td>{$row['islem_tarihi']}</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
