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
    
    <!-- Tailwind CSS (via CDN) with Stitch Tokens -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "background": "#f8f9ff",
                        "surface": "#f8f9ff",
                        "outline-variant": "#c5c5d3",
                        "on-primary-container": "#90a8ff",
                        "primary-fixed": "#dce1ff",
                        "surface-bright": "#f8f9ff",
                        "secondary-container": "#dae2fd",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#eff4ff",
                        "primary": "#00236f",
                        "on-tertiary-container": "#56b3f9",
                        "on-surface-variant": "#444651",
                        "on-secondary-fixed-variant": "#3f465c",
                        "surface-container": "#e5eeff",
                        "surface-tint": "#4059aa",
                        "on-secondary": "#ffffff",
                        "on-primary-fixed": "#00164e",
                        "inverse-on-surface": "#eaf1ff",
                        "tertiary-fixed": "#cce5ff",
                        "surface-container-highest": "#d3e4fe",
                        "error": "#ba1a1a",
                        "inverse-primary": "#b6c4ff",
                        "error-container": "#ffdad6",
                        "inverse-surface": "#213145",
                        "outline": "#757682",
                        "surface-dim": "#cbdbf5",
                        "primary-fixed-dim": "#b6c4ff",
                        "tertiary-container": "#004469",
                        "surface-container-high": "#dce9ff",
                        "surface-variant": "#d3e4fe",
                        "secondary-fixed-dim": "#bec6e0",
                        "on-secondary-container": "#5c647a",
                        "on-surface": "#0b1c30",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-fixed": "#001d31",
                        "on-error": "#ffffff",
                        "tertiary-fixed-dim": "#93ccff",
                        "primary-container": "#1e3a8a",
                        "secondary-fixed": "#dae2fd",
                        "on-background": "#0b1c30",
                        "on-tertiary-fixed-variant": "#004b73",
                        "tertiary": "#002d48",
                        "on-primary": "#ffffff",
                        "on-primary-fixed-variant": "#264191",
                        "secondary": "#565e74",
                        "on-error-container": "#93000a",
                        "on-secondary-fixed": "#131b2e"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "gutter": "1.5rem",
                        "margin": "2rem",
                        "space-xs": "0.25rem",
                        "space-sm": "0.5rem",
                        "space-md": "1rem",
                        "space-lg": "1.5rem",
                        "margin-mobile": "1rem",
                        "gutter-sm": "1rem",
                        "space-xl": "2rem"
                    },
                    "fontFamily": {
                        "headline-md": ["Plus Jakarta Sans"],
                        "body-md": ["Plus Jakarta Sans"],
                        "label-md": ["Plus Jakarta Sans"],
                        "body-lg": ["Plus Jakarta Sans"],
                        "code-audit-sm": ["JetBrains Mono"],
                        "display-lg": ["Plus Jakarta Sans"],
                        "body-sm": ["Plus Jakarta Sans"],
                        "code-audit": ["JetBrains Mono"],
                        "headline-lg": ["Plus Jakarta Sans"],
                        "headline-sm": ["Plus Jakarta Sans"],
                        "label-sm": ["Plus Jakarta Sans"]
                    },
                    "fontSize": {
                        "headline-md": ["20px", {"lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "label-md": ["13px", {"lineHeight": "18px", "fontWeight": "600"}],
                        "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "code-audit-sm": ["10px", {"lineHeight": "14px", "fontWeight": "400"}],
                        "display-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-sm": ["12px", {"lineHeight": "16px", "fontWeight": "400"}],
                        "code-audit": ["12px", {"lineHeight": "16px", "letterSpacing": "-0.01em", "fontWeight": "500"}],
                        "headline-lg": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.015em", "fontWeight": "600"}],
                        "headline-sm": ["16px", {"lineHeight": "24px", "letterSpacing": "-0.005em", "fontWeight": "600"}],
                        "label-sm": ["11px", {"lineHeight": "16px", "letterSpacing": "0.02em", "fontWeight": "600"}]
                    }
                }
            }
        };
    </script>
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
