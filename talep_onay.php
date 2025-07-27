<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
session_start();

if (!isset($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/includes/functions.php'; 
oturumKontrol();
$rol_id = $_SESSION['rol_id'];
$sayfa_id = getSayfaId(basename(__FILE__));
$sayfa_adi = basename(__FILE__); 
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

$personel_id = $_SESSION['kullanici_id'];

// -- SAYFA GÖRÜNTÜLEME LOGU (Talep Listeleme)
log_ekle($db, $personel_id, 60, 'goruntuleme', 'Talep Onay sayfası görüntülendi', 'başarılı');

$sebep_options = [
    "Stok yetersizliği",
    "Yanlış talep", 
    "Fazla talep",
    "Diğer"
];

// Onay/red işlemi (stok güncellemesi ve acil ihtiyaç güncellemesi dahil)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $talep_id = intval($_POST['talep_id']);
    $malzeme_id = intval($_POST['malzeme_id']);
    $istenen = floatval($_POST['istenen']);
    $onaylanan = isset($_POST['onayla']) ? floatval($_POST['onay_miktar']) : 0;
    $sebep = $_POST['sebep'] ?? null;
    $red = isset($_POST['reddet']);

    if ($red) {
        // Talep reddedildi
        $db->prepare("UPDATE talep_urunleri SET onaylanan_miktar = 0, sebep = ? WHERE talep_id = ? AND malzeme_id = ?")
            ->execute([$sebep ?? 'Reddedildi', $talep_id, $malzeme_id]);
        $db->prepare("UPDATE talepler SET durum = 'reddedildi', onay_tarihi = NOW() WHERE id = ?")
            ->execute([$talep_id]);
        
        // LOG: Red işlemi
        $log_mesaj = "Talep RED: Talep ID: $talep_id, Malzeme ID: $malzeme_id, Sebep: $sebep";
        log_ekle($db, $personel_id, 63, 'red', $log_mesaj, 'başarılı');

        // Sadece sebep 'Stok yetersizliği' ise acil ihtiyaca ekle
        if (($sebep === "Stok yetersizliği") && $istenen > 0) {
            $acil = $db->prepare("SELECT id FROM acil_ihtiyaclar WHERE malzeme_id = ?");
            $acil->execute([$malzeme_id]);
            $var = $acil->fetch(PDO::FETCH_ASSOC);
            if ($var) {
                $db->prepare("UPDATE acil_ihtiyaclar SET eksik_adet = eksik_adet + ? WHERE malzeme_id = ?")
                    ->execute([$istenen, $malzeme_id]);
            } else {
                $db->prepare("INSERT INTO acil_ihtiyaclar (malzeme_id, eksik_adet) VALUES (?, ?)")
                    ->execute([$malzeme_id, $istenen]);
            }
        }
    } elseif ($onaylanan > 0) {
        $durum = ($onaylanan == $istenen) ? 'onaylandi' : 'kismi_onay';
        $kismi = $onaylanan < $istenen;
        $db->prepare("UPDATE talep_urunleri SET onaylanan_miktar = ?, sebep = ? WHERE talep_id = ? AND malzeme_id = ?")
            ->execute([$onaylanan, $kismi ? $sebep : null, $talep_id, $malzeme_id]);
        $db->prepare("UPDATE malzemeler SET mevcut_stok = mevcut_stok - ? WHERE id = ?")
            ->execute([$onaylanan, $malzeme_id]);
        $db->prepare("UPDATE talepler SET durum = ?, onay_tarihi = NOW() WHERE id = ?")
            ->execute([$durum, $talep_id]);

        // LOG: Onay/Kısmi Onay
        $log_mesaj = "Talep ONAY: Talep ID: $talep_id, Malzeme ID: $malzeme_id, Onaylanan: $onaylanan, İstenen: $istenen, Sebep: $sebep";
        log_ekle($db, $personel_id, 62, $kismi ? 'kismi_onay' : 'onay', $log_mesaj, 'başarılı');

        // Stok hareketleri tablosuna çıkış kaydı ekle
        $hareket_tip = 'cikis'; // ENUM'a uygun değer!
        $aciklama = "Talep onaylandı (Talep ID: $talep_id, Personel ID: $personel_id)";
        $db->prepare("INSERT INTO stok_hareketleri (malzeme_id, hareket_tip, miktar, aciklama, islem_tarihi) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$malzeme_id, $hareket_tip, $onaylanan, $aciklama]);

        // Sadece sebep 'Stok yetersizliği' ise acil ihtiyaca ekle
        $eksik = $istenen - $onaylanan;
        if ($kismi && $eksik > 0 && $sebep === "Stok yetersizliği") {
            $acil = $db->prepare("SELECT id FROM acil_ihtiyaclar WHERE malzeme_id = ?");
            $acil->execute([$malzeme_id]);
            $var = $acil->fetch(PDO::FETCH_ASSOC);
            if ($var) {
                $db->prepare("UPDATE acil_ihtiyaclar SET eksik_adet = eksik_adet + ? WHERE malzeme_id = ?")
                    ->execute([$eksik, $malzeme_id]);
            } else {
                $db->prepare("INSERT INTO acil_ihtiyaclar (malzeme_id, eksik_adet) VALUES (?, ?)")
                    ->execute([$malzeme_id, $eksik]);
            }
        }
    }
}


// Bekleyen talepler
$bekleyen = $db->query("
    SELECT t.id AS talep_id, t.talep_tarihi, t.aciliyet, t.aciklama, t.durum, p.ad AS personel_ad, p.soyad, p.sicil,
        tu.malzeme_id, tu.istenen_miktar, tu.onaylanan_miktar, tu.sebep, m.ad AS malzeme_ad, m.mevcut_stok, m.birim
    FROM talepler t
    JOIN personeller p ON t.personel_id = p.id
    JOIN talep_urunleri tu ON tu.talep_id = t.id
    JOIN malzemeler m ON tu.malzeme_id = m.id
    WHERE t.durum = 'beklemede'
    ORDER BY FIELD(t.aciliyet, 'çok acil', 'acil', 'normal'), t.talep_tarihi ASC, t.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Cevaplanmış talepler
$cevaplanan = $db->query("
    SELECT t.id AS talep_id, t.talep_tarihi, t.aciliyet, t.aciklama, t.durum, p.ad AS personel_ad, p.soyad, p.sicil,
        tu.malzeme_id, tu.istenen_miktar, tu.onaylanan_miktar, tu.sebep, m.ad AS malzeme_ad, m.birim
    FROM talepler t
    JOIN personeller p ON t.personel_id = p.id
    JOIN talep_urunleri tu ON tu.talep_id = t.id
    JOIN malzemeler m ON tu.malzeme_id = m.id
    WHERE t.durum IN ('onaylandi','reddedildi','kismi_onay')
    ORDER BY t.talep_tarihi DESC, t.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Acil ihtiyaçlar tablosunu çek
$acil_ihtiyaclar = $db->query("
    SELECT ai.*, m.ad AS malzeme_ad, m.birim, m.mevcut_stok
    FROM acil_ihtiyaclar ai
    JOIN malzemeler m ON ai.malzeme_id = m.id
    WHERE ai.eksik_adet > 0
    ORDER BY ai.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Talep Onay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .eksik-adet { color:#fff; background:#dc3545; border-radius:4px; padding:2px 8px; font-weight:bold; }
    </style>
</head>
<body style="background:#f7f9fb;">
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary mb-0">Bekleyen Talepler</h2>
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#acilIhtiyacModal">
            <i class="bi bi-exclamation-circle-fill"></i>
            Acil İhtiyaçlar
            <?php if (count($acil_ihtiyaclar) > 0): ?>
                <span class="badge bg-danger"><?= count($acil_ihtiyaclar) ?></span>
            <?php endif; ?>
        </button>
    </div>
    <div class="row mb-2">
        <div class="col-md-4 col-12 mx-auto">
            <input type="text" class="form-control" id="searchBekleyen" placeholder="Arama: malzeme, personel, sicil, tarih...">
        </div>
    </div>
    <div class="table-responsive mb-5">
        <table class="table table-bordered align-middle shadow-sm" id="bekleyenTable">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Sicil</th>
                    <th>Talep Eden</th>
                    <th>Tarih</th>
                    <th>Malzeme</th>
                    <th>İstenen</th>
                    <th>Stokta</th>
                    <th>Aciliyet</th>
                    <th>Açıklama</th>
                    <th>Eksik</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($bekleyen as $i => $talep):
                $eksik = max(0, $talep['istenen_miktar'] - $talep['mevcut_stok']);
            ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($talep['sicil']) ?></td>
                    <td><?= htmlspecialchars($talep['personel_ad']) ?> <?= htmlspecialchars($talep['soyad']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($talep['talep_tarihi'])) ?></td>
                    <td><?= htmlspecialchars($talep['malzeme_ad']) ?> <span class="text-muted">(<?= $talep['birim'] ?>)</span></td>
                    <td><?= $talep['istenen_miktar'] ?></td>
                    <td><?= $talep['mevcut_stok'] ?></td>
                    <td><span class="badge bg-<?= aciliyetBadge($talep['aciliyet']) ?>"><?= ucfirst($talep['aciliyet']) ?></span></td>
                    <td><?= htmlspecialchars($talep['aciklama']) ?></td>
                    <td>
                        <?php if($eksik > 0): ?>
                            <span class="eksik-adet"><?= $eksik ?></span>
                        <?php else: ?>
                            <span class="badge bg-success">Yeterli</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" class="d-flex flex-column gap-1 onay-form">
                            <input type="hidden" name="talep_id" value="<?= $talep['talep_id'] ?>">
                            <input type="hidden" name="malzeme_id" value="<?= $talep['malzeme_id'] ?>">
                            <input type="hidden" name="istenen" value="<?= $talep['istenen_miktar'] ?>">
                            <?php if($talep['mevcut_stok'] <= 0): ?>
                                <span class="badge bg-warning text-dark">Stokta yok</span>
                                <select name="sebep" class="form-select form-select-sm mb-1 sebep-dropdown">
                                    <?php foreach($sebep_options as $opt): ?>
                                    <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button name="reddet" class="btn btn-outline-danger btn-sm mt-1">Reddet</button>
                            <?php else: ?>
                                <?php if($eksik > 0): ?>
                                    <div class="text-danger small mb-1">Stok yetersiz! En fazla <b><?= $talep['mevcut_stok'] ?></b> adet onaylanabilir.</div>
                                <?php endif; ?>
                                <input type="number" name="onay_miktar" class="form-control form-control-sm mb-1" min="1" max="<?= $talep['mevcut_stok'] ?>" value="<?= min($talep['mevcut_stok'], $talep['istenen_miktar']) ?>" required>
                                <select name="sebep" class="form-select form-select-sm mb-1 sebep-dropdown" style="display:none;">
                                    <?php foreach($sebep_options as $opt): ?>
                                    <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button name="onayla" class="btn btn-success btn-sm">Onayla</button>
                                <button name="reddet" class="btn btn-outline-danger btn-sm mt-1">Reddet</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; if(empty($bekleyen)): ?>
                <tr><td colspan="11" class="text-center">Bekleyen talep yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h4 class="fw-bold text-center mb-3">Cevaplanan Talepler</h4>
    <div class="row mb-2">
        <div class="col-md-4 col-12 mx-auto">
            <input type="text" class="form-control" id="searchCevaplanan" placeholder="Arama: malzeme, personel, sicil, tarih...">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle shadow-sm" id="cevaplananTable">
            <thead class="table-secondary">
                <tr>
                    <th>#</th>
                    <th>Sicil</th>
                    <th>Talep Eden</th>
                    <th>Tarih</th>
                    <th>Malzeme</th>
                    <th>İstenen</th>
                    <th>Onaylanan</th>
                    <th>Aciliyet</th>
                    <th>Açıklama</th>
                    <th>Durum</th>
                    <th>Sebep</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($cevaplanan as $i => $talep): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($talep['sicil']) ?></td>
                    <td><?= htmlspecialchars($talep['personel_ad']) ?> <?= htmlspecialchars($talep['soyad']) ?></td>
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
                    <td><?= htmlspecialchars($talep['sebep'] ?? '-') ?></td>
                </tr>
            <?php endforeach; if(empty($cevaplanan)): ?>
                <tr><td colspan="11" class="text-center">Cevaplanmış talep yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ACİL İHTİYAÇ MODALI -->
<div class="modal fade" id="acilIhtiyacModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-danger bg-opacity-75">
        <h5 class="modal-title text-white"><i class="bi bi-exclamation-circle-fill me-2"></i>Acil Stok İhtiyacı</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if(count($acil_ihtiyaclar) == 0): ?>
            <div class="alert alert-success">Acil stok ihtiyacı yok.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-danger align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Malzeme</th>
                            <th>Birim</th>
                            <th>Mevcut Stok</th>
                            <th>Acil İhtiyaç</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acil_ihtiyaclar as $idx => $a): ?>
                        <tr>
                            <td><?= $idx+1 ?></td>
                            <td><?= htmlspecialchars($a['malzeme_ad']) ?></td>
                            <td><?= htmlspecialchars($a['birim']) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($a['mevcut_stok']) ?></span></td>
                            <td><span class="badge bg-danger"><?= htmlspecialchars($a['eksik_adet']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Arama fonksiyonu ve log
function setupTableSearch(inputId, tableId, islemId) {
    document.getElementById(inputId).addEventListener("keyup", function() {
        let value = this.value.toLowerCase();
        let rows = document.querySelectorAll(`#${tableId} tbody tr`);
        rows.forEach(row => {
            let rowText = row.innerText.toLowerCase();
            row.style.display = rowText.indexOf(value) > -1 ? '' : 'none';
        });

        // LOG (Arama) - Sadece ilk karakterde tetikler
        if (this.value.length == 1 && islemId) {
            fetch('log_ekle_ajax.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: "islem_id=" + islemId + "&aciklama=Arama yapıldı ("+inputId+")"
            });
        }
    });
}
setupTableSearch('searchBekleyen', 'bekleyenTable', 94);
setupTableSearch('searchCevaplanan', 'cevaplananTable', 94);

// Kısmi onayda sebep zorunlu dropdownu göster
document.querySelectorAll('.onay-form').forEach(function(frm){
    let miktar = frm.querySelector('input[name="onay_miktar"]');
    let istenen = frm.querySelector('input[name="istenen"]');
    let sebepSel = frm.querySelector('.sebep-dropdown');
    if(!miktar) return;
    miktar.addEventListener('input', function(){
        if(parseFloat(this.value) < parseFloat(istenen.value)) {
            sebepSel.style.display = '';
            sebepSel.required = true;
        } else {
            sebepSel.style.display = 'none';
            sebepSel.required = false;
        }
    });
    if(parseFloat(miktar.value) < parseFloat(istenen.value)) {
        sebepSel.style.display = '';
        sebepSel.required = true;
    }
});
</script>
</body>
</html>
