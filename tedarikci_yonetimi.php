<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php'; // sifrele/coz fonksiyonları burada olmalı!
oturumKontrol();

$rol_id = $_SESSION['rol_id'];
$personel_id = $_SESSION['kullanici_id'];
$sayfa_adi = basename(__FILE__); 

if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

$hata = '';
$basari = '';

// --- SAYFA GÖRÜNTÜLEME LOGU (Tedarikçi Listeleme: id=100) ---
log_ekle($db, $personel_id, 100, 'goruntuleme', 'Tedarikçi Yönetimi görüntülendi.', 'başarılı');

// Tedarikçi Ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    $firma_kodu = trim($_POST['firma_kodu']);
    $ad = trim($_POST['ad']);
    $telefon = sifrele(trim($_POST['telefon']));
    $yetkili_adsoyad = trim($_POST['yetkili_adsoyad']);
    $yetkili_telefon = sifrele(trim($_POST['yetkili_telefon']));
    $email = sifrele(trim($_POST['email']));
    $vkn = sifrele(trim($_POST['vkn']));
    $adres = trim($_POST['adres']);
    $aciklama = sifrele(trim($_POST['aciklama']));
    $olusturan_id = $_SESSION['kullanici_id'];

    if ($ad && $firma_kodu && $telefon && $yetkili_adsoyad) {
        $sql = "INSERT INTO tedarikciler
            (firma_kodu, ad, telefon, yetkili_adsoyad, yetkili_telefon, email, vkn, adres, aciklama, aktif, olusturan_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $firma_kodu, $ad, $telefon, $yetkili_adsoyad, $yetkili_telefon, $email, $vkn, $adres, $aciklama, $olusturan_id
        ]);
        $basari = "Tedarikçi başarıyla eklendi!";

        // --- EKLEME LOGU (Tedarikçi Ekleme: id=101) ---
        log_ekle($db, $personel_id, 101, 'kaydet', "Tedarikçi eklendi: $ad ($firma_kodu)", 'başarılı');
    } else {
        $hata = "Zorunlu alanları doldurunuz!";
        // --- Hatalı ekleme logu ---
        log_ekle($db, $personel_id, 101, 'kaydet', "Tedarikçi eklenemedi: eksik alan.", 'başarısız');
    }
}

// Tedarikçi Güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guncelle_id'])) {
    $id = intval($_POST['guncelle_id']);
    $firma_kodu = trim($_POST['g_firma_kodu']);
    $ad = trim($_POST['g_ad']);
    $telefon = sifrele(trim($_POST['g_telefon']));
    $yetkili_adsoyad = trim($_POST['g_yetkili_adsoyad']);
    $yetkili_telefon = sifrele(trim($_POST['g_yetkili_telefon']));
    $email = sifrele(trim($_POST['g_email']));
    $vkn = sifrele(trim($_POST['g_vkn']));
    $adres = trim($_POST['g_adres']);
    $aciklama = sifrele(trim($_POST['g_aciklama']));
    $aktif = isset($_POST['g_aktif']) ? 1 : 0;

    $sql = "UPDATE tedarikciler SET firma_kodu=?, ad=?, telefon=?, yetkili_adsoyad=?, yetkili_telefon=?, email=?, vkn=?, adres=?, aciklama=?, aktif=? WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $firma_kodu, $ad, $telefon, $yetkili_adsoyad, $yetkili_telefon, $email, $vkn, $adres, $aciklama, $aktif, $id
    ]);
    $basari = "Tedarikçi başarıyla güncellendi!";

    // --- GÜNCELLEME LOGU (Tedarikçi Güncelleme: id=102) ---
    log_ekle($db, $personel_id, 102, 'guncelle', "Tedarikçi güncellendi: $ad ($firma_kodu), ID: $id", 'başarılı');
    header("Location: tedarikci_yonetimi.php?g=1");
    exit;
}

