<?php
/**
 * OneSign - Manajemen Akun Pengguna (Admin)
 * Design Stitch: Institutional Trust Modernism (100% Identik)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Manajemen Akun Pengguna';
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

// Query seluruh pengguna sistem
$stmt = $pdo->query("
    SELECT id, full_name, email, role, identifier_number, department, is_active, created_at
    FROM users
    ORDER BY is_active DESC, role ASC, full_name ASC
");
$usersList = $stmt->fetchAll();

// Hitung metrik pengguna
$totalUsers     = count($usersList);
$activeStudents = 0;
$totalLecturers = 0;
$totalStudents  = 0;
$totalAdmins    = 0;

$departments = [];
foreach ($usersList as $u) {
    if ($u['role'] === 'pemohon') {
        $totalStudents++;
        if ($u['is_active'] == 1) $activeStudents++;
    } elseif ($u['role'] === 'penandatangan') {
        $totalLecturers++;
    } elseif ($u['role'] === 'admin') {
        $totalAdmins++;
    }
    if (!empty($u['department']) && !in_array($u['department'], $departments, true)) {
        $departments[] = $u['department'];
    }
}
sort($departments);

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- Header Section with Breadcrumb & Context -->
<div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md mb-space-lg">
    <div class="flex flex-col gap-space-xs">
        <div class="flex items-center gap-space-xs text-secondary font-label-sm text-label-sm uppercase tracking-wider text-[11px]">
            <span>Portal Administrasi</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-primary font-semibold">Manajemen Pengguna</span>
        </div>
        <h1 class="font-display-lg text-display-lg text-on-surface tracking-tight font-bold text-2xl md:text-3xl">
            Manajemen Akun Pengguna
        </h1>
        <p class="font-body-md text-body-md text-secondary">
            Pengelolaan hak akses terpusat untuk Mahasiswa (Pemohon) dan Dosen (Penandatangan) Jurusan TIK.
        </p>
    </div>
    
    <!-- Quick Action Utilities -->
    <div class="flex items-center gap-space-sm self-start md:self-auto">
        <button type="button" 
                id="btnOpenModal"
                class="inline-flex items-center gap-space-xs px-space-md py-2.5 bg-primary text-on-primary rounded-lg font-label-md text-label-md font-semibold shadow-sm hover:bg-primary-container transition-all">
            <span class="material-symbols-outlined text-[20px]">person_add</span>
            <span>Tambah Pengguna Baru</span>
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
    <!-- Card 1: Total Pengguna -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Total Pengguna</span>
                <span class="font-display-lg text-display-lg font-bold text-on-surface"><?= $totalUsers ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">groups</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Mahasiswa Aktif -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Mahasiswa Aktif</span>
                <span class="font-display-lg text-display-lg font-bold text-emerald-700"><?= $activeStudents ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                <span class="material-symbols-outlined text-[20px]">school</span>
            </div>
        </div>
    </div>

    <!-- Card 3: Dosen Penandatangan -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Dosen Penandatangan</span>
                <span class="font-display-lg text-display-lg font-bold text-primary"><?= $totalLecturers ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-secondary-container flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">badge</span>
            </div>
        </div>
    </div>

    <!-- Card 4: Administrator TIK -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl border border-outline-variant/20 shadow-sm flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Administrator TIK</span>
                <span class="font-display-lg text-display-lg font-bold text-amber-700"><?= $totalAdmins ?></span>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700">
                <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Action Controls Section -->
<div class="bg-surface-container-lowest rounded-xl p-space-md border border-outline-variant/20 shadow-sm flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-space-md mb-space-md">
    <!-- Segmented Role Tabs on Left -->
    <div class="inline-flex p-1 bg-surface-container rounded-lg self-start">
        <button type="button" id="tabAll" class="px-space-md py-1.5 rounded-md font-label-md text-label-md bg-surface-container-lowest text-primary shadow-sm flex items-center gap-2 transition-all font-semibold">
            <span>Semua Pengguna</span>
            <span class="px-1.5 py-0.2 rounded-full bg-primary text-on-primary font-code-audit-sm text-[11px]"><?= $totalUsers ?></span>
        </button>
        <button type="button" id="tabStudent" class="px-space-md py-1.5 rounded-md font-label-md text-label-md text-on-surface-variant hover:text-on-surface flex items-center gap-2 transition-all font-semibold">
            <span>Mahasiswa (Pemohon)</span>
            <span class="px-1.5 py-0.2 rounded-full bg-surface-variant text-on-surface-variant font-code-audit-sm text-[11px]"><?= $totalStudents ?></span>
        </button>
        <button type="button" id="tabLecturer" class="px-space-md py-1.5 rounded-md font-label-md text-label-md text-on-surface-variant hover:text-on-surface flex items-center gap-2 transition-all font-semibold">
            <span>Dosen (Penandatangan)</span>
            <span class="px-1.5 py-0.2 rounded-full bg-surface-variant text-on-surface-variant font-code-audit-sm text-[11px]"><?= $totalLecturers ?></span>
        </button>
    </div>

    <!-- Right: Search & Department Filter -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-space-sm">
        <div class="relative flex items-center min-w-[280px]">
            <span class="material-symbols-outlined absolute left-3 text-secondary text-[20px] pointer-events-none">search</span>
            <input type="text" 
                   id="searchInput" 
                   placeholder="Cari nama, NIM, atau NIP..." 
                   class="w-full h-10 pl-10 pr-space-md bg-surface-container-low rounded-lg font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary transition-all">
        </div>
        
        <div class="relative inline-block min-w-[220px]">
            <select id="departmentFilter" class="w-full h-10 px-3 pr-8 bg-surface-container-low hover:bg-surface-container rounded-lg font-label-md text-label-md text-on-surface appearance-none focus:outline-none focus:ring-1 focus:ring-primary transition-colors cursor-pointer">
                <option value="">Semua Fakultas / Prodi</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <span class="material-symbols-outlined absolute right-2.5 top-2.5 text-secondary text-[18px] pointer-events-none">expand_more</span>
        </div>
    </div>
</div>

<!-- Users Table Container -->
<div class="bg-surface-container-lowest rounded-xl border border-outline-variant/20 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="usersTable">
            <thead>
                <tr class="border-b border-outline-variant/20 bg-surface-container-low/40 text-on-surface font-label-md text-label-md text-xs uppercase tracking-wider font-semibold">
                    <th class="py-3 px-space-md">Pengguna</th>
                    <th class="py-3 px-space-md">NIM / NIP</th>
                    <th class="py-3 px-space-md">Peran</th>
                    <th class="py-3 px-space-md">Jurusan / Program Studi</th>
                    <th class="py-3 px-space-md">Status Akun</th>
                    <th class="py-3 px-space-md text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10 text-on-surface font-body-sm text-body-sm">
                <?php if (empty($usersList)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-secondary">
                            Belum ada akun pengguna terdaftar pada sistem.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usersList as $u): 
                        $roleBadge = match($u['role']) {
                            'pemohon'        => ['label' => 'Mahasiswa', 'bg' => 'bg-secondary-fixed text-on-secondary-fixed'],
                            'penandatangan' => ['label' => 'Dosen', 'bg' => 'bg-primary-fixed text-on-primary-fixed'],
                            'admin'          => ['label' => 'Administrator', 'bg' => 'bg-surface-container-high text-primary']
                        };
                        $isActive = ((int)$u['is_active'] === 1);
                        $initials = strtoupper(substr($u['full_name'], 0, 2));
                    ?>
                        <tr class="hover:bg-surface-container-low/60 transition-colors user-row" 
                            data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>"
                            data-department="<?= htmlspecialchars($u['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-search="<?= htmlspecialchars(strtolower($u['full_name'] . ' ' . $u['email'] . ' ' . ($u['identifier_number'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                            
                            <!-- Pengguna -->
                            <td class="py-3.5 px-space-md">
                                <div class="flex items-center gap-space-sm">
                                    <div class="w-9 h-9 rounded-lg bg-surface-container-high flex items-center justify-center text-primary font-bold text-xs shrink-0">
                                        <?= $initials ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-label-md text-label-md text-primary font-semibold">
                                            <?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="font-code-audit text-code-audit-sm text-secondary text-xs">
                                            <?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- NIM / NIP -->
                            <td class="py-3.5 px-space-md font-code-audit text-xs text-on-surface">
                                <?= !empty($u['identifier_number']) ? htmlspecialchars($u['identifier_number'], ENT_QUOTES, 'UTF-8') : '<span class="text-secondary">-</span>' ?>
                            </td>

                            <!-- Peran -->
                            <td class="py-3.5 px-space-md">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full font-label-sm text-label-sm text-xs font-semibold <?= $roleBadge['bg'] ?>">
                                    <?= $roleBadge['label'] ?>
                                </span>
                            </td>

                            <!-- Jurusan / Prodi -->
                            <td class="py-3.5 px-space-md text-on-surface-variant">
                                <?= htmlspecialchars($u['department'] ?: 'Teknik Informatika dan Komputer', ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <!-- Status Akun -->
                            <td class="py-3.5 px-space-md">
                                <?php if ($isActive): ?>
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
                                    <!-- Edit User -->
                                    <button type="button" 
                                            class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded-lg transition-colors btn-edit-user"
                                            title="Edit Data Pengguna"
                                            data-id="<?= $u['id'] ?>"
                                            data-fullname="<?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-identifier="<?= htmlspecialchars($u['identifier_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-department="<?= htmlspecialchars($u['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>

                                    <!-- Reset Password -->
                                    <form method="POST" action="<?= BASE_URL ?>/actions/user_action.php" class="inline" onsubmit="return confirm('Reset kata sandi akun <?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?> ke default (password123)?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" 
                                                class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded-lg transition-colors"
                                                title="Reset Sandi ke password123">
                                            <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                                        </button>
                                    </form>

                                    <!-- Toggle Status (Soft Delete) -->
                                    <?php if ($u['id'] !== (int)$activeUser['id']): ?>
                                        <form method="POST" action="<?= BASE_URL ?>/actions/user_action.php" class="inline" onsubmit="return confirm('<?= $isActive ? 'Nonaktifkan akun ini? Pengguna tidak akan dapat masuk ke sistem.' : 'Aktifkan kembali akun ini?' ?>');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" 
                                                    class="p-1.5 rounded-lg transition-colors <?= $isActive ? 'text-on-surface-variant hover:text-error hover:bg-error-container/30' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container' ?>"
                                                    title="<?= $isActive ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>">
                                                <span class="material-symbols-outlined text-[18px]"><?= $isActive ? 'power_settings_new' : 'check_circle' ?></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Dialog: Tambah / Edit Pengguna (Stitch 100% Identik) -->
<div id="userModal" class="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 backdrop-blur-sm p-4 hidden">
    <div class="bg-surface-container-lowest rounded-xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col transform transition-all border border-outline-variant/30">
        
        <!-- Modal Header -->
        <div class="px-space-lg py-space-md bg-surface-container-low/70 flex items-start justify-between border-b border-outline-variant/20">
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                    <h2 id="modalTitle" class="font-headline-lg text-lg font-bold text-on-surface tracking-tight">Tambah Pengguna Baru</h2>
                </div>
                <p id="modalSubtitle" class="font-body-sm text-body-sm text-secondary mt-0.5">
                    Lengkapi data kredensial untuk mendaftarkan akun portal resmi OneSign.
                </p>
            </div>
            <button type="button" id="btnCloseModal" class="p-1 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Modal Body Form -->
        <form method="POST" action="<?= BASE_URL ?>/actions/user_action.php" id="formUser" class="p-space-lg flex flex-col gap-space-md">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="user_id" id="formUserId" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
                <!-- Field 1: Full Name -->
                <div class="flex flex-col gap-1.5 md:col-span-2">
                    <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Nama Lengkap &amp; Gelar <span class="text-error">*</span></label>
                    <input type="text" 
                           name="full_name" 
                           id="inputFullName" 
                           required 
                           placeholder="e.g. Dr. Budi Santoso, S.T., M.T."
                           class="w-full h-10 px-3 bg-surface-container-low rounded-lg font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary transition-all">
                </div>

                <!-- Field 2: Campus Email -->
                <div class="flex flex-col gap-1.5">
                    <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Email Kampus Resmi <span class="text-error">*</span></label>
                    <div class="relative flex items-center">
                        <span class="material-symbols-outlined absolute left-3 text-secondary text-[18px] pointer-events-none">mail</span>
                        <input type="email" 
                               name="email" 
                               id="inputEmail" 
                               required 
                               placeholder="user@pnj.ac.id"
                               class="w-full h-10 pl-9 pr-3 bg-surface-container-low rounded-lg font-code-audit text-xs text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary transition-all">
                    </div>
                </div>

                <!-- Field 3: Identifier (NIM/NIP) -->
                <div class="flex flex-col gap-1.5">
                    <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">NIM / NIP Identitas</label>
                    <div class="relative flex items-center">
                        <span class="material-symbols-outlined absolute left-3 text-secondary text-[18px] pointer-events-none">badge</span>
                        <input type="text" 
                               name="identifier_number" 
                               id="inputIdentifier" 
                               placeholder="198503152010..."
                               class="w-full h-10 pl-9 pr-3 bg-surface-container-low rounded-lg font-code-audit text-xs text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary transition-all">
                    </div>
                </div>

                <!-- Field 4: Role Selection -->
                <div class="flex flex-col gap-1.5">
                    <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Peran Pengguna (Role) <span class="text-error">*</span></label>
                    <div class="relative flex items-center">
                        <select name="role" 
                                id="inputRole" 
                                required
                                class="w-full h-10 px-3 pr-8 bg-surface-container-low rounded-lg font-body-md text-body-md text-on-surface appearance-none focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary transition-all cursor-pointer">
                            <option value="pemohon">Mahasiswa (Pemohon Dokumen)</option>
                            <option value="penandatangan">Dosen (Penandatangan Dokumen)</option>
                            <option value="admin">Administrator (Staf Administrasi)</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-2.5 text-secondary text-[18px] pointer-events-none">expand_more</span>
                    </div>
                </div>

                <!-- Field 5: Faculty / Department -->
                <div class="flex flex-col gap-1.5">
                    <label class="font-label-md text-label-md text-on-surface font-semibold text-xs">Fakultas / Program Studi</label>
                    <input type="text" 
                           name="department" 
                           id="inputDepartment" 
                           value="Teknik Informatika dan Komputer"
                           placeholder="Teknik Informatika dan Komputer"
                           class="w-full h-10 px-3 bg-surface-container-low rounded-lg font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary transition-all">
                </div>
            </div>

            <!-- Field 6: Password Info Banner (Pilihan A: password123 standar) -->
            <div id="passwordInfoBox" class="flex flex-col gap-1.5 pt-1">
                <label class="font-label-md text-label-md text-on-surface flex items-center justify-between font-semibold text-xs">
                    <span>Kata Sandi Bawaan Akun Baru</span>
                    <span class="text-primary font-code-audit-sm text-[11px] font-semibold">Standar Kampus</span>
                </label>
                <div class="flex items-center gap-space-sm">
                    <div class="relative flex-1 flex items-center">
                        <span class="material-symbols-outlined absolute left-3 text-secondary text-[18px]">key</span>
                        <input type="text" 
                               readonly 
                               value="password123" 
                               class="w-full h-10 pl-9 pr-3 bg-surface-container font-code-audit text-xs font-semibold text-primary rounded-lg focus:outline-none cursor-default select-all">
                    </div>
                </div>
                <span class="font-body-sm text-body-sm text-secondary text-[11px]">
                    Pengguna dapat langsung masuk dengan kata sandi di atas dan dianjurkan memperbaruinya saat login perdana.
                </span>
            </div>

            <!-- Modal Footer Actions -->
            <div class="flex items-center justify-end gap-space-sm pt-space-sm border-t border-outline-variant/10 mt-space-xs">
                <button type="button" 
                        id="btnCancelModal"
                        class="px-space-md py-2 rounded-lg bg-surface-container text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-colors font-medium">
                    Batal
                </button>
                <button type="submit" 
                        id="btnSubmitModal"
                        class="px-space-md py-2 rounded-lg bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container transition-colors shadow-sm font-semibold">
                    Simpan Akun
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('userModal');
    const btnOpen = document.getElementById('btnOpenModal');
    const btnClose = document.getElementById('btnCloseModal');
    const btnCancel = document.getElementById('btnCancelModal');
    const form = document.getElementById('formUser');
    const formAction = document.getElementById('formAction');
    const formUserId = document.getElementById('formUserId');
    const modalTitle = document.getElementById('modalTitle');
    const passwordInfoBox = document.getElementById('passwordInfoBox');

    const inputFullName = document.getElementById('inputFullName');
    const inputEmail = document.getElementById('inputEmail');
    const inputIdentifier = document.getElementById('inputIdentifier');
    const inputRole = document.getElementById('inputRole');
    const inputDepartment = document.getElementById('inputDepartment');

    function openCreateModal() {
        form.reset();
        formAction.value = 'create';
        formUserId.value = '';
        inputDepartment.value = 'Teknik Informatika dan Komputer';
        modalTitle.textContent = 'Tambah Pengguna Baru';
        passwordInfoBox.classList.remove('hidden');
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    btnOpen.addEventListener('click', openCreateModal);
    btnClose.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);

    // Edit User Modal
    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            formAction.value = 'edit';
            formUserId.value = this.dataset.id;
            inputFullName.value = this.dataset.fullname;
            inputEmail.value = this.dataset.email;
            inputIdentifier.value = this.dataset.identifier;
            inputRole.value = this.dataset.role;
            inputDepartment.value = this.dataset.department || 'Teknik Informatika dan Komputer';

            modalTitle.textContent = 'Edit Data Pengguna';
            passwordInfoBox.classList.add('hidden'); // Sembunyikan banner sandi saat edit
            modal.classList.remove('hidden');
        });
    });

    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    // Tabs Filtering (Semua, Mahasiswa, Dosen)
    const tabAll = document.getElementById('tabAll');
    const tabStudent = document.getElementById('tabStudent');
    const tabLecturer = document.getElementById('tabLecturer');
    const rows = document.querySelectorAll('.user-row');
    const searchInput = document.getElementById('searchInput');
    const deptFilter = document.getElementById('departmentFilter');

    let currentRoleFilter = 'all';

    function setTabActive(btnActive) {
        [tabAll, tabStudent, tabLecturer].forEach(b => {
            b.className = 'px-space-md py-1.5 rounded-md font-label-md text-label-md text-on-surface-variant hover:text-on-surface flex items-center gap-2 transition-all font-semibold';
        });
        btnActive.className = 'px-space-md py-1.5 rounded-md font-label-md text-label-md bg-surface-container-lowest text-primary shadow-sm flex items-center gap-2 transition-all font-semibold';
    }

    tabAll.addEventListener('click', function() {
        currentRoleFilter = 'all';
        setTabActive(this);
        applyFilters();
    });

    tabStudent.addEventListener('click', function() {
        currentRoleFilter = 'pemohon';
        setTabActive(this);
        applyFilters();
    });

    tabLecturer.addEventListener('click', function() {
        currentRoleFilter = 'penandatangan';
        setTabActive(this);
        applyFilters();
    });

    searchInput.addEventListener('input', applyFilters);
    deptFilter.addEventListener('change', applyFilters);

    function applyFilters() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedDept = deptFilter.value.trim();

        rows.forEach(row => {
            const role = row.dataset.role;
            const dept = row.dataset.department;
            const searchData = row.dataset.search;

            const matchRole = (currentRoleFilter === 'all' || role === currentRoleFilter);
            const matchSearch = (!query || searchData.includes(query));
            const matchDept = (!selectedDept || dept === selectedDept);

            if (matchRole && matchSearch && matchDept) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
