<?php
/**
 * OneSign - Halaman Masuk (Login)
 * Design Stitch: Unified Login Screen (100% Identik)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

// Jika sudah masuk, arahkan langsung ke dashboard sesuai peran
if (isAuthenticated()) {
    $role = $_SESSION['user_role'] ?? '';
    switch ($role) {
        case 'pemohon':
            header('Location: ' . BASE_URL . '/views/pemohon/dashboard.php');
            exit;
        case 'penandatangan':
            header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php');
            exit;
        case 'admin':
            header('Location: ' . BASE_URL . '/views/admin/dashboard.php');
            exit;
    }
}

$errorMessage = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

$loggedOut = isset($_GET['logged_out']);
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Masuk - OneSign</title>
    
    <!-- Google Fonts & Material Symbols (Stitch Design System) -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
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
    
    <!-- Tailwind CSS (via CDN) with Stitch Configuration -->
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
<main class="min-h-screen w-full flex flex-col items-center justify-center p-space-md">
    
    <!-- Unified Login Card (SAMA PERSIS DENGAN DESAIN STITCH) -->
    <div class="w-full max-w-md bg-surface-container-lowest rounded-xl shadow-xl p-space-lg flex flex-col gap-space-lg border border-surface-container -translate-y-4">
        
        <!-- Header & Logo -->
        <div class="flex flex-col items-center text-center gap-space-xs">
            <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight font-bold">
                OneSign
            </h1>
            <p class="font-body-md text-body-md text-secondary">
                Masuk ke Akun Portal Anda
            </p>
        </div>

        <!-- Alert Notifikasi Hasil Aksi / Error -->
        <?php if ($errorMessage): ?>
            <div class="p-3 bg-error-container text-on-error-container text-xs rounded-lg flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">error</span>
                <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if ($loggedOut): ?>
            <div class="p-3 bg-surface-container-low text-primary text-xs rounded-lg flex items-center gap-2 border border-outline-variant/30">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                <span>Anda telah berhasil keluar dari sistem.</span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form class="flex flex-col gap-space-md" id="loginForm" action="<?= BASE_URL ?>/actions/auth_action.php?action=login" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            
            <!-- Email / NIP / NIM Input -->
            <div class="flex flex-col gap-1.5">
                <label class="font-label-md text-label-md text-on-surface" for="identifier">Email Institusi / NIP / NIM</label>
                <div class="relative flex items-center">
                    <span class="material-symbols-outlined absolute left-3 text-secondary text-[20px] pointer-events-none">mail</span>
                    <input class="w-full h-11 pl-10 pr-3 rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary shadow-sm transition-all" 
                           id="identifier" 
                           name="email" 
                           placeholder="nama@univ.ac.id atau NIP" 
                           required="" 
                           type="text">
                </div>
            </div>

            <!-- Password Input -->
            <div class="flex flex-col gap-1.5">
                <div class="flex justify-between items-center">
                    <label class="font-label-md text-label-md text-on-surface" for="password">Kata Sandi</label>
                </div>
                <div class="relative flex items-center">
                    <span class="material-symbols-outlined absolute left-3 text-secondary text-[20px] pointer-events-none">lock</span>
                    <input class="w-full h-11 pl-10 pr-10 rounded-lg bg-surface-container-low text-on-surface font-body-md text-body-md placeholder:text-outline focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-primary shadow-sm transition-all" 
                           id="password" 
                           name="password" 
                           placeholder="Masukkan kata sandi" 
                           required="" 
                           type="password">
                    <button class="absolute right-3 text-secondary hover:text-on-surface flex items-center justify-center p-0.5" 
                            id="togglePassBtn" 
                            onclick="togglePasswordVisibility()" 
                            title="Tampilkan / Sembunyikan Sandi" 
                            type="button">
                        <span class="material-symbols-outlined text-[20px]" id="eyeIcon">visibility</span>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button class="w-full h-11 mt-1 bg-primary hover:bg-primary-container text-on-primary rounded-lg font-label-md text-label-md shadow-md flex items-center justify-center gap-2 transition-all active:scale-[0.99]" 
                    id="submitBtn" 
                    type="submit">
                <span class="material-symbols-outlined text-[18px]" id="lockIcon">login</span>
                <span id="btnText">Masuk</span>
            </button>
        </form>
    </div>

    <!-- Minimalist Demo Pills (Di luar kartu utama agar bentuk Stitch tetap murni) -->
    <div class="w-full max-w-md mt-4 flex items-center justify-center gap-2 text-xs text-secondary">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary/80">Demo:</span>
        <button type="button" onclick="fillCredentials('rahmat.maulana@stu.pnj.ac.id', 'password123')" 
                class="px-2.5 py-1 bg-surface-container-lowest hover:bg-surface-container-low border border-outline-variant/30 rounded-full font-label-sm text-label-sm text-on-surface hover:text-primary transition shadow-xs flex items-center gap-1"
                title="Isi Akun Mahasiswa (rahmat.maulana@stu.pnj.ac.id / NIM: 2207411000)">
            <span class="material-symbols-outlined text-[14px]">school</span>
            <span>Mahasiswa</span>
        </button>
        <button type="button" onclick="fillCredentials('bagus.wijaya@pnj.ac.id', 'password123')" 
                class="px-2.5 py-1 bg-surface-container-lowest hover:bg-surface-container-low border border-outline-variant/30 rounded-full font-label-sm text-label-sm text-on-surface hover:text-primary transition shadow-xs flex items-center gap-1"
                title="Isi Akun Dosen (bagus.wijaya@pnj.ac.id / NIP: 198502152010121003)">
            <span class="material-symbols-outlined text-[14px]">edit_document</span>
            <span>Dosen</span>
        </button>
        <button type="button" onclick="fillCredentials('rini.maharani@pnj.ac.id', 'password123')" 
                class="px-2.5 py-1 bg-surface-container-lowest hover:bg-surface-container-low border border-outline-variant/30 rounded-full font-label-sm text-label-sm text-on-surface hover:text-primary transition shadow-xs flex items-center gap-1"
                title="Isi Akun Staf Admin (rini.maharani@pnj.ac.id / NIP: 199208102018032001)">
            <span class="material-symbols-outlined text-[14px]">admin_panel_settings</span>
            <span>Admin</span>
        </button>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                eyeIcon.textContent = 'visibility';
            }
        }

        function fillCredentials(identifier, password) {
            document.getElementById('identifier').value = identifier;
            document.getElementById('password').value = password;
            const passField = document.getElementById('password');
            passField.focus();
        }
    </script>
</main>
</body>
</html>
