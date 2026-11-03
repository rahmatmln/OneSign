<?php
/**
 * OneSign - Dashboard Pemohon Dokumen
 * Design Stitch: Dashboard Pemohon OneSign (100% Identik)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Dashboard Pemohon';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

// Pastikan hanya peran 'pemohon' yang dapat mengakses
requireRole(['pemohon']);

$user = getActiveUser();
$pdo = Database::getConnection();

// Mengambil daftar dokumen yang diajukan oleh pemohon ini
$stmt = $pdo->prepare("
    SELECT 
        d.id, d.title, d.category, d.confidentiality, d.original_filename, 
        d.file_size, d.status, d.created_at,
        u.full_name AS signer_name,
        ds.status AS signer_status,
        ds.rejection_note
    FROM documents d
    LEFT JOIN document_signers ds ON d.id = ds.document_id
    LEFT JOIN users u ON ds.signer_id = u.id
    WHERE d.requester_id = :requester_id
    ORDER BY d.created_at DESC
");
$stmt->execute([':requester_id' => $user['id']]);
$documents = $stmt->fetchAll();

// Hitung Statistik Dokumen
$totalDocs = count($documents);
$pendingDocs = 0;
$approvedDocs = 0;
$rejectedDocs = 0;

foreach ($documents as $doc) {
    if ($doc['status'] === 'menunggu') $pendingDocs++;
    elseif ($doc['status'] === 'disetujui') $approvedDocs++;
    elseif ($doc['status'] === 'ditolak') $rejectedDocs++;
}

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- Header Section (Stitch 100% Identik) -->
<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-space-md">
    <div class="space-y-1">
        <div class="flex items-center gap-space-xs text-secondary">
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Portal Pemohon</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-semibold">Ringkasan Dokumen</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight">
            Selamat datang kembali, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
    </div>
    <div class="flex items-center gap-space-sm shrink-0">
        <a href="<?= BASE_URL ?>/views/pemohon/upload.php" 
           class="h-10 px-space-lg rounded-lg bg-primary text-on-primary shadow-sm hover:bg-primary-container transition-all flex items-center gap-space-xs font-label-md text-label-md">
            <span>+ Unggah &amp; Ajukan Dokumen Baru</span>
        </a>
    </div>
</div>

<!-- Alert Sukses Unggah -->
<?php if (isset($_GET['upload_success'])): ?>
    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 font-body-md text-body-md flex items-center gap-3 shadow-xs">
        <span class="material-symbols-outlined text-emerald-700 text-[22px]">check_circle</span>
        <span>Dokumen PDF berhasil diajukan! Berkas kini tercatat dan menunggu persetujuan pejabat penandatangan.</span>
    </div>
<?php endif; ?>

<!-- Key Metrics Grid (Stitch 100% Identik) -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
    <!-- Metric 1: Total -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col justify-between border border-outline-variant/20 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Total Berkas Diajukan</span>
                <div class="font-display-lg text-display-lg font-bold text-on-surface tracking-tight"><?= $totalDocs ?></div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[22px]">source</span>
            </div>
        </div>
    </div>

    <!-- Metric 2: Pending -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col justify-between border border-outline-variant/20 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Menunggu Tanda Tangan</span>
                <div class="font-display-lg text-display-lg font-bold text-amber-700 tracking-tight"><?= $pendingDocs ?></div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700">
                <span class="material-symbols-outlined text-[22px]">pending_actions</span>
            </div>
        </div>
    </div>

    <!-- Metric 3: Approved -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col justify-between border border-outline-variant/20 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Dokumen Disahkan</span>
                <div class="font-display-lg text-display-lg font-bold text-emerald-800 tracking-tight"><?= $approvedDocs ?></div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-800">
                <span class="material-symbols-outlined text-[22px]">verified_user</span>
            </div>
        </div>
    </div>

    <!-- Metric 4: Rejected / Revision -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex flex-col justify-between border border-outline-variant/20 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Ditolak / Perlu Revisi</span>
                <div class="font-display-lg text-display-lg font-bold text-error tracking-tight"><?= $rejectedDocs ?></div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-error-container/40 flex items-center justify-center text-error">
                <span class="material-symbols-outlined text-[22px]">assignment_late</span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content: Document Registry (Stitch 100% Identik) -->
<div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col border border-outline-variant/20">
    <!-- Toolbar & Filter Header -->
    <div class="p-space-md flex flex-col lg:flex-row lg:items-center justify-between gap-space-md bg-surface-container-lowest">
        <div class="space-y-1">
            <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Daftar Pengajuan Dokumen Terkini</h2>
        </div>
        <div class="flex flex-wrap items-center gap-space-sm">
            <!-- Search Bar -->
            <div class="relative min-w-[260px] flex-1 sm:flex-initial">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-secondary pointer-events-none">search</span>
                <input class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface-container-low text-on-surface placeholder:text-secondary font-body-sm text-body-sm focus:outline-none focus:bg-surface-container transition-colors border border-outline-variant/20" 
                       id="searchInput" 
                       onkeyup="filterSearch()"
                       placeholder="Cari nama berkas, penandatangan..." 
                       type="text">
            </div>
        </div>
    </div>

    <!-- Filter Tabs Navigation -->
    <div class="px-space-md py-space-xs bg-surface-container-low flex items-center justify-between gap-space-sm overflow-x-auto border-t border-b border-outline-variant/10">
        <div class="flex items-center gap-space-xs">
            <button class="tab-btn active px-3 py-1.5 rounded-md font-label-sm text-label-sm font-semibold transition-all bg-primary text-on-primary shadow-sm" 
                    data-tab="all" 
                    onclick="filterDocs('all')" 
                    type="button">
                Semua (<?= $totalDocs ?>)
            </button>
            <button class="tab-btn px-3 py-1.5 rounded-md font-label-sm text-label-sm font-semibold transition-all text-on-surface-variant hover:bg-surface-container" 
                    data-tab="menunggu" 
                    onclick="filterDocs('menunggu')" 
                    type="button">
                Menunggu (<?= $pendingDocs ?>)
            </button>
            <button class="tab-btn px-3 py-1.5 rounded-md font-label-sm text-label-sm font-semibold transition-all text-on-surface-variant hover:bg-surface-container" 
                    data-tab="disetujui" 
                    onclick="filterDocs('disetujui')" 
                    type="button">
                Disetujui (<?= $approvedDocs ?>)
            </button>
            <button class="tab-btn px-3 py-1.5 rounded-md font-label-sm text-label-sm font-semibold transition-all text-on-surface-variant hover:bg-surface-container" 
                    data-tab="ditolak" 
                    onclick="filterDocs('ditolak')" 
                    type="button">
                Revisi (<?= $rejectedDocs ?>)
            </button>
        </div>
        <div class="flex items-center gap-space-xs text-secondary font-code-audit-sm text-code-audit-sm">
            <button onclick="location.reload()" title="Segarkan Data" class="p-1 hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-[16px]">sync</span>
            </button>
        </div>
    </div>

    <!-- Document Data Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[950px]">
            <thead>
                <tr class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider border-b border-outline-variant/20">
                    <th class="py-space-sm px-space-md font-semibold">Nama Berkas</th>
                    <th class="py-space-sm px-space-md font-semibold">Kategori</th>
                    <th class="py-space-sm px-space-md font-semibold">Klasifikasi</th>
                    <th class="py-space-sm px-space-md font-semibold">Dosen / Penandatangan</th>
                    <th class="py-space-sm px-space-md font-semibold">Tanggal Pengajuan</th>
                    <th class="py-space-sm px-space-md font-semibold">Status</th>
                    <th class="py-space-sm px-space-md font-semibold text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10 text-on-surface font-body-md text-body-md" id="documentTableBody">
                <?php if (empty($documents)): ?>
                    <tr id="empty-row">
                        <td colspan="7" class="py-12 text-center text-secondary">
                            <div class="flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-[32px] text-outline mb-2">folder_open</span>
                                <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">Belum Ada Pengajuan Dokumen</p>
                                <p class="font-body-sm text-body-sm text-secondary mt-1">Klik tombol "+ Unggah &amp; Ajukan Dokumen Baru" untuk memulai.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <tr class="doc-row hover:bg-surface-container-low/60 transition-colors" data-status="<?= htmlspecialchars($doc['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <!-- Nama Berkas -->
                            <td class="py-3 px-space-md">
                                <div class="flex items-center gap-space-sm min-w-[220px]">
                                    <div class="w-8 h-8 rounded bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-label-md text-label-md font-semibold text-on-surface truncate doc-title" title="<?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="font-code-audit-sm text-code-audit-sm text-secondary">
                                            <?= htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8') ?> • <?= round($doc['file_size'] / 1024, 1) ?> KB
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Kategori -->
                            <td class="py-3 px-space-md whitespace-nowrap">
                                <span class="text-on-surface-variant font-body-sm text-body-sm doc-category">
                                    <?= htmlspecialchars($doc['category'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <!-- Klasifikasi -->
                            <td class="py-3 px-space-md whitespace-nowrap">
                                <?php if ($doc['confidentiality'] === 'rahasia'): ?>
                                    <span class="px-2 py-0.5 rounded-full font-label-sm text-label-sm bg-tertiary-fixed text-on-tertiary-fixed font-semibold">
                                        Rahasia TIK
                                    </span>
                                <?php elseif ($doc['confidentiality'] === 'internal'): ?>
                                    <span class="px-2 py-0.5 rounded-full font-label-sm text-label-sm bg-surface-container text-primary font-semibold">
                                        Internal Kampus
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full font-label-sm text-label-sm bg-secondary-container text-on-secondary-container font-semibold">
                                        Publik Terbuka
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Dosen / Penandatangan -->
                            <td class="py-3 px-space-md whitespace-nowrap">
                                <span class="font-body-sm text-body-sm font-medium text-on-surface doc-signer">
                                    <?= htmlspecialchars($doc['signer_name'] ?? 'Belum Ditentukan', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <!-- Tanggal Pengajuan -->
                            <td class="py-3 px-space-md whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-body-sm text-body-sm text-on-surface font-medium">
                                        <?= date('d M Y', strtotime($doc['created_at'])) ?>
                                    </span>
                                    <span class="font-code-audit-sm text-code-audit-sm text-secondary">
                                        <?= date('H:i', strtotime($doc['created_at'])) ?> WIB
                                    </span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-space-md whitespace-nowrap">
                                <?php if ($doc['status'] === 'menunggu'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-label-sm text-label-sm font-semibold bg-amber-100 text-amber-950">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                        Menunggu Tanda Tangan
                                    </span>
                                <?php elseif ($doc['status'] === 'disetujui'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-label-sm text-label-sm font-semibold bg-emerald-100 text-emerald-950">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        Disetujui
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-label-sm text-label-sm font-semibold bg-rose-100 text-rose-950">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                        Ditolak / Revisi
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Tindakan -->
                            <td class="py-3 px-space-md text-right whitespace-nowrap">
                                <?php if ($doc['status'] === 'disetujui'): ?>
                                    <a href="<?= BASE_URL ?>/actions/download_action.php?doc_id=<?= $doc['id'] ?>" 
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-on-primary font-label-sm text-label-sm shadow-sm hover:bg-primary-container transition-colors">
                                        <span class="material-symbols-outlined text-[16px]">download</span>
                                        <span>Unduh Dokumen</span>
                                    </a>
                                <?php elseif ($doc['status'] === 'menunggu'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container text-primary font-label-sm text-label-sm font-semibold cursor-default">
                                        <span class="material-symbols-outlined text-[16px]">notifications_active</span>
                                        <span>Dalam Antrean</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 text-rose-800 font-label-sm text-label-sm font-semibold" 
                                          title="<?= htmlspecialchars($doc['rejection_note'] ?? 'Perlu perbaikan', ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="material-symbols-outlined text-[16px]">info</span>
                                        <span>Lihat Alasan</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table Footer / Pagination -->
    <div class="px-space-md py-space-sm bg-surface-container-low border-t border-outline-variant/10 flex items-center justify-between text-secondary font-body-sm text-body-sm">
        <div>
            Menampilkan <span class="font-semibold text-on-surface"><?= count($documents) ?></span> berkas diajukan
        </div>
        <div class="font-code-audit-sm text-code-audit-sm">
            Total Arsip: <?= $totalDocs ?>
        </div>
    </div>
</div>

<script>
    // Tab filtering (All, Menunggu, Disetujui, Ditolak)
    function filterDocs(status) {
        // Reset active tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.className = "tab-btn px-3 py-1.5 rounded-md font-label-sm text-label-sm font-semibold transition-all text-on-surface-variant hover:bg-surface-container";
            if (btn.dataset.tab === status) {
                btn.className = "tab-btn active px-3 py-1.5 rounded-md font-label-sm text-label-sm font-semibold transition-all bg-primary text-on-primary shadow-sm";
            }
        });

        const rows = document.querySelectorAll('.doc-row');
        rows.forEach(row => {
            if (status === 'all' || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Client-side search input filter
    function filterSearch() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.doc-row');
        rows.forEach(row => {
            const title = row.querySelector('.doc-title')?.innerText.toLowerCase() || '';
            const category = row.querySelector('.doc-category')?.innerText.toLowerCase() || '';
            const signer = row.querySelector('.doc-signer')?.innerText.toLowerCase() || '';
            
            if (title.includes(query) || category.includes(query) || signer.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
