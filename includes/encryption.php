<?php

define('ENCRYPTION_KEY', 'SeninBuradaÇokGüçlü&BenzersizBirAnahtarınOlsun!'); // EN AZ 32 karakter!

function sifrele($veri) {
    if (empty($veri)) return '';
    $key = 'ANAHTARINIZ123456'; // 16 karakter, sadece sizin bildiğiniz bir şey!
    $iv = openssl_random_pseudo_bytes(16);
    $crypted = openssl_encrypt($veri, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $crypted);
}

function coz($veri) {
    if (empty($veri)) return '';
    $key = 'ANAHTARINIZ123456'; // Yukarıdaki ile aynı olmalı!
    $decoded = base64_decode($veri, true);
    if ($decoded === false || strlen($decoded) < 17) return '';
    $iv = substr($decoded, 0, 16);
    $data = substr($decoded, 16);
    $acik = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $acik !== false ? $acik : '';
}

function coz_akilli($veri) {
    if (empty($veri)) return '';
    $key = 'ANAHTARINIZ123456';
    $decoded = base64_decode($veri, true);
    // Eğer sadece base64 yapılmışsa ve sonuç yine base64 ise, tekrar decode et
    if ($decoded !== false && base64_encode($decoded) === $veri) {
        $ikincisi = base64_decode($decoded, true);
        if ($ikincisi !== false && preg_match('//u', $ikincisi)) {
            return $ikincisi;
        }
        return $decoded;
    }
    if ($decoded !== false && strlen($decoded) > 16) {
        $iv = substr($decoded, 0, 16);
        $data = substr($decoded, 16);
        $acik = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($acik !== false && $acik !== "") return $acik;
    }
    if ($decoded !== false && strlen($decoded) > 0 && preg_match('//u', $decoded)) {
        return $decoded;
    }
    return $veri;
}

function coz_akilli_gelişmiş($veri) {
    if (empty($veri)) return '';
    $key = 'ANAHTARINIZ123456';
    // Eğer veri normal metinse, base64 değilse doğrudan döndür.
    if (preg_match('//u', $veri) && !preg_match('/[^A-Za-z0-9\+\/\=]/', $veri)) {
        $decoded = base64_decode($veri, true);
        if ($decoded !== false && $decoded !== '') {
            // Çözüm denemesi: önce openssl
            if (strlen($decoded) > 16) {
                $iv = substr($decoded, 0, 16);
                $data = substr($decoded, 16);
                $acik = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
                if ($acik !== false && $acik !== "") return $acik;
            }
            // Yoksa doğrudan metin olarak döndür
            if (preg_match('//u', $decoded)) {
                return $decoded;
            }
        }
    }
    // Eğer hala çözülemediyse ve base64 değilse direkt döndür
    return $veri;
}

?>
