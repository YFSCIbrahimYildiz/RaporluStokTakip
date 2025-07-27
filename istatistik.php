<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['kullanici_id']) || !isset($_SESSION['rol_id'])) {
    header("Location: login.php");
    exit;
}
if (!sayfaErisimKontrol($_SESSION['rol_id'], basename(__FILE__))) {
    header("Location: dashboard.php");
    exit;
}

// --- LOG KAYITLARI ---
$islem_id = 80; // islemler tablosunda "İstatistik Görüntüleme" id'si (senin eklediğin gibi)
$page_name = basename(__FILE__);

// Sayfa ilk kez açıldığında log kaydı (sadece ilk yüklemede)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['t1']) && !isset($_GET['t2'])) {
    log_ekle(
        $db,
        $_SESSION['kullanici_id'],
        $islem_id,
        'goruntuleme',
        "$page_name görüntülendi.",
        'başarılı'
    );
}

// Filtre uygulandıysa ayrı log kaydı
if (isset($_GET['t1']) || isset($_GET['t2'])) {
    $filtre_t1 = htmlspecialchars($_GET['t1'] ?? '');
    $filtre_t2 = htmlspecialchars($_GET['t2'] ?? '');
    log_ekle(
        $db,
        $_SESSION['kullanici_id'],
        $islem_id,
        'filtre',
        "$page_name filtre uygulandı. Başlangıç: $filtre_t1, Bitiş: $filtre_t2",
        'başarılı'
    );
}

// Varsayılan: Veritabanındaki en eski ve en yeni talep tarihi
$now = date('Y-m-d');
$ilkTarih = $db->query("SELECT MIN(talep_tarihi) FROM talepler")->fetchColumn() ?: $now;
$sonTarih = $db->query("SELECT MAX(talep_tarihi) FROM talepler")->fetchColumn() ?: $now;

$t1 = $_GET['t1'] ?? $ilkTarih;
$t2 = $_GET['t2'] ?? $sonTarih;

