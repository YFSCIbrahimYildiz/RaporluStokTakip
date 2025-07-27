<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

oturumKontrol();
$rol_id = $_SESSION['rol_id'];
$sayfa_id = getSayfaId(basename(__FILE__));
$sayfa_adi = basename(__FILE__); 
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

$adSoyad = kullaniciAdi();

// === LOG: Sayfa görüntüleme kaydı ===
$islem_id = 2; // islemler tablosunda "Dashboard Görüntüleme" için açtığın id olsun!
log_ekle(
    $db,
    $_SESSION['kullanici_id'],
    $islem_id,
    'goruntuleme',
    'Kontrol paneli görüntülendi.',
    'başarılı'
);

// KULLANICININ YETKİSİ OLAN SAYFALARI ÇEK
$stmt = $db->prepare("
    SELECT s.*
    FROM sayfalar s
    INNER JOIN roller_yetkiler r ON r.sayfa_id = s.id
    WHERE s.aktif=1
      AND s.sayfa != 'dashboard.php'
      AND r.rol_id = ?
      AND r.goruntule = 1
    ORDER BY s.sira ASC, s.id ASC
");
$stmt->execute([$rol_id]);
$sayfaKartlari = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kontrol Paneli</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .dashboard-card {
            min-height: 120px;
            border-radius: 1.25rem;
            box-shadow: 0 0 14px #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .12s;
            cursor: pointer;
        }
        .dashboard-card:hover { transform: scale(1.04);}
        .dashboard-card i {
            font-size: 2.2rem;
            margin-right: .8rem;
            color: #2376ae;
        }
        .dashboard-card-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #222;
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container mt-4">
    <h2 class="mb-4">Hoşgeldiniz, <?= htmlspecialchars($adSoyad) ?></h2>
    <div class="row g-3">
        <?php foreach($sayfaKartlari as $s): ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="<?= htmlspecialchars($s['sayfa']) ?>" class="text-decoration-none">
                    <div class="dashboard-card bg-white p-3">
                        <?php if (!empty($s['icon'])): ?>
                            <i class="bi <?= htmlspecialchars($s['icon']) ?>"></i>
                        <?php endif; ?>
                        <span class="dashboard-card-title"><?= htmlspecialchars($s['baslik'] ?: $s['sayfa']) ?></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
