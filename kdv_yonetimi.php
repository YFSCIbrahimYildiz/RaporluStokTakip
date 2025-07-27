<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 
oturumKontrol();

$kullanici_id = $_SESSION["kullanici_id"] ?? 0;
$rol_id = $_SESSION['rol_id'] ?? 0;

$dosya_adi = basename(__FILE__);

// Sayfa ID'sini çek
$stmt = $db->prepare("SELECT id FROM sayfalar WHERE sayfa = ?");
$stmt->execute([$dosya_adi]);
$sayfa = $stmt->fetch(PDO::FETCH_ASSOC);
$SAYFA_ID = $sayfa ? $sayfa['id'] : null;

if (!$SAYFA_ID) {
    echo '<div class="alert alert-danger">Bu sayfa, sayfalar tablosunda tanımlı değil.</div>';
    exit;
}

// Yetki kontrolü
if (!yetkiVarMi($rol_id, $dosya_adi)) {
    echo '<div class="alert alert-danger">Bu sayfayı görüntüleme yetkiniz yok.</div>';
    exit;
}

// LOG - Sayfa görüntülendi (ör: islem_id = 8)
log_ekle($db, $kullanici_id, 8, 'goruntuleme', 'KDV Oranları Yönetimi sayfası görüntülendi.', 'başarılı');

$mesaj = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oran'])) {
    $oran = floatval(str_replace(',', '.', $_POST['oran']));
    $islem_id_ekle = 81; // KDV Oranı Ekleme (islemler tablosunda olmalı!)
    if ($oran > 0) {
        // Zaten aynı oran var mı?
        $mevcut = $db->prepare("SELECT id FROM kdv_oranlari WHERE oran=?");
        $mevcut->execute([$oran]);
        if ($mevcut->fetch()) {
            $mesaj = "<div class='alert alert-warning'>Bu KDV oranı zaten kayıtlı.</div>";
            // BAŞARISIZ (zaten mevcut) log kaydı
            log_ekle(
                $db,
                $kullanici_id,
                $islem_id_ekle,
                'ekle',
                "KDV oranı eklenemedi (zaten mevcut): $oran",
                'başarısız'
            );
        } else {
            try {
                $db->prepare("INSERT INTO kdv_oranlari (oran, aktif) VALUES (?, 1)")->execute([$oran]);
                $mesaj = "<div class='alert alert-success'>KDV oranı eklendi.</div>";
                // BAŞARILI log kaydı
                log_ekle(
                    $db,
                    $kullanici_id,
                    $islem_id_ekle,
                    'ekle',
                    "Yeni KDV oranı eklendi: $oran",
                    'başarılı'
                );
            } catch (Exception $ex) {
                $mesaj = "<div class='alert alert-danger'>Veritabanı hatası: {$ex->getMessage()}</div>";
                // BAŞARISIZ log kaydı
                log_ekle(
                    $db,
                    $kullanici_id,
                    $islem_id_ekle,
                    'ekle',
                    "KDV oranı eklenemedi (VT hatası): $oran. Hata: {$ex->getMessage()}",
                    'başarısız'
                );
            }
        }
    } else {
        $mesaj = "<div class='alert alert-danger'>Geçerli bir oran giriniz.</div>";
        // BAŞARISIZ (geçersiz oran) log kaydı
        log_ekle(
            $db,
            $kullanici_id,
            $islem_id_ekle,
            'ekle',
            "Geçersiz oran girildi: $oran",
            'başarısız'
        );
    }
}

// KDV oranı aktif/pasif işlemi
// KDV oranı aktif/pasif işlemi
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $kdv_id = intval($_GET['toggle']);
    $row = $db->prepare("SELECT aktif, oran FROM kdv_oranlari WHERE id=?");
    $row->execute([$kdv_id]);
    $oran = $row->fetch();
    $islem_id_toggle = 82; // Aktif/Pasif Değiştirme (islemler tablosunda olmalı!)
    if ($oran) {
        $yeni_durum = $oran['aktif'] ? 0 : 1;
        try {
            $db->prepare("UPDATE kdv_oranlari SET aktif=? WHERE id=?")->execute([$yeni_durum, $kdv_id]);
            // Log için işlem tipi
            $islemTipi = $yeni_durum ? 'aktifleştir' : 'pasifleştir';
            $logAciklama = "KDV oranı {$oran['oran']} (id:$kdv_id) $islemTipi.";
            log_ekle(
                $db,
                $kullanici_id,
                $islem_id_toggle,
                $islemTipi,
                $logAciklama,
                'başarılı'
            );
            header("Location: kdv_yonetimi.php");
            exit;
        } catch (Exception $ex) {
            $islemTipi = $yeni_durum ? 'aktifleştir' : 'pasifleştir';
            $mesaj = "<div class='alert alert-danger'>Veritabanı hatası: {$ex->getMessage()}</div>";
            log_ekle(
                $db,
                $kullanici_id,
                $islem_id_toggle,
                $islemTipi,
                "KDV oranı güncellenemedi. Hata: {$ex->getMessage()}",
                'başarısız'
            );
        }
    }
}


// Oranları çek
$oranlar = $db->query("SELECT * FROM kdv_oranlari ORDER BY oran ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>KDV Oranları Yönetimi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafb; }
        .table th, .table td { vertical-align: middle; }
        .card { border-radius: 1.15rem; box-shadow: 0 2px 16px #e5e7eb; }
        .form-label { font-weight: 500; }
        .badge { font-size: 1em; }
        .card-header { font-weight: 600; font-size: 1.12rem; }
        @media (max-width: 576px) {
            .table-responsive { font-size: 15px; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container py-4">
    <div class="row align-items-center mb-3">
        <div class="col">
            <h4 style="color:#224488;"><i class="bi bi-percent"></i> KDV Oranları Yönetimi</h4>
        </div>
        <div class="col-auto">
            <?php include 'includes/personel_bar.php'; ?>
        </div>
    </div>
    <?php if($mesaj) echo $mesaj; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">Yeni KDV Oranı Ekle</div>
        <div class="card-body">
            <form method="post" class="row g-2 align-items-end">
                <div class="col-md-6 col-8">
                    <label class="form-label">KDV Oranı (%)</label>
                    <input type="number" step="0.01" min="0.01" name="oran" class="form-control" placeholder="Örn: 20.00" required>
                </div>
                <div class="col-md-3 col-4">
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle"></i> Ekle</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">Kayıtlı KDV Oranları</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">#</th>
                            <th>KDV Oranı (%)</th>
                            <th>Durum</th>
                            <th class="text-center" style="width:90px;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($oranlar as $o): ?>
                        <tr>
                            <td class="text-center"><?= $o['id'] ?></td>
                            <td><?= rtrim(rtrim(number_format($o['oran'],2,',','.'),'0'),',') ?></td>
                            <td>
                                <?php if($o['aktif']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($o['aktif']): ?>
                                    <a href="?toggle=<?= $o['id'] ?>" class="btn btn-sm btn-danger" title="Pasifleştir">Pasif Yap</a>
                                <?php else: ?>
                                    <a href="?toggle=<?= $o['id'] ?>" class="btn btn-sm btn-success" title="Aktif Yap">Aktif Yap</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(count($oranlar) == 0): ?>
                        <tr><td colspan="4" class="text-center text-muted">KDV oranı bulunamadı.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
