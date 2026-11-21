<?php
/**
 * OneSign - Front Controller & Entry Router
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

// Router PHP built-in server: blokir direktori unggahan dan berkas sensitif sebelum menyajikan file statis
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    if (preg_match('#^/(uploads/|\.)|\.(sql|md|txt|json|lock|htaccess)$#i', $path)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
        return false;
    }
}

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';

// Jika belum masuk, arahkan ke form login
if (!isAuthenticated()) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

// Jika sudah masuk, arahkan ke dashboard peran masing-masing
$role = $_SESSION['user_role'] ?? '';
switch ($role) {
    case 'pemohon':
        header('Location: ' . BASE_URL . '/views/pemohon/dashboard.php');
        exit;
    case 'penandatangan':
        header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php');
        exit;
    case 'admin':
        header('Location: ' . BASE_URL . '/views/admin/dashboard.php');
        exit;
    default:
        // Sesi tidak valid, bersihkan dan minta masuk ulang
        header('Location: ' . BASE_URL . '/actions/auth_action.php?action=logout');
        exit;
}
