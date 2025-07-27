<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 
oturumKontrol();
$rol_id = $_SESSION['rol_id'];
$personel_id = $_SESSION['kullanici_id'];
$sayfa_adi = basename(__FILE__); 

if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

// SAYFA GÖRÜNTÜLEME LOGU
log_ekle(
    $db,
    $personel_id,
    41, // islemler tablosunda "Stok Giriş" id'si
    'goruntuleme',
    'Stok Ekle (Sipariş) sayfası görüntülendi.',
    'başarılı'
);

$malzemeler = getMalzemeler();
$tedarikciler = getTedarikciler();

$hata = '';
$basari = '';

// --- STOK/SİPARİŞ EKLEME ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['siparis_ekle'])) {
    try {
        $db->beginTransaction();

        $siparis_no = 'SP' . date('ymdHis') . rand(100,999);
        $tedarikci_id = intval($_POST['tedarikci_id']);
        $aciklama = trim($_POST['aciklama'] ?? '');
        $fatura_no = trim($_POST['fatura_no'] ?? '');

        $db->prepare("INSERT INTO siparisler (siparis_no, tedarikci_id, personel_id, aciklama, fatura_no) VALUES (?, ?, ?, ?, ?)")
            ->execute([$siparis_no, $tedarikci_id, $personel_id, $aciklama, $fatura_no]);
        $siparis_id = $db->lastInsertId();

        $toplam_tutar = 0;
        $urun_log_aciklama = [];

        foreach ($_POST['malzeme_id'] as $i => $malzeme_id) {
            $malzeme_id = intval($malzeme_id);
            $miktar = floatval($_POST['miktar'][$i]);
            $birim_fiyat = floatval($_POST['birim_fiyat'][$i]);
            $malzeme = array_filter($malzemeler, fn($m) => $m['id'] == $malzeme_id);
            $malzeme = reset($malzeme);
            $kdv_orani = $malzeme['kdv_orani'] ?? 0;
            $kdvli_birim_fiyat = $birim_fiyat * (1 + $kdv_orani/100);
            $toplam = $kdvli_birim_fiyat * $miktar;
            $toplam_tutar += $toplam;
            $urun_log_aciklama[] = $malzeme['ad'].'('.$miktar.')';

            $db->prepare("INSERT INTO siparis_urunleri (siparis_id, malzeme_id, miktar, birim_fiyat) VALUES (?, ?, ?, ?)")
                ->execute([$siparis_id, $malzeme_id, $miktar, $birim_fiyat]);
            $db->prepare("UPDATE malzemeler SET mevcut_stok = mevcut_stok + ? WHERE id = ?")
                ->execute([$miktar, $malzeme_id]);
            // --- STOK HAREKETLERİNE KAYIT EKLE (AÇIKLAMA İLE) ---
            $stok_aciklama = "Sipariş Girişi: Sipariş No: $siparis_no, Fatura No: $fatura_no, Tedarikçi: ".getTedarikciAdi($tedarikciler, $tedarikci_id).", Personel ID: $personel_id";
            $db->prepare("INSERT INTO stok_hareketleri (malzeme_id, hareket_tip, miktar, aciklama, ilgili_id) VALUES (?, 'giris', ?, ?, ?)")
                ->execute([$malzeme_id, $miktar, $stok_aciklama, $siparis_id]);

            // ACİL İHTİYAÇ GÜNCELLEME
            $acilSorgu = $db->prepare("SELECT * FROM acil_ihtiyaclar WHERE malzeme_id = ?");
            $acilSorgu->execute([$malzeme_id]);
            $acil = $acilSorgu->fetch(PDO::FETCH_ASSOC);
            if ($acil) {
                $yeni_eksik = $acil['eksik_adet'] - $miktar;
                if ($yeni_eksik > 0) {
                    $db->prepare("UPDATE acil_ihtiyaclar SET eksik_adet = ? WHERE id = ?")
                        ->execute([$yeni_eksik, $acil['id']]);
                } else {
                    $db->prepare("DELETE FROM acil_ihtiyaclar WHERE id = ?")
                        ->execute([$acil['id']]);
                }
            }
        }

        $db->prepare("UPDATE siparisler SET toplam_tutar = ? WHERE id = ?")
            ->execute([$toplam_tutar, $siparis_id]);

        // Fatura ekleme
        if (isset($_FILES['fatura']) && $_FILES['fatura']['error'] !== UPLOAD_ERR_NO_FILE) {
            $faturaSonuc = faturaEkle($siparis_id, $_FILES['fatura'], $personel_id);
            if (!$faturaSonuc) {
                // HATA LOGU
                log_ekle(
                    $db,
                    $personel_id,
                    51,
                    'ekleme',
                    'Sipariş kaydedildi ancak fatura yüklenemedi.',
                    'başarısız'
                );
                throw new Exception("Sipariş kaydedildi ancak fatura yüklenemedi. Dosya formatı, boyutu veya yazma izni hatalı olabilir.");
            } else {
                // FATURA EKLEME LOGU
                log_ekle(
                    $db,
                    $personel_id,
                    51,
                    'ekleme',
                    'Sipariş oluşturulurken fatura başarıyla yüklendi (Sipariş ID: ' . $siparis_id . ')',
                    'başarılı'
                );
            }
        }

        $db->commit();
        $basari = "Sipariş başarıyla kaydedildi!";

        // SİPARİŞ EKLEME LOGU
        log_ekle(
            $db,
            $personel_id,
            41,
            'ekleme',
            'Sipariş/Stok Ekleme (ID: '.$siparis_id.'). Malzemeler: ' . implode(', ', $urun_log_aciklama),
            'başarılı'
        );

    } catch (Exception $e) {
        $db->rollBack();
        $hata = "Kayıt sırasında hata: " . $e->getMessage();

        // HATA LOGU
        log_ekle(
            $db,
            $personel_id,
            41,
            'ekleme',
            'Sipariş/Stok ekleme sırasında hata: ' . $e->getMessage(),
            'başarısız'
        );
    }
}

