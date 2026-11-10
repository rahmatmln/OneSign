<?php
/**
 * OneSign - Global Constants
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

// URL Dasar Aplikasi di Localhost
define('BASE_URL', 'https://currencies-violations-icon-html.trycloudflare.com');

// Batasan Pengunggahan Berkas PDF (10 MB dalam bytes)
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// Direktori Penyimpanan Berkas
define('DIR_UPLOADS_ORIGINAL', __DIR__ . '/../uploads/originals/');
define('DIR_UPLOADS_SIGNED', __DIR__ . '/../uploads/signed/');
define('DIR_UPLOADS_SIGNATURES', __DIR__ . '/../uploads/signatures/');

// Zona Waktu Sistem
date_default_timezone_set('Asia/Jakarta');
