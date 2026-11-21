<?php
/**
 * OneSign - Preview Action Handler
 * Menampilkan pratinjau (inline view) berkas PDF di tab peramban
 * Mendukung pratinjau naskah bertanda tangan sah atau draf asli
 * Menegakkan aturan akses kerahasiaan dokumen (BR-04)
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

// Batasi akses dokumen non-publik atau draf belum sah hanya untuk pemohon, penandatangan, dan admin
if ($doc['status'] !== 'disetujui' || $doc['confidentiality'] !== 'publik') {
    $isRequester = ((int)$user['id'] === (int)$doc['requester_id']);
    $isSigner    = ((int)$user['id'] === (int)$doc['signer_id']);
    $isAdmin     = ($user['role'] === 'admin');

    if (!$isRequester && !$isSigner && !$isAdmin) {
        http_response_code(403);
        die('Akses Ditolak: Anda tidak memiliki wewenang untuk melihat pratinjau berkas ini.');
    }
}

$targetFile = null;

if ($doc['status'] === 'disetujui') {
    $targetFile = OneSignPdfEngine::resolveSignedDocumentPath($doc, $pdo);
}

$origPath = resolveUploadPath(DIR_UPLOADS_ORIGINAL, $doc['file_path']);
if (empty($targetFile) && !empty($origPath) && file_exists($origPath)) {
    $targetFile = $origPath;
}

if (empty($targetFile) || !file_exists($targetFile)) {
    http_response_code(404);
    die('Berkas fisik dokumen tidak ditemukan di server.');
}

// Catat Peristiwa Pratinjau ke Audit Trail (FR-06)
writeAuditLog('PREVIEW_DOC', $docId, 'Membuka pratinjau dokumen: ' . $doc['title'] . '. Identitas: ' . $user['full_name'] . ' (' . $user['role'] . ')');

// Susun Nama Tampilan Inline
$cleanTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc['title']);
$statusPrefix = ($doc['status'] === 'disetujui') ? 'SAH' : 'DRAFT';
$previewFilename = 'OneSign_' . $statusPrefix . '_' . $cleanTitle . '.pdf';

// Alirkan Berkas untuk Pratinjau di Peramban dengan Security Watermark Halus (Screen Capture Protection)
OneSignPdfEngine::streamPreviewWithWatermark($targetFile, $user, $doc, $previewFilename);
exit;
