<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
if (!isset($_SESSION['kullanici_id']) || !yetkiVarMi($_SESSION['rol_id'], 'raporlar_excel.php')) {
    exit('Yetkiniz yok!');
}   
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=siparisler_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
        <th>ID</th>
        <th>Sipariş No</th>
        <th>Tedarikçi</th>
        <th>Personel</th>
        <th>Fatura Durumu</th>
        <th>Toplam Tutar</th>
        <th>Açıklama</th>
        <th>Tarih</th>
      </tr>";

$query = $db->query("
    SELECT 
        s.id, 
        s.siparis_no, 
        t.ad AS tedarikci, 
        p.ad, p.soyad, 
        s.toplam_tutar, 
        s.aciklama, 
        s.siparis_tarihi
    FROM siparisler s
    LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
    LEFT JOIN personeller p ON s.personel_id = p.id
    ORDER BY s.siparis_tarihi DESC
");
foreach ($query as $row) {
    // Fatura VAR/YOK kontrolü
    $stmtFatura = $db->prepare("SELECT COUNT(*) FROM faturalar WHERE siparis_id = ?");
    $stmtFatura->execute([$row['id']]);
    $faturaDurumu = $stmtFatura->fetchColumn() > 0 ? "VAR" : "YOK";
    // Personel Ad Soyad
    $personel = trim($row['ad'] . " " . $row['soyad']);

    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['siparis_no']}</td>";
    echo "<td>{$row['tedarikci']}</td>";
    echo "<td>{$personel}</td>";
    echo "<td>{$faturaDurumu}</td>";
    echo "<td>{$row['toplam_tutar']}</td>";
    echo "<td>{$row['aciklama']}</td>";
    echo "<td>{$row['siparis_tarihi']}</td>";
    echo "</tr>";
}
echo "</table>";
exit;
?>
