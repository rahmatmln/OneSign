<?php
/**
 * OneSign - Upload Action Handler
 * Memproses pengunggahan berkas PDF dan pendaftaran antrean penandatangan
 * REVISI: Posisi tanda tangan ditentukan oleh pemohon saat pengajuan berkas
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

requireRole(['pemohon']);

$user = getActiveUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// 1. Validasi Token CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['upload_error'] = 'Validasi keamanan (CSRF) gagal. Silakan ulangi pengunggahan.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// 2. Ambil dan Sanitasi Input Teks
$title           = trim($_POST['title'] ?? '');
$category        = trim($_POST['category'] ?? '');
$confidentiality = trim($_POST['confidentiality'] ?? 'internal');
$signerId        = (int)($_POST['signer_id'] ?? 0);

if (empty($title) || empty($category) || $signerId <= 0) {
    $_SESSION['upload_error'] = 'Seluruh kolom bertanda bintang (*) wajib diisi dengan benar.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// Validasi Pilihan Kerahasiaan
$allowedConfidentiality = ['publik', 'internal', 'rahasia'];
if (!in_array($confidentiality, $allowedConfidentiality, true)) {
    $confidentiality = 'internal';
}

// 2b. Posisi tanda tangan yang ditentukan oleh pemohon
$pageNum   = max(1, (int)($_POST['page_number'] ?? 1));
$posX      = (float)($_POST['pos_x'] ?? -1);
$posY      = (float)($_POST['pos_y'] ?? -1);
$boxWidth  = (float)($_POST['box_width'] ?? 0);
$boxHeight = (float)($_POST['box_height'] ?? 0);

if ($posX < 0 || $posY < 0 || $boxWidth < 50 || $boxHeight < 20) {
    $_SESSION['upload_error'] = 'Posisi tanda tangan belum ditentukan. Silakan atur posisi pada pratinjau dokumen.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

$pdo = Database::getConnection();

// 3. Validasi Akun Penandatangan Aktif di Basis Data
$stmtCheckSigner = $pdo->prepare("SELECT id, full_name FROM users WHERE id = :id AND role = 'penandatangan' AND is_active = 1 LIMIT 1");
$stmtCheckSigner->execute([':id' => $signerId]);
$signer = $stmtCheckSigner->fetch();

if (!$signer) {
    $_SESSION['upload_error'] = 'Dosen penandatangan yang dipilih tidak valid, tidak aktif, atau tidak terdaftar.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// 4. Validasi Berkas Unggahan PDF
if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = 'Gagal mengunggah berkas PDF. Pastikan koneksi stabil dan ukuran berkas tidak melebihi batas server.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

$fileTmpPath   = $_FILES['pdf_file']['tmp_name'];
$originalName  = basename($_FILES['pdf_file']['name']);
$fileSize      = (int)$_FILES['pdf_file']['size'];

// Cek Ukuran Berkas (Maksimal 10 MB)
if ($fileSize > MAX_UPLOAD_SIZE) {
    $_SESSION['upload_error'] = 'Ukuran berkas melebihi batas maksimal 10 MB.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// Validasi Ekstensi dan MIME Type Asli PDF
$fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($fileExt !== 'pdf') {
    $_SESSION['upload_error'] = 'Format berkas tidak diizinkan. Hanya berkas berekstensi .pdf yang diperbolehkan.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fileTmpPath);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    $_SESSION['upload_error'] = 'Format konten berkas tidak valid sebagai berkas PDF murni.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// Periksa Magic Bytes PDF (%PDF-)
$handle = fopen($fileTmpPath, 'rb');
$headerBytes = fread($handle, 5);
fclose($handle);

if (strpos($headerBytes, '%PDF-') !== 0) {
    $_SESSION['upload_error'] = 'Struktur berkas PDF rusak atau dimanipulasi.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// 5. Simpan Berkas Fisik ke Direktori uploads/originals/
$safePrefix = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
$cleanFileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName);
$storedFileName = $safePrefix . '_' . $cleanFileName;
$targetPath = DIR_UPLOADS_ORIGINAL . $storedFileName;

if (!move_uploaded_file($fileTmpPath, $targetPath)) {
    $_SESSION['upload_error'] = 'Terjadi kesalahan sistem saat menyimpan berkas ke server penyimpanan.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}

// 6. Transaksi Database Penyimpanan Metadata & Penugasan
try {
    $pdo->beginTransaction();

    // Simpan ke tabel documents
    $stmtDoc = $pdo->prepare("
        INSERT INTO documents (requester_id, title, category, confidentiality, original_filename, file_path, file_size, status)
        VALUES (:requester_id, :title, :category, :confidentiality, :original_filename, :file_path, :file_size, 'menunggu')
    ");
    $stmtDoc->execute([
        ':requester_id'       => $user['id'],
        ':title'              => $title,
        ':category'           => $category,
        ':confidentiality'    => $confidentiality,
        ':original_filename'  => $originalName,
        ':file_path'          => $targetPath,
        ':file_size'          => $fileSize
    ]);
    $docId = (int)$pdo->lastInsertId();

    // Simpan ke document_signers dengan posisi TTD pilihan pemohon
    $stmtSigner = $pdo->prepare("
        INSERT INTO document_signers (document_id, signer_id, page_number, pos_x, pos_y, box_width, box_height, status)
        VALUES (:document_id, :signer_id, :page_number, :pos_x, :pos_y, :box_width, :box_height, 'menunggu')
    ");
    $stmtSigner->execute([
        ':document_id' => $docId,
        ':signer_id'   => $signerId,
        ':page_number' => $pageNum,
        ':pos_x'       => $posX,
        ':pos_y'       => $posY,
        ':box_width'   => $boxWidth,
        ':box_height'  => $boxHeight
    ]);

    // Kirim notifikasi in-app ke penandatangan
    $stmtNotif = $pdo->prepare("
        INSERT INTO notifications (user_id, document_id, title, message, is_read)
        VALUES (:user_id, :document_id, :title, :message, 0)
    ");
    $notifTitle   = 'Permohonan Pengesahan Dokumen Baru';
    $notifMessage = $user['full_name'] . ' mengajukan permohonan tanda tangan untuk berkas: "' . $title . '".';
    $stmtNotif->execute([
        ':user_id'     => $signerId,
        ':document_id' => $docId,
        ':title'       => $notifTitle,
        ':message'     => $notifMessage
    ]);

    $pdo->commit();

    // 7. Catat ke Audit Log
    writeAuditLog('UPLOAD_DOC', $docId, 'Mengunggah naskah PDF: ' . $originalName . ' untuk ditandatangani oleh ' . $signer['full_name'] . ' (posisi TTD: hal. ' . $pageNum . ', X:' . round($posX) . ', Y:' . round($posY) . ')');

    // Arahkan ke dashboard dengan pesan sukses
    header('Location: ' . BASE_URL . '/views/pemohon/dashboard.php?upload_success=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Hapus file yang sempat terunggah jika transaksi DB gagal
    if (file_exists($targetPath)) {
        @unlink($targetPath);
    }
    error_log('Gagal menyimpan pengajuan dokumen: ' . $e->getMessage());
    $_SESSION['upload_error'] = 'Terjadi kesalahan basis data saat memproses pengajuan Anda.';
    header('Location: ' . BASE_URL . '/views/pemohon/upload.php');
    exit;
}
