<?php
require_once 'includes/db.php';
require_once __DIR__ . '/includes/functions.php';
oturumKontrol();
$rol_id = $_SESSION['rol_id'];
$personel_id = $_SESSION['kullanici_id'];
$sayfa_adi = basename(__FILE__);

if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

$mesaj = '';

// --- SAYFA GÖRÜNTÜLEME LOGU (id: 110) ---
log_ekle($db, $personel_id, 110, 'goruntuleme', 'Yetki Yönetimi görüntülendi.', 'başarılı');

// --- 1. YENİ SAYFA EKLEME ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeni_sayfa_ekle'])) {
    $sayfa = trim($_POST['sayfa'] ?? '');
    $baslik = trim($_POST['baslik'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if ($sayfa && $baslik) {
        $varmi = $db->prepare("SELECT id FROM sayfalar WHERE sayfa = ?");
        $varmi->execute([$sayfa]);
        if ($varmi->rowCount() > 0) {
            $mesaj = '<div class="alert alert-warning mt-2">Bu dosya zaten eklenmiş!</div>';
            log_ekle($db, $personel_id, 113, 'kaydet', "Sayfa eklenemedi, zaten mevcut: $sayfa", 'başarısız');
        } else {
            $ekle = $db->prepare("INSERT INTO sayfalar (sayfa, baslik, aktif) VALUES (?, ?, ?)");
            if ($ekle->execute([$sayfa, $baslik, $aktif])) {
                $mesaj = '<div class="alert alert-success mt-2">Sayfa başarıyla eklendi.</div>';
                log_ekle($db, $personel_id, 113, 'kaydet', "Yeni sayfa eklendi: $sayfa ($baslik)", 'başarılı');
            } else {
                $mesaj = '<div class="alert alert-danger mt-2">Kayıt eklenirken bir hata oluştu.</div>';
                log_ekle($db, $personel_id, 113, 'kaydet', "Sayfa eklenemedi: $sayfa ($baslik)", 'başarısız');
            }
        }
    } else {
        $mesaj = '<div class="alert alert-danger mt-2">Tüm alanları doldurmalısınız.</div>';
        log_ekle($db, $personel_id, 113, 'kaydet', "Sayfa eklenemedi, eksik alan.", 'başarısız');
    }
}

// --- 2. YENİ ROL EKLEME (MODAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeni_rol_ekle'])) {
    $rol_adi = trim($_POST['rol_adi'] ?? '');
    if ($rol_adi) {
        $varmi = $db->prepare("SELECT id FROM roller WHERE rol_adi = ?");
        $varmi->execute([$rol_adi]);
        if ($varmi->rowCount() > 0) {
            $mesaj .= '<div class="alert alert-warning mt-2">Bu rol adı zaten var!</div>';
            log_ekle($db, $personel_id, 112, 'kaydet', "Rol eklenemedi, zaten var: $rol_adi", 'başarısız');
        } else {
            $ekle = $db->prepare("INSERT INTO roller (rol_adi) VALUES (?)");
            if ($ekle->execute([$rol_adi])) {
                $mesaj .= '<div class="alert alert-success mt-2">Rol başarıyla eklendi.</div>';
                log_ekle($db, $personel_id, 112, 'kaydet', "Yeni rol eklendi: $rol_adi", 'başarılı');
            } else {
                $mesaj .= '<div class="alert alert-danger mt-2">Rol eklenirken bir hata oluştu.</div>';
                log_ekle($db, $personel_id, 112, 'kaydet', "Rol eklenemedi: $rol_adi", 'başarısız');
            }
        }
    } else {
        $mesaj .= '<div class="alert alert-danger mt-2">Rol adı boş olamaz.</div>';
        log_ekle($db, $personel_id, 112, 'kaydet', "Rol eklenemedi, rol adı boş.", 'başarısız');
    }
}

// --- 3. ROL, SAYFA, YETKİLERİ ÇEK ---
$roller = $db->query("SELECT * FROM roller")->fetchAll(PDO::FETCH_ASSOC);
$sayfalar = $db->query("SELECT * FROM sayfalar WHERE aktif=1")->fetchAll(PDO::FETCH_ASSOC);
$secili_rol_id = intval($_GET['rol_id'] ?? ($roller[0]['id'] ?? 1));

