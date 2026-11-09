<?php
/**
 * OneSign - User Management Action Handler (CRUD Pengguna Admin)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/views/admin/users.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Validasi keamanan (CSRF) gagal. Silakan coba kembali.';
    header('Location: ' . BASE_URL . '/views/admin/users.php');
    exit;
}

$action = trim($_POST['action'] ?? '');
$pdo = Database::getConnection();

function respondOrRedirect(bool $success, string $message, string $redirectUrl): void {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    if ($success) {
        $_SESSION['flash_success'] = $message;
    } else {
        $_SESSION['flash_error'] = $message;
    }
    header('Location: ' . $redirectUrl);
    exit;
}

$redirectUrl = BASE_URL . '/views/admin/users.php';

try {
    switch ($action) {
        case 'create':
            $fullName         = trim($_POST['full_name'] ?? '');
            $email            = trim($_POST['email'] ?? '');
            $identifierNumber = trim($_POST['identifier_number'] ?? '');
            $role             = trim($_POST['role'] ?? '');
            $department       = trim($_POST['department'] ?? 'Teknik Informatika dan Komputer');
            $customPassword   = trim($_POST['password'] ?? '');

            if (empty($fullName) || empty($email) || empty($role)) {
                respondOrRedirect(false, 'Nama lengkap, email resmi, dan peran pengguna wajib diisi.', $redirectUrl);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respondOrRedirect(false, 'Format email tidak valid.', $redirectUrl);
            }

            $allowedRoles = ['pemohon', 'penandatangan', 'admin'];
            if (!in_array($role, $allowedRoles, true)) {
                respondOrRedirect(false, 'Peran pengguna tidak valid.', $redirectUrl);
            }

            // Periksa keunikan email
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmtCheck->execute([':email' => $email]);
            if ($stmtCheck->fetch()) {
                respondOrRedirect(false, 'Alamat email ' . htmlspecialchars($email) . ' sudah terdaftar pada sistem.', $redirectUrl);
            }

            // Password default: password123 jika tidak diisi
            $plainPassword = !empty($customPassword) ? $customPassword : 'password123';
            $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);

            $stmtInsert = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, identifier_number, department, is_active)
                VALUES (:full_name, :email, :password_hash, :role, :identifier_number, :department, 1)
            ");
            $stmtInsert->execute([
                ':full_name'         => $fullName,
                ':email'             => $email,
                ':password_hash'     => $passwordHash,
                ':role'              => $role,
                ':identifier_number' => $identifierNumber,
                ':department'        => $department
            ]);

            $newUserId = (int)$pdo->lastInsertId();
            $roleLabel = ($role === 'penandatangan') ? 'Dosen' : (($role === 'pemohon') ? 'Mahasiswa' : 'Admin');
            writeAuditLog('CREATE_USER', null, "Menambahkan akun baru: {$fullName} ({$roleLabel} - {$email})");

            respondOrRedirect(true, "Akun pengguna {$fullName} berhasil didaftarkan dengan kata sandi bawaan.", $redirectUrl);
            break;

        case 'edit':
            $userId           = (int)($_POST['user_id'] ?? 0);
            $fullName         = trim($_POST['full_name'] ?? '');
            $email            = trim($_POST['email'] ?? '');
            $identifierNumber = trim($_POST['identifier_number'] ?? '');
            $role             = trim($_POST['role'] ?? '');
            $department       = trim($_POST['department'] ?? 'Teknik Informatika dan Komputer');

            if ($userId <= 0 || empty($fullName) || empty($email) || empty($role)) {
                respondOrRedirect(false, 'Data pengguna untuk pembaruan tidak lengkap.', $redirectUrl);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respondOrRedirect(false, 'Format email tidak valid.', $redirectUrl);
            }

            $allowedRoles = ['pemohon', 'penandatangan', 'admin'];
            if (!in_array($role, $allowedRoles, true)) {
                respondOrRedirect(false, 'Peran pengguna tidak valid.', $redirectUrl);
            }

            // Periksa keunikan email selain akun yang diedit
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $stmtCheck->execute([':email' => $email, ':id' => $userId]);
            if ($stmtCheck->fetch()) {
                respondOrRedirect(false, 'Alamat email sudah digunakan oleh pengguna lain.', $redirectUrl);
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE users 
                SET full_name = :full_name,
                    email = :email,
                    role = :role,
                    identifier_number = :identifier_number,
                    department = :department
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':full_name'         => $fullName,
                ':email'             => $email,
                ':role'              => $role,
                ':identifier_number' => $identifierNumber,
                ':department'        => $department,
                ':id'                => $userId
            ]);

            writeAuditLog('UPDATE_USER', null, "Memperbarui data akun pengguna ID #{$userId}: {$fullName}");
            respondOrRedirect(true, "Data pengguna {$fullName} berhasil diperbarui.", $redirectUrl);
            break;

        case 'toggle_status':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                respondOrRedirect(false, 'ID pengguna tidak valid.', $redirectUrl);
            }

            // Cegah Admin menonaktifkan akun sendiri
            if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
                respondOrRedirect(false, 'Anda tidak dapat menonaktifkan akun administrator Anda sendiri.', $redirectUrl);
            }

            $stmtFind = $pdo->prepare("SELECT full_name, is_active FROM users WHERE id = :id LIMIT 1");
            $stmtFind->execute([':id' => $userId]);
            $targetUser = $stmtFind->fetch();

            if (!$targetUser) {
                respondOrRedirect(false, 'Pengguna tidak ditemukan.', $redirectUrl);
            }

            $newStatus = ($targetUser['is_active'] == 1) ? 0 : 1;
            $stmtToggle = $pdo->prepare("UPDATE users SET is_active = :new_status WHERE id = :id");
            $stmtToggle->execute([':new_status' => $newStatus, ':id' => $userId]);

            $statusText = ($newStatus === 1) ? 'diaktifkan kembali' : 'dinonaktifkan (akses dibekukan)';
            writeAuditLog('TOGGLE_USER_STATUS', null, "Mengubah status akun ID #{$userId} ({$targetUser['full_name']}) menjadi {$statusText}");

            respondOrRedirect(true, "Akun {$targetUser['full_name']} berhasil {$statusText}.", $redirectUrl);
            break;

        case 'reset_password':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                respondOrRedirect(false, 'ID pengguna tidak valid.', $redirectUrl);
            }

            $stmtFind = $pdo->prepare("SELECT full_name FROM users WHERE id = :id LIMIT 1");
            $stmtFind->execute([':id' => $userId]);
            $targetUser = $stmtFind->fetch();

            if (!$targetUser) {
                respondOrRedirect(false, 'Pengguna tidak ditemukan.', $redirectUrl);
            }

            $defaultHash = password_hash('password123', PASSWORD_BCRYPT);
            $stmtReset = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmtReset->execute([':hash' => $defaultHash, ':id' => $userId]);

            writeAuditLog('RESET_PASSWORD', null, "Mereset kata sandi akun ID #{$userId} ({$targetUser['full_name']}) ke standar password123");
            respondOrRedirect(true, "Kata sandi {$targetUser['full_name']} berhasil direset ke 'password123'.", $redirectUrl);
            break;

        default:
            respondOrRedirect(false, 'Aksi pengelolaan akun tidak dikenali.', $redirectUrl);
            break;
    }
} catch (Exception $e) {
    error_log('User action error: ' . $e->getMessage());
    respondOrRedirect(false, 'Terjadi kesalahan sistem saat memproses permintaan pengguna: ' . $e->getMessage(), $redirectUrl);
}