// EN ÇOK KULLANILAN MALZEMELER
$malzemeKullanAll = $db->query("
    SELECT m.id, m.ad, m.kod, SUM(tu.onaylanan_miktar) AS toplam_kullanilan
    FROM talep_urunleri tu
    INNER JOIN talepler t ON tu.talep_id = t.id
    INNER JOIN malzemeler m ON tu.malzeme_id = m.id
    WHERE tu.onaylanan_miktar > 0
      AND t.talep_tarihi BETWEEN '$t1' AND '$t2'
    GROUP BY m.id
    ORDER BY toplam_kullanilan DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pieLabelsKullanilan = [];
$pieDataKullanilan = [];
$otherSumKullanilan = 0;
foreach ($malzemeKullanAll as $i => $row) {
    if ($i < 150) {
        $pieLabelsKullanilan[] = $row['ad'];
        $pieDataKullanilan[] = $row['toplam_kullanilan'];
    } else {
        $otherSumKullanilan += $row['toplam_kullanilan'];
    }
}
if ($otherSumKullanilan > 0) {
    $pieLabelsKullanilan[] = "Diğer";
    $pieDataKullanilan[] = $otherSumKullanilan;
}

// EN ÇOK TALEP GİREN PERSONEL
$talepPersonelAll = $db->query("
    SELECT p.id, p.ad, p.soyad, p.sicil, COUNT(t.id) AS toplam_talep
    FROM talepler t
    INNER JOIN personeller p ON t.personel_id = p.id
    WHERE t.talep_tarihi BETWEEN '$t1' AND '$t2'
    GROUP BY p.id
    ORDER BY toplam_talep DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pieLabelsTalepci = [];
$pieDataTalepci = [];
$otherSumTalepci = 0;
foreach ($talepPersonelAll as $i => $p) {
    if ($i < 150) {
        $pieLabelsTalepci[] = $p['ad'].' '.$p['soyad'];
        $pieDataTalepci[] = $p['toplam_talep'];
    } else {
        $otherSumTalepci += $p['toplam_talep'];
    }
}
if ($otherSumTalepci > 0) {
    $pieLabelsTalepci[] = "Diğer";
    $pieDataTalepci[] = $otherSumTalepci;
}

// EN ÇOK MALZEME ALAN PERSONEL
$malAlanAll = $db->query("
    SELECT p.id AS personel_id, p.ad, p.soyad, p.sicil,
        GROUP_CONCAT(DISTINCT m.ad ORDER BY m.ad SEPARATOR ', ') AS malzemeler,
        SUM(tu.onaylanan_miktar) AS toplam_malzeme
    FROM talepler t
    INNER JOIN personeller p ON t.personel_id = p.id
    INNER JOIN talep_urunleri tu ON tu.talep_id = t.id
    INNER JOIN malzemeler m ON tu.malzeme_id = m.id
    WHERE tu.onaylanan_miktar > 0
      AND t.talep_tarihi BETWEEN '$t1' AND '$t2'
    GROUP BY p.id
    ORDER BY toplam_malzeme DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pieLabelsAlankisi = [];
$pieDataAlankisi = [];
$otherSumAlankisi = 0;
foreach ($malAlanAll as $i => $p) {
    if ($i < 150) {
        $pieLabelsAlankisi[] = $p['ad'].' '.$p['soyad'];
        $pieDataAlankisi[] = $p['toplam_malzeme'];
    } else {
        $otherSumAlankisi += $p['toplam_malzeme'];
    }
}
if ($otherSumAlankisi > 0) {
    $pieLabelsAlankisi[] = "Diğer";
    $pieDataAlankisi[] = $otherSumAlankisi;
}

// EN ÇOK STOK EKLENEN MALZEME
$stokEklenenAll = $db->query("
    SELECT m.id, m.ad, m.kod, SUM(su.miktar) AS toplam_eklenen
    FROM siparis_urunleri su
    INNER JOIN malzemeler m ON su.malzeme_id = m.id
    INNER JOIN siparisler s ON su.siparis_id = s.id
    WHERE s.siparis_tarihi BETWEEN '$t1' AND '$t2'
    GROUP BY m.id
    ORDER BY toplam_eklenen DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pieLabelsStokeklenen = [];
$pieDataStokeklenen = [];
$otherSumStokeklenen = 0;
foreach ($stokEklenenAll as $i => $row) {
    if ($i < 150) {
        $pieLabelsStokeklenen[] = $row['ad'];
        $pieDataStokeklenen[] = $row['toplam_eklenen'];
    } else {
        $otherSumStokeklenen += $row['toplam_eklenen'];
    }
}
if ($otherSumStokeklenen > 0) {
    $pieLabelsStokeklenen[] = "Diğer";
    $pieDataStokeklenen[] = $otherSumStokeklenen;
}

// MALZEME BAZLI ORTALAMA BİRİM FİYAT
$malzemeFiyatAll = $db->query("
    SELECT m.id, m.ad, m.kod, 
        SUM(su.miktar) AS toplam_urun, 
        SUM(su.birim_fiyat * su.miktar) AS toplam_tutar,
        ROUND(SUM(su.birim_fiyat * su.miktar) / NULLIF(SUM(su.miktar), 0), 2) AS ortalama_birim_fiyat
    FROM siparis_urunleri su
    INNER JOIN malzemeler m ON su.malzeme_id = m.id
    INNER JOIN siparisler s ON su.siparis_id = s.id
    WHERE s.siparis_tarihi BETWEEN '$t1' AND '$t2'
    GROUP BY m.id
    ORDER BY toplam_tutar DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pieLabelsMalzemefiyat = [];
$pieDataMalzemefiyat = [];
$otherSumMalzemefiyat = 0;
foreach ($malzemeFiyatAll as $i => $row) {
    if ($i < 150) {
        $pieLabelsMalzemefiyat[] = $row['ad'];
        $pieDataMalzemefiyat[] = $row['ortalama_birim_fiyat'];
    } else {
        $otherSumMalzemefiyat += $row['ortalama_birim_fiyat'];
    }
}
if ($otherSumMalzemefiyat > 0) {
    $pieLabelsMalzemefiyat[] = "Diğer";
    $pieDataMalzemefiyat[] = $otherSumMalzemefiyat;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İstatistikler</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tab-content {background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(60,72,100,.09);}
        .tab-pane table {margin-bottom: 0;}
    </style>
</head>
<body style="background:#f7f9fb;">
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container py-5">
    <h2 class="mb-4 fw-bold text-center">Stok İstatistikleri</h2>
    <form method="get" class="row g-3 align-items-end mb-4">
        <div class="col-auto">
            <label for="t1" class="form-label">Başlangıç</label>
            <input type="date" id="t1" name="t1" class="form-control" value="<?= htmlspecialchars($t1) ?>">
        </div>
        <div class="col-auto">
            <label for="t2" class="form-label">Bitiş</label>
            <input type="date" id="t2" name="t2" class="form-control" value="<?= htmlspecialchars($t2) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filtrele</button>
            <?php if(isset($_GET['t1']) || isset($_GET['t2'])): ?>
                <a href="istatistik.php" class="btn btn-outline-secondary ms-2">Tarihi Sıfırla</a>
            <?php endif; ?>
        </div>
    </form>
    <ul class="nav nav-tabs mb-4" id="istatistikTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="kullanilan-tab" data-bs-toggle="tab" data-bs-target="#kullanilan" type="button" role="tab">En Çok Kullanılan Malzemeler</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="talepci-tab" data-bs-toggle="tab" data-bs-target="#talepci" type="button" role="tab">En Çok Talep Giren Personel</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="alankisi-tab" data-bs-toggle="tab" data-bs-target="#alankisi" type="button" role="tab">En Çok Malzeme Alan Personel</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="stokeklenen-tab" data-bs-toggle="tab" data-bs-target="#stokeklenen" type="button" role="tab">En Çok Stok Eklenen Malzemeler</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="malzemefiyat-tab" data-bs-toggle="tab" data-bs-target="#malzemefiyat" type="button" role="tab">Malzeme Ortalama Fiyat</button></li>
    </ul>
    <div class="tab-content py-3" id="istatistikTabContent">
        <!-- Her sekmede arama ve tablo -->
        <div class="tab-pane fade show active" id="kullanilan" role="tabpanel">
            <button class="btn btn-outline-success mb-3" onclick="showPieChartFull('kullanilan')">Tümünü Pasta Grafik Olarak Görüntüle</button>
            <input type="text" class="form-control mb-3" id="searchKullanilan" placeholder="Malzeme adı/kodu ile ara...">
            <table class="table table-striped table-bordered align-middle shadow-sm" id="kullanilanTable">
                <thead class="table-primary"><tr><th>#</th><th>Malzeme Kodu</th><th>Malzeme Adı</th><th>Kullanım Miktarı</th></tr></thead>
                <tbody>
                <?php foreach ($malzemeKullanAll as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($row['kod']) ?></td>
                        <td><?= htmlspecialchars($row['ad']) ?></td>
                        <td><span class="badge bg-success"><?= $row['toplam_kullanilan'] ?></span></td>
                    </tr>
                <?php endforeach; if (empty($malzemeKullanAll)): ?>
                    <tr><td colspan="4" class="text-center">Veri bulunamadı.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="tab-pane fade" id="talepci" role="tabpanel">
            <button class="btn btn-outline-success mb-3" onclick="showPieChartFull('talepci')">Tümünü Pasta Grafik Olarak Görüntüle</button>
            <input type="text" class="form-control mb-3" id="searchTalepci" placeholder="Ad, soyad, sicil ile ara...">
            <table class="table table-striped table-bordered align-middle shadow-sm" id="talepciTable">
                <thead class="table-secondary"><tr><th>#</th><th>Sicil</th><th>Ad Soyad</th><th>Toplam Talep</th></tr></thead>
                <tbody>
                <?php foreach($talepPersonelAll as $i => $p): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($p['sicil']) ?></td>
                        <td><?= htmlspecialchars($p['ad'].' '.$p['soyad']) ?></td>
                        <td><span class="badge bg-info"><?= $p['toplam_talep'] ?></span></td>
                    </tr>
                <?php endforeach; if(empty($talepPersonelAll)): ?>
                    <tr><td colspan="4" class="text-center">Veri bulunamadı.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="tab-pane fade" id="alankisi" role="tabpanel">
            <button class="btn btn-outline-success mb-3" onclick="showPieChartFull('alankisi')">Tümünü Pasta Grafik Olarak Görüntüle</button>
            <input type="text" class="form-control mb-3" id="searchAlankisi" placeholder="Ad, soyad, sicil, malzeme ile ara...">
            <table class="table table-striped table-bordered align-middle shadow-sm" id="alankisiTable">
                <thead class="table-secondary"><tr><th>#</th><th>Sicil</th><th>Ad Soyad</th><th>Malzemeler</th><th>Toplam Aldığı Miktar</th></tr></thead>
                <tbody>
                <?php foreach($malAlanAll as $i => $p): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($p['sicil']) ?></td>
                        <td><?= htmlspecialchars($p['ad'].' '.$p['soyad']) ?></td>
                        <td><?= htmlspecialchars($p['malzemeler']) ?></td>
                        <td><span class="badge bg-primary"><?= $p['toplam_malzeme'] ?></span></td>
                    </tr>
                <?php endforeach; if(empty($malAlanAll)): ?>
                    <tr><td colspan="5" class="text-center">Veri bulunamadı.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="tab-pane fade" id="stokeklenen" role="tabpanel">
            <button class="btn btn-outline-success mb-3" onclick="showPieChartFull('stokeklenen')">Tümünü Pasta Grafik Olarak Görüntüle</button>
            <input type="text" class="form-control mb-3" id="searchStokeklenen" placeholder="Malzeme adı/kodu ile ara...">
            <table class="table table-striped table-bordered align-middle shadow-sm" id="stokeklenenTable">
                <thead class="table-secondary"><tr><th>#</th><th>Malzeme Kodu</th><th>Malzeme Adı</th><th>Eklenen Miktar</th></tr></thead>
                <tbody>
                <?php foreach($stokEklenenAll as $i => $row): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($row['kod']) ?></td>
                        <td><?= htmlspecialchars($row['ad']) ?></td>
                        <td><span class="badge bg-success"><?= $row['toplam_eklenen'] ?></span></td>
                    </tr>
                <?php endforeach; if(empty($stokEklenenAll)): ?>
                    <tr><td colspan="4" class="text-center">Veri bulunamadı.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="tab-pane fade" id="malzemefiyat" role="tabpanel">
            <button class="btn btn-outline-success mb-3" onclick="showPieChartFull('malzemefiyat')">Tümünü Pasta Grafik Olarak Görüntüle</button>
            <input type="text" class="form-control mb-3" id="searchMalzemefiyat" placeholder="Malzeme adı/kodu ile ara...">
            <table class="table table-striped table-bordered align-middle shadow-sm" id="malzemefiyatTable">
                <thead class="table-secondary"><tr><th>#</th><th>Malzeme Kodu</th><th>Malzeme Adı</th><th>Ortalama Birim Fiyat (KDV'li)</th><th>Toplam Miktar</th><th>Toplam Tutar</th></tr></thead>
                <tbody>
                <?php foreach($malzemeFiyatAll as $i => $row): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($row['kod']) ?></td>
                        <td><?= htmlspecialchars($row['ad']) ?></td>
                        <td><?= number_format($row['ortalama_birim_fiyat'],2) ?></td>
                        <td><?= $row['toplam_urun'] ?></td>
                        <td><?= number_format($row['toplam_tutar'],2) ?></td>
                    </tr>
                <?php endforeach; if(empty($malzemeFiyatAll)): ?>
                    <tr><td colspan="6" class="text-center">Veri bulunamadı.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="pieChartModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pieChartTitle">Pasta Grafik</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><canvas id="pieChartCanvas"></canvas></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var pieData = {
    kullanilan: { labels: <?= json_encode($pieLabelsKullanilan) ?>, data: <?= json_encode($pieDataKullanilan) ?> },
    talepci: { labels: <?= json_encode($pieLabelsTalepci) ?>, data: <?= json_encode($pieDataTalepci) ?> },
    alankisi: { labels: <?= json_encode($pieLabelsAlankisi) ?>, data: <?= json_encode($pieDataAlankisi) ?> },
    stokeklenen: { labels: <?= json_encode($pieLabelsStokeklenen) ?>, data: <?= json_encode($pieDataStokeklenen) ?> },
    malzemefiyat: { labels: <?= json_encode($pieLabelsMalzemefiyat) ?>, data: <?= json_encode($pieDataMalzemefiyat) ?> }
};
function showPieChartFull(tab) {
    let labels = pieData[tab].labels;
    let data = pieData[tab].data;
    var ctx = document.getElementById('pieChartCanvas').getContext('2d');
    if(window.pieChartObj) window.pieChartObj.destroy();
    window.pieChartObj = new Chart(ctx, {
        type: 'pie',
        data: { labels: labels, datasets: [{ data: data }] },
        options: { responsive:true, plugins:{legend:{display:true},title:{display:false}} }
    });
    document.getElementById('pieChartTitle').innerText = "Pasta Grafik";
    var modal = new bootstrap.Modal(document.getElementById('pieChartModal'));
    modal.show();
}
function setupTableSearch(inputId, tableId) {
    document.getElementById(inputId)?.addEventListener("keyup", function() {
        let value = this.value.toLowerCase();
        let rows = document.querySelectorAll(`#${tableId} tbody tr`);
        rows.forEach(row => {
            let rowText = row.innerText.toLowerCase();
            row.style.display = rowText.indexOf(value) > -1 ? '' : 'none';
        });
    });
}
setupTableSearch('searchKullanilan', 'kullanilanTable');
setupTableSearch('searchTalepci', 'talepciTable');
setupTableSearch('searchAlankisi', 'alankisiTable');
setupTableSearch('searchStokeklenen', 'stokeklenenTable');
setupTableSearch('searchMalzemefiyat', 'malzemefiyatTable');
</script>
</body>
</html>
