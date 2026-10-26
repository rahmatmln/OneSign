<?php
/**
 * OneSign - Secure PDF Streamer
 * Mengalirkan berkas PDF asli ke penampil peramban (PDF.js) dengan proteksi otorisasi
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die('Akses ditolak: Silakan masuk ke akun Anda.');
}

$user  = getActiveUser();
$docId = (int)($_GET['doc_id'] ?? 0);

if ($docId <= 0) {
    http_response_code(400);
    die('Parameter dokumen tidak valid.');
}

$pdo = Database::getConnection();

// Mengambil data dokumen dan relasi penandatangan
$stmt = $pdo->prepare("
    SELECT d.id, d.title, d.confidentiality, d.original_filename, d.file_path, d.requester_id, ds.signer_id
    FROM documents d
    LEFT JOIN document_signers ds ON d.id = ds.document_id
    WHERE d.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Dokumen tidak ditemukan dalam repositori.');
}

// Otorisasi Akses: Hanya Pemohon, Penandatangan dokumen bersangkutan, atau Admin yang boleh membuka
$isRequester = ($user['id'] === (int)$doc['requester_id']);
$isSigner    = ($user['id'] === (int)$doc['signer_id']);
$isAdmin     = ($user['role'] === 'admin');

if (!$isRequester && !$isSigner && !$isAdmin) {
    http_response_code(403);
    die('Akses Ditolak: Anda tidak memiliki wewenang untuk melihat berkas ini.');
}

$filePath = $doc['file_path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    die('Berkas fisik PDF tidak ditemukan pada server.');
}

// Catat aktivitas melihat berkas ke Audit Log
writeAuditLog('PREVIEW_DOC', $docId, 'Membuka pratinjau berkas: ' . $doc['original_filename']);

// Kirim header dan alirkan konten PDF secara aman
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
