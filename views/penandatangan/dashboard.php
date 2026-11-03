<?php
/**
 * OneSign - Dashboard Penandatangan (Dosen)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Antrean Dokumen Masuk';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

// Pastikan hanya peran 'penandatangan' yang dapat mengakses
requireRole(['penandatangan']);

$user = getActiveUser();
$pdo = Database::getConnection();

// Mengambil dokumen yang ditugaskan ke penandatangan ini
$stmt = $pdo->prepare("
    SELECT 
        d.id, d.title, d.category, d.confidentiality, d.original_filename, 
        d.file_size, d.created_at,
        u.full_name AS requester_name,
        u.identifier_number AS requester_nim,
        ds.id AS signer_entry_id,
        ds.status AS sign_status,
        ds.rejection_note
    FROM document_signers ds
    JOIN documents d ON ds.document_id = d.id
    JOIN users u ON d.requester_id = u.id
    WHERE ds.signer_id = :signer_id
    ORDER BY d.created_at DESC
");
$stmt->execute([':signer_id' => $user['id']]);
$assignedDocs = $stmt->fetchAll();

$totalAssigned = count($assignedDocs);
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

$firstPendingDocId = null;
foreach ($assignedDocs as $doc) {
    if ($doc['sign_status'] === 'menunggu') {
        $pendingCount++;
        if ($firstPendingDocId === null) {
            $firstPendingDocId = $doc['id'];
        }
    } elseif ($doc['sign_status'] === 'disetujui') {
        $approvedCount++;
    } elseif ($doc['sign_status'] === 'ditolak') {
        $rejectedCount++;
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- Header Section -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-md mb-space-lg">
    <div class="space-y-1">
        <div class="flex items-center gap-space-xs text-secondary">
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Portal Penandatangan</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-semibold">Antrean Pengesahan</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight">
            Selamat datang kembali, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <p class="font-body-md text-body-md text-secondary">
            Daftar permohonan tanda tangan elektronik yang memerlukan verifikasi dan pengesahan Anda.
        </p>
    </div>
    <div class="flex items-center gap-space-sm self-start sm:self-center">
        <?php if ($firstPendingDocId): ?>
            <a href="<?= BASE_URL ?>/views/penandatangan/sign_workspace.php?doc_id=<?= $firstPendingDocId ?>" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-semibold rounded-lg shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">draw</span>
                <span>Proses Antrean Teratas (<?= $pendingCount ?>)</span>
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/views/penandatangan/dashboard.php" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-surface-container hover:bg-surface-container-high text-on-surface font-label-md text-label-md font-semibold rounded-lg border border-outline-variant/30 transition-all">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                <span>Segarkan Antrean</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Alert Notifikasi Hasil Aksi -->
<?php if (isset($_GET['sign_success'])): ?>
    <div class="mb-space-md p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950 font-body-md text-body-md flex items-center gap-3">
        <span class="material-symbols-outlined text-emerald-700 text-[22px]">check_circle</span>
        <span>Dokumen berhasil disahkan secara elektronik! Status berkas telah diperbarui menjadi Disetujui dan terkunci permanen.</span>
    </div>
<?php endif; ?>

<?php if (isset($_GET['reject_success'])): ?>
    <div class="mb-space-md p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-950 font-body-md text-body-md flex items-center gap-3">
        <span class="material-symbols-outlined text-rose-700 text-[22px]">cancel</span>
        <span>Penolakan berkas berhasil diproses. Catatan revisi telah tersimpan dan diteruskan ke pemohon.</span>
    </div>
<?php endif; ?>

<!-- 4 Clean Metric Cards (Stitch Style) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
    <!-- Total Pengajuan -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Total Pengajuan</span>
                <span class="font-display-lg text-display-lg font-bold text-on-surface"><?= $totalAssigned ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">assignment</span>
            </div>
        </div>
    </div>

    <!-- Menunggu TTD -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Menunggu TTD</span>
                <span class="font-display-lg text-display-lg font-bold text-amber-700"><?= $pendingCount ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700">
                <span class="material-symbols-outlined text-[20px]">pending_actions</span>
            </div>
        </div>
    </div>

    <!-- Telah Disetujui -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Telah Disetujui</span>
                <span class="font-display-lg text-display-lg font-bold text-emerald-700"><?= $approvedCount ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                <span class="material-symbols-outlined text-[20px]">verified</span>
            </div>
        </div>
    </div>

    <!-- Ditolak / Revisi -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Ditolak</span>
                <span class="font-display-lg text-display-lg font-bold text-rose-700"><?= $rejectedCount ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-700">
                <span class="material-symbols-outlined text-[20px]">rule</span>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Dokumen Antrean -->
<div class="bg-surface-container-lowest rounded-xl border border-outline-variant/20 shadow-sm overflow-hidden flex flex-col">
    <div class="px-space-lg py-space-md border-b border-outline-variant/20 flex items-center justify-between bg-surface-container-lowest">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[20px]">folder_open</span>
            <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Daftar Antrean & Riwayat Dokumen</h2>
        </div>
        <span class="text-body-sm font-body-sm text-secondary font-code-audit"><?= $totalAssigned ?> naskah terdata</span>
    </div>

    <?php if (empty($assignedDocs)): ?>
        <div class="p-12 text-center flex flex-col items-center justify-center">
            <div class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center text-secondary mb-3">
                <span class="material-symbols-outlined text-[24px]">task_alt</span>
            </div>
            <div class="font-headline-sm text-headline-sm font-semibold text-on-surface">Tidak Ada Antrean Dokumen</div>
            <p class="font-body-md text-body-md text-secondary max-w-sm mt-1">
                Semua dokumen telah diproses. Saat ini belum ada pengajuan baru yang menunggu persetujuan Anda.
            </p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr class="border-b border-outline-variant/20 bg-surface-container-low/50 text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                        <th class="py-3.5 px-space-md w-[28%] font-semibold" scope="col">Judul Dokumen</th>
                        <th class="py-3.5 px-space-md w-[22%] font-semibold" scope="col">Pemohon Dokumen</th>
                        <th class="py-3.5 px-space-md w-[16%] font-semibold" scope="col">Kategori & Kerahasiaan</th>
                        <th class="py-3.5 px-space-md w-[14%] font-semibold" scope="col">Tanggal Pengajuan</th>
                        <th class="py-3.5 px-space-md w-[10%] font-semibold" scope="col">Status</th>
                        <th class="py-3.5 px-space-md w-[10%] font-semibold text-right" scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/20 font-body-md text-body-md text-on-surface">
                    <?php foreach ($assignedDocs as $doc): ?>
                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                            <!-- Judul Dokumen -->
                            <td class="py-3.5 px-space-md align-top">
                                <div class="font-semibold text-on-surface leading-snug">
                                    <?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="font-body-sm text-body-sm text-secondary mt-1 flex items-center gap-1.5 flex-nowrap">
                                    <span class="material-symbols-outlined text-[16px] text-error shrink-0">picture_as_pdf</span>
                                    <span class="truncate max-w-[150px] sm:max-w-[190px]" title="<?= htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="text-outline-variant">•</span>
                                    <span class="font-code-audit-sm text-code-audit-sm text-secondary whitespace-nowrap shrink-0">
                                        <?= round($doc['file_size'] / 1024, 1) ?> KB
                                    </span>
                                </div>
                            </td>

                            <!-- Pemohon Dokumen -->
                            <td class="py-3.5 px-space-md align-top">
                                <div class="font-semibold text-on-surface leading-snug">
                                    <?= htmlspecialchars($doc['requester_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="font-code-audit text-code-audit text-secondary mt-0.5 whitespace-nowrap">
                                    NIM: <?= htmlspecialchars($doc['requester_nim'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>

                            <!-- Kategori & Kerahasiaan -->
                            <td class="py-3.5 px-space-md align-top">
                                <div class="inline-block px-2.5 py-0.5 rounded font-label-sm text-label-sm bg-surface-container text-on-surface font-medium whitespace-nowrap mb-1">
                                    <?= htmlspecialchars($doc['category'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div>
                                    <?php if ($doc['confidentiality'] === 'rahasia'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm font-semibold bg-purple-50 text-purple-900 border border-purple-200 whitespace-nowrap">
                                            Rahasia
                                        </span>
                                    <?php elseif ($doc['confidentiality'] === 'internal'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm font-semibold bg-blue-50 text-blue-900 border border-blue-200 whitespace-nowrap">
                                            Internal TIK
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm font-semibold bg-surface-container-high text-secondary whitespace-nowrap">
                                            Publik
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Tanggal Pengajuan -->
                            <td class="py-3.5 px-space-md align-top whitespace-nowrap">
                                <div class="font-medium text-on-surface font-body-sm text-body-sm">
                                    <?= date('d M Y', strtotime($doc['created_at'])) ?>
                                </div>
                                <div class="font-code-audit text-code-audit text-secondary mt-0.5">
                                    <?= date('H:i', strtotime($doc['created_at'])) ?> WIB
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-space-md align-top">
                                <?php if ($doc['sign_status'] === 'menunggu'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 text-amber-950 font-label-sm text-label-sm font-semibold whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                        Menunggu TTD
                                    </span>
                                <?php elseif ($doc['sign_status'] === 'disetujui'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-950 font-label-sm text-label-sm font-semibold whitespace-nowrap">
                                        <span class="material-symbols-outlined text-[14px] text-emerald-700">verified</span>
                                        Disetujui
                                    </span>
                                <?php elseif ($doc['sign_status'] === 'ditolak'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-100 text-rose-950 font-label-sm text-label-sm font-semibold whitespace-nowrap">
                                        <span class="material-symbols-outlined text-[14px] text-rose-700">cancel</span>
                                        Ditolak
                                    </span>
                                    <?php if (!empty($doc['rejection_note'])): ?>
                                        <div class="mt-1.5 px-2.5 py-1.5 bg-rose-50 border border-rose-200 rounded-lg font-body-sm text-body-sm text-rose-900 leading-tight">
                                            <span class="font-semibold">Alasan:</span> "<?= htmlspecialchars($doc['rejection_note'], ENT_QUOTES, 'UTF-8') ?>"
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-space-md align-top text-right whitespace-nowrap">
                                <?php if ($doc['sign_status'] === 'menunggu'): ?>
                                    <a href="<?= BASE_URL ?>/views/penandatangan/sign_workspace.php?doc_id=<?= $doc['id'] ?>" 
                                       class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary-container text-on-primary font-label-sm text-label-sm font-semibold rounded-lg shadow-sm transition-all whitespace-nowrap">
                                        <span class="material-symbols-outlined text-[16px]">draw</span>
                                        <span>Buka Workspace TTE</span>
                                    </a>
                                <?php elseif ($doc['sign_status'] === 'disetujui'): ?>
                                    <a href="<?= BASE_URL ?>/actions/download_action.php?doc_id=<?= $doc['id'] ?>" 
                                       class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white font-label-sm text-label-sm font-semibold rounded-lg shadow-sm transition-all whitespace-nowrap">
                                        <span class="material-symbols-outlined text-[16px]">download</span>
                                        <span>Unduh Berkas</span>
                                    </a>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-secondary bg-surface-container rounded-lg font-label-sm text-label-sm font-medium whitespace-nowrap">
                                        <span class="material-symbols-outlined text-[14px]">lock</span>
                                        <span>Terkunci</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
