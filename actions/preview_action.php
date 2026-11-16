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

// Penegakan Aturan Bisnis BR-04: Akses Dokumen 'Rahasia'
if ($doc['confidentiality'] === 'rahasia') {
    $isRequester = ($user['id'] === (int)$doc['requester_id']);
    $isSigner    = ($user['id'] === (int)$doc['signer_id']);
    $isAdmin     = ($user['role'] === 'admin');

    if (!$isRequester && !$isSigner && !$isAdmin) {
        http_response_code(403);
        die('Akses Ditolak: Dokumen berstatus RAHASIA hanya dapat dilihat oleh Pemohon, Penandatangan, dan Staf Administrasi yang sah.');
    }
}

// Menentukan berkas target untuk ditampilkan
$targetFile = null;

if ($doc['status'] === 'disetujui') {
    $signedPath = $doc['signed_file_path'];
    $safeSignedName = 'SIGNED_' . basename($doc['file_path']);
    $targetSignedPath = DIR_UPLOADS_SIGNED . $safeSignedName;

    // Periksa keabsahan berkas bertanda tangan fisik yang ada
    $isSignedValid = false;
    if (!empty($signedPath) && file_exists($signedPath) && filesize($signedPath) > 0) {
        try {
            $tester = new OneSignPdfEngine('P', 'pt');
            $tester->setSourceFile($signedPath);
            $isSignedValid = true;
        } catch (\Throwable $e) {
            $isSignedValid = false;
        }
    }

    // Jika file bertanda tangan belum ada di disk atau tidak valid, hasilkan ulang stempel lengkap beserta QR Code
    if (!$isSignedValid && !empty($doc['signature_image_path']) && file_exists($doc['signature_image_path'])) {
        $qrFilePath = '';
        if (!empty($doc['verify_token'])) {
            $verifyUrl = BASE_URL . '/views/verify.php?token=' . $doc['verify_token'];
            $qrFileName = 'QR_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
            $qrFilePath = DIR_UPLOADS_SIGNATURES . $qrFileName;
            OneSignPdfEngine::generateQrCode($verifyUrl, $qrFilePath);
        }

        $signedDateFormatted = !empty($doc['signed_at']) ? date('d/m/Y', strtotime($doc['signed_at'])) : date('d/m/Y');
        $stamped = OneSignPdfEngine::stampSignature(
            $doc['file_path'],
            $targetSignedPath,
            (int)$doc['page_number'],
            $doc['signature_image_path'],
            (float)$doc['pos_x'],
            (float)$doc['pos_y'],
            (float)$doc['box_width'],
            (float)$doc['box_height'],
            $doc['signer_name'] ?? '',
            $doc['signer_nip'] ?? '',
            $signedDateFormatted,
            $qrFilePath
        );

        if (!empty($qrFilePath) && file_exists($qrFilePath)) {
            unlink($qrFilePath);
        }

        if ($stamped) {
            $signedPath = $targetSignedPath;
            try {
                $stmtUpd = $pdo->prepare("UPDATE documents SET signed_file_path = :path WHERE id = :id");
                $stmtUpd->execute([':path' => $targetSignedPath, ':id' => $docId]);
            } catch (\Throwable $e) {}
        }
    }

    if (!empty($signedPath) && file_exists($signedPath)) {
        $targetFile = $signedPath;
    }
}

// Jika belum disetujui atau file bertanda tangan belum ada, tampilkan naskah asli
if (empty($targetFile) && !empty($doc['file_path']) && file_exists($doc['file_path'])) {
    $targetFile = $doc['file_path'];
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

// Alirkan Berkas untuk Pratinjau di Peramban (Inline)
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $previewFilename . '"');
header('Content-Length: ' . filesize($targetFile));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($targetFile);
exit;
