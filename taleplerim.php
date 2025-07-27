<?php
require_once 'includes/db.php';
session_start();
require_once __DIR__ . '/includes/functions.php'; 
oturumKontrol();
$rol_id = $_SESSION['rol_id'];
$sayfa_id = getSayfaId(basename(__FILE__));
$sayfa_adi = basename(__FILE__); 
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

// Giriş kontrolü
if (!isset($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}
$personel_id = $_SESSION['kullanici_id'];

// -- SAYFA GÖRÜNTÜLEME LOGU (Talep Listeleme: id 60)
log_ekle($db, $personel_id, 60, 'goruntuleme', 'Taleplerim sayfası görüntülendi', 'başarılı');

// Bekleyen taleplerim
$bekleyen = $db->prepare("
    SELECT t.id AS talep_id, t.talep_tarihi, t.aciliyet, t.aciklama, t.durum,
        tu.malzeme_id, tu.istenen_miktar, tu.onaylanan_miktar, m.ad AS malzeme_ad, m.birim
    FROM talepler t
    JOIN talep_urunleri tu ON tu.talep_id = t.id
    JOIN malzemeler m ON tu.malzeme_id = m.id
    WHERE t.durum = 'beklemede' AND t.personel_id = ?
    ORDER BY FIELD(t.aciliyet, 'çok acil', 'acil', 'normal'), t.talep_tarihi ASC, t.id DESC
");
$bekleyen->execute([$personel_id]);
$bekleyen = $bekleyen->fetchAll(PDO::FETCH_ASSOC);

// Cevaplanan taleplerim
$cevaplanan = $db->prepare("
    SELECT t.id AS talep_id, t.talep_tarihi, t.aciliyet, t.aciklama, t.durum,
        tu.malzeme_id, tu.istenen_miktar, tu.onaylanan_miktar, m.ad AS malzeme_ad, m.birim
    FROM talepler t
    JOIN talep_urunleri tu ON tu.talep_id = t.id
    JOIN malzemeler m ON tu.malzeme_id = m.id
    WHERE t.durum IN ('onaylandi','reddedildi','kismi_onay') AND t.personel_id = ?
    ORDER BY t.talep_tarihi DESC, t.id DESC
");
$cevaplanan->execute([$personel_id]);
$cevaplanan = $cevaplanan->fetchAll(PDO::FETCH_ASSOC);

// Aciliyet badge rengi fonksiyonu
function aciliyetBadge($aciliyet) {
    if($aciliyet == 'çok acil') return 'danger';
    if($aciliyet == 'acil') return 'warning text-dark';
    return 'secondary';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Taleplerim</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f7f9fb;">
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
    <div class="container py-5">
        <h2 class="mb-4 fw-bold text-center">Bekleyen Taleplerim</h2>
        <div class="row mb-2">
            <div class="col-md-4 col-12 mx-auto">
                <input type="text" class="form-control" id="searchBekleyen" placeholder="Arama: malzeme, açıklama, tarih...">
            </div>
        </div>
        <div class="table-responsive mb-5">
            <table class="table table-bordered align-middle shadow-sm" id="bekleyenTable">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>Tarih</th>
                        <th>Malzeme</th>
                        <th>İstenen</th>
                        <th>Aciliyet</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($bekleyen as $i => $talep): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($talep['talep_tarihi'])) ?></td>
                        <td><?= htmlspecialchars($talep['malzeme_ad']) ?> <span class="text-muted">(<?= $talep['birim'] ?>)</span></td>
                        <td><?= $talep['istenen_miktar'] ?></td>
                        <td><span class="badge bg-<?= aciliyetBadge($talep['aciliyet']) ?>"><?= ucfirst($talep['aciliyet']) ?></span></td>
                        <td><?= htmlspecialchars($talep['aciklama']) ?></td>
                    </tr>
                <?php endforeach; if(empty($bekleyen)): ?>
                    <tr><td colspan="6" class="text-center">Bekleyen talebiniz yok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h4 class="fw-bold text-center mb-3">Cevaplanan Taleplerim</h4>
        <div class="row mb-2">
            <div class="col-md-4 col-12 mx-auto">
                <input type="text" class="form-control" id="searchCevaplanan" placeholder="Arama: malzeme, açıklama, tarih...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle shadow-sm" id="cevaplananTable">
                <thead class="table-secondary">
                    <tr>
                        <th>#</th>
                        <th>Tarih</th>
                        <th>Malzeme</th>
                        <th>İstenen</th>
                        <th>Onaylanan</th>
                        <th>Aciliyet</th>
                        <th>Açıklama</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($cevaplanan as $i => $talep): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($talep['talep_tarihi'])) ?></td>
                        <td><?= htmlspecialchars($talep['malzeme_ad']) ?> <span class="text-muted">(<?= $talep['birim'] ?>)</span></td>
                        <td><?= $talep['istenen_miktar'] ?></td>
                        <td><?= $talep['onaylanan_miktar'] ?></td>
                        <td><span class="badge bg-<?= aciliyetBadge($talep['aciliyet']) ?>"><?= ucfirst($talep['aciliyet']) ?></span></td>
                        <td><?= htmlspecialchars($talep['aciklama']) ?></td>
                        <td>
                            <?php
                            if ($talep['durum'] == 'onaylandi') echo '<span class="badge bg-success">Onaylandı</span>';
                            elseif ($talep['durum'] == 'kismi_onay') echo '<span class="badge bg-info text-dark">Kısmi Onay</span>';
                            else echo '<span class="badge bg-danger">Reddedildi</span>';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; if(empty($cevaplanan)): ?>
                    <tr><td colspan="8" class="text-center">Cevaplanmış talebiniz yok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // AJAX ile arama logu (Talep Arama: id 94)
    function logArama(islemId, aciklama) {
        fetch('log_ekle_ajax.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: "islem_id=" + islemId + "&aciklama=" + encodeURIComponent(aciklama)
        });
    }
    // Tablo arama fonksiyonu (her tabloya ayrı)
    function setupTableSearch(inputId, tableId) {
        document.getElementById(inputId).addEventListener("keyup", function(e) {
            let value = this.value.toLowerCase();
            let rows = document.querySelectorAll(`#${tableId} tbody tr`);
            rows.forEach(row => {
                let rowText = row.innerText.toLowerCase();
                row.style.display = rowText.indexOf(value) > -1 ? '' : 'none';
            });
            // Sadece ilk harfte log atsın, çoklu log oluşmasın
            if (this.value.length === 1) {
                logArama(94, "Taleplerim tablosunda arama yapıldı: " + this.value);
            }
        });
    }
    setupTableSearch('searchBekleyen', 'bekleyenTable');
    setupTableSearch('searchCevaplanan', 'cevaplananTable');
    </script>
</body>
</html>
