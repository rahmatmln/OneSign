<?php
/**
 * OneSign - Layout Header & Global Theme (Stitch Institutional Modernism)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

// Verifikasi sesi pengguna
if (!isAuthenticated()) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

$user = getActiveUser();
$roleLabels = [
    'pemohon'        => 'Pemohon Dokumen',
    'penandatangan' => 'Dosen Penandatangan',
    'admin'          => 'Staf Administrasi'
];
$displayRole = $roleLabels[$user['role']] ?? ucfirst($user['role']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' - OneSign' : 'OneSign - Sistem TTE & Dynamic Watermark' ?></title>
    
    <!-- Google Fonts & Material Symbols (Stitch Design System) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <style>
        @layer base {
            html, body { margin: 0; padding: 0; }
            body { overscroll-behavior: none; }
            main > :first-child { margin-top: 0 !important; }
            main > :last-child { margin-bottom: 0 !important; }
        }
        ::-webkit-scrollbar { display: none; }
    </style>
    
    <?php require __DIR__ . '/tailwind_config.php'; ?>
</head>
<body class="bg-background font-body-md text-on-surface antialiased selection:bg-primary selection:text-on-primary">

    <!-- Fixed Left Sidebar (Design Stitch 100% Identik) -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main App Shell Offset (pl-64) -->
    <div class="pl-64 flex flex-col min-h-screen">
        
        <!-- Fixed Top Header (Design Stitch 100% Identik) -->
        <header class="fixed top-0 left-64 right-0 h-16 bg-surface-container-lowest/90 backdrop-blur-md border-b border-outline-variant/20 z-40 px-space-lg flex items-center justify-between">
            <!-- Left Header Area -->
            <div class="flex items-center gap-space-md"></div>

            <!-- Right: User Session Profile & Actions -->
            <div class="flex items-center gap-space-lg">
                <!-- User Profile -->
                <div class="flex items-center gap-space-sm">
                    <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-md text-label-md font-bold shrink-0">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <div class="flex flex-col text-left">
                        <span class="font-label-md text-label-md text-on-surface leading-tight font-semibold">
                            <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="font-body-sm text-body-sm text-secondary leading-tight">
                            <?= $displayRole ?> <?= !empty($user['identifier_number']) ? '• ' . htmlspecialchars($user['identifier_number'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </span>
                    </div>
                </div>

                <div class="h-6 w-px bg-outline-variant/30"></div>

                <!-- Logout Button -->
                <a href="<?= BASE_URL ?>/actions/auth_action.php?action=logout" 
                   onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')"
                   class="p-2 rounded-lg text-secondary hover:text-error hover:bg-error-container/30 transition-colors" 
                   title="Keluar dari Sistem">
                    <span class="material-symbols-outlined text-[20px]">logout</span>
                </a>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="relative pt-16 min-h-screen bg-background w-full flex-1">
            <div class="flex flex-col w-full">
                <div class="px-margin py-space-lg max-w-[1600px] w-full mx-auto space-y-space-lg">
