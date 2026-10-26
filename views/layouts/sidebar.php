<?php
/**
 * OneSign - Layout Sidebar Navigation
 * Menu berorientasi pada peran pengguna (RBAC)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$userRole = $_SESSION['user_role'] ?? '';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-4">
    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-2">
        Menu Utama
    </div>

    <nav class="space-y-1 text-xs font-semibold">
        
        <?php if ($userRole === 'pemohon'): ?>
            <a href="<?= BASE_URL ?>/views/pemohon/dashboard.php" 
               class="block px-3 py-2 rounded transition <?= $currentScript === 'dashboard.php' ? 'bg-blue-800 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>">
                Dashboard Pemohon
            </a>
            <a href="<?= BASE_URL ?>/views/pemohon/upload.php" 
               class="block px-3 py-2 rounded transition <?= $currentScript === 'upload.php' ? 'bg-blue-800 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>">
                Ajukan Permohonan PDF
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'penandatangan'): ?>
            <a href="<?= BASE_URL ?>/views/penandatangan/dashboard.php" 
               class="block px-3 py-2 rounded transition <?= $currentScript === 'dashboard.php' ? 'bg-blue-800 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>">
                Dashboard Penandatangan
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'admin'): ?>
            <a href="<?= BASE_URL ?>/views/admin/dashboard.php" 
               class="block px-3 py-2 rounded transition <?= $currentScript === 'dashboard.php' ? 'bg-blue-800 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>">
                Rekap Arsip Dokumen
            </a>
            <a href="<?= BASE_URL ?>/views/admin/audit_trail.php" 
               class="block px-3 py-2 rounded transition <?= $currentScript === 'audit_trail.php' ? 'bg-blue-800 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>">
                Log Audit Trail
            </a>
        <?php endif; ?>

    </nav>

    <!-- Info Box Status Instansi -->
    <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1 px-2">
        <div class="font-semibold text-slate-700">Jurusan TIK PNJ</div>
        <div>Sistem Pengesahan Internal</div>
        <div class="text-[10px] text-slate-400">Versi 1.0 (PBL 2026)</div>
    </div>
</div>