// Fatura ekle (Ayrı)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fatura_ekle_id'])) {
    $siparis_id = intval($_POST['fatura_ekle_id']);
    if (isset($_FILES['fatura_ekle']) && $_FILES['fatura_ekle']['error'] == UPLOAD_ERR_OK) {
        if (faturaEkle($siparis_id, $_FILES['fatura_ekle'], $personel_id)) {
            $basari = "Fatura başarıyla yüklendi!";
            // FATURA EKLEME LOGU
            log_ekle(
                $db,
                $personel_id,
                51,
                'ekleme',
                'Sipariş sonrası fatura yüklendi. Sipariş ID: '.$siparis_id,
                'başarılı'
            );
        } else {
            $hata = "Fatura eklenemedi!";
            log_ekle(
                $db,
                $personel_id,
                51,
                'ekleme',
                'Sipariş sonrası fatura yüklenemedi. Sipariş ID: '.$siparis_id,
                'başarısız'
            );
        }
    } else {
        $hata = "Lütfen dosya seçiniz!";
    }
}

$siparisler = getSiparislerFull();

// Yardımcı fonksiyon: tedarikçi adını döndür
function getTedarikciAdi($tedarikciler, $id) {
    foreach ($tedarikciler as $t) {
        if ($t['id'] == $id) return $t['ad'];
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Stok Ekle / Sipariş</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6fa; }
        .card { border-radius: 1.25rem; box-shadow: 0 2px 16px #e2e2e2; }
        .form-title { font-size: 1.2rem; color: #224488; font-weight: 600; letter-spacing: 1px;}
        .urun-satir { background: #f8fafc; border-radius: .9rem; padding: 1rem; margin-bottom: .5rem; border:1px solid #eee;}
        .satir-sil { font-size: 1.1rem; margin-top: 2.1rem;}
        .table thead { background: #d1e6ff; color: #263e57; }
        .table tbody tr { border-bottom: 1.5px solid #e3e3e3; }
        .alert-success, .alert-danger { font-size:1rem; font-weight:500; }
        .siparis-table td, .siparis-table th { vertical-align: middle !important; }
        .siparis-table .btn { font-size: .9rem; padding: 3px 8px;}
        @media (max-width: 600px) {
            .urun-satir { padding: .7rem;}
            .form-title { font-size: 1.06rem;}
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-7 mx-auto">
            <div class="card p-4 mb-5">
                <div class="form-title mb-3">
                    <i class="bi bi-plus-circle me-1"></i>Yeni Stok Girişi / Sipariş Ekle
                </div>
                <?php if ($hata): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $hata ?></div><?php endif; ?>
                <?php if ($basari): ?><div class="alert alert-success"><i class="bi bi-check2-circle me-2"></i><?= $basari ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="siparisForm" autocomplete="off">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label">Tedarikçi</label>
                            <select name="tedarikci_id" class="form-select" required>
                                <option value="">Tedarikçi Seçiniz</option>
                                <?php foreach($tedarikciler as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['ad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fatura No</label>
                            <input type="text" name="fatura_no" class="form-control" placeholder="Fatura No" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fatura (jpg, png, pdf)</label>
                            <input type="file" name="fatura" accept=".jpg,.jpeg,.png,.pdf" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Açıklama</label>
                            <input type="text" name="aciklama" class="form-control" placeholder="Açıklama">
                        </div>
                    </div>
                    <div id="urunler-alani">
                        <div class="row g-2 urun-satir align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Malzeme</label>
                                <select name="malzeme_id[]" class="form-select malzeme-dropdown" required onchange="setKdvOrani(this)">
                                    <option value="">Malzeme Seçiniz</option>
                                    <?php foreach($malzemeler as $m): ?>
                                        <option value="<?= $m['id'] ?>" data-kdv="<?= $m['kdv_orani'] ?>">
                                            <?= htmlspecialchars($m['ad']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">KDV</label>
                                <input type="text" class="form-control kdv-orani text-center" readonly tabindex="-1" placeholder="%">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Miktar</label>
                                <input type="number" name="miktar[]" min="1" step="0.01" class="form-control miktar-input" required placeholder="Adet">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">KDV’siz Birim</label>
                                <input type="number" name="birim_fiyat[]" min="0" step="0.01" class="form-control kdvsize-fiyat" required placeholder="₺">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">KDV’li Birim</label>
                                <input type="text" class="form-control kdvlifiyat text-success" readonly tabindex="-1" placeholder="₺">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">KDV’li Toplam</label>
                                <input type="text" class="form-control kdvlitoplam text-primary" readonly tabindex="-1" placeholder="₺">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-danger satir-sil w-100" title="Satırı Sil"><i class="bi bi-trash3"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" class="btn btn-outline-secondary" id="satir-ekle-btn"><i class="bi bi-plus-lg"></i> Satır Ekle</button>
                        <button type="submit" name="siparis_ekle" class="btn btn-success px-4"><i class="bi bi-save me-1"></i> Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Sipariş Listesi -->
    <div class="card p-4 mb-5">
        <div class="form-title mb-2"><i class="bi bi-list-ul me-1"></i>Son Siparişler & Stok Girişleri</div>
        <div class="row mb-3">
            <div class="col-md-4 ms-auto">
                <input type="text" id="siparisAra" class="form-control" placeholder="Tedarikçi, Fatura No, Açıklama, Tarih, Tutar, Ürünler veya Fatura var/yok...">
            </div>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 siparis-table" id="siparisTablo">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tedarikçi</th>
                    <th>Fatura No</th>
                    <th>Açıklama</th>
                    <th>Tarih</th>
                    <th>Tutar</th>
                    <th>Ürünler</th>
                    <th>Fatura</th>
                    <th>Fatura Ekle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($siparisler as $s): ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['tedarikci_adi']) ?></td>
                    <td><?= htmlspecialchars($s['fatura_no']) ?></td>
                    <td><?= htmlspecialchars($s['aciklama']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($s['siparis_tarihi'])) ?></td>
                    <td><?= $s['toplam_tutar'] ? number_format($s['toplam_tutar'],2) : '-' ?> ₺</td>
                    <td>
                        <?php foreach($s['urunler'] as $u): ?>
                            <div>
                                <span class="badge bg-light text-dark border">
                                    <?= htmlspecialchars($u['malzeme_adi']) ?> (<?= $u['miktar'] ?> <?= $u['birim'] ?>, KDV: %<?= $u['kdv_orani'] ?>)
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td class="fatura-cell">
                        <?php if($s['fatura']): ?>
                            <a href="<?= $s['fatura']['dosya_yolu'] ?>" target="_blank" class="btn btn-outline-info btn-sm fatura-link"
                               data-siparis-id="<?= $s['id'] ?>"
                               onclick="logFaturaGoruntule(<?= $s['id'] ?>)">
                                <i class="bi bi-receipt"></i> Görüntüle
                            </a>
                            <span class="fatura-var d-none">var</span>
                        <?php else: ?>
                            <span class="text-danger">Yok</span>
                            <span class="fatura-yok d-none">yok</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" enctype="multipart/form-data" style="display:inline;">
                            <input type="hidden" name="fatura_ekle_id" value="<?= $s['id'] ?>">
                            <input type="file" name="fatura_ekle" accept=".jpg,.jpeg,.png,.pdf" class="form-control form-control-sm mb-1" required>
                            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// KDV oranını göster + otomatik hesaplama
function setKdvOrani(sel){
    var kdv = sel.selectedOptions[0].dataset.kdv || '';
    var row = sel.closest('.urun-satir');
    row.querySelector('.kdv-orani').value = kdv;
    hesaplaSatir(row);
}
function hesaplaSatir(row){
    let kdv = parseFloat(row.querySelector('.malzeme-dropdown').selectedOptions[0].dataset.kdv) || 0;
    let fiyat = parseFloat(row.querySelector('.kdvsize-fiyat').value) || 0;
    let m = parseFloat(row.querySelector('.miktar-input').value) || 0;
    let kdvlifiyat = fiyat * (1 + kdv/100);
    let toplam = kdvlifiyat * m;
    row.querySelector('.kdvlifiyat').value = kdvlifiyat.toFixed(2);
    row.querySelector('.kdvlitoplam').value = toplam.toFixed(2);
}
document.addEventListener('input', function(e){
    if(e.target.matches('.kdvsize-fiyat, .miktar-input')){
        var row = e.target.closest('.urun-satir');
        hesaplaSatir(row);
    }
});
document.querySelectorAll('.malzeme-dropdown').forEach(setKdvOrani);
document.getElementById('satir-ekle-btn').onclick = function() {
    let firstRow = document.querySelector('.urun-satir');
    let row = firstRow.cloneNode(true);
    row.querySelectorAll('input,select').forEach(i => { i.value=''; });
    row.querySelector('.kdv-orani').value = '';
    row.querySelector('.kdvlifiyat').value = '';
    row.querySelector('.kdvlitoplam').value = '';
    row.querySelector('.malzeme-dropdown').onchange = function(){setKdvOrani(this)};
    row.querySelector('.satir-sil').onclick = function(){ row.remove(); };
    document.getElementById('urunler-alani').appendChild(row);
};
document.querySelectorAll('.satir-sil').forEach(btn => {
    btn.onclick = function(){ btn.closest('.urun-satir').remove(); }
});
// Tablo canlı arama/filtresi
document.getElementById('siparisAra').addEventListener('keyup', function() {
    let value = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll('#siparisTablo tbody tr');
    rows.forEach(function(row) {
        let text = row.innerText.toLowerCase();
        if (["fatura var", "var"].includes(value)) {
            let hasFatura = row.querySelector('.fatura-var');
            row.style.display = hasFatura ? '' : 'none';
        } else if (["fatura yok", "yok"].includes(value)) {
            let hasFatura = row.querySelector('.fatura-yok');
            row.style.display = hasFatura ? '' : 'none';
        } else {
            row.style.display = text.indexOf(value) > -1 ? '' : 'none';
        }
    });
});

// FATURA GÖRÜNTÜLEME LOGU (AJAX ile arka plana log düşürür)
function logFaturaGoruntule(siparis_id) {
    fetch('includes/log_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'islem_id=51&tip=goruntuleme&aciklama=' + encodeURIComponent('Fatura görüntülendi (Sipariş ID: ' + siparis_id + ')')
    });
}
</script>
</body>
</html>
