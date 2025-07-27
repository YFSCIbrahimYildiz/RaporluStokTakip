<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
session_start();
require_once __DIR__ . '/includes/functions.php'; 
oturumKontrol();

$rol_id = $_SESSION['rol_id'];
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

// --- getIslemId fonksiyonu (functions.php'de tanımlı olmalı) ---
if (!function_exists('getIslemId')) {
    function getIslemId($ad) {
        global $db;
        $q = $db->prepare("SELECT id FROM islemler WHERE ad = ?");
        $q->execute([$ad]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ? intval($row['id']) : null;
    }
}

// --- log_encrypt fonksiyonu (örnek) ---
if (!function_exists('log_encrypt')) {
    function log_encrypt($data) {
        if ($data === null) return '';
        return base64_encode($data); // Gerçek projede openssl_encrypt kullan!
    }
}

// --- log_ekle fonksiyonu ---
if (!function_exists('log_ekle')) {
    function log_ekle($pdo, $personel_id, $islem_id, $islem_tipi, $aciklama = '', $islem_sonucu = null) {
        $ip_adresi = log_encrypt($_SERVER['REMOTE_ADDR'] ?? '');
        $oturum_id = log_encrypt(session_id());
        $sayfa = log_encrypt(basename($_SERVER['PHP_SELF']));
        $cihaz_bilgisi = log_encrypt($_SERVER['HTTP_USER_AGENT'] ?? '');
        $aciklama_enc = log_encrypt($aciklama);
        $islem_sonucu_enc = log_encrypt($islem_sonucu ?? '');

        $sql = "INSERT INTO loglar (personel_id, ip_adresi, oturum_id, sayfa, islem_id, islem_tipi, aciklama, cihaz_bilgisi, islem_sonucu)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $personel_id,
            $ip_adresi,
            $oturum_id,
            $sayfa,
            $islem_id,
            $islem_tipi,
            $aciklama_enc,
            $cihaz_bilgisi,
            $islem_sonucu_enc
        ]);
    }
}

// 1. SAYFAYA GİRİŞ LOGU
$islem_giris_id = getIslemId('Talep Girişi Görüntüleme');
if ($islem_giris_id) {
    log_ekle($db, $personel_id, $islem_giris_id, 'goruntuleme', 'talep_giris.php görüntülendi.', 'başarılı');
}

// Malzeme listesini çek
$malzemeler = $db->query("SELECT id, ad, birim FROM malzemeler")->fetchAll(PDO::FETCH_ASSOC);

// Başarı/hata mesajı
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $malzeme_id = intval($_POST['malzeme_id'] ?? 0);
    $miktar = floatval($_POST['miktar'] ?? 0);
    $aciklama = trim($_POST['aciklama'] ?? '');
    $aciliyet = $_POST['aciliyet'] ?? 'normal';

    if ($malzeme_id && $miktar > 0) {
        try {
            $stmt = $db->prepare("INSERT INTO talepler (personel_id, aciklama, aciliyet) VALUES (?, ?, ?)");
            $stmt->execute([$personel_id, $aciklama, $aciliyet]);
            $talep_id = $db->lastInsertId();

            $stmt2 = $db->prepare("INSERT INTO talep_urunleri (talep_id, malzeme_id, istenen_miktar) VALUES (?, ?, ?)");
            $stmt2->execute([$talep_id, $malzeme_id, $miktar]);

            $mesaj = '<div class="alert alert-success mt-3">Talebiniz başarıyla kaydedildi.</div>';

            $islem_talep_ekle_id = getIslemId('Talep Ekleme');
            if ($islem_talep_ekle_id) {
                log_ekle(
                    $db, $personel_id, $islem_talep_ekle_id, 'kaydet',
                    "Talep kaydedildi. Talep ID: {$talep_id}, Malzeme ID: {$malzeme_id}, Miktar: {$miktar}, Aciliyet: {$aciliyet}, Açıklama: {$aciklama}",
                    'başarılı'
                );
            }
        } catch(Exception $e) {
            $mesaj = '<div class="alert alert-danger mt-3">Kayıt sırasında hata oluştu: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $islem_talep_ekle_id = getIslemId('Talep Ekleme');
            if ($islem_talep_ekle_id) {
                log_ekle(
                    $db, $personel_id, $islem_talep_ekle_id, 'kaydet',
                    "Talep kaydı sırasında hata oluştu! Hata: " . $e->getMessage(),
                    'başarısız'
                );
            }
        }
    } else {
        $mesaj = '<div class="alert alert-danger mt-3">Malzeme ve miktar zorunludur.</div>';
        $islem_talep_ekle_id = getIslemId('Talep Ekleme');
        if ($islem_talep_ekle_id) {
            log_ekle(
                $db, $personel_id, $islem_talep_ekle_id, 'kaydet',
                "Talep eklenemedi. Eksik alan. Malzeme ID: {$malzeme_id}, Miktar: {$miktar}",
                'başarısız'
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Talep Girişi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        .talep-form-card {
            max-width: 480px;
            margin: auto;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(60, 72, 100, 0.16);
            background: #fff;
        }
        @media (max-width: 576px) {
            .talep-form-card { box-shadow: 0 2px 12px rgba(60,72,100,.10);}
        }
    </style>
</head>
<body style="background:#f7f9fb;">
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-12">
                <div class="card talep-form-card p-4 py-5">
                    <h3 class="mb-4 text-center fw-bold">Yeni Talep Oluştur</h3>
                    <?= $mesaj ?>
                    <form method="post" autocomplete="off">
                        <div class="mb-3">
                            <label for="malzeme_id" class="form-label fw-semibold">Malzeme</label>
                            <select name="malzeme_id" id="malzeme_id" class="form-select" required>
                                <option value="">Malzeme seçiniz...</option>
                                <?php foreach($malzemeler as $m): ?>
                                    <option value="<?= $m['id'] ?>">
                                        <?= htmlspecialchars($m['ad']) . " (" . htmlspecialchars($m['birim']) . ")" ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="miktar" class="form-label fw-semibold">Miktar</label>
                            <input type="number" min="1" step="any" name="miktar" id="miktar" class="form-control" required placeholder="İstenen miktarı girin">
                        </div>
                        <div class="mb-3">
                            <label for="aciklama" class="form-label fw-semibold">Sebep / Açıklama <small class="text-muted">(Opsiyonel)</small></label>
                            <textarea name="aciklama" id="aciklama" rows="2" class="form-control" maxlength="250" placeholder="Talep sebebini yazabilirsiniz..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="aciliyet" class="form-label fw-semibold">Aciliyet</label>
                            <select name="aciliyet" id="aciliyet" class="form-select">
                                <option value="normal" selected>Normal</option>
                                <option value="acil">Acil</option>
                                <option value="çok acil">Çok Acil</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">Talebi Gönder</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS (Opsiyonel) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (Select2 için şart) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#malzeme_id').select2({
        width: '100%',
        placeholder: 'Malzeme seçiniz...',
        allowClear: true,
        language: "tr"
    });
});
</script>

</body>
</html>
