<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // Burada log_decrypt fonksiyonu var!
session_start();

// Yetki kontrolü (ör: raporlar_excel.php yetkisi)
if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=loglar_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
        <th>ID</th>
        <th>Personel Sicil</th>
        <th>Personel Ad Soyad</th>
        <th>IP Adresi</th>
        <th>Oturum ID</th>
        <th>Sayfa</th>
        <th>İşlem ID</th>
        <th>İşlem Tipi</th>
        <th>Açıklama</th>
        <th>Cihaz Bilgisi</th>
        <th>İşlem Sonucu</th>
        <th>İşlem Tarihi</th>
      </tr>";

$sorgu = $db->query("
    SELECT l.*, p.sicil, p.ad, p.soyad
    FROM loglar l
    LEFT JOIN personeller p ON l.personel_id = p.id
    ORDER BY l.id DESC
");

foreach ($sorgu as $row) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['sicil']) . "</td>";
    echo "<td>" . htmlspecialchars(($row['ad'] ?? '') . ' ' . ($row['soyad'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars(log_decrypt($row['ip_adresi'])) . "</td>";
    echo "<td>" . htmlspecialchars(log_decrypt($row['oturum_id'])) . "</td>";
    echo "<td>" . htmlspecialchars(log_decrypt($row['sayfa'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['islem_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['islem_tipi']) . "</td>";
    echo "<td>" . htmlspecialchars(log_decrypt($row['aciklama'])) . "</td>";
    echo "<td>" . htmlspecialchars(log_decrypt($row['cihaz_bilgisi'])) . "</td>";
    echo "<td>" . htmlspecialchars(log_decrypt($row['islem_sonucu'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['islem_tarihi']) . "</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
