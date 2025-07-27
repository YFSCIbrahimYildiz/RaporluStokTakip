<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
session_start();
oturumKontrol();
$kullanici_id = $_SESSION['kullanici_id'];
$rol_id = $_SESSION['rol_id'];
$sayfa_adi = basename(__FILE__); 
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

// LOG: Personel Yönetimi sayfası görüntülendi
log_ekle(
    $db,
    $kullanici_id,
    10, // Personel Listeleme
    'goruntuleme',
    "Personel Yönetimi sayfası görüntülendi.",
    'başarılı'
);

// Şifre oluşturucu
function randomStrongPassword($length = 10) {
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $nums  = '0123456789';
    $spec  = '!@#$%^&*()-_=+[]{};:,.<>/?';
    $all = $lower.$upper.$nums.$spec;
    $pw = '';
    $pw .= $lower[rand(0, strlen($lower)-1)];
    $pw .= $upper[rand(0, strlen($upper)-1)];
    $pw .= $nums[rand(0, strlen($nums)-1)];
    $pw .= $spec[rand(0, strlen($spec)-1)];
    for ($i=4;$i<$length;$i++) {
        $pw .= $all[rand(0, strlen($all)-1)];
    }
    return str_shuffle($pw);
}
function isPasswordStrong($pw) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $pw);
}

$hata = '';
$basari = '';

// AJAX: Şifre oluştur ve kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'sifre_olustur') {
    $id = intval($_POST['id'] ?? 0);

    $sifre_plain = randomStrongPassword(10);
    if (!isPasswordStrong($sifre_plain)) $sifre_plain = randomStrongPassword(12);

    $hash = password_hash($sifre_plain, PASSWORD_DEFAULT);
    $guncelle = $db->prepare("UPDATE personeller SET sifre = ? WHERE id = ?");
    $guncelle->execute([$hash, $id]);
    // LOG: Şifre Oluşturma
    log_ekle(
        $db,
        $kullanici_id,
        13, // Personel Şifre Oluşturma
        'guncelle',
        "ID: $id için yeni şifre oluşturuldu.",
        'başarılı'
    );
    echo json_encode(['success' => true, 'sifre' => $sifre_plain]);
    exit;
}

// Kullanıcı ekleme (ilk kayıt)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    $ad      = trim($_POST['ad'] ?? '');
    $soyad   = trim($_POST['soyad'] ?? '');
    $sicil   = trim($_POST['sicil'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $rol_id2 = intval($_POST['rol_id'] ?? 0);

    if ($ad && $soyad && $sicil && $email && $telefon && $rol_id2) {
        $varMi = $db->prepare("SELECT id FROM personeller WHERE email = ? OR sicil = ?");
        $varMi->execute([$email, $sicil]);
        if ($varMi->fetch()) {
            $hata = "Bu e-posta veya sicil ile kayıtlı kullanıcı var!";
            log_ekle(
                $db,
                $kullanici_id,
                11,
                'ekle',
                "Personel eklenemedi (mükerrer eposta/sicil): $ad $soyad ($sicil, $email)",
                'başarısız'
            );
        } else {
            $sifre_plain = randomStrongPassword(10);
            if (!isPasswordStrong($sifre_plain)) $sifre_plain = randomStrongPassword(12);
            $sifre_hash  = password_hash($sifre_plain, PASSWORD_DEFAULT);

            $ekle = $db->prepare("INSERT INTO personeller (ad, soyad, sicil, email, telefon, sifre, rol_id, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $ekle->execute([
                $ad,
                $soyad,
                $sicil,
                $email,
                $telefon,
                $sifre_hash,
                $rol_id2
            ]);
            $basari = "Kullanıcı başarıyla eklendi! <b>Otomatik Şifresi:</b> <code id='newPassword'>{$sifre_plain}</code>
            <button type='button' class='btn btn-sm btn-outline-secondary ms-2' onclick='copyPassword()'>Kopyala</button>";
            log_ekle(
                $db,
                $kullanici_id,
                11,
                'ekle',
                "Personel eklendi: $ad $soyad ($sicil, $email, Rol ID: $rol_id2)",
                'başarılı'
            );
        }
    } else {
        $hata = "Tüm alanları doldurun!";
        log_ekle(
            $db,
            $kullanici_id,
            11,
            'ekle',
            "Personel eklenemedi (eksik alan).",
            'başarısız'
        );
    }
}

