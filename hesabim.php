<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 
oturumKontrol();
$rol_id = $_SESSION['rol_id'];
$sayfa_id = getSayfaId(basename(__FILE__));
$sayfa_adi = basename(__FILE__); 
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

$kullanici_id = $_SESSION["kullanici_id"] ?? 0;
$kullanici = getKullaniciById($kullanici_id);
$adi = $kullanici['ad'] ?? '';
$soyadi = $kullanici['soyad'] ?? '';
$sicil = $kullanici['sicil'] ?? '';
$email = $kullanici['email'] ?? '';
$hata = $basari = "";

// Şifre kurallarını kontrol eden fonksiyon
function sifre_kurali($pw) {
    return strlen($pw) >= 8 &&
        preg_match('/[A-Z]/', $pw) &&
        preg_match('/[a-z]/', $pw) &&
        preg_match('/[0-9]/', $pw) &&
        preg_match('/[\W_]/', $pw);
}
$islem_id = 2; // islemler tablosunda "Kontrol Paneli Görüntüleme"
log_ekle(
    $db,
    $kullanici_id,
    $islem_id,
    'goruntuleme',
    'Kontrol paneli açıldı.',
    'başarılı'
);
// LOG için id: islemler tablosunda Şifre Güncelleme için id: 90 (örnek) olsun!
$islem_id = 90; // (islemler tablosunda "Şifre Güncelleme" işleminin id'si)

// Şifre güncelleme işlemi
if (isset($_POST["sifre_guncelle"])) {
    $eski = $_POST["eski_parola"];
    $yeni = $_POST["yeni_parola"];
    $yeni2 = $_POST["yeni_parola2"];

    if (!$eski || !$yeni || !$yeni2) {
        $hata = "Lütfen tüm alanları doldurun.";
        log_ekle(
            $db,
            $kullanici_id,
            $islem_id,
            'guncelle',
            'Şifre değiştirme denemesi: Eksik alan.',
            'başarısız'
        );
    } elseif (!password_verify($eski, $kullanici["sifre"])) {
        $hata = "Eski şifreniz hatalı!";
        log_ekle(
            $db,
            $kullanici_id,
            $islem_id,
            'guncelle',
            'Şifre değiştirme denemesi: Eski şifre hatalı.',
            'başarısız'
        );
    } elseif ($yeni !== $yeni2) {
        $hata = "Yeni şifreler uyuşmuyor!";
        log_ekle(
            $db,
            $kullanici_id,
            $islem_id,
            'guncelle',
            'Şifre değiştirme denemesi: Yeni şifreler uyuşmuyor.',
            'başarısız'
        );
    } elseif (!sifre_kurali($yeni)) {
        $hata = "Şifreniz en az 8 karakter olmalı, bir büyük harf, küçük harf, rakam ve özel karakter (!@#\$%^&*) içermelidir.";
        log_ekle(
            $db,
            $kullanici_id,
            $islem_id,
            'guncelle',
            'Şifre değiştirme denemesi: Şifre kurallarına uymuyor.',
            'başarısız'
        );
    } else {
        if (updateSifre($kullanici_id, $yeni)) {
            $basari = "Şifre başarıyla güncellendi.";
            log_ekle(
                $db,
                $kullanici_id,
                $islem_id,
                'guncelle',
                'Şifre başarıyla değiştirildi.',
                'başarılı'
            );
        } else {
            $hata = "Şifre güncellenemedi.";
            log_ekle(
                $db,
                $kullanici_id,
                $islem_id,
                'guncelle',
                'Şifre değiştirme işlemi: Veritabanı hatası.',
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
    <title>Şifre Değiştir - Körfez Ticaret Odası</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f9fb; min-height: 100vh; }
        .card { border-radius: 1rem; max-width: 410px; margin: 48px auto; }
        .form-label { font-weight: 500; color: #223672; }
        .navlink-dashboard {
            text-decoration: none;
            font-weight: 500;
        }
        .navlink-dashboard:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h4 class="m-0" style="color:#223672;">
                <a href="dashboard.php" class="navlink-dashboard text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" fill="currentColor" class="bi bi-arrow-left me-1 mb-1" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 0 1 .708.708L2.707 7.5H14.5A.5.5 0 0 1 15 8z"/>
                    </svg>
                    Ana Sayfa
                </a> | Şifre Değiştir
            </h4>
        </div>
        <div class="col-auto text-end">
            <span class="badge bg-primary text-light fs-6" style="padding:.6em 1em;">
                <?= htmlspecialchars($adi . " " . $soyadi) ?>
            </span>
            <span class="badge bg-secondary text-light ms-2 fs-6" style="padding:.6em 1em;">
                Sicil: <?= htmlspecialchars($sicil) ?>
            </span>
        </div>
    </div>
    <?php if ($hata): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($hata) ?></div>
    <?php elseif ($basari): ?>
        <div class="alert alert-success"><?= htmlspecialchars($basari) ?></div>
    <?php endif; ?>
    <div class="card p-4 shadow-sm">
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Mevcut Şifre</label>
                <input type="password" name="eski_parola" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Yeni Şifre</label>
                <input type="password" name="yeni_parola" class="form-control" minlength="8" required
                       pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\W_]).{8,}"
                       title="En az 8 karakter, bir büyük harf, bir küçük harf, bir rakam ve bir özel karakter (!@#$%^&*) içermelidir.">
            </div>
            <div class="mb-3">
                <label class="form-label">Yeni Şifre (Tekrar)</label>
                <input type="password" name="yeni_parola2" class="form-control" minlength="8" required>
            </div>
            <button type="submit" name="sifre_guncelle" class="btn btn-primary w-100">Şifreyi Güncelle</button>
        </form>
    </div>
</div>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
