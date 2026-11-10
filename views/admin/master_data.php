<?php
/**
 * OneSign - Master Data Dokumen (Admin)
 * Design Stitch: Institutional Trust Modernism (100% Identik)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Master Data Dokumen';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

requireRole(['admin']);

$activeUser = getActiveUser();
$pdo = Database::getConnection();

// Ambil notifikasi flash sesi
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Tab aktif dari query param (?tab=klasifikasi atau default ?tab=kategori)
$activeTab = $_GET['tab'] ?? 'kategori';
if (!in_array($activeTab, ['kategori', 'klasifikasi'], true)) {
    $activeTab = 'kategori';
}

// 1. Query Kategori beserta jumlah dokumen yang menggunakannya
$stmtCategories = $pdo->query("
    SELECT 
        c.id, c.name, c.code, c.description, c.default_classification, 
        c.icon_name, c.is_active, c.created_at,
        COUNT(d.id) AS total_docs
    FROM document_categories c
    LEFT JOIN documents d ON d.category = c.name
    GROUP BY c.id
    ORDER BY c.is_active DESC, c.name ASC
");
$categoriesList = $stmtCategories->fetchAll();

// 2. Query Klasifikasi Dokumen
$stmtClassifications = $pdo->query("
    SELECT id, name, code, badge_label, description, verification_level, tier_code, tier_level, is_active
    FROM document_classifications
    ORDER BY tier_level ASC
");
$classificationsList = $stmtClassifications->fetchAll();

// 3. Query Statistik Master Data
$activeCategoriesCount = 0;
foreach ($categoriesList as $cat) {
    if ($cat['is_active'] == 1) $activeCategoriesCount++;
}

$activeClassificationsCount = 0;
foreach ($classificationsList as $cls) {
    if ($cls['is_active'] == 1) $activeClassificationsCount++;
}

$totalDocsCountStmt = $pdo->query("SELECT COUNT(*) FROM documents");
$totalCatalogedDocs = (int)$totalDocsCountStmt->fetchColumn();

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- Header Section with Breadcrumb & Context -->
<div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
    <div class="flex flex-col gap-space-xs">
        <div class="flex items-center gap-space-xs text-secondary font-label-sm text-label-sm uppercase tracking-wider text-[11px]">
            <span>Portal Administrasi</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-primary font-semibold">Master Data Dokumen</span>
        </div>
        <h1 class="font-display-lg text-display-lg text-on-surface tracking-tight font-bold text-2xl md:text-3xl">
            Master Data &amp; Kebijakan Naskah
        </h1>
        <p class="font-body-md text-body-md text-secondary">
            Konfigurasi jenis berkas akademik, slug registri, dan tingkat kerahasiaan tanda tangan terpusat.
        </p>
    </div>
    
    <!-- Quick Action Utilities -->
    <div class="flex items-center gap-space-sm self-start md:self-auto">
        <button type="button" 
                id="btnOpenCategoryModal"
                class="inline-flex items-center gap-space-xs px-space-md py-2.5 bg-primary text-on-primary rounded-lg font-label-md text-label-md font-semibold shadow-sm hover:bg-primary-container transition-all">
            <span class="material-symbols-outlined text-[20px]">add_circle</span>
            <span>Tambah Kategori Naskah</span>
        </button>
    </div>
</div>

<!-- Flash Alerts -->
<?php if ($flashSuccess): ?>
    <div class="p-space-md rounded-xl bg-surface-container-lowest border border-outline-variant/30 flex items-center justify-between text-primary shadow-sm mb-space-md">
        <div class="flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-[20px] text-primary">check_circle</span>
            <span class="font-body-md text-body-md font-medium"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-secondary hover:text-on-surface">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="p-space-md rounded-xl bg-error-container/20 border border-error/20 flex items-center justify-between text-error shadow-sm mb-space-md">
        <div class="flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-[20px]">error</span>
            <span class="font-body-md text-body-md font-medium"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-error hover:opacity-80">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>
<?php endif; ?>

<!-- 4 Clean Metric Cards (Stitch Style - Sesuai Proposisi Riwayat & Audit Trail) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
    <!-- Card 1: Total Kategori -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Total Kategori Naskah</span>
                <span class="font-display-lg text-display-lg font-bold text-on-surface"><?= count($categoriesList) ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">folder_open</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Kategori Aktif -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Kategori Aktif</span>
                <span class="font-display-lg text-display-lg font-bold text-emerald-700"><?= $activeCategoriesCount ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                <span class="material-symbols-outlined text-[20px]">folder_special</span>
            </div>
        </div>
    </div>

    <!-- Card 3: Tingkat Klasifikasi -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Tingkat Klasifikasi</span>
                <span class="font-display-lg text-display-lg font-bold text-primary"><?= $activeClassificationsCount ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-secondary-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">shield</span>
            </div>
        </div>
    </div>

    <!-- Card 4: Dokumen Terkatalog -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Dokumen Terkatalog</span>
                <span class="font-display-lg text-display-lg font-bold text-amber-700"><?= $totalCatalogedDocs ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700">
                <span class="material-symbols-outlined text-[20px]">inventory_2</span>
            </div>
        </div>
    </div>
</div>

<!-- Segmented Tabs Navigation (Stitch Micro-interactions) -->
<div class="w-full max-w-md p-1 bg-surface-container rounded-xl flex items-center mb-space-md">
    <button type="button" 
            id="tabBtn1" 
            class="flex-1 flex items-center justify-center gap-2 py-2 px-space-md rounded-lg font-label-md text-label-md transition-all font-semibold <?= ($activeTab === 'kategori') ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' ?>">
        <span class="material-symbols-outlined text-[18px]">folder_open</span>
        <span>Kategori Dokumen</span>
        <span class="px-1.5 py-0.2 rounded-full bg-surface-container-high text-primary font-code-audit-sm text-[11px]"><?= count($categoriesList) ?></span>
    </button>
    <button type="button" 
            id="tabBtn2" 
            class="flex-1 flex items-center justify-center gap-2 py-2 px-space-md rounded-lg font-label-md text-label-md transition-all font-semibold <?= ($activeTab === 'klasifikasi') ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' ?>">
        <span class="material-symbols-outlined text-[18px]">security</span>
        <span>Klasifikasi Kerahasiaan</span>
        <span class="px-1.5 py-0.2 rounded-full bg-surface-container-high text-primary font-code-audit-sm text-[11px]"><?= count($classificationsList) ?></span>
    </button>
</div>

<!-- ======================================================== -->
<!-- TAB 1: KATEGORI DOKUMEN -->
<!-- ======================================================== -->
<div id="tabContent1" class="<?= ($activeTab === 'kategori') ? '' : 'hidden' ?> space-y-space-md">
    <!-- Filter Bar Kategori -->
    <div class="bg-surface-container-lowest rounded-xl p-space-md border border-outline-variant/20 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-space-sm">
        <div class="relative flex items-center flex-1 max-w-md">
            <span class="material-symbols-outlined absolute left-3 text-secondary text-[20px] pointer-events-none">search</span>
            <input type="text" 
                   id="searchCategoryInput" 
                   placeholder="Cari nama naskah atau kode slug..." 
                   class="w-full h-10 pl-10 pr-space-md bg-surface-container-low rounded-lg font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary transition-all">
        </div>
        <div class="flex items-center gap-2 text-secondary font-body-sm text-xs">
            <span class="material-symbols-outlined text-[16px]">info</span>
            <span>Kategori aktif otomatis muncul pada formulir pengajuan berkas pemohon.</span>
        </div>
    </div>

    <!-- Table Kategori -->
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/20 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="categoriesTable">
                <thead>
                    <tr class="border-b border-outline-variant/20 bg-surface-container-low/40 text-on-surface font-label-md text-label-md text-xs uppercase tracking-wider font-semibold">
                        <th class="py-3 px-space-md">Nama Kategori &amp; Identifikasi</th>
                        <th class="py-3 px-space-md">Deskripsi Naskah</th>
                        <th class="py-3 px-space-md">Klasifikasi Bawaan</th>
                        <th class="py-3 px-space-md">Statistik Berkas</th>
                        <th class="py-3 px-space-md">Status Sistem</th>
                        <th class="py-3 px-space-md text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10 text-on-surface font-body-sm text-body-sm">
                    <?php if (empty($categoriesList)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-secondary">
                                Belum ada kategori naskah yang dikonfigurasi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categoriesList as $cat): 
                            $isCatActive = ((int)$cat['is_active'] === 1);
                            $classBadge = match($cat['default_classification']) {
                                'publik'   => ['label' => 'Publik Terbuka', 'bg' => 'bg-tertiary-fixed text-on-tertiary-fixed'],
                                'internal' => ['label' => 'Internal Kampus', 'bg' => 'bg-secondary-fixed text-on-secondary-fixed'],
                                'rahasia'  => ['label' => 'Rahasia Terbatas', 'bg' => 'bg-primary-fixed text-on-primary-fixed'],
                                default    => ['label' => ucfirst($cat['default_classification']), 'bg' => 'bg-surface-container text-primary']
                            };
                            $icon = !empty($cat['icon_name']) ? $cat['icon_name'] : 'description';
                        ?>
                            <tr class="hover:bg-surface-container-low/60 transition-colors category-row"
                                data-search="<?= htmlspecialchars(strtolower($cat['name'] . ' ' . $cat['code'] . ' ' . ($cat['description'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                
                                <!-- Nama & Slug -->
                                <td class="py-3.5 px-space-md">
                                    <div class="flex items-center gap-space-sm">
                                        <div class="w-9 h-9 rounded-lg bg-surface-container-high flex items-center justify-center text-primary shrink-0">
                                            <span class="material-symbols-outlined text-[20px]"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-label-md text-label-md text-primary font-semibold">
                                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <span class="font-code-audit text-code-audit-sm text-secondary text-xs">
                                                <?= htmlspecialchars($cat['code'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Deskripsi -->
                                <td class="py-3.5 px-space-md text-on-surface-variant max-w-xs">
                                    <span class="line-clamp-2 text-xs"><?= htmlspecialchars($cat['description'] ?: 'Tidak ada deskripsi khusus.', ENT_QUOTES, 'UTF-8') ?></span>
                                </td>

                                <!-- Klasifikasi Bawaan -->
                                <td class="py-3.5 px-space-md">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-label-sm text-label-sm text-xs font-semibold <?= $classBadge['bg'] ?>">
                                        <?= $classBadge['label'] ?>
                                    </span>
                                </td>

                                <!-- Statistik Berkas -->
                                <td class="py-3.5 px-space-md">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container text-primary font-code-audit text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                        <?= (int)$cat['total_docs'] ?> Berkas Terkait
                                    </span>
                                </td>

                                <!-- Status Sistem -->
                                <td class="py-3.5 px-space-md">
                                    <?php if ($isCatActive): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-surface-container text-primary font-label-sm text-label-sm text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-error-container/30 text-error font-label-sm text-label-sm text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                                            Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3.5 px-space-md text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <!-- Edit Kategori -->
                                        <button type="button" 
                                                class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded-lg transition-colors btn-edit-category"
                                                title="Edit Kategori Naskah"
                                                data-id="<?= $cat['id'] ?>"
                                                data-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-code="<?= htmlspecialchars($cat['code'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-desc="<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-classification="<?= htmlspecialchars($cat['default_classification'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-icon="<?= htmlspecialchars($cat['icon_name'] ?? 'description', ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </button>

                                        <!-- Toggle Status (Soft Delete) -->
                                        <form method="POST" action="<?= BASE_URL ?>/actions/master_data_action.php" class="inline" onsubmit="return confirm('<?= $isCatActive ? 'Nonaktifkan kategori ini dari formulir pengajuan berkas?' : 'Aktifkan kembali kategori ini?' ?>');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="toggle_category">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" 
                                                    class="p-1.5 rounded-lg transition-colors <?= $isCatActive ? 'text-on-surface-variant hover:text-error hover:bg-error-container/30' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container' ?>"
                                                    title="<?= $isCatActive ? 'Nonaktifkan Kategori' : 'Aktifkan Kategori' ?>">
                                                <span class="material-symbols-outlined text-[18px]"><?= $isCatActive ? 'power_settings_new' : 'check_circle' ?></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- TAB 2: KLASIFIKASI KERAHASIAAN DOKUMEN -->
<!-- ======================================================== -->
<div id="tabContent2" class="<?= ($activeTab === 'klasifikasi') ? '' : 'hidden' ?> space-y-space-md">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
        <?php foreach ($classificationsList as $cls): 
            $isClsActive = ((int)$cls['is_active'] === 1);
            $tierStyle = match($cls['code']) {
                'publik'   => ['icon' => 'public', 'badge' => 'bg-tertiary-fixed text-on-tertiary-fixed', 'iconBg' => 'bg-tertiary-fixed text-on-tertiary-fixed'],
                'internal' => ['icon' => 'domain', 'badge' => 'bg-secondary-fixed text-on-secondary-fixed', 'iconBg' => 'bg-secondary-fixed text-on-secondary-fixed'],
                'rahasia'  => ['icon' => 'lock', 'badge' => 'bg-primary-fixed text-on-primary-fixed', 'iconBg' => 'bg-primary-fixed text-on-primary-fixed'],
                default    => ['icon' => 'vpn_key', 'badge' => 'bg-surface-container text-primary', 'iconBg' => 'bg-surface-container text-primary']
            };
        ?>
            <div class="bg-surface-container-lowest rounded-xl p-space-lg border border-outline-variant/20 shadow-sm flex flex-col justify-between">
                <div>
                    <!-- Card Header -->
                    <div class="flex items-start justify-between gap-2 mb-space-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg <?= $tierStyle['iconBg'] ?> flex items-center justify-center font-bold">
                                <span class="material-symbols-outlined text-[18px]"><?= $tierStyle['icon'] ?></span>
                            </div>
                            <h3 class="font-headline-sm text-headline-sm text-primary font-bold">
                                <?= htmlspecialchars($cls['name'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-label-sm text-label-sm text-xs font-semibold <?= $tierStyle['badge'] ?>">
                            <?= htmlspecialchars($cls['badge_label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <!-- Kode Registri -->
                    <div class="flex items-center gap-2 mb-space-sm">
                        <span class="font-code-audit text-xs text-secondary">KODE REGISTRI:</span>
                        <span class="font-code-audit text-xs text-primary bg-surface-container px-2 py-0.5 rounded font-semibold">
                            <?= htmlspecialchars($cls['code'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <!-- Deskripsi -->
                    <p class="font-body-md text-xs text-on-surface-variant leading-relaxed">
                        <?= htmlspecialchars($cls['description'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                <!-- Footer Tingkat Keamanan -->
                <div class="mt-space-md pt-space-sm bg-surface-container-low p-space-sm rounded-lg flex items-center justify-between text-secondary">
                    <span class="font-body-sm text-[11px]"><?= htmlspecialchars($cls['verification_level'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="font-code-audit text-[11px] font-bold text-primary"><?= htmlspecialchars($cls['tier_code'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL DIALOG: TAMBAH / EDIT KATEGORI NASKAH -->
<!-- ======================================================== -->
<div id="categoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 hidden">
    <div class="bg-surface-container-lowest rounded-xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col transform transition-all border border-outline-variant/30">
        
        <!-- Modal Header -->
        <div class="px-space-lg py-space-md bg-surface-container-low flex items-center justify-between border-b border-outline-variant/20">
            <div class="flex flex-col">
                <h3 id="categoryModalTitle" class="font-headline-sm text-base font-bold text-primary">Tambah Kategori Dokumen Baru</h3>
                <p class="font-body-sm text-body-sm text-secondary mt-0.5">Daftarkan kategori naskah baru ke katalog alur persetujuan OneSign.</p>
            </div>
            <button type="button" id="btnCloseCategoryModal" class="p-1 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Modal Body Form -->
        <form method="POST" action="<?= BASE_URL ?>/actions/master_data_action.php" id="formCategory" class="p-space-lg flex flex-col gap-space-md">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" id="categoryFormAction" value="create_category">
            <input type="hidden" name="category_id" id="categoryFormId" value="">

            <!-- Field 1: Nama Kategori -->
            <div class="flex flex-col gap-1">
                <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Nama Kategori Naskah <span class="text-error">*</span></label>
                <input type="text" 
                       name="name" 
                       id="inputCatName" 
                       required 
                       placeholder="e.g. Surat Bebas Pustaka &amp; Laboratorium"
                       class="h-10 px-3 rounded-lg bg-surface-container-lowest border border-outline-variant/30 font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary shadow-sm">
            </div>

            <!-- Field 2: Kode Slug -->
            <div class="flex flex-col gap-1">
                <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Kode Identifier (Slug Registri Unik) <span class="text-error">*</span></label>
                <div class="relative">
                    <input type="text" 
                           name="code" 
                           id="inputCatCode" 
                           required 
                           placeholder="e.g. SK-BEBAS-PUSTAKA"
                           class="w-full h-10 px-3 bg-surface-container-low rounded-lg font-code-audit text-xs text-primary uppercase focus:outline-none focus:ring-1 focus:ring-primary">
                    <span class="material-symbols-outlined absolute right-3 top-2.5 text-secondary text-[18px]">fingerprint</span>
                </div>
                <span class="font-code-audit text-code-audit-sm text-secondary text-[11px]">Digunakan sebagai identifikasi sistem dan awalan penomoran audit berkas.</span>
            </div>

            <!-- Field 3: Deskripsi -->
            <div class="flex flex-col gap-1">
                <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Deskripsi Singkat</label>
                <textarea name="description" 
                          id="inputCatDesc" 
                          rows="2" 
                          placeholder="Penjelasan tujuan naskah dan syarat kelengkapan..."
                          class="p-3 rounded-lg bg-surface-container-lowest border border-outline-variant/30 font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary shadow-sm resize-none"></textarea>
            </div>

            <!-- Field 4: Klasifikasi Bawaan & Icon Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <div class="flex flex-col gap-1">
                    <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Klasifikasi Bawaan</label>
                    <div class="relative">
                        <select name="default_classification" 
                                id="inputCatClassification" 
                                class="w-full h-10 pl-3 pr-8 rounded-lg bg-surface-container-lowest border border-outline-variant/30 font-body-md text-body-md text-on-surface appearance-none focus:outline-none focus:ring-1 focus:ring-primary shadow-sm cursor-pointer text-xs">
                            <option value="internal">Internal Kampus (Civitas TIK)</option>
                            <option value="publik">Publik Terbuka (Bebas Akses)</option>
                            <option value="rahasia">Rahasia Terbatas (Restricted)</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-secondary text-[18px] pointer-events-none">expand_more</span>
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Ikon Naskah</label>
                    <div class="relative">
                        <select name="icon_name" 
                                id="inputCatIcon" 
                                class="w-full h-10 pl-3 pr-8 rounded-lg bg-surface-container-lowest border border-outline-variant/30 font-body-md text-body-md text-on-surface appearance-none focus:outline-none focus:ring-1 focus:ring-primary shadow-sm cursor-pointer text-xs">
                            <option value="school">school (Tugas Akhir/Skripsi)</option>
                            <option value="assignment_turned_in">assignment_turned_in (Magang/MBKM)</option>
                            <option value="verified">verified (Lembar Pengesahan)</option>
                            <option value="gavel">gavel (Nota Dinas)</option>
                            <option value="fact_check">fact_check (Berita Acara)</option>
                            <option value="description">description (Administrasi Umum)</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-secondary text-[18px] pointer-events-none">expand_more</span>
                    </div>
                </div>
            </div>

            <!-- Field 5: Active Switch (Khusus create) -->
            <div id="catActiveSwitchBox" class="p-space-sm rounded-lg bg-surface-container-low flex items-center justify-between">
                <div class="flex flex-col">
                    <span class="font-label-md text-label-md text-primary font-semibold text-xs">Status Aktif Langsung</span>
                    <span class="font-body-sm text-body-sm text-secondary text-[11px]">Kategori langsung dapat dipilih pemohon pada form pengajuan</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" id="inputCatActive" value="1" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-surface-container-high peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-on-primary after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                </label>
            </div>

            <!-- Modal Footer Actions -->
            <div class="flex items-center justify-end gap-space-sm pt-space-sm border-t border-outline-variant/10">
                <button type="button" 
                        id="btnCancelCategoryModal"
                        class="px-space-md py-2 rounded-lg bg-surface-container text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-colors font-medium">
                    Batal
                </button>
                <button type="submit" 
                        class="px-space-md py-2 rounded-lg bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container transition-colors shadow-sm font-semibold">
                    Simpan Kategori
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching micro-interactions
    const tabBtn1 = document.getElementById('tabBtn1');
    const tabBtn2 = document.getElementById('tabBtn2');
    const tabContent1 = document.getElementById('tabContent1');
    const tabContent2 = document.getElementById('tabContent2');

    tabBtn1.addEventListener('click', () => {
        tabBtn1.className = 'flex-1 flex items-center justify-center gap-2 py-2 px-space-md rounded-lg font-label-md text-label-md bg-surface-container-lowest text-primary shadow-sm transition-all font-semibold';
        tabBtn2.className = 'flex-1 flex items-center justify-center gap-2 py-2 px-space-md rounded-lg font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-all font-semibold';
        tabContent1.classList.remove('hidden');
        tabContent2.classList.add('hidden');
    });

    tabBtn2.addEventListener('click', () => {
        tabBtn2.className = 'flex-1 flex items-center justify-center gap-2 py-2 px-space-md rounded-lg font-label-md text-label-md bg-surface-container-lowest text-primary shadow-sm transition-all font-semibold';
        tabBtn1.className = 'flex-1 flex items-center justify-center gap-2 py-2 px-space-md rounded-lg font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-all font-semibold';
        tabContent2.classList.remove('hidden');
        tabContent1.classList.add('hidden');
    });

    // Modal Kategori Interactions
    const modal = document.getElementById('categoryModal');
    const btnOpen = document.getElementById('btnOpenCategoryModal');
    const btnClose = document.getElementById('btnCloseCategoryModal');
    const btnCancel = document.getElementById('btnCancelCategoryModal');
    const form = document.getElementById('formCategory');
    const formAction = document.getElementById('categoryFormAction');
    const formId = document.getElementById('categoryFormId');
    const modalTitle = document.getElementById('categoryModalTitle');
    const switchBox = document.getElementById('catActiveSwitchBox');

    const inputName = document.getElementById('inputCatName');
    const inputCode = document.getElementById('inputCatCode');
    const inputDesc = document.getElementById('inputCatDesc');
    const inputClassification = document.getElementById('inputCatClassification');
    const inputIcon = document.getElementById('inputCatIcon');

    function openCreateModal() {
        form.reset();
        formAction.value = 'create_category';
        formId.value = '';
        modalTitle.textContent = 'Tambah Kategori Dokumen Baru';
        switchBox.classList.remove('hidden');
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    btnOpen.addEventListener('click', openCreateModal);
    btnClose.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);

    // Edit Kategori
    document.querySelectorAll('.btn-edit-category').forEach(btn => {
        btn.addEventListener('click', function() {
            formAction.value = 'edit_category';
            formId.value = this.dataset.id;
            inputName.value = this.dataset.name;
            inputCode.value = this.dataset.code;
            inputDesc.value = this.dataset.desc || '';
            inputClassification.value = this.dataset.classification;
            inputIcon.value = this.dataset.icon || 'description';

            modalTitle.textContent = 'Edit Kategori Dokumen';
            switchBox.classList.add('hidden');
            modal.classList.remove('hidden');
        });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    // Real-time search categories
    const searchCatInput = document.getElementById('searchCategoryInput');
    const catRows = document.querySelectorAll('.category-row');

    searchCatInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        catRows.forEach(row => {
            const searchData = row.dataset.search;
            if (!query || searchData.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
