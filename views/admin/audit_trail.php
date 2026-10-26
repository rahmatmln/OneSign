<?php
/**
 * OneSign - Log Audit Trail (Staf Administrasi)
 * Menampilkan pencatatan riwayat kronologis immutable dengan filter peristiwa (action)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Log Audit Trail';
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

// Pemetaan label peristiwa (action) dan skema warnanya
$actionMeta = [
    'SIGN_DOC'     => ['label' => 'Tanda Tangan TTE',    'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
    'UPLOAD_DOC'   => ['label' => 'Unggah Berkas',       'class' => 'bg-blue-50 text-blue-800 border-blue-200'],
    'DOWNLOAD_DOC' => ['label' => 'Unduh Berkas',        'class' => 'bg-purple-50 text-purple-800 border-purple-200'],
    'REJECT_DOC'   => ['label' => 'Penolakan Berkas',    'class' => 'bg-rose-50 text-rose-800 border-rose-200'],
    'PREVIEW_DOC'  => ['label' => 'Pratinjau Dokumen',   'class' => 'bg-cyan-50 text-cyan-800 border-cyan-200'],
    'LOGIN'        => ['label' => 'Autentikasi Masuk',   'class' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
    'LOGOUT'       => ['label' => 'Sesi Keluar',         'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
];

// Menghitung distribusi entri per aksi untuk pencacah dropdown
$countStmt = $pdo->query("SELECT action, COUNT(*) AS total FROM audit_logs GROUP BY action");
$actionCounts = [];
$totalAllEntries = 0;
while ($row = $countStmt->fetch()) {
    $actionCounts[$row['action']] = (int)$row['total'];
    $totalAllEntries += (int)$row['total'];
}

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

<!-- Header Section -->
<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-lg font-bold text-slate-900">Log Audit Trail Forensik (Immutable)</h1>
        <p class="text-xs text-slate-500 mt-0.5">Rekam jejak seluruh transaksi pengesahan dokumen, pengunduhan berkas, dan riwayat sesi pengguna.</p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($isFiltered): ?>
            <a href="audit_trail.php" class="text-xs text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 font-medium px-3 py-1.5 rounded transition">
                Reset Filter
            </a>
        <?php endif; ?>
        <div class="text-xs font-mono text-slate-600 bg-slate-100 px-3 py-1.5 rounded border border-slate-200">
            Ditemukan: <span class="font-bold text-slate-900"><?= count($logs) ?></span> dari <?= $totalAllEntries ?> entri
        </div>
    </div>
</div>

<!-- Panel Filter Interaktif (Dropdown Aksi, Rentang Tanggal, & Pencarian) -->
<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
    <form method="GET" action="audit_trail.php" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Dropdown Jenis Peristiwa (Action) -->
            <div>
                <label for="action" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Jenis Peristiwa (Aksi)
                </label>
                <div class="relative">
                    <select 
                        id="action" 
                        name="action" 
                        class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-800 focus:border-blue-800 transition"
                    >
                        <option value="">Semua Peristiwa (<?= $totalAllEntries ?> entri)</option>
                        <?php foreach ($actionMeta as $actKey => $actInfo): ?>
                            <?php $count = $actionCounts[$actKey] ?? 0; ?>
                            <option value="<?= $actKey ?>" <?= ($selectedAction === $actKey) ? 'selected' : '' ?>>
                                <?= $actInfo['label'] ?> (<?= $count ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 2. Rentang Waktu: Dari Tanggal -->
            <div>
                <label for="start_date" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Dari Tanggal
                </label>
                <input 
                    type="date" 
                    id="start_date" 
                    name="start_date" 
                    value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-800 focus:border-blue-800 transition"
                >
            </div>

            <!-- 3. Rentang Waktu: Sampai Tanggal -->
            <div>
                <label for="end_date" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Sampai Tanggal
                </label>
                <input 
                    type="date" 
                    id="end_date" 
                    name="end_date" 
                    value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-800 focus:border-blue-800 transition"
                >
            </div>

            <!-- 4. Kata Kunci Pencarian -->
            <div>
                <label for="q" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Kata Kunci Pencarian
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        id="q" 
                        name="q" 
                        value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" 
                        placeholder="Nama, judul berkas, IP..." 
                        class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-800 focus:border-blue-800 text-slate-800 placeholder-slate-400 transition"
                    >
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Filter -->
        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="text-[11px] text-slate-500">
                <?php if ($isFiltered): ?>
                    <span class="inline-flex items-center gap-1.5 font-medium text-blue-900 bg-blue-50 px-2.5 py-1 rounded border border-blue-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filter aktif diterapkan
                    </span>
                <?php else: ?>
                    <span>Menampilkan seluruh rekam jejak sistem secara terbalik kronologis.</span>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($isFiltered): ?>
                    <a href="audit_trail.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg text-center transition">
                        Bersihkan Filter
                    </a>
                <?php endif; ?>
                <button type="submit" class="px-4 py-2 bg-blue-900 hover:bg-blue-950 text-white text-xs font-semibold rounded-lg transition shadow-xs flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span>Terapkan Filter</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Tabel Rekam Jejak Audit -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700 min-w-[950px]">
            <thead class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-800 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="py-3.5 px-4 w-[16%]">Waktu Kejadian (WIB)</th>
                    <th class="py-3.5 px-4 w-[20%]">Aktor / Pengguna</th>
                    <th class="py-3.5 px-4 w-[18%]">Peristiwa (Action)</th>
                    <th class="py-3.5 px-4 w-[32%]">Keterangan & Rincian</th>
                    <th class="py-3.5 px-4 w-[14%]">Alamat IP & Jaringan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-500 font-sans">
                            <div class="max-w-xs mx-auto">
                                <p class="text-sm font-semibold text-slate-700">Tidak ada log aktivitas yang cocok</p>
                                <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau ubah penyaring peristiwa.</p>
                                <?php if ($isFiltered): ?>
                                    <div class="mt-3">
                                        <a href="audit_trail.php" class="text-xs font-semibold text-blue-900 hover:underline">Tampilkan Seluruh Entri Log</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-slate-50/80 transition items-start">
                            <!-- Waktu Kejadian -->
                            <td class="py-3.5 px-4 align-top">
                                <div class="font-medium text-slate-900">
                                    <?= date('d M Y', strtotime($log['created_at'])) ?>
                                </div>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5">
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?> WIB
                                </div>
                            </td>

                            <!-- Aktor / Pengguna -->
                            <td class="py-3.5 px-4 align-top font-sans">
                                <?php if (!empty($log['user_name'])): ?>
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($log['user_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1.5">
                                        <span class="capitalize px-1.5 py-0.2 bg-slate-100 rounded text-slate-600 font-medium">
                                            <?= htmlspecialchars($log['user_role'] ?? 'user', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <?php if (!empty($log['identifier_number'])): ?>
                                            <span class="font-mono text-[10px] text-slate-400">ID: <?= htmlspecialchars($log['identifier_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[11px] font-medium">Sistem / Tamu</span>
                                <?php endif; ?>
                            </td>

                            <!-- Peristiwa (Action) -->
                            <td class="py-3.5 px-4 align-top">
                                <?php
                                $act = $log['action'];
                                $badgeStyle = $actionMeta[$act]['class'] ?? 'bg-slate-100 text-slate-800 border-slate-200';
                                $actLabel   = $actionMeta[$act]['label'] ?? $act;
                                ?>
                                <span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold border <?= $badgeStyle ?>">
                                    <?= htmlspecialchars($actLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="text-[10px] font-mono text-slate-400 mt-1">
                                    [<?= htmlspecialchars($act, ENT_QUOTES, 'UTF-8') ?>]
                                </div>
                            </td>

                            <!-- Keterangan & Rincian -->
                            <td class="py-3.5 px-4 align-top font-sans">
                                <div class="text-slate-800 leading-relaxed font-normal">
                                    <?= htmlspecialchars($log['details'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if (!empty($log['doc_title'])): ?>
                                    <div class="mt-1.5 inline-flex items-center gap-1.5 px-2 py-0.5 bg-slate-100 rounded border border-slate-200 text-[11px] text-slate-700">
                                        <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="truncate max-w-[280px]" title="<?= htmlspecialchars($log['doc_title'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($log['doc_title'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Alamat IP & Jaringan -->
                            <td class="py-3.5 px-4 align-top">
                                <div class="font-mono text-[11px] font-semibold text-slate-700 bg-slate-50 px-2 py-1 rounded border border-slate-200 inline-block">
                                    <?= htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if (!empty($log['user_agent'])): ?>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[150px] mt-1" title="<?= htmlspecialchars($log['user_agent'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(substr($log['user_agent'], 0, 30) . '...', ENT_QUOTES, 'UTF-8') ?>
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
