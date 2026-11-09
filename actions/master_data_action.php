<?php
/**
 * OneSign - Master Data Action Handler (CRUD Kategori & Klasifikasi Dokumen)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/views/admin/master_data.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Validasi keamanan (CSRF) gagal. Silakan coba kembali.';
    header('Location: ' . BASE_URL . '/views/admin/master_data.php');
    exit;
}

$action = trim($_POST['action'] ?? '');
$pdo = Database::getConnection();

function respondOrRedirect(bool $success, string $message, string $redirectUrl, string $tab = 'kategori'): void {
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
    $url = $redirectUrl . ($tab !== 'kategori' ? '?tab=' . urlencode($tab) : '');
    header('Location: ' . $url);
    exit;
}

$redirectUrl = BASE_URL . '/views/admin/master_data.php';

try {
    switch ($action) {
        case 'create_category':
            $name                  = trim($_POST['name'] ?? '');
            $code                  = strtoupper(trim($_POST['code'] ?? ''));
            $description           = trim($_POST['description'] ?? '');
            $defaultClassification = trim($_POST['default_classification'] ?? 'internal');
            $iconName              = trim($_POST['icon_name'] ?? 'description');
            $isActive              = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name) || empty($code)) {
                respondOrRedirect(false, 'Nama kategori naskah dan kode identifier unik wajib diisi.', $redirectUrl, 'kategori');
            }

            $allowedClassifications = ['publik', 'internal', 'rahasia'];
            if (!in_array($defaultClassification, $allowedClassifications, true)) {
                $defaultClassification = 'internal';
            }

            // Periksa keunikan kode
            $stmtCheck = $pdo->prepare("SELECT id FROM document_categories WHERE code = :code LIMIT 1");
            $stmtCheck->execute([':code' => $code]);
            if ($stmtCheck->fetch()) {
                respondOrRedirect(false, "Kode identifier '{$code}' sudah digunakan pada kategori lain.", $redirectUrl, 'kategori');
            }

            $stmtInsert = $pdo->prepare("
                INSERT INTO document_categories (name, code, description, default_classification, icon_name, is_active)
                VALUES (:name, :code, :description, :default_classification, :icon_name, :is_active)
            ");
            $stmtInsert->execute([
                ':name'                   => $name,
                ':code'                   => $code,
                ':description'            => $description,
                ':default_classification' => $defaultClassification,
                ':icon_name'              => $iconName ?: 'description',
                ':is_active'              => $isActive
            ]);

            writeAuditLog('CREATE_CATEGORY', null, "Menambahkan kategori dokumen baru: {$name} [{$code}]");
            respondOrRedirect(true, "Kategori dokumen '{$name}' berhasil ditambahkan ke katalog master data.", $redirectUrl, 'kategori');
            break;

        case 'edit_category':
            $categoryId            = (int)($_POST['category_id'] ?? 0);
            $name                  = trim($_POST['name'] ?? '');
            $code                  = strtoupper(trim($_POST['code'] ?? ''));
            $description           = trim($_POST['description'] ?? '');
            $defaultClassification = trim($_POST['default_classification'] ?? 'internal');
            $iconName              = trim($_POST['icon_name'] ?? 'description');

            if ($categoryId <= 0 || empty($name) || empty($code)) {
                respondOrRedirect(false, 'Data kategori naskah tidak lengkap untuk diperbarui.', $redirectUrl, 'kategori');
            }

            $allowedClassifications = ['publik', 'internal', 'rahasia'];
            if (!in_array($defaultClassification, $allowedClassifications, true)) {
                $defaultClassification = 'internal';
            }

            // Periksa keunikan kode selain kategori ini
            $stmtCheck = $pdo->prepare("SELECT id FROM document_categories WHERE code = :code AND id != :id LIMIT 1");
            $stmtCheck->execute([':code' => $code, ':id' => $categoryId]);
            if ($stmtCheck->fetch()) {
                respondOrRedirect(false, "Kode identifier '{$code}' sudah digunakan oleh kategori lain.", $redirectUrl, 'kategori');
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE document_categories
                SET name = :name,
                    code = :code,
                    description = :description,
                    default_classification = :default_classification,
                    icon_name = :icon_name
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':name'                   => $name,
                ':code'                   => $code,
                ':description'            => $description,
                ':default_classification' => $defaultClassification,
                ':icon_name'              => $iconName ?: 'description',
                ':id'                     => $categoryId
            ]);

            writeAuditLog('UPDATE_CATEGORY', null, "Memperbarui kategori dokumen ID #{$categoryId}: {$name}");
            respondOrRedirect(true, "Kategori dokumen '{$name}' berhasil diperbarui.", $redirectUrl, 'kategori');
            break;

        case 'toggle_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            if ($categoryId <= 0) {
                respondOrRedirect(false, 'ID kategori tidak valid.', $redirectUrl, 'kategori');
            }

            $stmtFind = $pdo->prepare("SELECT name, is_active FROM document_categories WHERE id = :id LIMIT 1");
            $stmtFind->execute([':id' => $categoryId]);
            $category = $stmtFind->fetch();

            if (!$category) {
                respondOrRedirect(false, 'Kategori tidak ditemukan.', $redirectUrl, 'kategori');
            }

            $newStatus = ($category['is_active'] == 1) ? 0 : 1;
            $stmtToggle = $pdo->prepare("UPDATE document_categories SET is_active = :status WHERE id = :id");
            $stmtToggle->execute([':status' => $newStatus, ':id' => $categoryId]);

            $statusText = ($newStatus === 1) ? 'diaktifkan kembali' : 'dinonaktifkan (disembunyikan dari formulir pengajuan)';
            writeAuditLog('TOGGLE_CATEGORY_STATUS', null, "Mengubah status kategori ID #{$categoryId} ({$category['name']}) menjadi {$statusText}");

            respondOrRedirect(true, "Kategori '{$category['name']}' berhasil {$statusText}.", $redirectUrl, 'kategori');
            break;

        case 'toggle_classification':
            $classificationId = (int)($_POST['classification_id'] ?? 0);
            if ($classificationId <= 0) {
                respondOrRedirect(false, 'ID klasifikasi tidak valid.', $redirectUrl, 'klasifikasi');
            }

            $stmtFind = $pdo->prepare("SELECT name, is_active FROM document_classifications WHERE id = :id LIMIT 1");
            $stmtFind->execute([':id' => $classificationId]);
            $classItem = $stmtFind->fetch();

            if (!$classItem) {
                respondOrRedirect(false, 'Kebijakan klasifikasi tidak ditemukan.', $redirectUrl, 'klasifikasi');
            }

            $newStatus = ($classItem['is_active'] == 1) ? 0 : 1;
            $stmtToggle = $pdo->prepare("UPDATE document_classifications SET is_active = :status WHERE id = :id");
            $stmtToggle->execute([':status' => $newStatus, ':id' => $classificationId]);

            $statusText = ($newStatus === 1) ? 'diaktifkan' : 'dinonaktifkan';
            writeAuditLog('TOGGLE_CLASSIFICATION_STATUS', null, "Mengubah status klasifikasi ID #{$classificationId} ({$classItem['name']}) menjadi {$statusText}");

            respondOrRedirect(true, "Kebijakan '{$classItem['name']}' berhasil {$statusText}.", $redirectUrl, 'klasifikasi');
            break;

        default:
            respondOrRedirect(false, 'Aksi master data tidak dikenali.', $redirectUrl, 'kategori');
            break;
    }
} catch (Exception $e) {
    error_log('Master data action error: ' . $e->getMessage());
    respondOrRedirect(false, 'Terjadi kesalahan sistem saat memproses master data: ' . $e->getMessage(), $redirectUrl, 'kategori');
}
