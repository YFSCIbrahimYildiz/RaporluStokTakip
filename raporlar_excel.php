<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

oturumKontrol();
$kullanici_id = $_SESSION['kullanici_id'] ?? 0;
$rol_id = $_SESSION['rol_id'] ?? 0;
$sayfa_adi = basename(__FILE__);

// Sayfa yetki kontrolü
if (!yetkiVarMi($rol_id, $sayfa_adi)) {
    header("Location: dashboard.php");
    exit;
}

// SAYFA GÖRÜNTÜLEME LOGU (işlem_id=150 örnektir, dilediğin gibi kullan)
log_ekle($db, $kullanici_id, 150, 'goruntuleme', 'Raporlar ve Excel İndirme Merkezi görüntülendi.', 'başarılı');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Rapor ve Excel İndirme Merkezi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6fa; }
        .rapor-card {
            border-radius: 1.5rem;
            box-shadow: 0 2px 14px #e2e2e2;
            transition: box-shadow .18s;
            min-height: 220px;
        }
        .rapor-card:hover {
            box-shadow: 0 4px 30px #d2ecff;
        }
        .rapor-icon {
            font-size: 2.8rem;
            color: #198754;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: .7rem;
        }
        .rapor-btn {
            font-size: 1rem;
            padding: .5rem 1.1rem;
        }
        @media (max-width: 767px) {
            .rapor-card { min-height: 150px; }
            .rapor-icon { font-size: 2rem; }
            .card-title { font-size: 1.05rem;}
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/personel_bar.php'; ?>
<div class="container py-5">
    <h2 class="mb-4 text-center text-success"><i class="bi bi-file-earmark-spreadsheet"></i> Rapor ve Excel İndirme Merkezi</h2>
    <div class="row g-4">

        <!-- KARTLAR - her biri için aşağıdaki örneği kullan -->
        <?php
        // Raporlar ve ilgili export dosyaları
        $raporlar = [
            [
                "baslik" => "Malzeme Listesi",
                "icon" => "bi-box-seam text-success",
                "desc" => "Tüm malzemelerin temel bilgileri, stok durumu, açıklamalar.",
                "btn_class" => "btn-success",
                "dosya" => "export_malzeme.php",
                "islem_id" => 151
            ],
            [
                "baslik" => "Stok Geçmişi",
                "icon" => "bi-arrow-repeat text-info",
                "desc" => "Tüm giriş/çıkış stok hareketleri ve ayrıntıları.",
                "btn_class" => "btn-primary",
                "dosya" => "export_stok_gecmisi.php",
                "islem_id" => 152
            ],
            [
                "baslik" => "Talepler",
                "icon" => "bi-envelope-check text-warning",
                "desc" => "Tüm talepler, onay/red/iptal durumları ile birlikte.",
                "btn_class" => "btn-warning",
                "dosya" => "export_talepler.php",
                "islem_id" => 153
            ],
            [
                "baslik" => "Siparişler",
                "icon" => "bi-cart-check text-secondary",
                "desc" => "Tüm siparişler, fatura ve tutar bilgileri.",
                "btn_class" => "btn-secondary",
                "dosya" => "export_siparisler.php",
                "islem_id" => 154
            ],
            [
                "baslik" => "Personeller",
                "icon" => "bi-people text-danger",
                "desc" => "Tüm personeller, roller ve iletişim bilgileri.",
                "btn_class" => "btn-danger",
                "dosya" => "export_personeller.php",
                "islem_id" => 155
            ],
            [
                "baslik" => "Loglar",
                "icon" => "bi-clipboard-data text-dark",
                "desc" => "Tüm sistem log kayıtları, işlem ve hata hareketleri.",
                "btn_class" => "btn-dark",
                "dosya" => "export_loglar.php",
                "islem_id" => 156
            ],
            [
                "baslik" => "Kritik Stoklar",
                "icon" => "bi-exclamation-diamond text-danger",
                "desc" => "Min. limit altındaki malzemeler otomatik vurgulu.",
                "btn_class" => "btn-outline-danger",
                "dosya" => "export_krikik_stoklar.php",
                "islem_id" => 157
            ],
            [
                "baslik" => "Acil İhtiyaçlar",
                "icon" => "bi-exclamation-circle text-primary",
                "desc" => "Acil ihtiyacı bulunan stoklar ve eksik adetler.",
                "btn_class" => "btn-outline-primary",
                "dosya" => "export_acil_ihtiyac.php",
                "islem_id" => 158
            ],
            [
                "baslik" => "Tedarikçi Raporu",
                "icon" => "bi-building text-secondary",
                "desc" => "Tedarikçi bazlı satın alma ve stok hareketleri.",
                "btn_class" => "btn-outline-secondary",
                "dosya" => "export_tedarikci_analiz.php",
                "islem_id" => 159
            ],
            [
                "baslik" => "Tedarikçi Listesi",
                "icon" => "bi-building text-secondary",
                "desc" => "Tüm tedarikçiler, tüm bilgileri.",
                "btn_class" => "btn-outline-secondary",
                "dosya" => "export_tedarikciler.php",
                "islem_id" => 160
            ],
        ];
        foreach ($raporlar as $rapor): ?>
        <div class="col-lg-4 col-md-6">
            <div class="card rapor-card py-4 px-3 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="rapor-icon"><i class="bi <?= $rapor['icon'] ?>"></i></div>
                    <div class="card-title"><?= htmlspecialchars($rapor['baslik']) ?></div>
                    <div class="text-secondary small"><?= htmlspecialchars($rapor['desc']) ?></div>
                </div>
                <a href="excel/<?= $rapor['dosya'] ?>" 
                   class="btn <?= $rapor['btn_class'] ?> rapor-btn mt-3 w-100 export-excel-btn"
                   target="_blank" 
                   data-islem-id="<?= $rapor['islem_id'] ?>"
                   data-baslik="<?= htmlspecialchars($rapor['baslik']) ?>"
                   download>
                    <i class="bi bi-file-earmark-excel"></i> Excel İndir
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- LOG EKLEME (AJAX) -->
<script>
document.querySelectorAll('.export-excel-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        // Butona tıklanınca log kaydını AJAX ile arka planda gönderiyoruz
        var islem_id = this.dataset.islemId;
        var aciklama = this.dataset.baslik + " raporu excel indirme işlemi başlatıldı.";
        fetch('log_ekle_ajax.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: "islem_id=" + encodeURIComponent(islem_id) + "&tip=excel_indir&aciklama=" + encodeURIComponent(aciklama)
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
