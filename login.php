<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; // Fonksiyonların burada!

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['kullanici_id'])) {
    header('Location: dashboard.php');
    exit;
}

$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sicil = trim($_POST['sicil'] ?? '');
    $sifre = trim($_POST['sifre'] ?? '');

    $islem_id = 1; // islemler tablosunda "Kullanıcı Girişi" işlemine ait ID

    if ($sicil && $sifre) {
        $stmt = $db->prepare("SELECT * FROM personeller WHERE sicil = ?");
        $stmt->execute([$sicil]);
        $kullanici = $stmt->fetch();

        if ($kullanici && password_verify($sifre, $kullanici['sifre']) && $kullanici['aktif']) {
            // BAŞARILI GİRİŞ
            $_SESSION['kullanici_id'] = $kullanici['id'];
            $_SESSION['rol_id'] = $kullanici['rol_id'];
            $_SESSION['ad'] = $kullanici['ad'];
            $_SESSION['soyad'] = $kullanici['soyad'];

            // Logla
            log_ekle(
                $db,
                $kullanici['id'],
                $islem_id,
                'giris',
                'Başarılı giriş yapıldı.',
                'başarılı'
            );

            header("Location: dashboard.php");
            exit;
        } else {
            $personel_id = $kullanici ? $kullanici['id'] : 0;

            // Başarısız giriş denemesini logla
            $personel_id = $kullanici ? $kullanici['id'] : null;
log_ekle(
    $db,
    $personel_id,
    $islem_id,
    'giris',
    'Başarısız giriş denemesi. Sicil: ' . $sicil,
    'başarısız'
);

            $hata = "Sicil numarası veya şifre hatalı ya da hesabınız pasif!";
        }
    } else {
        $hata = "Lütfen tüm alanları doldurun!";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Giriş Yap</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body {
            min-height: 100vh;
            background: #f4f6f8;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-form {
            width: 100%;
            max-width: 360px;
            padding: 24px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 12px #eee;
        }
    </style>
</head>
<body>
    <div class="login-form">
        <h3 class="mb-4 text-center">Kullanıcı Girişi</h3>
        <?php if ($hata): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($hata) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <input type="text" class="form-control" name="sicil" placeholder="Sicil Numarası" required>
            </div>
            <div class="mb-3">
                <input type="password" class="form-control" name="sifre" placeholder="Şifre" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
        </form>
    </div>
</body>
</html>
