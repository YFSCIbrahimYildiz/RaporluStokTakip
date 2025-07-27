<?php if (session_status() == PHP_SESSION_NONE) session_start(); ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm rounded-bottom mb-4 py-2">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" href="dashboard.php" style="font-size:1.35rem;">
            <svg width="28" height="28" fill="currentColor" class="bi bi-box-seam" viewBox="0 0 16 16">
                <path d="M8.48.35a1.5 1.5 0 0 0-1.04 0l-5.5 2A1.5 1.5 0 0 0 1 3.76V12.5c0 .642.408 1.19 1.01 1.388l5.5 2a1.5 1.5 0 0 0 1.04 0l5.5-2A1.5 1.5 0 0 0 15 12.24V3.76c0-.642-.408-1.19-1.01-1.388l-5.5-2zM8 1.58l5.5 2V4.5L8 2.5V1.58zm-6 2 5.5-2V2.5L2 4.5V3.58zm0 1.81L7.74 6.2v7.57L2 13.03V5.39zm12 7.64-5.74 2.73V6.2l5.74-2.81v7.64z"/>
            </svg>
            Stok Takip Paneli
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPanel" aria-controls="navbarPanel" aria-expanded="false" aria-label="Menüyü Aç/Kapat">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarPanel">
            <ul class="navbar-nav mb-2 mb-lg-0 align-items-center gap-lg-2">
                <li class="nav-item">
                    <span class="fw-semibold text-dark px-2 py-1 rounded bg-light border d-inline-block">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION['ad'] ?? '') . " " . htmlspecialchars($_SESSION['soyad'] ?? '') ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a href="cikis.php" class="btn btn-outline-primary btn-sm ms-lg-3 mt-2 mt-lg-0">
                        <i class="bi bi-box-arrow-right me-1"></i> Çıkış
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- Bootstrap Icons CDN (navbar için ikonlar) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
