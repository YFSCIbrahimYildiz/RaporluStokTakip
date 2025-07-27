<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/encryption.php'; // log_decrypt burada
if (session_status() == PHP_SESSION_NONE) session_start();

// Yetki kontrolü
if (!isset($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}
$rol_id = $_SESSION['rol_id'];
$sayfa_adi = 'loglar.php';
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}
$personel_id = $_SESSION['kullanici_id'];

// Sayfa görüntüleme logu
log_ekle($db, $personel_id, 70, 'goruntuleme', 'Loglar sayfası görüntülendi', 'başarılı');

// Tüm logları çek (son 1000 kayıt)
$sql = "SELECT l.*, p.ad, p.soyad, p.sicil
        FROM loglar l
        LEFT JOIN personeller p ON l.personel_id = p.id
        ORDER BY l.islem_tarihi DESC
        LIMIT 1000";
$stmt = $db->prepare($sql);
$stmt->execute();
$loglar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PHP'de filtreleme
$filtre = mb_strtolower(trim($_GET['filtre'] ?? ''));
if ($filtre !== '') {
    $loglar = array_filter($loglar, function($log) use ($filtre) {
        $searchable = [
            $log['personel_id'] ?? '',
            $log['ad'] ?? '',
            $log['soyad'] ?? '',
            $log['sicil'] ?? '',
            $log['islem_tipi'] ?? '',
            log_decrypt($log['aciklama'] ?? ''),
            log_decrypt($log['islem_sonucu'] ?? ''),
            log_decrypt($log['sayfa'] ?? ''),
            log_decrypt($log['oturum_id'] ?? ''),
            log_decrypt($log['ip_adresi'] ?? ''),
            log_decrypt($log['cihaz_bilgisi'] ?? ''),
        ];
        foreach ($searchable as $val) {
            if (mb_stripos(mb_strtolower($val), $filtre) !== false) return true;
        }
        return false;
    });
    // Filtreleme logu (bunu SQL yerine burada yazmak artık güvenli)
    log_ekle($GLOBALS['db'], $_SESSION['kullanici_id'], 71, 'filtreleme', 'Loglarda filtreleme yapıldı. Filtre: ' . $filtre, 'başarılı');
}

// Tablo çıktısı
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Log Kayıtları</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive { border-radius: 12px; background: #fff; box-shadow: 0 2px 10px #e4e4e4; }
        .log-table thead th { white-space: nowrap; }
        @media (max-width: 991px) { .log-table th, .log-table td { font-size: 13px; } }
        @media (max-width: 767px) { .log-table th, .log-table td { padding: 7px 4px; } }
    </style>
</head>
<body style="background:#f7f9fb;">
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container py-5 py-md-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
        <h2 class="fw-bold text-primary mb-0">Log Kayıtları</h2>
        <form class="d-flex gap-2" method="get" autocomplete="off">
            <input type="text" name="filtre" class="form-control" style="max-width:220px" value="<?= htmlspecialchars($_GET['filtre'] ?? '') ?>" placeholder="Her alanda ara...">
            <button class="btn btn-info text-white" type="submit">Filtrele</button>
            <a href="loglar.php" class="btn btn-outline-secondary">Tümü</a>
        </form>
    </div>
    <div class="table-responsive mb-4 shadow-sm">
        <table class="table table-bordered align-middle log-table">
            <thead class="table-secondary">
                <tr>
                    <th>#</th>
                    <th>Kullanıcı ID</th>
                    <th>Sicil</th>
                    <th>Ad Soyad</th>
                    <th>İşlem Tipi</th>
                    <th>Açıklama</th>
                    <th>İşlem Sonucu</th>
                    <th>Sayfa</th>
                    <th>Oturum ID</th>
                    <th>IP Adresi</th>
                    <th>Cihaz Bilgisi</th>
                    <th>İşlem Tarihi</th>
                </tr>
            </thead>
            <tbody>
<?php
$i = 1;
foreach($loglar as $log): ?>
    <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($log['personel_id'] ?? '-') ?></td>
        <td><?= htmlspecialchars($log['sicil'] ?? '-') ?></td>
        <td><?= htmlspecialchars(($log['ad'] ?? '') . ' ' . ($log['soyad'] ?? '')) ?></td>
        <td><?= htmlspecialchars($log['islem_tipi'] ?? '-') ?></td>
        <td><?= htmlspecialchars(log_decrypt($log['aciklama'] ?? '')) ?></td>
        <td><?= htmlspecialchars(log_decrypt($log['islem_sonucu'] ?? '')) ?></td>
        <td><?= htmlspecialchars(log_decrypt($log['sayfa'] ?? '')) ?></td>
        <td><?= htmlspecialchars(log_decrypt($log['oturum_id'] ?? '')) ?></td>
        <td><?= htmlspecialchars(log_decrypt($log['ip_adresi'] ?? '')) ?></td>
        <td><?= htmlspecialchars(log_decrypt($log['cihaz_bilgisi'] ?? '')) ?></td>
        <td><?= date('d.m.Y H:i:s', strtotime($log['islem_tarihi'])) ?></td>
    </tr>
<?php endforeach; if(empty($loglar)): ?>
    <tr><td colspan="12" class="text-center">Kayıt bulunamadı.</td></tr>
<?php endif; ?>
</tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