// Aktif/Pasif işlemi
if (isset($_GET['durum']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $yeniDurum = $_GET['durum'] == '1' ? 1 : 0;
    $db->prepare("UPDATE personeller SET aktif = ? WHERE id = ?")->execute([$yeniDurum, $id]);
    // LOG: Durum değişikliği
    log_ekle(
        $db,
        $kullanici_id,
        14, // Personel Durum Değişikliği
        'guncelle',
        "Personel ID $id durumu ".($yeniDurum ? "Aktif" : "Pasif")." olarak değiştirildi.",
        'başarılı'
    );
    header("Location: personel_yonetimi.php");
    exit;
}

// Kullanıcı güncelleme (şifre değişikliği hariç!)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guncelle_id'])) {
    $id      = intval($_POST['guncelle_id']);
    $ad      = trim($_POST['g_ad'] ?? '');
    $soyad   = trim($_POST['g_soyad'] ?? '');
    $sicil   = trim($_POST['g_sicil'] ?? '');
    $email   = trim($_POST['g_email'] ?? '');
    $telefon = trim($_POST['g_telefon'] ?? '');
    $rol_id2  = intval($_POST['g_rol_id'] ?? 0);

    if ($ad && $soyad && $sicil && $email && $telefon && $rol_id2) {
        $guncelle = $db->prepare("UPDATE personeller SET ad = ?, soyad = ?, sicil = ?, email = ?, telefon = ?, rol_id = ? WHERE id = ?");
        $guncelle->execute([
            $ad, $soyad, $sicil, $email, $telefon, $rol_id2, $id
        ]);
        $basari = "Kullanıcı başarıyla güncellendi!";
        log_ekle(
            $db,
            $kullanici_id,
            12, // Personel Güncelleme
            'guncelle',
            "Personel güncellendi: $ad $soyad ($sicil, $email, Rol ID: $rol_id2, ID: $id)",
            'başarılı'
        );
        header("Location: personel_yonetimi.php?g=1");
        exit;
    } else {
        $hata = "Tüm alanları doldurun!";
        log_ekle(
            $db,
            $kullanici_id,
            12,
            'guncelle',
            "Personel güncellenemedi (eksik alan, ID: $id).",
            'başarısız'
        );
    }
}

