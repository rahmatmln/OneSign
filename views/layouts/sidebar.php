<?php
/**
 * OneSign - Layout Sidebar Navigation
 * Design Stitch: Institutional Trust Modernism (100% Identik)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$userRole = $_SESSION['user_role'] ?? '';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<aside class="fixed left-0 top-0 h-screen w-64 bg-surface-container-lowest border-r border-outline-variant/30 z-50 flex flex-col justify-between shadow-[0_1px_8px_rgba(0,0,0,0.03)] select-none">
    <div class="flex flex-col">
        <!-- Brand Header (Wordmark OneSign persis login page) -->
        <div class="h-16 px-space-lg flex items-center border-b border-outline-variant/20 bg-surface-container-lowest">
            <span class="font-headline-lg text-headline-lg font-bold text-primary tracking-tight">OneSign</span>
        </div>

        <!-- Navigation Group 1: Menu -->
        <div class="px-space-md pt-space-lg pb-space-xs">
            <p class="px-space-sm font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-xs text-[11px] font-semibold">Navigasi Menu</p>
        </div>

        <nav class="px-space-sm space-y-1">
            <?php if ($userRole === 'pemohon'): ?>
                <!-- Dashboard Pemohon -->
                <a href="<?= BASE_URL ?>/views/pemohon/dashboard.php" 
                   class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg font-label-md text-label-md transition-all <?= ($currentScript === 'dashboard.php') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <span class="material-symbols-outlined text-[20px]">space_dashboard</span>
                    <span>Dashboard Pemohon</span>
                </a>
                
                <!-- Ajukan Dokumen -->
                <a href="<?= BASE_URL ?>/views/pemohon/upload.php" 
                   class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg font-label-md text-label-md transition-all <?= ($currentScript === 'upload.php') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <span class="material-symbols-outlined text-[20px]">post_add</span>
                    <span>Ajukan Dokumen</span>
                </a>
            <?php endif; ?>

            <?php if ($userRole === 'penandatangan'): ?>
                <!-- Workspace Penandatangan -->
                <a href="<?= BASE_URL ?>/views/penandatangan/dashboard.php" 
                   class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg font-label-md text-label-md transition-all <?= ($currentScript === 'dashboard.php' || $currentScript === 'sign_workspace.php') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <span class="material-symbols-outlined text-[20px]">draw</span>
                    <span>Workspace Penandatangan</span>
                </a>
            <?php endif; ?>

            <?php if ($userRole === 'admin'): ?>
                <!-- 1. Riwayat & Arsip Berkas -->
                <a href="<?= BASE_URL ?>/views/admin/dashboard.php" 
                   class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg font-label-md text-label-md transition-all <?= ($currentScript === 'dashboard.php') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <span class="material-symbols-outlined text-[20px]">folder_supervised</span>
                    <span>Riwayat &amp; Arsip Berkas</span>
                </a>

                <!-- 2. Audit Trail & Security Log -->
                <a href="<?= BASE_URL ?>/views/admin/audit_trail.php" 
                   class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg font-label-md text-label-md transition-all <?= ($currentScript === 'audit_trail.php') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <span class="material-symbols-outlined text-[20px]">verified_user</span>
                    <span>Audit Trail &amp; Security Log</span>
                </a>

                <!-- 3. Manajemen Akun -->
                <a href="<?= BASE_URL ?>/views/admin/users.php" 
                   class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg font-label-md text-label-md transition-all <?= ($currentScript === 'users.php') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
                    <span>Manajemen Akun</span>
                </a>

                <!-- 4. Master Data Dokumen -->
                <a href="<?= BASE_URL ?>/views/admin/master_data.php" 
                   class="flex items-center gap-space-sm px-space-md py-space-sm rounded-lg font-label-md text-label-md transition-all <?= ($currentScript === 'master_data.php') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
                    <span class="material-symbols-outlined text-[20px]">tune</span>
                    <span>Master Data Dokumen</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</aside>
