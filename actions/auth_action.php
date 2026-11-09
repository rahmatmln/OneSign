<?php
/**
 * OneSign - Authentication Action Handler
 * Memproses permintaan POST Login & Logout dengan aman
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$action = $_GET['action'] ?? '';

// ==========================================
// 1. PROSES LOGIN
// ==========================================
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validasi Token CSRF
    if (!validateCsrfToken($csrfToken)) {
        $_SESSION['login_error'] = 'Validasi sesi keamanan (CSRF) gagal. Silakan coba kembali.';
        header('Location: ' . BASE_URL . '/views/auth/login.php');
        exit;
    }

    // Validasi Masukan Dasar
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Alamat email dan kata sandi wajib diisi.';
        header('Location: ' . BASE_URL . '/views/auth/login.php');
        exit;
    }

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, role, identifier_number, department, is_active FROM users WHERE email = :email OR identifier_number = :nim LIMIT 1");
        $stmt->execute([
            ':email' => $email,
            ':nim'   => $email
        ]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Periksa Status Keaktifan Akun (Soft Delete)
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                $_SESSION['login_error'] = 'Akun Anda sedang dinonaktifkan oleh Administrator. Hubungi Unit TIK untuk bantuan.';
                header('Location: ' . BASE_URL . '/views/auth/login.php');
                exit;
            }

            // Regenerasi ID sesi untuk mencegah Session Fixation Attack
            session_regenerate_id(true);

            // Simpan Data Pengguna ke Sesi
            $_SESSION['user_id']         = $user['id'];
            $_SESSION['user_name']       = $user['full_name'];
            $_SESSION['user_email']      = $user['email'];
            $_SESSION['user_role']       = $user['role'];
            $_SESSION['user_identifier'] = $user['identifier_number'];
            $_SESSION['user_department'] = $user['department'];

            // Catat Keberhasilan Masuk ke Audit Log
            writeAuditLog('LOGIN', null, 'Pengguna berhasil masuk ke sistem dengan peran: ' . $user['role']);

            // Arahkan ke Dashboard Sesuai Peran
            switch ($user['role']) {
                case 'pemohon':
                    header('Location: ' . BASE_URL . '/views/pemohon/dashboard.php');
                    break;
                case 'penandatangan':
                    header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php');
                    break;
                case 'admin':
                    header('Location: ' . BASE_URL . '/views/admin/dashboard.php');
                    break;
                default:
                    header('Location: ' . BASE_URL . '/index.php');
                    break;
            }
            exit;
        } else {
            $_SESSION['login_error'] = 'Kombinasi email kampus atau kata sandi tidak valid.';
            header('Location: ' . BASE_URL . '/views/auth/login.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['login_error'] = 'Terjadi gangguan pada server. Silakan coba sesaat lagi.';
        header('Location: ' . BASE_URL . '/views/auth/login.php');
        exit;
    }
}

// ==========================================
// 2. PROSES LOGOUT
// ==========================================
if ($action === 'logout') {
    if (isAuthenticated()) {
        writeAuditLog('LOGOUT', null, 'Pengguna keluar dari sistem');
    }

    // Bersihkan seluruh data sesi
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    header('Location: ' . BASE_URL . '/views/auth/login.php?logged_out=1');
    exit;
}

// Arahkan ke beranda jika aksi tidak dikenal
header('Location: ' . BASE_URL . '/index.php');
exit;
