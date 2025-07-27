<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

oturumKontrol();
$kullanici_id = $_SESSION['kullanici_id'];
$rol_id = $_SESSION['rol_id'];
$sayfa_id = getSayfaId(basename(__FILE__));
$sayfa_adi = basename(__FILE__);
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

// Malzeme tipleri ve kdvler
$tipler = $db->query("SELECT * FROM malzeme_tipleri ORDER BY tip_adi")->fetchAll(PDO::FETCH_ASSOC);
$kdvler = $db->query("SELECT * FROM kdv_oranlari WHERE aktif=1 ORDER BY oran")->fetchAll(PDO::FETCH_ASSOC);

$birimler = [
    'adet'   => 'Adet',
    'paket'  => 'Paket',
    'kg'     => 'Kilogram (kg)',
    'lt'     => 'Litre (lt)',
    'm'      => 'Metre (m)',
    'koli'   => 'Koli',
    'kutu'   => 'Kutu',
    'çift'   => 'Çift'
];
// Kategori işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['kategori_ekle'])) {
        $kategori_adi = trim($_POST['yeni_kategori_adi'] ?? '');
        if ($kategori_adi) {
            kategori_ekle($db, $kullanici_id, $kategori_adi);
            header("Location: malzeme_yonetimi.php#kategoriModal"); // <<--- ÖNEMLİ
            exit;
        } else {
            $hata = "Kategori adı boş olamaz!";
        }
    }
    if (isset($_POST['kategori_guncelle'])) {
        $kategori_id = intval($_POST['duzenle_kategori_id'] ?? 0);
        $kategori_adi = trim($_POST['duzenle_kategori_adi'] ?? '');
        if ($kategori_adi && $kategori_id > 0) {
            kategori_guncelle($db, $kullanici_id, $kategori_id, $kategori_adi);
            header("Location: malzeme_yonetimi.php#kategoriModal"); // <<--- ÖNEMLİ
            exit;
        } else {
            $hata = "Kategori adı boş olamaz!";
        }
    }
}


// LOG: Sayfa görüntüleme
log_ekle(
    $db,
    $kullanici_id,
    30, // Malzeme Listeleme
    'goruntuleme',
    "Malzeme Yönetimi sayfası görüntülendi.",
    'başarılı'
);

// Malzeme işlemleri
$hata = '';
$basari = '';

// EKLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    $ad = trim($_POST['ad'] ?? '');
    $tip_id = intval($_POST['tip_id'] ?? 0);
    $kdv_id = intval($_POST['kdv_id'] ?? 0);
    $birim = $_POST['birim'] ?? '';
    $min_stok_limiti = intval($_POST['min_stok_limiti'] ?? 0);
    $mevcut_stok = intval($_POST['mevcut_stok'] ?? 0);
    $aciklama = trim($_POST['aciklama'] ?? '');

    if ($ad && $tip_id && $kdv_id && $birim) {
        try {
            $ekle = $db->prepare("INSERT INTO malzemeler (ad, tip_id, kdv_id, birim, min_stok_limiti, mevcut_stok, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ekle->execute([$ad, $tip_id, $kdv_id, $birim, $min_stok_limiti, $mevcut_stok, $aciklama]);
            $yeni_malzeme_id = $db->lastInsertId();

            // --- STOK HAREKETİ EKLE (YENİ MALZEME) ---
            if ($mevcut_stok > 0) {
                $sh = $db->prepare("INSERT INTO stok_hareketleri (malzeme_id, hareket_tip, miktar, aciklama, islem_tarihi) VALUES (?, ?, ?, ?, NOW())");
                $sh->execute([
                    $yeni_malzeme_id,
                    'giris',
                    $mevcut_stok,
                    'Malzeme ekleme işlemiyle ilk stok girişi'
                ]);
            }
            // ------------------------------------------

            $basari = "Malzeme başarıyla eklendi!";
            // LOG: Başarılı ekleme
            log_ekle(
                $db,
                $kullanici_id,
                31, // Malzeme Ekleme
                'ekle',
                "Malzeme eklendi: $ad (Tip ID: $tip_id, KDV ID: $kdv_id, Birim: $birim, MinStok: $min_stok_limiti, MevcutStok: $mevcut_stok, Açıklama: $aciklama)",
                'başarılı'
            );
        } catch (Exception $ex) {
            $hata = "Malzeme eklenirken hata oluştu.";
            // LOG: Başarısız ekleme
            log_ekle(
                $db,
                $kullanici_id,
                31, // Malzeme Ekleme
                'ekle',
                "Malzeme eklenemedi: $ad. Hata: {$ex->getMessage()}",
                'başarısız'
            );
        }
    } else {
        $hata = "Tüm alanları doldurun!";
        // LOG: Eksik alan ile başarısız ekleme
        log_ekle(
            $db,
            $kullanici_id,
            31, // Malzeme Ekleme
            'ekle',
            "Malzeme eklenemedi (eksik alanlar): ad:$ad tip:$tip_id kdv:$kdv_id birim:$birim",
            'başarısız'
        );
    }
}

// GÜNCELLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guncelle_id'])) {
    $id = intval($_POST['guncelle_id']);
    $ad = trim($_POST['g_ad'] ?? '');
    $tip_id = intval($_POST['g_tip_id'] ?? 0);
    $kdv_id = intval($_POST['g_kdv_id'] ?? 0);
    $birim = $_POST['g_birim'] ?? '';
    $min_stok_limiti = intval($_POST['g_min_stok_limiti'] ?? 0);
    $mevcut_stok = intval($_POST['g_mevcut_stok'] ?? 0);
    $aciklama = trim($_POST['g_aciklama'] ?? '');

    if ($ad && $tip_id && $kdv_id && $birim) {
        try {
            // Önce eski stok miktarını al:
            $eski = $db->prepare("SELECT mevcut_stok FROM malzemeler WHERE id = ?");
            $eski->execute([$id]);
            $eski_stok = $eski->fetchColumn();

            $guncelle = $db->prepare("UPDATE malzemeler SET ad = ?, tip_id = ?, kdv_id = ?, birim = ?, min_stok_limiti = ?, mevcut_stok = ?, aciklama = ? WHERE id = ?");
            $guncelle->execute([$ad, $tip_id, $kdv_id, $birim, $min_stok_limiti, $mevcut_stok, $aciklama, $id]);

            // Sadece stok miktarı değişmişse hareket ekle:
            if ($mevcut_stok != $eski_stok) {
                $hareket = ($mevcut_stok > $eski_stok) ? 'giris' : 'cikis';
                $degisen_miktar = abs($mevcut_stok - $eski_stok);

                $sh = $db->prepare("INSERT INTO stok_hareketleri (malzeme_id, hareket_tip, miktar, aciklama, islem_tarihi) VALUES (?, ?, ?, ?, NOW())");
                $sh->execute([
                    $id,
                    $hareket,
                    $degisen_miktar,
                    'Malzeme güncellemesiyle stok ' . ($hareket === 'giris' ? 'arttı' : 'azaldı')
                ]);
            }

            $basari = "Malzeme başarıyla güncellendi!";
            // LOG: Başarılı güncelleme
            log_ekle(
                $db,
                $kullanici_id,
                32, // Malzeme Güncelleme
                'guncelle',
                "Malzeme güncellendi (id:$id): $ad (Tip ID: $tip_id, KDV ID: $kdv_id, Birim: $birim, MinStok: $min_stok_limiti, MevcutStok: $mevcut_stok, Açıklama: $aciklama)",
                'başarılı'
            );
            header("Location: malzeme_yonetimi.php?g=1");
            exit;
        } catch (Exception $ex) {
            $hata = "Malzeme güncellenirken hata oluştu.";
            // LOG: Başarısız güncelleme
            log_ekle(
                $db,
                $kullanici_id,
                32, // Malzeme Güncelleme
                'guncelle',
                "Malzeme güncellenemedi (id:$id). Hata: {$ex->getMessage()}",
                'başarısız'
            );
        }
    } else {
        $hata = "Tüm alanları doldurun!";
        // LOG: Eksik alan ile başarısız güncelleme
        log_ekle(
            $db,
            $kullanici_id,
            32, // Malzeme Güncelleme
            'guncelle',
            "Malzeme güncellenemedi (eksik alanlar, id:$id): ad:$ad tip:$tip_id kdv:$kdv_id birim:$birim",
            'başarısız'
        );
    }
}

