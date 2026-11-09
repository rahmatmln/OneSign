<?php
/**
 * OneSign - Sign Action Handler
 * Memproses persetujuan (pembubuhan TTE) dan penolakan dokumen oleh Dosen
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

// Mengambil data dokumen (termasuk posisi TTD dari pemohon) dan memastikan penugasan valid
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

// ==========================================
// KASUS A: PERSETUJUAN DOKUMEN (APPROVE)
// ==========================================
if ($action === 'approve') {
    // Posisi TTD diambil dari database (ditentukan pemohon), bukan dari $_POST
    $pageNum   = (int)$docData['page_number'];
    $posX      = (float)$docData['pos_x'];
    $posY      = (float)$docData['pos_y'];
    $boxWidth  = (float)$docData['box_width'];
    $boxHeight = (float)$docData['box_height'];
    $sigData   = $_POST['signature_data'] ?? '';

    if (empty($sigData)) {
        die('Goresan tanda tangan tidak ditemukan. Harap goreskan TTD pada kanvas.');
    }

    // Dekode Citra Base64 PNG
    if (preg_match('/^data:image\/(\w+);base64,/', $sigData, $type)) {
        $data = substr($sigData, strpos($sigData, ',') + 1);
        $data = base64_decode($data);
        if ($data === false) {
            die('Format data tanda tangan tidak valid.');
        }
    } else {
        die('Format citra tanda tangan tidak didukung.');
    }

    // Simpan Citra PNG Tanda Tangan ke Direktori uploads/signatures/
    $sigFileName = 'SIG_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.png';
    $sigFilePath = DIR_UPLOADS_SIGNATURES . $sigFileName;
    file_put_contents($sigFilePath, $data);

    try {
        $pdo->beginTransaction();

        // 1. Perbarui data document_signers (posisi tidak ditimpa)
        $stmtUpdateSigner = $pdo->prepare("
            UPDATE document_signers
            SET signature_type = 'canvas',
                signature_image_path = :sig_path,
                status = 'disetujui',
                signed_at = NOW()
            WHERE id = :signer_entry_id
        ");
        $stmtUpdateSigner->execute([
            ':sig_path'        => $sigFilePath,
            ':signer_entry_id' => $docData['signer_entry_id']
        ]);

        // 2. Lakukan injeksi tanda tangan fisik ke berkas PDF
        $safeSignedName   = 'SIGNED_' . basename($docData['file_path']);
        $targetSignedPath = DIR_UPLOADS_SIGNED . $safeSignedName;

        OneSignPdfEngine::stampSignature(
            $docData['file_path'],
            $targetSignedPath,
            $pageNum,
            $sigFilePath,
            $posX,
            $posY,
            $boxWidth,
            $boxHeight,
            $user['full_name'],
            $user['identifier_number'] ?? '',
            date('d/m/Y')
        );

        // 3. Perbarui status master dokumen dan path signed PDF
        $stmtUpdateDoc = $pdo->prepare("
            UPDATE documents 
            SET status = 'disetujui',
                signed_file_path = :signed_path
            WHERE id = :doc_id
        ");
        $stmtUpdateDoc->execute([
            ':signed_path' => $targetSignedPath,
            ':doc_id'      => $docId
        ]);

        // 4. Kirim notifikasi in-app kepada mahasiswa pemohon
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, document_id, title, message, is_read)
            VALUES (:user_id, :doc_id, :title, :message, 0)
        ");
        $stmtNotif->execute([
            ':user_id' => $docData['requester_id'],
            ':doc_id'  => $docId,
            ':title'   => 'Dokumen Berhasil Disahkan',
            ':message' => 'Dokumen "' . $docData['title'] . '" telah disetujui dan ditandatangani oleh ' . $user['full_name'] . '.'
        ]);

        $pdo->commit();

        // 5. Catat aktivitas pengesahan ke Audit Trail
        writeAuditLog('SIGN_DOC', $docId, 'Mengesahkan dokumen pada halaman ' . $pageNum . ' di koordinat X:' . round($posX) . ', Y:' . round($posY));

        header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php?sign_success=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Gagal mengesahkan dokumen: ' . $e->getMessage());
        die('Terjadi kesalahan basis data saat mengesahkan dokumen.');
    }
}

// ==========================================
// KASUS B: PENOLAKAN DOKUMEN (REJECT)
// ==========================================
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

        // 2. Perbarui status dokumen menjadi 'ditolak'
        $stmtRejectDoc = $pdo->prepare("UPDATE documents SET status = 'ditolak' WHERE id = :doc_id");
        $stmtRejectDoc->execute([':doc_id' => $docId]);

        // 3. Kirim notifikasi in-app kepada mahasiswa pemohon
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, document_id, title, message, is_read)
            VALUES (:user_id, :doc_id, :title, :message, 0)
        ");
        $stmtNotif->execute([
            ':user_id' => $docData['requester_id'],
            ':doc_id'  => $docId,
            ':title'   => 'Permohonan Dokumen Ditolak',
            ':message' => 'Dokumen "' . $docData['title'] . '" ditolak oleh ' . $user['full_name'] . '. Catatan: "' . $rejectionNote . '".'
        ]);

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