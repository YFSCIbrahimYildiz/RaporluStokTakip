<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/db.php';

// Giriş oturum kontrolü
function oturumKontrol() {
    if (!isset($_SESSION['kullanici_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Kullanıcı adı/soyadı çek
function kullaniciAdi() {
    return ($_SESSION['ad'] ?? '') . ' ' . ($_SESSION['soyad'] ?? '');
}

// Rol adı getir (id'den)
function getRolAdi($rol_id) {
    global $db;
    $stmt = $db->prepare("SELECT rol_adi FROM roller WHERE id = ?");
    $stmt->execute([$rol_id]);
    return $stmt->fetchColumn() ?: '';
}

// Roller listesini getir (dropdown için)
function getRoller() {
    global $db;
    $stmt = $db->query("SELECT * FROM roller ORDER BY rol_adi");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Yetki kontrolü (sayfa_id bazlı - yeni yapı)
function sayfaErisimKontrol($rol_id, $sayfa_adi) {
    global $db;
    $stmt = $db->prepare("SELECT id FROM sayfalar WHERE sayfa = ?");
    $stmt->execute([$sayfa_adi]);
    $sayfa_id = $stmt->fetchColumn();
    if (!$sayfa_id) return false;
    $stmt2 = $db->prepare("SELECT COUNT(*) FROM roller_yetkiler WHERE rol_id = ? AND sayfa_id = ? AND goruntule=1");
    $stmt2->execute([$rol_id, $sayfa_id]);
    return $stmt2->fetchColumn() > 0;
}

// Kısayol yetki kontrolü (sayfa adı ile)
function yetkiVarMi($rol_id, $sayfa_adi) {
    global $db;
    $stmt = $db->prepare("SELECT id FROM sayfalar WHERE sayfa = ?");
    $stmt->execute([$sayfa_adi]);
    $sayfa_id = $stmt->fetchColumn();
    if (!$sayfa_id) return false;
    $stmt2 = $db->prepare("SELECT 1 FROM roller_yetkiler WHERE rol_id = ? AND sayfa_id = ? AND goruntule=1");
    $stmt2->execute([$rol_id, $sayfa_id]);
    return $stmt2->fetchColumn() ? true : false;
}

function getSayfaId($dosya_adi) {
    global $db;
    $stmt = $db->prepare("SELECT id FROM sayfalar WHERE sayfa = ?");
    $stmt->execute([$dosya_adi]);
    return $stmt->fetchColumn() ?: 0;
}


// Malzeme bilgisi döner
function getMalzemeler() {
    global $db;
    return $db->query("SELECT m.*, k.oran as kdv_orani FROM malzemeler m LEFT JOIN kdv_oranlari k ON m.kdv_id = k.id ORDER BY m.ad")->fetchAll(PDO::FETCH_ASSOC);
}

// Tedarikçi listesi
function getTedarikciler() {
    global $db;
    return $db->query("SELECT * FROM tedarikciler ORDER BY ad")->fetchAll(PDO::FETCH_ASSOC);
}

// KDV oranı döner (id'den)
function getKdvOrani($kdv_id) {
    global $db;
    $stmt = $db->prepare("SELECT oran FROM kdv_oranlari WHERE id=?");
    $stmt->execute([$kdv_id]);
    return $stmt->fetchColumn();
}

// Siparişler ve detay ürünleri ile birlikte döner
function getSiparislerFull() {
    global $db;
    $siparisler = $db->query("
        SELECT s.*, t.ad as tedarikci_adi, t.firma_kodu
        FROM siparisler s
        LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
        ORDER BY s.siparis_tarihi DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($siparisler as &$siparis) {
        $stmt = $db->prepare("
            SELECT su.*, m.ad as malzeme_adi, m.birim, k.oran as kdv_orani
            FROM siparis_urunleri su
            LEFT JOIN malzemeler m ON su.malzeme_id = m.id
            LEFT JOIN kdv_oranlari k ON m.kdv_id = k.id
            WHERE su.siparis_id = ?
        ");
        $stmt->execute([$siparis['id']]);
        $siparis['urunler'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Faturası var mı?
        $fatura = $db->query("SELECT * FROM faturalar WHERE siparis_id = {$siparis['id']}")->fetch(PDO::FETCH_ASSOC);
        $siparis['fatura'] = $fatura;
    }
    return $siparisler;
}

// Faturayı ekle
function faturaEkle($siparis_id, $file, $personel_id) {
    // Ana dizindeki 'faturalar' klasörü
    $hedef_klasor = __DIR__ . '/../faturalar/';
    if (!is_dir($hedef_klasor)) mkdir($hedef_klasor, 0777, true);

    $izinli_uzantilar = ['jpg','jpeg','png','pdf'];
    $max_boyut = 8 * 1024 * 1024; // 8 MB

    $dosya_adi = $file['name'];
    $uzanti = strtolower(pathinfo($dosya_adi, PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) return "Dosya yükleme hatası!";
    if (!in_array($uzanti, $izinli_uzantilar)) return "Dosya formatı uygun değil!";
    if ($file['size'] > $max_boyut) return "Dosya boyutu çok büyük!";

    $yeni_ad = 'fatura_'.$siparis_id.'_'.uniqid().'.'.$uzanti;
    $hedef_yol = $hedef_klasor . $yeni_ad;
    $gosterilecek_yol = 'faturalar/'.$yeni_ad; // Veritabanına bu şekilde kaydedilecek

    if (!move_uploaded_file($file['tmp_name'], $hedef_yol)) return "Dosya sunucuya taşınamadı (move_uploaded_file hatası)!";

    global $db;
    $stmt = $db->prepare("INSERT INTO faturalar (siparis_id, dosya_yolu, yukleyen_id) VALUES (?, ?, ?)");
$stmt->execute([$siparis_id, $gosterilecek_yol, $personel_id]);

    return true;
}


function getYetkiliSayfaIdler($rol_id) {
    global $db;
    $stmt = $db->prepare("SELECT sayfa_id FROM roller_yetkiler WHERE rol_id = ? AND goruntule=1");
    $stmt->execute([$rol_id]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'sayfa_id');
}
function updateSifre($id, $yeni_sifre) {
    global $db;
    $hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE personeller SET sifre = ? WHERE id = ?");
    return $stmt->execute([$hash, $id]);
}
function getKullaniciById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM personeller WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
// functions.php içerisine ekle
function getTaleplerWithUrunler($db) {
    $talepler = $db->query("
        SELECT t.*, p.ad AS personel_adi
        FROM talepler t
        LEFT JOIN personeller p ON t.personel_id = p.id
        ORDER BY t.olusturma_tarihi DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($talepler as &$talep) {
        $talep_id = $talep['id'];
        $urunler = $db->prepare("
            SELECT tu.*, m.ad AS malzeme_adi, m.birim
            FROM talep_urunleri tu
            LEFT JOIN malzemeler m ON tu.malzeme_id = m.id
            WHERE tu.talep_id = ?
        ");
        $urunler->execute([$talep_id]);
        $talep['urunler'] = [];
        foreach ($urunler as $u) {
            $eksik = max(0, $u['istenen_miktar'] - $u['onaylanan_miktar']);
            $u['eksik_adet'] = $eksik;
            $talep['urunler'][] = $u;
        }
    }
    return $talepler;
}
function log_encrypt($data) {
    $key = LOG_SECRET_KEY;
    $iv = LOG_SECRET_IV;
    return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv));
}

function log_decrypt($data) {
    $key = LOG_SECRET_KEY;
    $iv = LOG_SECRET_IV;
    return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, 0, $iv);
}
function log_ekle($pdo, $personel_id, $islem_id, $islem_tipi, $aciklama = '', $islem_sonucu = null) {
    $ip_adresi = log_encrypt(getClientIp());
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
function getIslemId($ad) {
    global $db;
    $stmt = $db->prepare("SELECT id FROM islemler WHERE ad = ?");
    $stmt->execute([$ad]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['id'] : null;
}

function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Birden fazla IP olabilir, ilkini al
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}
function kategori_ekle($db, $kullanici_id, $kategori_adi) {
    $ekle = $db->prepare("INSERT INTO malzeme_tipleri (tip_adi) VALUES (?)");
    $ekle->execute([$kategori_adi]);
    $id = $db->lastInsertId();
    log_ekle($db, $kullanici_id, 41, 'kategori_ekle', "Kategori eklendi: $kategori_adi (ID:$id)", 'başarılı');
    return $id;
}

function kategori_guncelle($db, $kullanici_id, $kategori_id, $kategori_adi) {
    $guncelle = $db->prepare("UPDATE malzeme_tipleri SET tip_adi = ? WHERE id = ?");
    $guncelle->execute([$kategori_adi, $kategori_id]);
    log_ekle($db, $kullanici_id, 40, 'kategori_guncelle', "Kategori güncellendi: $kategori_adi (ID:$kategori_id)", 'başarılı');
}



?>