// Tüm malzemeler
$malzemeler = $db->query(
    "SELECT m.*, t.tip_adi, k.oran AS kdv_orani
     FROM malzemeler m
     LEFT JOIN malzeme_tipleri t ON m.tip_id = t.id
     LEFT JOIN kdv_oranlari k ON m.kdv_id = k.id
     ORDER BY m.ad"
)->fetchAll(PDO::FETCH_ASSOC);

// Kritik stok limiti altındaki malzemeler
$stok_uyari_malzeme = $db->query("
    SELECT m.*, t.tip_adi, k.oran AS kdv_orani
    FROM malzemeler m
    LEFT JOIN malzeme_tipleri t ON m.tip_id = t.id
    LEFT JOIN kdv_oranlari k ON m.kdv_id = k.id
    WHERE m.mevcut_stok < m.min_stok_limiti
    ORDER BY m.ad
")->fetchAll(PDO::FETCH_ASSOC);
$stok_uyari_sayisi = count($stok_uyari_malzeme);

// ACİL İHTİYAÇLAR (acil_ihtiyaclar tablosundan)
$acil_ihtiyaclar = $db->query("
    SELECT 
        a.*, 
        m.ad AS malzeme_adi, 
        m.birim, 
        m.mevcut_stok
    FROM acil_ihtiyaclar a
    JOIN malzemeler m ON a.malzeme_id = m.id
    WHERE a.eksik_adet > 0
    ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$acil_ihtiyac_sayisi = count($acil_ihtiyaclar);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Malzeme Yönetimi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .card { border-radius: 1.25rem; box-shadow: 0 0 14px #ddd; }
        .table thead { background: #002d72; color: #fff; }
        .btn:focus { box-shadow: none !important; }
        .stok-uyari { background: #fff3cd !important; }
        .acil-ihtiyac { background: #ffd7d7 !important; }
        .vurgulu { background: #ffe5a4; font-weight: bold; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0 text-primary">Malzeme Yönetimi</h3>
        <div>
            <button class="btn btn-outline-danger position-relative me-2" data-bs-toggle="modal" data-bs-target="#acilIhtiyacModal">
                <i class="bi bi-exclamation-circle-fill"></i>
                Acil İhtiyaçlar
                <?php if ($acil_ihtiyac_sayisi > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $acil_ihtiyac_sayisi ?>
                    </span>
                <?php endif; ?>
            </button>
            <button class="btn btn-outline-warning position-relative me-2" data-bs-toggle="modal" data-bs-target="#stokUyariModal">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Stok Uyarıları
                <?php if ($stok_uyari_sayisi > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $stok_uyari_sayisi ?>
                    </span>
                <?php endif; ?>
            </button>
            <button class="btn btn-success px-3 py-2" data-bs-toggle="modal" data-bs-target="#kategoriModal" onclick="kategoriFormSifirla()">
    <b>+ Kategori Yönet</b>
</button>
            <button class="btn btn-success px-3 py-2" data-bs-toggle="modal" data-bs-target="#ekleModal">
                <b>+ Malzeme Ekle</b>
            </button>
        </div>
    </div>
    <!-- Arama Kutusu -->
    <div class="mb-3">
        <input type="text" id="arama" class="form-control" placeholder="Malzeme adı, birim, açıklama veya Kategori ile ara...">
    </div>
    <?php if ($hata): ?>
        <div class="alert alert-danger"><?= $hata ?></div>
    <?php endif; ?>
    <?php if ($basari): ?>
        <div class="alert alert-success"><?= $basari ?></div>
    <?php endif; ?>
    <div class="card p-4">
        <table class="table table-hover align-middle mb-0" id="malzemeTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Adı</th>
                    <th>Kategori</th>
                    <th>
                        Birim
                        <span class="text-muted" style="font-size:0.9em; font-weight:normal;">
                            (Örn: adet, paket, kg)
                        </span>
                    </th>
                    <th>KDV</th>
                    <th>Mevcut Stok</th>
                    <th>Min. Stok Limiti</th>
                    <th>Açıklama</th>
                    <th class="text-center">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($malzemeler as $m): ?>
                <tr class="<?= ($m['mevcut_stok'] < $m['min_stok_limiti']) ? 'stok-uyari' : '' ?>">
                    <td><?= $m['id'] ?></td>
                    <td><?= htmlspecialchars($m['ad']) ?></td>
                    <td><?= htmlspecialchars($m['tip_adi']) ?></td>
                    <td><?= htmlspecialchars($m['birim']) ?></td>
                    <td>%<?= htmlspecialchars($m['kdv_orani']) ?></td>
                    <td><?= htmlspecialchars($m['mevcut_stok']) ?></td>
                    <td><?= htmlspecialchars($m['min_stok_limiti']) ?></td>
                    <td><?= htmlspecialchars($m['aciklama']) ?></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-primary btn-sm ms-2"
                            data-bs-toggle="modal"
                            data-bs-target="#guncelleModal"
                            data-id="<?= $m['id'] ?>"
                            data-ad="<?= htmlspecialchars($m['ad']) ?>"
                            data-tip_id="<?= $m['tip_id'] ?>"
                            data-kdv_id="<?= $m['kdv_id'] ?>"
                            data-birim="<?= htmlspecialchars($m['birim']) ?>"
                            data-min_stok_limiti="<?= $m['min_stok_limiti'] ?>"
                            data-mevcut_stok="<?= $m['mevcut_stok'] ?>"
                            data-aciklama="<?= htmlspecialchars($m['aciklama']) ?>"
                        >Düzenle</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ACİL İHTİYAÇ MODALI -->
<div class="modal fade" id="acilIhtiyacModal" tabindex="-1" aria-labelledby="acilIhtiyacModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-danger bg-opacity-75">
        <h5 class="modal-title text-white" id="acilIhtiyacModalLabel">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            Acil Stok İhtiyacı (<?= $acil_ihtiyac_sayisi ?>)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if ($acil_ihtiyac_sayisi > 0): ?>
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
                            <td><?= htmlspecialchars($a['malzeme_adi']) ?></td>
                            <td><?= htmlspecialchars($a['birim']) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($a['mevcut_stok']) ?></span></td>
                            <td><span class="badge bg-danger"><?= htmlspecialchars($a['eksik_adet']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-success">Acil stok ihtiyacı yok.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<!-- KATEGORİLERİ YÖNET MODALI -->
<div class="modal fade" id="kategoriModal" tabindex="-1" aria-labelledby="kategoriModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="kategoriModalLabel">Kategoriler</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Kategori Ekleme Formu -->
        <form method="POST" class="row g-2 mb-3" autocomplete="off">
          <div class="col-md-8">
            <input type="text" name="yeni_kategori_adi" class="form-control" placeholder="Yeni Kategori Adı" maxlength="100" required>
          </div>
          <div class="col-md-4">
            <button type="submit" name="kategori_ekle" class="btn btn-success w-100">Ekle</button>
          </div>
        </form>
        <!-- Kategori Listesi Tablosu -->
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead>
              <tr>
                <th style="width:60px;">#</th>
                <th>Kategori Adı</th>
                <th style="width:120px;">İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tipler as $kat): ?>
              <tr>
                <td><?= $kat['id'] ?></td>
                <td><?= htmlspecialchars($kat['tip_adi']) ?></td>
                <td>
                  <button type="button" class="btn btn-outline-primary btn-sm"
                      onclick="kategoriDuzenleAc(<?= $kat['id'] ?>, '<?= htmlspecialchars($kat['tip_adi'], ENT_QUOTES) ?>')">
                      Düzenle
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- KATEGORİ DÜZENLE MODALI -->
<div class="modal fade" id="kategoriDuzenleModal" tabindex="-1" aria-labelledby="kategoriDuzenleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="kategoriDuzenleModalLabel">Kategori Düzenle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="duzenle_kategori_id" id="duzenle_kategori_id">
          <input type="text" name="duzenle_kategori_adi" id="duzenle_kategori_adi" class="form-control" required maxlength="100" placeholder="Kategori Adı">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" name="kategori_guncelle" class="btn btn-primary">Kaydet</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- STOK UYARI MODALI -->
<div class="modal fade" id="stokUyariModal" tabindex="-1" aria-labelledby="stokUyariModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning bg-opacity-75">
        <h5 class="modal-title text-dark" id="stokUyariModalLabel">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Kritik Stok Uyarısı - Min Limit Altı Malzemeler (<?= $stok_uyari_sayisi ?>)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if ($stok_uyari_sayisi > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-warning align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Adı</th>
                            <th>Tip</th>
                            <th>Birim</th>
                            <th>KDV</th>
                            <th>Mevcut Stok</th>
                            <th>Min. Stok Limiti</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stok_uyari_malzeme as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['ad']) ?></td>
                            <td><?= htmlspecialchars($u['tip_adi']) ?></td>
                            <td><?= htmlspecialchars($u['birim']) ?></td>
                            <td>%<?= htmlspecialchars($u['kdv_orani']) ?></td>
                            <td><span class="badge bg-danger text-white"><?= htmlspecialchars($u['mevcut_stok']) ?></span></td>
                            <td><?= htmlspecialchars($u['min_stok_limiti']) ?></td>
                            <td><?= htmlspecialchars($u['aciklama']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-success">Min. stok limiti altında olan malzeme yok.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- EKLEME MODALI -->
<div class="modal fade" id="ekleModal" tabindex="-1" aria-labelledby="ekleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Malzeme Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
            <div class="col-12">
                <label class="form-label">Malzeme Adı</label>
                <input name="ad" class="form-control" placeholder="Malzeme Adı" required>
            </div>
            <div class="col-6">
                <label class="form-label">Kategori</label>
                <select name="tip_id" class="form-select" required>
                    <option value="">Kategori Seçiniz</option>
                    <?php foreach ($tipler as $tip): ?>
                        <option value="<?= $tip['id'] ?>"><?= htmlspecialchars($tip['tip_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">KDV Oranı</label>
                <select name="kdv_id" class="form-select" required>
                    <option value="">KDV Seçiniz</option>
                    <?php foreach ($kdvler as $k): ?>
                        <option value="<?= $k['id'] ?>">%<?= htmlspecialchars($k['oran']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Birim <span class="text-muted" style="font-size:0.85em;">(Seçiniz)</span></label>
                <select name="birim" class="form-select" required>
                    <option value="">Birim Seçiniz</option>
                    <?php foreach($birimler as $key => $val): ?>
                        <option value="<?= $key ?>"><?= $val ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Min Stok Limiti</label>
                <input name="min_stok_limiti" type="number" class="form-control" min="0" value="0">
            </div>
            <div class="col-6">
                <label class="form-label">Mevcut Stok</label>
                <input name="mevcut_stok" type="number" class="form-control" min="0" value="0">
            </div>
            <div class="col-6">
                <label class="form-label">Açıklama</label>
                <input name="aciklama" class="form-control" placeholder="Açıklama">
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="ekle" class="btn btn-success">Kaydet</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- GÜNCELLEME MODALI -->
<div class="modal fade" id="guncelleModal" tabindex="-1" aria-labelledby="guncelleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="guncelleModalLabel">Malzeme Düzenle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
            <input type="hidden" name="guncelle_id" id="guncelle_id">
            <div class="col-12">
                <label class="form-label">Malzeme Adı</label>
                <input name="g_ad" id="g_ad" class="form-control" required>
            </div>
            <div class="col-6">
                <label class="form-label">Kategori</label>
                <select name="g_tip_id" id="g_tip_id" class="form-select" required>
                    <option value="">Tip Seçiniz</option>
                    <?php foreach ($tipler as $tip): ?>
                        <option value="<?= $tip['id'] ?>"><?= htmlspecialchars($tip['tip_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">KDV Oranı</label>
                <select name="g_kdv_id" id="g_kdv_id" class="form-select" required>
                    <option value="">KDV Seçiniz</option>
                    <?php foreach ($kdvler as $k): ?>
                        <option value="<?= $k['id'] ?>">%<?= htmlspecialchars($k['oran']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Birim</label>
                <select name="g_birim" id="g_birim" class="form-select" required>
                    <option value="">Birim Seçiniz</option>
                    <?php foreach($birimler as $key => $val): ?>
                        <option value="<?= $key ?>"><?= $val ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Min Stok Limiti</label>
                <input name="g_min_stok_limiti" id="g_min_stok_limiti" type="number" class="form-control" required>
            </div>
            <div class="col-6">
                <label class="form-label">Mevcut Stok</label>
                <input name="g_mevcut_stok" id="g_mevcut_stok" type="number" class="form-control" required>
            </div>
            <div class="col-6">
                <label class="form-label">Açıklama</label>
                <input name="g_aciklama" id="g_aciklama" class="form-control">
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Arama ve vurgulama
document.getElementById('arama').addEventListener('input', function(){
    let val = this.value.toLowerCase();
    document.querySelectorAll("#malzemeTable tbody tr").forEach(tr=>{
        let metin = tr.innerText.toLowerCase();
        tr.style.display = metin.includes(val) ? "" : "none";
        // Sadece metin içeren hücreleri vurgula, buton olan td'ye dokunma!
        tr.querySelectorAll("td:not(:last-child)").forEach(td => {
            td.innerHTML = td.textContent.replace(
                new RegExp('('+val+')', 'gi'),
                val ? '<span class="vurgulu">$1</span>' : '$1'
            );
        });
    });
});

// Güncelle modalını doldur
const guncelleModal = document.getElementById('guncelleModal');
if (guncelleModal) {
    guncelleModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('guncelle_id').value = button.getAttribute('data-id');
        document.getElementById('g_ad').value = button.getAttribute('data-ad');
        document.getElementById('g_tip_id').value = button.getAttribute('data-tip_id');
        document.getElementById('g_kdv_id').value = button.getAttribute('data-kdv_id');
        document.getElementById('g_birim').value = button.getAttribute('data-birim');
        document.getElementById('g_min_stok_limiti').value = button.getAttribute('data-min_stok_limiti');
        document.getElementById('g_mevcut_stok').value = button.getAttribute('data-mevcut_stok');
        document.getElementById('g_aciklama').value = button.getAttribute('data-aciklama');
    });
}
function kategoriDuzenleAc(id, ad) {
    // Ana modalı kapat
    var anaModal = bootstrap.Modal.getInstance(document.getElementById('kategoriModal'));
    anaModal.hide();

    // Düzenle modalı açılırken formu doldur
    document.getElementById('duzenle_kategori_id').value = id;
    document.getElementById('duzenle_kategori_adi').value = ad;
    var duzenleModal = new bootstrap.Modal(document.getElementById('kategoriDuzenleModal'));
    duzenleModal.show();

    // Düzenle modalı kapatılınca ana modal tekrar açılsın
    document.getElementById('kategoriDuzenleModal').addEventListener('hidden.bs.modal', function handler() {
        anaModal.show();
        document.getElementById('kategoriDuzenleModal').removeEventListener('hidden.bs.modal', handler);
    });
}
document.addEventListener("DOMContentLoaded", function() {
    if (window.location.hash === "#kategoriModal") {
        var kategoriModal = new bootstrap.Modal(document.getElementById('kategoriModal'));
        kategoriModal.show();
        // Hash'i temizle, sayfa F5 yapılınca tekrar açılmasın
        history.replaceState(null, null, window.location.pathname);
    }
});

</script>
</body>
</html>
