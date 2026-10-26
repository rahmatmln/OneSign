<?php
/**
 * OneSign - Dashboard Pemohon Dokumen
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

<!-- Header Section -->
<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Dashboard Pemohon Dokumen</h1>
        <p class="text-xs text-slate-500 mt-0.5">Kelola dan pantau status permohonan pengesahan berkas akademik Anda.</p>
    </div>
    <a href="<?= BASE_URL ?>/views/pemohon/upload.php" 
       class="px-4 py-2 bg-blue-800 hover:bg-blue-900 text-white font-semibold text-xs rounded shadow transition">
        Ajukan Dokumen PDF Baru
    </a>
</div>

<!-- Alert Sukses Unggah -->
<?php if (isset($_GET['upload_success'])): ?>
    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium">
        Dokumen PDF berhasil diajukan! Berkas kini tercatat dalam repositori dan masuk ke dalam antrean persetujuan dosen penandatangan.
    </div>
<?php endif; ?>

<!-- Statistik Ringkasan -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm border-l-4 border-l-blue-800 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Pengajuan</div>
            <div class="text-2xl font-bold text-slate-900 mt-1"><?= $totalDocs ?></div>
        </div>
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-800 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm border-l-4 border-l-amber-500 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Menunggu TTD</div>
            <div class="text-2xl font-bold text-amber-700 mt-1"><?= $pendingDocs ?></div>
        </div>
        <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm border-l-4 border-l-emerald-600 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Telah Disetujui</div>
            <div class="text-2xl font-bold text-emerald-700 mt-1"><?= $approvedDocs ?></div>
        </div>
        <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm border-l-4 border-l-rose-500 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Ditolak</div>
            <div class="text-2xl font-bold text-rose-700 mt-1"><?= $rejectedDocs ?></div>
        </div>
        <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
</div>

<!-- Daftar Dokumen -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900">Riwayat Pengajuan Dokumen</h2>
        <span class="text-xs text-slate-500"><?= $totalDocs ?> berkas tercatat</span>
    </div>

    <?php if (empty($documents)): ?>
        <div class="p-8 text-center space-y-3">
            <div class="text-sm font-semibold text-slate-700">Belum Ada Pengajuan Dokumen</div>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                Anda belum pernah mengajukan permohonan pengesahan. Klik tombol di atas untuk mengunggah dokumen PDF pertama Anda.
            </p>
            <a href="<?= BASE_URL ?>/views/pemohon/upload.php" class="inline-block px-4 py-2 bg-blue-800 text-white text-xs font-semibold rounded hover:bg-blue-900 transition">
                Mulai Unggah Dokumen
            </a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 min-w-[850px]">
                <thead class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-800 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="py-3.5 px-4 w-[28%]">Judul Dokumen</th>
                        <th class="py-3.5 px-4 w-[20%]">Penandatangan</th>
                        <th class="py-3.5 px-4 w-[16%]">Kategori & Label</th>
                        <th class="py-3.5 px-4 w-[14%]">Tanggal Pengajuan</th>
                        <th class="py-3.5 px-4 w-[12%]">Status</th>
                        <th class="py-3.5 px-4 w-[10%] text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($documents as $doc): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 align-top">
                                <div class="font-bold text-slate-900 leading-snug"><?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1.5 flex-nowrap">
                                    <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="truncate max-w-[130px] sm:max-w-[160px]" title="<?= htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($doc['original_filename'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-slate-400 font-mono text-[10px] whitespace-nowrap shrink-0"><?= round($doc['file_size'] / 1024, 1) ?> KB</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 align-top">
                                <div class="font-semibold text-slate-800 leading-snug">
                                    <?= htmlspecialchars($doc['signer_name'] ?? 'Belum Ditentukan', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">Dosen Penandatangan</div>
                            </td>
                            <td class="py-3.5 px-4 align-top">
                                <div class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 border border-slate-200 text-slate-700 whitespace-nowrap mb-1">
                                    <?= htmlspecialchars($doc['category'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div>
                                    <?php if ($doc['confidentiality'] === 'rahasia'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">
                                            Rahasia
                                        </span>
                                    <?php elseif ($doc['confidentiality'] === 'internal'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 whitespace-nowrap">
                                            Internal TIK
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-50 text-slate-600 border border-slate-200 whitespace-nowrap">
                                            Publik
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 align-top whitespace-nowrap">
                                <div class="font-medium text-slate-800 text-xs">
                                    <?= date('d M Y', strtotime($doc['created_at'])) ?>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5 font-mono">
                                    <?= date('H:i', strtotime($doc['created_at'])) ?> WIB
                                </div>
                            </td>
                            <td class="py-3.5 px-4 align-top">
                                <?php if ($doc['status'] === 'menunggu'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 font-semibold text-[11px] border border-amber-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                                        Menunggu TTD
                                    </span>
                                <?php elseif ($doc['status'] === 'disetujui'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 font-semibold text-[11px] border border-emerald-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span>
                                        Disetujui
                                    </span>
                                <?php elseif ($doc['status'] === 'ditolak'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-rose-50 text-rose-800 font-semibold text-[11px] border border-rose-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600 mr-1.5"></span>
                                        Ditolak
                                    </span>
                                    <?php if (!empty($doc['rejection_note'])): ?>
                                        <div class="mt-1 px-2 py-1 bg-rose-50 border border-rose-200 rounded text-[10px] text-rose-700 leading-tight">
                                            <span class="font-semibold">Revisi:</span> "<?= htmlspecialchars($doc['rejection_note'], ENT_QUOTES, 'UTF-8') ?>"
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 align-top text-right whitespace-nowrap">
                                <?php if ($doc['status'] === 'disetujui'): ?>
                                    <a href="<?= BASE_URL ?>/actions/download_action.php?doc_id=<?= $doc['id'] ?>" 
                                       class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded text-xs shadow-sm transition whitespace-nowrap">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <span>Unduh Berkas</span>
                                    </a>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-slate-400 bg-slate-50 border border-slate-200 rounded text-[11px] font-medium whitespace-nowrap">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
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
