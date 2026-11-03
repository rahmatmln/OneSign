<?php
/**
 * OneSign - Log Audit Trail (Staf Administrasi)
 * Menampilkan pencatatan riwayat kronologis immutable dengan filter peristiwa (action)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Log Audit Trail Forensik';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

requireRole(['admin']);

$pdo = Database::getConnection();

// Parameter filter dari query string
$selectedAction = trim($_GET['action'] ?? '');
$searchQuery    = trim($_GET['q'] ?? '');
$startDate      = trim($_GET['start_date'] ?? '');
$endDate        = trim($_GET['end_date'] ?? '');

// Pemetaan label peristiwa (action) dan skema warnanya sesuai Stitch Tokens
$actionMeta = [
    'SIGN_DOC'     => ['label' => 'Tanda Tangan TTE',    'icon' => 'draw',           'class' => 'bg-emerald-100 text-emerald-950 font-semibold'],
    'UPLOAD_DOC'   => ['label' => 'Unggah Berkas',       'icon' => 'upload_file',    'class' => 'bg-secondary-container text-on-secondary-container font-semibold'],
    'DOWNLOAD_DOC' => ['label' => 'Unduh Berkas',        'icon' => 'download',       'class' => 'bg-purple-100 text-purple-950 font-semibold'],
    'REJECT_DOC'   => ['label' => 'Penolakan Berkas',    'icon' => 'rule',           'class' => 'bg-rose-100 text-rose-950 font-semibold'],
    'PREVIEW_DOC'  => ['label' => 'Pratinjau Dokumen',   'icon' => 'visibility',     'class' => 'bg-cyan-100 text-cyan-950 font-semibold'],
    'LOGIN'        => ['label' => 'Autentikasi Masuk',   'icon' => 'login',          'class' => 'bg-blue-100 text-blue-950 font-semibold'],
    'LOGOUT'       => ['label' => 'Sesi Keluar',         'icon' => 'logout',         'class' => 'bg-surface-container text-secondary font-semibold'],
];

// Menghitung distribusi entri per aksi untuk pencacah dropdown
$countStmt = $pdo->query("SELECT action, COUNT(*) AS total FROM audit_logs GROUP BY action");
$actionCounts = [];
$totalAllEntries = 0;
while ($row = $countStmt->fetch()) {
    $actionCounts[$row['action']] = (int)$row['total'];
    $totalAllEntries += (int)$row['total'];
}

$signCount     = $actionCounts['SIGN_DOC'] ?? 0;
$downloadCount = $actionCounts['DOWNLOAD_DOC'] ?? 0;

// Membangun klausa WHERE secara dinamis menggunakan Prepared Statements
$whereClauses = [];
$params = [];

if (!empty($selectedAction) && $selectedAction !== 'ALL' && isset($actionMeta[$selectedAction])) {
    $whereClauses[] = "a.action = :action";
    $params[':action'] = $selectedAction;
}

if (!empty($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $whereClauses[] = "a.created_at >= :start_date";
    $params[':start_date'] = $startDate . ' 00:00:00';
}

if (!empty($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $whereClauses[] = "a.created_at <= :end_date";
    $params[':end_date'] = $endDate . ' 23:59:59.999';
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(u.full_name LIKE :q_user OR d.title LIKE :q_doc OR a.ip_address LIKE :q_ip OR a.details LIKE :q_details)";
    $searchWildcard = '%' . $searchQuery . '%';
    $params[':q_user']    = $searchWildcard;
    $params[':q_doc']     = $searchWildcard;
    $params[':q_ip']      = $searchWildcard;
    $params[':q_details'] = $searchWildcard;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Mengambil entri log yang sesuai kriteria (dibatasi 200 entri terbaru untuk performa optimal)
$stmt = $pdo->prepare("
    SELECT 
        a.id, a.action, a.details, a.ip_address, a.user_agent, a.created_at,
        u.full_name AS user_name, u.role AS user_role, u.identifier_number,
        d.title AS doc_title, d.original_filename
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN documents d ON a.document_id = d.id
    {$whereSql}
    ORDER BY a.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$isFiltered = (!empty($selectedAction) && $selectedAction !== 'ALL') 
    || !empty($searchQuery) 
    || !empty($startDate) 
    || !empty($endDate);

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- Header Section (Stitch Style) -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-md mb-space-lg">
    <div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight">
            Audit Trail & Log Keamanan
        </h1>
        <p class="font-body-md text-body-md text-secondary mt-0.5">
            Rekam jejak seluruh transaksi, pengesahan tanda tangan elektronik, dan riwayat akses berkas akademik.
        </p>
    </div>
    <div class="flex items-center gap-space-sm self-start sm:self-center">
        <?php if ($isFiltered): ?>
            <a href="audit_trail.php" 
               class="inline-flex items-center gap-1.5 px-3 py-2 text-label-sm font-label-sm text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 font-semibold rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[16px]">clear_all</span>
                <span>Reset Filter</span>
            </a>
        <?php endif; ?>
        <a href="audit_trail.php" 
           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-label-md font-label-md text-on-surface bg-surface-container-lowest hover:bg-surface-container border border-outline-variant/30 font-semibold rounded-lg shadow-sm transition-colors">
            <span class="material-symbols-outlined text-[18px] text-secondary">sync</span>
            <span>Segarkan Log</span>
        </a>
    </div>
</div>

<!-- 4 Clean Metric Cards (Stitch Style) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
    <!-- Metric 1: Total Aktivitas -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Total Aktivitas</span>
                <span class="font-display-lg text-display-lg font-bold text-on-surface"><?= number_format($totalAllEntries) ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">receipt_long</span>
            </div>
        </div>
        <div class="mt-3 text-body-sm font-body-sm text-secondary">Log tersimpan secara permanen</div>
    </div>

    <!-- Metric 2: Dokumen Disahkan -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Dokumen Disahkan</span>
                <span class="font-display-lg text-display-lg font-bold text-emerald-700"><?= number_format($signCount) ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                <span class="material-symbols-outlined text-[20px]">draw</span>
            </div>
        </div>
        <div class="mt-3 text-body-sm font-body-sm text-emerald-700 font-medium">Pengesahan TTE terverifikasi</div>
    </div>

    <!-- Metric 3: Unduh Berkas -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Unduh Berkas</span>
                <span class="font-display-lg text-display-lg font-bold text-primary"><?= number_format($downloadCount) ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-secondary-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">file_download</span>
            </div>
        </div>
        <div class="mt-3 text-body-sm font-body-sm text-secondary">Pengunduhan berkas bersertifikat</div>
    </div>

    <!-- Metric 4: Keamanan Forensik -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Keamanan Integritas</span>
                <span class="font-display-lg text-display-lg font-bold text-emerald-700">100%</span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                <span class="material-symbols-outlined text-[20px]">shield</span>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-1.5 text-emerald-700 font-label-sm text-label-sm font-semibold">
            <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
            <span>Jejak audit immutable (terkunci)</span>
        </div>
    </div>
</div>

<!-- Streamlined Filter Bar (Stitch Style) -->
<div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm mb-space-lg">
    <form method="GET" action="audit_trail.php" class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-space-sm items-center">
            
            <!-- 1. Kata Kunci Pencarian -->
            <div class="lg:col-span-4 relative">
                <input type="text" 
                       id="q" 
                       name="q" 
                       value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" 
                       placeholder="Cari aktor, judul berkas, IP..." 
                       class="w-full h-10 pl-9 pr-3 rounded-lg bg-surface-container-low font-body-md text-body-md text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest border border-outline-variant/20 transition-all">
                <span class="material-symbols-outlined absolute left-2.5 top-2.5 text-[18px] text-secondary pointer-events-none">search</span>
            </div>

            <!-- 2. Dropdown Jenis Peristiwa (Action) -->
            <div class="lg:col-span-3 relative">
                <select id="action" 
                        name="action" 
                        class="w-full h-10 pl-3 pr-8 rounded-lg bg-surface-container-low font-body-md text-body-md text-on-surface focus:outline-none border border-outline-variant/20 cursor-pointer appearance-none">
                    <option value="">Semua Peristiwa (<?= $totalAllEntries ?>)</option>
                    <?php foreach ($actionMeta as $actKey => $actInfo): ?>
                        <?php $count = $actionCounts[$actKey] ?? 0; ?>
                        <option value="<?= $actKey ?>" <?= ($selectedAction === $actKey) ? 'selected' : '' ?>>
                            <?= $actInfo['label'] ?> (<?= $count ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-[18px] text-secondary pointer-events-none">expand_more</span>
            </div>

            <!-- 3. Dari Tanggal -->
            <div class="lg:col-span-2">
                <input type="date" 
                       id="start_date" 
                       name="start_date" 
                       value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>"
                       title="Dari Tanggal"
                       class="w-full h-10 px-3 rounded-lg bg-surface-container-low font-body-sm text-body-sm text-on-surface focus:outline-none border border-outline-variant/20">
            </div>

            <!-- 4. Sampai Tanggal -->
            <div class="lg:col-span-2">
                <input type="date" 
                       id="end_date" 
                       name="end_date" 
                       value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>"
                       title="Sampai Tanggal"
                       class="w-full h-10 px-3 rounded-lg bg-surface-container-low font-body-sm text-body-sm text-on-surface focus:outline-none border border-outline-variant/20">
            </div>

            <!-- 5. Submit Button -->
            <div class="lg:col-span-1">
                <button type="submit" 
                        class="w-full h-10 flex items-center justify-center bg-primary hover:bg-primary-container text-on-primary rounded-lg transition-colors shadow-sm"
                        title="Terapkan Filter">
                    <span class="material-symbols-outlined text-[18px]">filter_list</span>
                </button>
            </div>

        </div>

        <div class="flex items-center justify-between text-body-sm font-body-sm text-secondary pt-2 border-t border-outline-variant/10">
            <div>
                <?php if ($isFiltered): ?>
                    <span class="inline-flex items-center gap-1.5 font-semibold text-primary">
                        <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                        Filter Aktif Diterapkan (Ditemukan: <?= count($logs) ?> entri)
                    </span>
                <?php else: ?>
                    <span>Menampilkan 200 aktivitas kronologis terbaru dari repositori forensik.</span>
                <?php endif; ?>
            </div>
            <div class="font-code-audit text-code-audit-sm">
                Total Arsip: <?= $totalAllEntries ?> catatan
            </div>
        </div>
    </form>
</div>

<!-- Spacious High-Contrast Audit Trail Table (Stitch Style) -->
<div class="bg-surface-container-lowest rounded-xl border border-outline-variant/20 shadow-sm overflow-hidden flex flex-col">
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse min-w-[950px]">
            <thead>
                <tr class="border-b border-outline-variant/20 bg-surface-container-low/50 text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <th class="py-3.5 px-space-md w-[16%] font-semibold" scope="col">Waktu Kejadian</th>
                    <th class="py-3.5 px-space-md w-[22%] font-semibold" scope="col">Aktor / Pengguna</th>
                    <th class="py-3.5 px-space-md w-[18%] font-semibold" scope="col">Aktivitas (Peristiwa)</th>
                    <th class="py-3.5 px-space-md w-[30%] font-semibold" scope="col">Keterangan & Naskah</th>
                    <th class="py-3.5 px-space-md w-[14%] font-semibold text-right" scope="col">Alamat IP & Klien</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20 font-body-md text-body-md text-on-surface">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center">
                            <div class="max-w-xs mx-auto flex flex-col items-center">
                                <div class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center text-secondary mb-3">
                                    <span class="material-symbols-outlined text-[24px]">search_off</span>
                                </div>
                                <p class="font-headline-sm text-headline-sm font-semibold text-on-surface">Tidak ada log aktivitas yang cocok</p>
                                <p class="font-body-sm text-body-sm text-secondary mt-1">Coba sesuaikan kata kunci pencarian atau ubah penyaring peristiwa.</p>
                                <?php if ($isFiltered): ?>
                                    <div class="mt-3">
                                        <a href="audit_trail.php" class="font-label-md text-label-md font-semibold text-primary hover:underline">
                                            Tampilkan Seluruh Entri Log
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                            <!-- Waktu Kejadian -->
                            <td class="py-3.5 px-space-md align-top whitespace-nowrap">
                                <div class="font-semibold text-on-surface font-body-sm text-body-sm">
                                    <?= date('d M Y', strtotime($log['created_at'])) ?>
                                </div>
                                <div class="font-code-audit text-code-audit text-secondary mt-0.5">
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?> WIB
                                </div>
                            </td>

                            <!-- Aktor / Pengguna -->
                            <td class="py-3.5 px-space-md align-top">
                                <?php if (!empty($log['user_name'])): ?>
                                    <div class="flex items-center gap-space-sm">
                                        <div class="w-8 h-8 rounded-full bg-secondary-container text-primary flex items-center justify-center font-label-md text-label-md font-bold shrink-0">
                                            <?= strtoupper(substr($log['user_name'], 0, 1)) ?>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-on-surface leading-snug">
                                                <?= htmlspecialchars($log['user_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <div class="flex items-center gap-1.5 font-code-audit-sm text-code-audit-sm text-secondary mt-0.5">
                                                <span class="capitalize px-1.5 py-0.2 bg-surface-container rounded text-on-surface-variant font-medium">
                                                    <?= htmlspecialchars($log['user_role'] ?? 'user', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <?php if (!empty($log['identifier_number'])): ?>
                                                    <span>ID: <?= htmlspecialchars($log['identifier_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-block px-2.5 py-0.5 bg-surface-container text-secondary rounded font-label-sm text-label-sm font-medium">
                                        Sistem Otomatis
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Aktivitas / Peristiwa -->
                            <td class="py-3.5 px-space-md align-top">
                                <?php
                                $act = $log['action'];
                                $badgeStyle = $actionMeta[$act]['class'] ?? 'bg-surface-container text-secondary font-medium';
                                $actLabel   = $actionMeta[$act]['label'] ?? $act;
                                $actIcon    = $actionMeta[$act]['icon'] ?? 'history';
                                ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full <?= $badgeStyle ?> font-label-sm text-label-sm whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[14px]"><?= $actIcon ?></span>
                                    <span><?= htmlspecialchars($actLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                                <div class="font-code-audit-sm text-code-audit-sm text-secondary mt-1">
                                    [<?= htmlspecialchars($act, ENT_QUOTES, 'UTF-8') ?>]
                                </div>
                            </td>

                            <!-- Keterangan & Naskah -->
                            <td class="py-3.5 px-space-md align-top">
                                <div class="text-on-surface font-body-sm text-body-sm leading-relaxed">
                                    <?= htmlspecialchars($log['details'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if (!empty($log['doc_title'])): ?>
                                    <div class="mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-1 bg-surface-container-low rounded-lg border border-outline-variant/20 font-body-sm text-body-sm text-on-surface">
                                        <span class="material-symbols-outlined text-[16px] text-error shrink-0">picture_as_pdf</span>
                                        <span class="truncate max-w-[280px]" title="<?= htmlspecialchars($log['doc_title'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($log['doc_title'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Alamat IP & Klien -->
                            <td class="py-3.5 px-space-md align-top text-right">
                                <div class="font-code-audit text-code-audit font-semibold text-primary bg-surface-container-low px-2 py-0.5 rounded border border-outline-variant/20 inline-block">
                                    <?= htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if (!empty($log['user_agent'])): ?>
                                    <div class="font-code-audit-sm text-code-audit-sm text-secondary truncate max-w-[170px] mt-1 ml-auto" title="<?= htmlspecialchars($log['user_agent'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(substr($log['user_agent'], 0, 32) . '...', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
