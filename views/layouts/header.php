<?php
/**
 * OneSign - Layout Header & Top Navigation
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

$user = getActiveUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

// Format Peran untuk Tampilan
$roleLabels = [
    'pemohon'        => 'Pemohon Dokumen',
    'penandatangan'  => 'Dosen Penandatangan',
    'admin'          => 'Staf Administrasi & Arsip'
];
$displayRole = $roleLabels[$user['role']] ?? ucfirst($user['role']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?> - OneSign Jurusan TIK PNJ</title>
    <!-- Tailwind CSS (via CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Top Navigation Bar -->
    <header class="bg-blue-900 border-b border-blue-950 text-white sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
            
            <div class="flex items-center space-x-3">
                <a href="<?= BASE_URL ?>" class="flex items-center space-x-2">
                    <span class="w-8 h-8 rounded bg-blue-700 flex items-center justify-center font-bold text-white text-sm shadow-inner">
                        1S
                    </span>
                    <span class="font-bold text-lg tracking-tight text-white">OneSign</span>
                </a>
                <span class="hidden sm:inline-block text-xs bg-blue-950 px-2 py-0.5 rounded text-blue-200 border border-blue-800">
                    TIK PNJ
                </span>
            </div>

            <!-- User Session Details & Logout -->
            <div class="flex items-center space-x-4">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-semibold text-white leading-tight">
                        <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="text-[11px] text-blue-200 leading-tight">
                        <?= $displayRole ?> <?= !empty($user['identifier_number']) ? '• ' . htmlspecialchars($user['identifier_number'], ENT_QUOTES, 'UTF-8') : '' ?>
                    </div>
                </div>

                <a href="<?= BASE_URL ?>/actions/auth_action.php?action=logout" 
                   onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')"
                   class="px-2.5 py-1 text-xs font-semibold text-blue-100 bg-blue-950 border border-blue-800 rounded hover:bg-rose-900 hover:text-white hover:border-rose-700 transition">
                    Keluar
                </a>
            </div>

        </div>
    </header>

    <!-- Main Container Wrapper -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex-1">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Sidebar -->
            <aside class="lg:col-span-3">
                <?php require_once __DIR__ . '/sidebar.php'; ?>
            </aside>

            <!-- Main Content Area -->
            <main class="lg:col-span-9 space-y-6">
