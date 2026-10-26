<?php
/**
 * OneSign - Authentication & Session Helper
 * Mengelola sesi, otorisasi peran (RBAC), CSRF, dan Audit Log
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Memeriksa apakah pengguna telah berhasil masuk
 */
function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Mengambil data pengguna aktif dari sesi
 */
function getActiveUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }
    return [
        'id'                => $_SESSION['user_id'],
        'full_name'         => $_SESSION['user_name'],
        'email'             => $_SESSION['user_email'],
        'role'              => $_SESSION['user_role'],
        'identifier_number' => $_SESSION['user_identifier'] ?? '',
        'department'        => $_SESSION['user_department'] ?? ''
    ];
}

/**
 * Memastikan pengguna memiliki peran yang diizinkan untuk mengakses halaman
 */
function requireRole(array $allowedRoles): void {
    if (!isAuthenticated()) {
        header('Location: ' . BASE_URL . '/views/auth/login.php');
        exit;
    }

    $currentRole = $_SESSION['user_role'] ?? '';
    if (!in_array($currentRole, $allowedRoles, true)) {
        http_response_code(403);
        die('Akses Ditolak: Anda tidak memiliki wewenang untuk membuka halaman ini.');
    }
}

/**
 * Menghasilkan token CSRF untuk form mutasi data
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Memvalidasi token CSRF pada permintaan POST
 */
function validateCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Menuliskan rekam jejak aksi ke tabel audit_logs
 */
function writeAuditLog(string $action, ?int $documentId = null, ?string $details = null): void {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, document_id, action, details, ip_address, user_agent)
            VALUES (:user_id, :document_id, :action, :details, :ip_address, :user_agent)
        ");

        $userId    = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmt->execute([
            ':user_id'     => $userId,
            ':document_id' => $documentId,
            ':action'      => $action,
            ':details'     => $details,
            ':ip_address'  => $ipAddress,
            ':user_agent'  => substr($userAgent, 0, 255)
        ]);
    } catch (Exception $e) {
        // Log kesalahan internal tanpa menghentikan aliran utama pengguna
        error_log('Gagal mencatat audit log: ' . $e->getMessage());
    }
}