// Tedarikçi Listesi
$tedarikciler = $db->query("SELECT * FROM tedarikciler ORDER BY ad")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Tedarikçi Yönetimi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body { background: #f8f9fa; }
    .card { border-radius: 1.25rem; box-shadow: 0 0 14px #ddd; }
    .table thead { background: #002d72; color: #fff; }
    .btn:focus { box-shadow: none !important; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0 text-primary">Tedarikçi Yönetimi</h3>
        <button class="btn btn-success px-3 py-2" data-bs-toggle="modal" data-bs-target="#ekleModal">
            <b>+ Tedarikçi Ekle</b>
        </button>
    </div>
    <?php if ($hata): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($hata) ?></div>
    <?php endif; ?>
    <?php if ($basari): ?>
        <div class="alert alert-success"><?= htmlspecialchars($basari) ?></div>
    <?php endif; ?>
    <div class="card p-4">
        <div class="row mb-3">
            <div class="col-md-4 ms-auto">
                <input type="text" id="tedarikciAra" class="form-control" placeholder="Firma, yetkili, telefon, mail, adres, açıklama ara...">
            </div>
        </div>
        <table class="table table-hover align-middle mb-0" id="tedarikciTablo">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Firma Kodu</th>
                    <th>Firma Adı</th>
                    <th>Telefon</th>
                    <th>Yetkili</th>
                    <th>Yetkili Tel</th>
                    <th>Email</th>
                    <th>VKN</th>
                    <th>Adres</th>
                    <th>Açıklama</th>
                    <th>Aktif</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$tedarikciler): ?>
                <tr><td colspan="12" class="text-center text-danger">Kayıtlı tedarikçi bulunamadı!</td></tr>
            <?php endif; ?>
            <?php foreach ($tedarikciler as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($t['firma_kodu'] ?? '') ?></td>
                    <td><?= htmlspecialchars($t['ad'] ?? '') ?></td>
                    <td><?= htmlspecialchars(coz($t['telefon'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($t['yetkili_adsoyad'] ?? '') ?></td>
                    <td><?= htmlspecialchars(coz($t['yetkili_telefon'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(coz($t['email'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(coz($t['vkn'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($t['adres'] ?? '') ?></td>
                    <td><?= htmlspecialchars(coz($t['aciklama'] ?? '')) ?></td>
                    <td>
                        <?php if (!empty($t['aktif'])): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pasif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-outline-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#guncelleModal"
                                data-id="<?= htmlspecialchars($t['id'] ?? '') ?>"
                                data-firma_kodu="<?= htmlspecialchars($t['firma_kodu'] ?? '') ?>"
                                data-ad="<?= htmlspecialchars($t['ad'] ?? '') ?>"
                                data-telefon="<?= htmlspecialchars(coz($t['telefon'] ?? '')) ?>"
                                data-yetkili_adsoyad="<?= htmlspecialchars($t['yetkili_adsoyad'] ?? '') ?>"
                                data-yetkili_telefon="<?= htmlspecialchars(coz($t['yetkili_telefon'] ?? '')) ?>"
                                data-email="<?= htmlspecialchars(coz($t['email'] ?? '')) ?>"
                                data-vkn="<?= htmlspecialchars(coz($t['vkn'] ?? '')) ?>"
                                data-adres="<?= htmlspecialchars($t['adres'] ?? '') ?>"
                                data-aciklama="<?= htmlspecialchars(coz($t['aciklama'] ?? '')) ?>"
                                data-aktif="<?= !empty($t['aktif']) ? '1' : '0' ?>"
                                onclick="logDuzenle('<?= addslashes($t['ad']) ?>','<?= addslashes($t['firma_kodu']) ?>','<?= $t['id'] ?>')"
                        >Düzenle</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- EKLE MODALI -->
<div class="modal fade" id="ekleModal" tabindex="-1" aria-labelledby="ekleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Tedarikçi Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
          <div class="col-md-4">
            <label class="form-label">Firma Kodu</label>
            <input type="text" name="firma_kodu" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Firma Adı</label>
            <input type="text" name="ad" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Firma Telefon</label>
            <input type="tel" name="telefon" class="form-control" pattern="[0-9]{10,15}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Yetkili Adı Soyadı</label>
            <input type="text" name="yetkili_adsoyad" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Yetkili Telefon</label>
            <input type="tel" name="yetkili_telefon" class="form-control" pattern="[0-9]{10,15}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Mail</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">VKN</label>
            <input type="text" name="vkn" class="form-control" pattern="[0-9]{10,15}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Adres</label>
            <input type="text" name="adres" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Açıklama</label>
            <input type="text" name="aciklama" class="form-control">
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
  <div class="modal-dialog modal-lg">
    <form method="POST" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Tedarikçi Güncelle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
            <input type="hidden" name="guncelle_id" id="guncelle_id">
            <div class="col-md-4">
                <label class="form-label">Firma Kodu</label>
                <input type="text" name="g_firma_kodu" id="g_firma_kodu" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Firma Adı</label>
                <input type="text" name="g_ad" id="g_ad" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Firma Telefon</label>
                <input type="tel" name="g_telefon" id="g_telefon" class="form-control" pattern="[0-9]{10,15}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Yetkili Adı Soyadı</label>
                <input type="text" name="g_yetkili_adsoyad" id="g_yetkili_adsoyad" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Yetkili Telefon</label>
                <input type="tel" name="g_yetkili_telefon" id="g_yetkili_telefon" class="form-control" pattern="[0-9]{10,15}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Mail</label>
                <input type="email" name="g_email" id="g_email" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">VKN</label>
                <input type="text" name="g_vkn" id="g_vkn" class="form-control" pattern="[0-9]{10,15}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Adres</label>
                <input type="text" name="g_adres" id="g_adres" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Açıklama</label>
                <input type="text" name="g_aciklama" id="g_aciklama" class="form-control">
            </div>
            <div class="col-md-4 d-flex align-items-center">
                <input type="checkbox" name="g_aktif" id="g_aktif" class="form-check-input ms-2">
                <label class="form-check-label ms-2">Aktif</label>
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Güncelle</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Modal doldurma
document.querySelectorAll('button[data-bs-target="#guncelleModal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('guncelle_id').value = this.dataset.id;
        document.getElementById('g_firma_kodu').value = this.dataset.firma_kodu;
        document.getElementById('g_ad').value = this.dataset.ad;
        document.getElementById('g_telefon').value = this.dataset.telefon;
        document.getElementById('g_yetkili_adsoyad').value = this.dataset.yetkili_adsoyad;
        document.getElementById('g_yetkili_telefon').value = this.dataset.yetkili_telefon;
        document.getElementById('g_email').value = this.dataset.email;
        document.getElementById('g_vkn').value = this.dataset.vkn;
        document.getElementById('g_adres').value = this.dataset.adres;
        document.getElementById('g_aciklama').value = this.dataset.aciklama;
        document.getElementById('g_aktif').checked = (this.dataset.aktif == "1");

        // DÜZENLEME BUTONU LOGU (Tedarikçi Düzenleme Açma: id=104)
        fetch('log_ekle_ajax.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: "islem_id=104&aciklama=" + encodeURIComponent("Tedarikçi düzenleme açıldı: " + this.dataset.ad + " (" + this.dataset.firma_kodu + "), ID: " + this.dataset.id)
        });
    });
});

// Tedarikçi tablosunda canlı arama
document.getElementById('tedarikciAra').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#tedarikciTablo tbody tr');
    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().indexOf(value) > -1 ? '' : 'none';
    });
    // ARAMA LOGU (Tedarikçi Arama: id=105)
    if (this.value.length === 1) {
        fetch('log_ekle_ajax.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: "islem_id=105&aciklama=" + encodeURIComponent("Tedarikçi tablosunda arama yapıldı: " + this.value)
        });
    }
});
</script>
</body>
</html>