// Rol değiştirme (GET ile)
if (isset($_GET['rol_id'])) {
    log_ekle($db, $personel_id, 111, 'goruntuleme', "Rol değiştirildi: ID {$_GET['rol_id']}", 'başarılı');
}

// Seçili role ait yetkiler
$yetkiler = [];
$stmt = $db->prepare("SELECT * FROM roller_yetkiler WHERE rol_id = ?");
$stmt->execute([$secili_rol_id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['sayfa_id'] !== null) {
        $yetkiler[$row['sayfa_id']] = $row;
    }
}

// --- 4. AJAX GÜNCELLEME (YETKİLER) ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['rol_id'], $_POST['sayfa_id'])
    && !isset($_POST['yeni_sayfa_ekle']) && !isset($_POST['yeni_rol_ekle'])
) {
    $rol_id = intval($_POST['rol_id']);
    $sayfa_id = intval($_POST['sayfa_id']);

    $goruntule = isset($_POST['goruntule']) ? intval($_POST['goruntule']) : 0;
    $ekle      = isset($_POST['ekle'])      ? intval($_POST['ekle'])      : 0;
    $duzenle   = isset($_POST['duzenle'])   ? intval($_POST['duzenle'])   : 0;
    $sil       = isset($_POST['sil'])       ? intval($_POST['sil'])       : 0;

    $kontrol = $db->prepare("SELECT id FROM roller_yetkiler WHERE rol_id = ? AND sayfa_id = ?");
    $kontrol->execute([$rol_id, $sayfa_id]);
    if ($row = $kontrol->fetch(PDO::FETCH_ASSOC)) {
        $guncelle = $db->prepare("UPDATE roller_yetkiler SET goruntule=?, ekle=?, duzenle=?, sil=? WHERE id=?");
        $guncelle->execute([$goruntule, $ekle, $duzenle, $sil, $row['id']]);
        log_ekle($db, $personel_id, 114, 'guncelle', "Yetki güncellendi: rol_id=$rol_id, sayfa_id=$sayfa_id", 'başarılı');
    } else {
        $ekleYetki = $db->prepare("INSERT INTO roller_yetkiler (rol_id, sayfa_id, goruntule, ekle, duzenle, sil) VALUES (?, ?, ?, ?, ?, ?)");
        $ekleYetki->execute([$rol_id, $sayfa_id, $goruntule, $ekle, $duzenle, $sil]);
        log_ekle($db, $personel_id, 114, 'kaydet', "Yeni yetki eklendi: rol_id=$rol_id, sayfa_id=$sayfa_id", 'başarılı');
    }
    echo json_encode(['success' => true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yetki Yönetimi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container py-4">
    <h3 class="mb-3">Yetki Yönetimi</h3>

    <!-- YENİ SAYFA EKLEME FORMU -->
    <button class="btn btn-outline-primary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#sayfaEkleFormu" aria-expanded="false" aria-controls="sayfaEkleFormu">
        + Yeni Sayfa Ekle
    </button>
    <button class="btn btn-outline-success mb-2 ms-2" type="button" data-bs-toggle="modal" data-bs-target="#rolEkleModal">
        + Yeni Rol Ekle
    </button>
    <div class="collapse mb-3" id="sayfaEkleFormu">
        <form method="post" class="row g-3">
            <input type="hidden" name="yeni_sayfa_ekle" value="1">
            <div class="col-md-4">
                <input type="text" name="sayfa" class="form-control" required placeholder="Dosya Adı (ör: dashboard.php)">
            </div>
            <div class="col-md-4">
                <input type="text" name="baslik" class="form-control" required placeholder="Başlık (ör: Dashboard)">
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="aktif" id="aktif" checked>
                    <label class="form-check-label" for="aktif">Aktif</label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <button type="submit" class="btn btn-success">Ekle</button>
            </div>
        </form>
    </div>
    <?= $mesaj ?>

    <!-- ROL SEÇİMİ -->
    <form class="mb-3" method="get" action="">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="rol_id" class="form-label">Rol Seçin:</label>
            </div>
            <div class="col-auto">
                <select name="rol_id" id="rol_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($roller as $rol): ?>
                        <option value="<?= $rol['id'] ?>" <?= $rol['id'] == $secili_rol_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rol['rol_adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <!-- YETKİ TABLOSU -->
    <table class="table table-bordered table-hover bg-white align-middle">
        <thead class="table-light">
            <tr>
                <th>Sayfa</th>
                <th>Görüntüle</th>
                <th>Ekle</th>
                <th>Düzenle</th>
                <th>Sil</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sayfalar as $sf):
            $y = $yetkiler[$sf['id']] ?? ['goruntule'=>0, 'ekle'=>0, 'duzenle'=>0, 'sil'=>0];
        ?>
            <tr>
                <td><?= htmlspecialchars($sf['baslik'] ?? $sf['sayfa']) ?></td>
                <td class="text-center">
                    <input type="checkbox" class="yetki-checkbox"
                        data-sayfa="<?= $sf['id'] ?>"
                        data-yetki="goruntule"
                        <?= $y['goruntule'] ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="yetki-checkbox"
                        data-sayfa="<?= $sf['id'] ?>"
                        data-yetki="ekle"
                        <?= $y['ekle'] ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="yetki-checkbox"
                        data-sayfa="<?= $sf['id'] ?>"
                        data-yetki="duzenle"
                        <?= $y['duzenle'] ? 'checked' : '' ?>>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="yetki-checkbox"
                        data-sayfa="<?= $sf['id'] ?>"
                        data-yetki="sil"
                        <?= $y['sil'] ? 'checked' : '' ?>>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div id="yetkiKayitSonucu"></div>
    <div class="row mt-3">
        <div class="col-md-6 ms-auto">
            <input type="text" id="yetkiArama" class="form-control" placeholder="Sayfa adı ara...">
        </div>
    </div>
</div>

<!-- Rol Ekle Modal -->
<div class="modal fade" id="rolEkleModal" tabindex="-1" aria-labelledby="rolEkleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" autocomplete="off">
      <input type="hidden" name="yeni_rol_ekle" value="1">
      <div class="modal-header">
        <h5 class="modal-title" id="rolEkleModalLabel">Yeni Rol Ekle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label for="rol_adi" class="form-label">Rol Adı</label>
            <input type="text" class="form-control" name="rol_adi" id="rol_adi" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
        <button type="submit" class="btn btn-success">Ekle</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function(){
    $('.yetki-checkbox').on('change', function() {
        var row = $(this).closest('tr');
        var sayfa_id = $(this).data('sayfa');
        var rol_id = $('#rol_id').val();

        var goruntule = row.find('input[data-yetki="goruntule"]').is(':checked') ? 1 : 0;
        var ekle      = row.find('input[data-yetki="ekle"]').is(':checked') ? 1 : 0;
        var duzenle   = row.find('input[data-yetki="duzenle"]').is(':checked') ? 1 : 0;
        var sil       = row.find('input[data-yetki="sil"]').is(':checked') ? 1 : 0;

        $.post('', {
            rol_id: rol_id,
            sayfa_id: sayfa_id,
            goruntule: goruntule,
            ekle: ekle,
            duzenle: duzenle,
            sil: sil
        }, function(cevap){
            $('#yetkiKayitSonucu').html('<div class="alert alert-success py-1 my-2">Kayıt güncellendi!</div>');
            setTimeout(function() { $('#yetkiKayitSonucu').html(''); }, 1200);
            // --- YETKİ GÜNCELLEME LOGU (id: 114, AJAX ile tetiklenir) ---
            fetch('log_ekle_ajax.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: "islem_id=114&aciklama=" + encodeURIComponent("Yetki güncellendi: rol_id=" + rol_id + ", sayfa_id=" + sayfa_id)
            });
        }, 'json');
    });

    // Sayfa arama (log tutar)
    $('#yetkiArama').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('table tbody tr').each(function(){
            var rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(value) > -1);
        });
        // --- ARAMA LOGU (id: 94) sadece ilk harfte (isteğe göre her seferde de yapılabilir) ---
        if (this.value.length === 1) {
            fetch('log_ekle_ajax.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: "islem_id=94&aciklama=" + encodeURIComponent("Yetki yönetiminde arama: " + this.value)
            });
        }
    });
});
</script>
</body>
</html>
