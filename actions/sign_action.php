<?php
/**
 * OneSign - Sign Action Handler
 * Memproses persetujuan (pembubuhan TTE & QR Code verifikasi) dan penolakan dokumen oleh Dosen
 * REVISI TERPADU: Posisi TTD ditentukan pemohon; verifikasi keaslian via QR Code (Stitch Institutional Modernism)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/pdf_helper.php';

requireRole(['penandatangan']);

$user   = getActiveUser();
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php');
    exit;
}

// 1. Validasi Token CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    die('Validasi keamanan (CSRF) gagal. Silakan ulangi aksi.');
}

$docId = (int)($_POST['doc_id'] ?? 0);
if ($docId <= 0) {
    die('Parameter dokumen tidak valid.');
}

$pdo = Database::getConnection();

// Mengambil data dokumen (termasuk posisi TTD pilihan pemohon) dan memastikan penugasan valid
$stmtCheck = $pdo->prepare("
    SELECT d.id, d.title, d.file_path, d.original_filename, d.requester_id,
           ds.id AS signer_entry_id, ds.status AS sign_status,
           ds.page_number, ds.pos_x, ds.pos_y, ds.box_width, ds.box_height
    FROM documents d
    JOIN document_signers ds ON d.id = ds.document_id
    WHERE d.id = :doc_id AND ds.signer_id = :signer_id
    LIMIT 1
");
$stmtCheck->execute([
    ':doc_id'    => $docId,
    ':signer_id' => $user['id']
]);
$docData = $stmtCheck->fetch();

if (!$docData) {
    die('Akses ditolak: Dokumen tidak ditemukan atau Anda tidak memiliki hak penandatanganan.');
}

if ($docData['sign_status'] !== 'menunggu') {
    die('Dokumen ini sudah pernah diproses sebelumnya dan statusnya terkunci.');
}

// Persetujuan Dokumen (Approve)
if ($action === 'approve') {
    // Posisi TTD diambil dari penentuan pemohon pada database
    $pageNum   = max(1, (int)($docData['page_number'] ?: 1));
    $posX      = (float)$docData['pos_x'];
    $posY      = (float)$docData['pos_y'];
    $boxWidth  = (float)($docData['box_width'] ?: 170);
    $boxHeight = (float)($docData['box_height'] ?: 80);
    $sigData   = $_POST['signature_data'] ?? '';

    if (empty($sigData)) {
        die('Goresan tanda tangan tidak ditemukan. Harap goreskan TTD pada kanvas.');
    }

    // Validasi ketat citra PNG base64 (format, batas ukuran 512 KB, dan magic bytes PNG)
    if (!preg_match('/^data:image\/png;base64,/', $sigData)) {
        die('Format citra tanda tangan tidak didukung. Gunakan format PNG.');
    }
    $rawBase64 = substr($sigData, strpos($sigData, ',') + 1);
    $data = base64_decode($rawBase64, true);
    if ($data === false || strlen($data) === 0 || strlen($data) > 512 * 1024 || strncmp($data, "\x89PNG\r\n\x1a\n", 8) !== 0) {
        die('Format data tanda tangan tidak valid atau melebihi batas ukuran.');
    }

    // Simpan Citra PNG Tanda Tangan ke Direktori uploads/signatures/
    $sigFileName = 'SIG_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.png';
    $sigFilePath = DIR_UPLOADS_SIGNATURES . $sigFileName;
    file_put_contents($sigFilePath, $data);

    // Generate token verifikasi dan tangkap IP penandatangan
    $verifyToken = bin2hex(random_bytes(16));
    $signerIp = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '127.0.0.1';
    // Ambil IP pertama jika X-Forwarded-For berisi chain
    if (str_contains($signerIp, ',')) {
        $signerIp = trim(explode(',', $signerIp)[0]);
    }

    // Generate gambar QR code verifikasi
    $verifyUrl = BASE_URL . '/views/verify.php?token=' . $verifyToken;
    $qrFileName = 'QR_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
    $qrFilePath = DIR_UPLOADS_SIGNATURES . $qrFileName;
    OneSignPdfEngine::generateQrCode($verifyUrl, $qrFilePath);

    try {
        $pdo->beginTransaction();

        $stmtUpdateSigner = $pdo->prepare("
            UPDATE document_signers 
            SET signature_type = 'canvas',
                signature_image_path = :sig_path,
                status = 'disetujui',
                signed_at = NOW(),
                verify_token = :verify_token,
                signer_ip = :signer_ip
            WHERE id = :signer_entry_id
        ");
        $stmtUpdateSigner->execute([
            ':sig_path'        => $sigFileName,
            ':verify_token'    => $verifyToken,
            ':signer_ip'       => $signerIp,
            ':signer_entry_id' => $docData['signer_entry_id']
        ]);

        // Injeksi tanda tangan fisik & QR code ke berkas PDF
        $sourceFilePath = resolveUploadPath(DIR_UPLOADS_ORIGINAL, $docData['file_path']);
        if (empty($sourceFilePath) || !file_exists($sourceFilePath)) {
            throw new RuntimeException('Berkas PDF sumber tidak ditemukan di server.');
        }

        $safeSignedName   = 'SIGNED_' . basename($sourceFilePath);
        $targetSignedPath = DIR_UPLOADS_SIGNED . $safeSignedName;

        $stamped = OneSignPdfEngine::stampSignature(
            $sourceFilePath,
            $targetSignedPath,
            $pageNum,
            $sigFilePath,
            $posX,
            $posY,
            $boxWidth,
            $boxHeight,
            $user['full_name'],
            $user['identifier_number'] ?? '',
            date('d/m/Y'),
            $qrFilePath
        );
        if (!$stamped || !file_exists($targetSignedPath) || filesize($targetSignedPath) === 0) {
            throw new RuntimeException('Gagal menempelkan tanda tangan dan QR code pada berkas PDF.');
        }

        $stmtUpdateDoc = $pdo->prepare("
            UPDATE documents 
            SET status = 'disetujui',
                signed_file_path = :signed_path
            WHERE id = :doc_id
        ");
        $stmtUpdateDoc->execute([
            ':signed_path' => $safeSignedName,
            ':doc_id'      => $docId
        ]);

        $pdo->commit();

        // Bersihkan berkas QR sementara (sudah tertempel di PDF)
        if (file_exists($qrFilePath)) {
            unlink($qrFilePath);
        }

        // 5. Catat aktivitas pengesahan ke Audit Trail
        writeAuditLog('SIGN_DOC', $docId, 'Mengesahkan dokumen pada halaman ' . $pageNum . ' di koordinat X:' . round($posX) . ', Y:' . round($posY) . ' (QR Verifikasi terinjeksi)');

        header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php?sign_success=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!empty($qrFilePath) && file_exists($qrFilePath)) {
            unlink($qrFilePath);
        }
        if (!empty($sigFilePath) && file_exists($sigFilePath)) {
            unlink($sigFilePath);
        }
        if (!empty($targetSignedPath) && file_exists($targetSignedPath)) {
            unlink($targetSignedPath);
        }
        error_log('Gagal mengesahkan dokumen ID #' . $docId . ': ' . $e->getMessage());
        die('Terjadi kendala pada sistem saat memproses pengesahan dokumen. Silakan coba beberapa saat lagi.');
    }
}

// Penolakan Dokumen (Reject)
if ($action === 'reject') {
    $rejectionNote = trim($_POST['rejection_note'] ?? '');

    if (empty($rejectionNote)) {
        die('Alasan penolakan dokumen wajib dicantumkan.');
    }

    try {
        $pdo->beginTransaction();

        // 1. Perbarui status document_signers menjadi 'ditolak'
        $stmtRejectSigner = $pdo->prepare("
            UPDATE document_signers 
            SET status = 'ditolak',
                rejection_note = :note,
                signed_at = NOW()
            WHERE id = :signer_entry_id
        ");
        $stmtRejectSigner->execute([
            ':note'            => $rejectionNote,
            ':signer_entry_id' => $docData['signer_entry_id']
        ]);

        $stmtRejectDoc = $pdo->prepare("UPDATE documents SET status = 'ditolak' WHERE id = :doc_id");
        $stmtRejectDoc->execute([':doc_id' => $docId]);

        $pdo->commit();

        // 4. Catat aktivitas penolakan ke Audit Trail
        writeAuditLog('REJECT_DOC', $docId, 'Menolak dokumen dengan catatan: "' . $rejectionNote . '"');

        header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php?reject_success=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Gagal memproses penolakan dokumen: ' . $e->getMessage());
        die('Terjadi kesalahan basis data saat memproses penolakan dokumen.');
    }
}

header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php');
exit;
