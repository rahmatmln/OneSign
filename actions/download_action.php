<?php
/**
 * OneSign - Download Action Handler
 * Mengalirkan berkas PDF yang telah disahkan dengan Dynamic Security Watermarking (FR-04)
 * Menegakkan aturan bisnis BR-03 (Mandatory Watermark) dan BR-04 (Akses Dokumen Rahasia)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/pdf_helper.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die('Akses Ditolak: Anda wajib masuk ke akun terlebih dahulu.');
}

$user  = getActiveUser();
$docId = (int)($_GET['doc_id'] ?? $_GET['id'] ?? 0);

if ($docId <= 0) {
    http_response_code(400);
    die('Parameter berkas tidak valid.');
}

$pdo = Database::getConnection();

// Mengambil data dokumen lengkap beserta tanda tangan dan identitas penandatangan
$stmt = $pdo->prepare("
    SELECT 
        d.id, d.title, d.category, d.confidentiality, d.original_filename, 
        d.file_path, d.signed_file_path, d.status, d.requester_id,
        ds.signer_id, ds.page_number, ds.pos_x, ds.pos_y, ds.box_width, ds.box_height, 
        ds.signature_image_path, ds.signed_at, ds.verify_token,
        u_sign.full_name AS signer_name, u_sign.identifier_number AS signer_nip
    FROM documents d
    LEFT JOIN document_signers ds ON d.id = ds.document_id
    LEFT JOIN users u_sign ON ds.signer_id = u_sign.id
    WHERE d.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Dokumen tidak ditemukan dalam repositori.');
}

// 1. Validasi Status Dokumen
if ($doc['status'] !== 'disetujui') {
    http_response_code(403);
    die('Akses Dibatasi: Dokumen belum disahkan secara elektronik oleh dosen penandatangan.');
}

// 2. Penegakan Aturan Bisnis BR-04: Akses Dokumen 'Rahasia'
if ($doc['confidentiality'] === 'rahasia') {
    $isRequester = ($user['id'] === (int)$doc['requester_id']);
    $isSigner    = ($user['id'] === (int)$doc['signer_id']);
    $isAdmin     = ($user['role'] === 'admin');

    if (!$isRequester && !$isSigner && !$isAdmin) {
        http_response_code(403);
        die('Akses Ditolak: Dokumen berstatus RAHASIA hanya dapat diunduh oleh Pemohon, Penandatangan, dan Staf Administrasi yang sah.');
    }
}

// Pastikan Berkas Fisik Hasil Stempel TTD Siap
$signedPath = OneSignPdfEngine::resolveSignedDocumentPath($doc, $pdo);

// 4. Catat Peristiwa Pengunduhan ke Audit Trail (FR-06)
writeAuditLog('DOWNLOAD_DOC', $docId, 'Mengunduh berkas. Identitas: ' . $user['full_name'] . ' (' . $user['role'] . ')');

// 5. Susun Nama Berkas Unduhan Resmi
$cleanTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc['title']);
$downloadFilename = 'OneSign_' . strtoupper($doc['confidentiality']) . '_' . $cleanTitle . '.pdf';

// 6. Alirkan Berkas Bersih Resmi (Tanda Tangan & QR Code tanpa watermark diagonal)
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Content-Length: ' . filesize($signedPath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($signedPath);
exit;

