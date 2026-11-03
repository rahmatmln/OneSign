<?php
/**
 * OneSign - Dashboard Staf Administrasi
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Rekap Arsip Dokumen';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

// Pastikan hanya peran 'admin' yang dapat mengakses
requireRole(['admin']);

$user = getActiveUser();
$pdo = Database::getConnection();

// Mengambil seluruh dokumen di sistem beserta data penandatangan & catatan penolakan
$stmt = $pdo->query("
    SELECT 
        d.id, d.title, d.category, d.confidentiality, d.original_filename, 
        d.file_size, d.status, d.created_at,
        u_req.full_name AS requester_name,
        u_req.identifier_number AS requester_nim,
        u_sign.full_name AS signer_name,
        ds.rejection_note
    FROM documents d
    JOIN users u_req ON d.requester_id = u_req.id
    LEFT JOIN document_signers ds ON d.id = ds.document_id
    LEFT JOIN users u_sign ON ds.signer_id = u_sign.id
    ORDER BY d.created_at DESC
");
$allDocs = $stmt->fetchAll();

// Hitung Statistik Arsip
$totalAllDocs = count($allDocs);
$approvedCount = 0;
$pendingCount = 0;
$rejectedCount = 0;

foreach ($allDocs as $doc) {
    if ($doc['status'] === 'disetujui') $approvedCount++;
    elseif ($doc['status'] === 'menunggu') $pendingCount++;
    elseif ($doc['status'] === 'ditolak') $rejectedCount++;
}

// Total Catatan Audit Trail
$logCountStmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
$totalLogs = (int)$logCountStmt->fetchColumn();

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- Header Section (Stitch Style) -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-md mb-space-lg">
    <div class="space-y-1">
        <div class="flex items-center gap-space-xs text-secondary">
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Portal Administrasi</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-semibold">Rekap &amp; Arsip Berkas</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight">
            Selamat datang kembali, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <p class="font-body-md text-body-md text-secondary">
            Monitoring peredaran naskah akademik, lembar pengesahan, dan dokumen internal secara terpadu.
        </p>
    </div>
    <div class="flex items-center gap-space-sm self-start sm:self-center">
        <a href="<?= BASE_URL ?>/views/admin/audit_trail.php" 
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-semibold rounded-lg shadow-sm transition-all">
            <span class="material-symbols-outlined text-[18px]">verified_user</span>
            <span>Buka Audit Trail &amp; Keamanan</span>
        </a>
    </div>
</div>

<!-- 4 Clean Metric Cards (Stitch Style) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
    <!-- Total Arsip -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Total Arsip Berkas</span>
                <span class="font-display-lg text-display-lg font-bold text-on-surface"><?= $totalAllDocs ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">inventory_2</span>
            </div>
        </div>
    </div>

    <!-- Berkas Sah -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Berkas Sah (Selesai)</span>
                <span class="font-display-lg text-display-lg font-bold text-emerald-700"><?= $approvedCount ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                <span class="material-symbols-outlined text-[20px]">verified</span>
            </div>
        </div>
    </div>

    <!-- Dalam Proses TTD -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Dalam Proses TTD</span>
                <span class="font-display-lg text-display-lg font-bold text-amber-700"><?= $pendingCount ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700">
                <span class="material-symbols-outlined text-[20px]">pending_actions</span>
            </div>
        </div>
    </div>

    <!-- Log Audit Trail -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Entri Audit Trail</span>
                <span class="font-display-lg text-display-lg font-bold text-primary"><?= $totalLogs ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-secondary-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">receipt_long</span>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Arsip Dokumen -->
<div class="bg-surface-container-lowest rounded-xl border border-outline-variant/20 shadow-sm overflow-hidden flex flex-col">
    <div class="px-space-lg py-space-md border-b border-outline-variant/20 flex items-center justify-between bg-surface-container-lowest">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[20px]">folder_supervised</span>
            <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Seluruh Berkas Terdaftar</h2>
        </div>
        <span class="text-body-sm font-body-sm text-secondary font-code-audit"><?= $totalAllDocs ?> berkas tercatat</span>
    </div>

    <?php if (empty($allDocs)): ?>
        <div class="p-12 text-center flex flex-col items-center justify-center">
            <div class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center text-secondary mb-3">
                <span class="material-symbols-outlined text-[24px]">folder_off</span>
            </div>
            <div class="font-headline-sm text-headline-sm font-semibold text-on-surface">Belum Ada Dokumen Terdaftar</div>
            <p class="font-body-md text-body-md text-secondary max-w-sm mt-1">
                Saat ini belum ada naskah atau lembar pengesahan yang diajukan ke dalam sistem.
            </p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[950px]">
                <thead>
                    <tr class="border-b border-outline-variant/20 bg-surface-container-low/50 text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                        <th class="py-3.5 px-space-md w-[28%] font-semibold" scope="col">Judul Dokumen</th>
                        <th class="py-3.5 px-space-md w-[22%] font-semibold" scope="col">Pihak Berkas</th>
                        <th class="py-3.5 px-space-md w-[16%] font-semibold" scope="col">Kategori & Kerahasiaan</th>
                        <th class="py-3.5 px-space-md w-[14%] font-semibold" scope="col">Tanggal Pengajuan</th>
                        <th class="py-3.5 px-space-md w-[10%] font-semibold" scope="col">Status</th>
                        <th class="py-3.5 px-space-md w-[10%] font-semibold text-right" scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/20 font-body-md text-body-md text-on-surface">
                    <?php foreach ($allDocs as $doc): ?>
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

                            <!-- Pihak Berkas -->
                            <td class="py-3.5 px-space-md align-top">
                                <div class="font-semibold text-on-surface leading-snug">
                                    <?= htmlspecialchars($doc['requester_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="font-code-audit text-code-audit text-secondary mt-0.5 whitespace-nowrap">
                                    NIM: <?= htmlspecialchars($doc['requester_nim'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="font-body-sm text-body-sm text-secondary mt-1.5 pt-1.5 border-t border-outline-variant/20 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px] text-primary">draw</span>
                                    <span class="text-secondary font-medium">TTD:</span>
                                    <span class="font-semibold text-on-surface truncate max-w-[150px]">
                                        <?= htmlspecialchars($doc['signer_name'] ?? 'Belum Ditentukan', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
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
                                <?php if ($doc['status'] === 'menunggu'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 text-amber-950 font-label-sm text-label-sm font-semibold whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                        Menunggu
                                    </span>
                                <?php elseif ($doc['status'] === 'disetujui'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-950 font-label-sm text-label-sm font-semibold whitespace-nowrap">
                                        <span class="material-symbols-outlined text-[14px] text-emerald-700">verified</span>
                                        Disetujui
                                    </span>
                                <?php else: ?>
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
                                <?php if ($doc['status'] === 'disetujui'): ?>
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
