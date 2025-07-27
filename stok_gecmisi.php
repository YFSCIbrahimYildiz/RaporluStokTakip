<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/functions.php';

oturumKontrol();

// Rol ve dosya adı
$rol_id = $_SESSION['rol_id'] ?? 0;
$personel_id = $_SESSION['kullanici_id'] ?? 0;
$sayfa_adi = basename(__FILE__);
$sayfa_id = getSayfaId($sayfa_adi);

if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

// --- SAYFA GÖRÜNTÜLEME LOGU (ör: id 120) ---
log_ekle($db, $personel_id, 120, 'goruntuleme', 'Stok Geçmişi görüntülendi.', 'başarılı');

// SQL ve veri çekme
global $db;
$sql = "
SELECT 
    sh.id,
    m.ad AS malzeme_adi,
    sh.hareket_tip,
    sh.miktar,
    sh.aciklama,
    sh.islem_tarihi
FROM stok_hareketleri sh
INNER JOIN malzemeler m ON sh.malzeme_id = m.id
ORDER BY sh.islem_tarihi DESC
";
$stmt = $db->query($sql);
$hareketler = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Stok Geçmişi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container mt-5">
    <h3>Stok Geçmişi</h3>
    <div class="row mb-3">
        <div class="col-md-4 ms-auto">
            <input type="text" id="aramaInput" class="form-control" placeholder="Malzeme, işlem tipi, açıklama ara...">
        </div>
    </div>
    <table class="table table-bordered" id="stokGecmisTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Malzeme</th>
                <th>İşlem Tipi</th>
                <th>Miktar</th>
                <th>Açıklama</th>
                <th>Tarih</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$hareketler): ?>
                <tr>
                    <td colspan="6" class="text-danger text-center">Kayıt bulunamadı.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($hareketler as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['malzeme_adi']) ?></td>
                    <td><?= htmlspecialchars($row['hareket_tip']) ?></td>
                    <td><?= $row['miktar'] ?></td>
                    <td><?= htmlspecialchars($row['aciklama']) ?></td>
                    <td><?= $row['islem_tarihi'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tablo arama ve log kaydı (ör: id 121)
document.getElementById('aramaInput').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#stokGecmisTable tbody tr');
    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().indexOf(value) > -1 ? '' : 'none';
    });
    // Sadece ilk harf girildiğinde log atalım
    if (this.value.length === 1) {
        fetch('log_ekle_ajax.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: "islem_id=121&aciklama=" + encodeURIComponent("Stok geçmişi arama: " + this.value)
        });
    }
});
</script>
</body>
</html>