$kullanicilar = $db->query(
    "SELECT p.*, r.rol_adi 
     FROM personeller p 
     LEFT JOIN roller r ON p.rol_id = r.id 
     ORDER BY p.ad, p.soyad"
)->fetchAll(PDO::FETCH_ASSOC);
$roller = $db->query("SELECT * FROM roller")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Personel Yönetimi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .card { border-radius: 1.25rem; box-shadow: 0 0 14px #ddd; }
        .table thead { background: #002d72; color: #fff; }
        .btn:focus { box-shadow: none !important; }
        .user-status { font-size: .85rem; }
        .status-dot { height: 12px; width: 12px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .status-active { background: #28a745; }
        .status-passive { background: #6c757d; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container mt-4">
    <div class="card p-4 mb-4">
        <h3 class="mb-3 text-primary">Personel / Kullanıcı Yönetimi</h3>
        <?php if (isset($_GET['b'])): ?>
            <div class="alert alert-success">Kullanıcı başarıyla eklendi!</div>
        <?php elseif (isset($_GET['g'])): ?>
            <div class="alert alert-success">Kullanıcı başarıyla güncellendi!</div>
        <?php endif; ?>
        <?php if ($hata): ?>
            <div class="alert alert-danger"><?= $hata ?></div>
        <?php endif; ?>
        <?php if ($basari): ?>
            <div class="alert alert-success"><?= $basari ?></div>
        <?php endif; ?>
        <form method="POST" class="row g-2 align-items-end mb-3">
            <div class="col-md-2"><input name="ad" class="form-control" placeholder="Ad" required></div>
            <div class="col-md-2"><input name="soyad" class="form-control" placeholder="Soyad" required></div>
            <div class="col-md-2"><input name="sicil" class="form-control" placeholder="Sicil No" required></div>
            <div class="col-md-2"><input name="email" type="email" class="form-control" placeholder="E-posta" required></div>
            <div class="col-md-2"><input name="telefon" type="tel" class="form-control" placeholder="Telefon" required></div>
            <div class="col-md-2">
                <select name="rol_id" class="form-select" required>
                    <option value="">Rol Seçiniz</option>
                    <?php foreach ($roller as $rol): ?>
                        <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['rol_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mt-2">
                <button type="submit" name="ekle" class="btn btn-success w-100">Ekle</button>
            </div>
        </form>
    </div>

    <div class="card p-4">
        <div class="row mb-3">
            <div class="col-md-4 ms-auto">
                <input type="text" id="personelAra" class="form-control" placeholder="Ad, soyad, sicil, e-posta, telefon veya rol ara...">
            </div>
        </div>
        <table class="table table-hover align-middle mb-0" id="personelTablo">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ad Soyad</th>
                    <th>Sicil</th>
                    <th>E-posta</th>
                    <th>Telefon</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th class="text-center">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($kullanicilar as $k): ?>
                <tr>
                    <td><?= $k['id'] ?></td>
                    <td><?= htmlspecialchars($k['ad'] . " " . $k['soyad']) ?></td>
                    <td><?= htmlspecialchars($k['sicil']) ?></td>
                    <td><?= htmlspecialchars($k['email']) ?></td>
                    <td><?= htmlspecialchars($k['telefon']) ?></td>
                    <td><?= htmlspecialchars($k['rol_adi']) ?></td>
                    <td>
                        <?php if ($k['aktif']): ?>
                            <span class="status-dot status-active"></span>
                            <span class="user-status text-success">Aktif</span>
                        <?php else: ?>
                            <span class="status-dot status-passive"></span>
                            <span class="user-status text-secondary">Pasif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($k['aktif']): ?>
                            <a href="?durum=0&id=<?= $k['id'] ?>" class="btn btn-outline-warning btn-sm" title="Pasifleştir">Pasif Yap</a>
                        <?php else: ?>
                            <a href="?durum=1&id=<?= $k['id'] ?>" class="btn btn-outline-success btn-sm" title="Aktif Yap">Aktif Yap</a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-primary btn-sm ms-2" 
                            data-bs-toggle="modal"
                            data-bs-target="#guncelleModal"
                            data-id="<?= $k['id'] ?>"
                            data-ad="<?= htmlspecialchars($k['ad']) ?>"
                            data-soyad="<?= htmlspecialchars($k['soyad']) ?>"
                            data-sicil="<?= htmlspecialchars($k['sicil']) ?>"
                            data-email="<?= htmlspecialchars($k['email']) ?>"
                            data-telefon="<?= htmlspecialchars($k['telefon']) ?>"
                            data-rol_id="<?= $k['rol_id'] ?>"
                            >Düzenle</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- GÜNCELLEME MODALI -->
<div class="modal fade" id="guncelleModal" tabindex="-1" aria-labelledby="guncelleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title" id="guncelleModalLabel">Kullanıcıyı Düzenle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
            <input type="hidden" name="guncelle_id" id="guncelle_id">
            <div class="col-6">
                <input name="g_ad" id="g_ad" class="form-control" placeholder="Ad" required>
            </div>
            <div class="col-6">
                <input name="g_soyad" id="g_soyad" class="form-control" placeholder="Soyad" required>
            </div>
            <div class="col-6">
                <input name="g_sicil" id="g_sicil" class="form-control" placeholder="Sicil No" required>
            </div>
            <div class="col-6">
                <input name="g_email" id="g_email" type="email" class="form-control" placeholder="E-posta" required>
            </div>
            <div class="col-6">
                <input name="g_telefon" id="g_telefon" type="tel" class="form-control" placeholder="Telefon" required>
            </div>
            <div class="col-6">
                <select name="g_rol_id" id="g_rol_id" class="form-select" required>
                    <option value="">Rol Seçiniz</option>
                    <?php foreach ($roller as $rol): ?>
                        <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['rol_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="generatePasswordBtn">Şifre Oluştur</button>
                <input name="g_sifre" id="g_sifre" type="text" class="form-control mt-2 d-none" placeholder="Yeni Şifre" autocomplete="off" readonly>
                <div id="pwinfo" class="form-text d-none">Yeni şifre otomatik kaydedildi!<br>
                    <b>Şifre kuralları:</b> En az 8 karakter, küçük/büyük harf, sayı ve özel karakter zorunlu.
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Şifre kopyala
function copyPassword() {
    var pw = document.getElementById('newPassword').innerText;
    navigator.clipboard.writeText(pw);
    alert('Şifre panoya kopyalandı!');
}

// Modal veri doldurma/reset
const guncelleModal = document.getElementById('guncelleModal');
if (guncelleModal) {
    guncelleModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('guncelle_id').value = button.getAttribute('data-id');
        document.getElementById('g_ad').value = button.getAttribute('data-ad');
        document.getElementById('g_soyad').value = button.getAttribute('data-soyad');
        document.getElementById('g_sicil').value = button.getAttribute('data-sicil');
        document.getElementById('g_email').value = button.getAttribute('data-email');
        document.getElementById('g_telefon').value = button.getAttribute('data-telefon');
        var rolId = button.getAttribute('data-rol_id');
        var rolDropdown = document.getElementById('g_rol_id');
        for (var i=0; i<rolDropdown.options.length; i++) {
            if (rolDropdown.options[i].value == rolId) {
                rolDropdown.selectedIndex = i;
                break;
            }
        }
        document.getElementById('g_sifre').classList.add('d-none');
        document.getElementById('g_sifre').value = "";
        document.getElementById('pwinfo').classList.add('d-none');
    });
    guncelleModal.addEventListener('hidden.bs.modal', function (event) {
        document.getElementById('g_sifre').classList.add('d-none');
        document.getElementById('g_sifre').value = "";
        document.getElementById('pwinfo').classList.add('d-none');
    });
}

// Şifre oluşturma butonuna AJAX
document.addEventListener('DOMContentLoaded', function() {
    let btn = document.getElementById('generatePasswordBtn');
    if (btn) {
        btn.onclick = function() {
            let userId = document.getElementById('guncelle_id').value;
            if (!userId) return alert('Önce kullanıcıyı seçiniz.');
            btn.disabled = true;
            btn.innerHTML = 'Şifre oluşturuluyor...';
            fetch(window.location.pathname, {
                method: "POST",
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: "ajax=sifre_olustur&id=" + encodeURIComponent(userId)
            })
            .then(resp=>resp.json())
            .then(data=>{
                if(data.success){
                    let inp = document.getElementById('g_sifre');
                    inp.value = data.sifre;
                    inp.classList.remove('d-none');
                    document.getElementById('pwinfo').classList.remove('d-none');
                }else{
                    alert('Şifre oluşturulamadı!');
                }
            })
            .finally(()=>{
                btn.disabled = false;
                btn.innerHTML = 'Şifre Oluştur';
            });
        }
    }

    // Personel tablosu arama (canlı filtre)
    const ara = document.getElementById('personelAra');
    if (ara) {
        ara.addEventListener('keyup', function() {
            let value = this.value.toLowerCase();
            let rows = document.querySelectorAll('#personelTablo tbody tr');
            rows.forEach(function(row) {
                row.style.display = row.innerText.toLowerCase().indexOf(value) > -1 ? '' : 'none';
            });
        });
    }
});
</script>
</body>
</html>
