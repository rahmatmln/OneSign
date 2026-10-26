<?php
/**
 * OneSign - Halaman Masuk (Login)
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - OneSign Jurusan TIK PNJ</title>
    <!-- Tailwind CSS (via CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Header Instansi -->
    <header class="bg-blue-900 border-b border-blue-950 py-3 px-6 shadow-sm">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded bg-blue-700 flex items-center justify-center font-bold text-white text-base shadow-inner">
                    1S
                </div>
                <div>
                    <div class="font-bold text-white tracking-tight text-base">OneSign</div>
                    <div class="text-[11px] text-blue-200">Politeknik Negeri Jakarta • Jurusan TIK</div>
                </div>
            </div>
            <div class="text-xs text-blue-200 font-medium hidden sm:block">
                Sistem Tanda Tangan Elektronik Internal & Pengamanan Berkas
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-4xl mx-auto px-4 py-8 w-full">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
            
            <!-- Left Column: Deskripsi Sistem -->
            <div class="md:col-span-6 space-y-4">
                <div class="inline-block px-2.5 py-1 text-xs font-semibold bg-blue-100 text-blue-900 rounded border border-blue-200">
                    Sistem Layanan Internal Kampus
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">
                    Pengesahan Dokumen Akademik Lebih Cepat, Sah, dan Aman.
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Platform pengesahan berkas PDF internal Jurusan TIK yang dilengkapi kanvas pembubuhan tanda tangan elektronik serta teknologi <strong>Dynamic Security Watermarking</strong> untuk melindungi distribusi dokumen dari kebocoran tanpa jejak.
                </p>

                <div class="pt-2 border-t border-slate-200">
                    <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">Akun Pengujian Demo (Klik untuk Isi Cepat):</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <button type="button" onclick="fillCredentials('rahmat.maulana@stu.pnj.ac.id', 'password123')" class="p-2 text-left bg-white border border-slate-200 rounded hover:border-blue-500 hover:bg-blue-50/50 transition">
                            <div class="text-[11px] font-bold text-slate-900">Mahasiswa (Pemohon)</div>
                            <div class="text-[10px] text-slate-500 truncate">rahmat.maulana@...</div>
                        </button>
                        <button type="button" onclick="fillCredentials('bagus.wijaya@pnj.ac.id', 'password123')" class="p-2 text-left bg-white border border-slate-200 rounded hover:border-blue-500 hover:bg-blue-50/50 transition">
                            <div class="text-[11px] font-bold text-slate-900">Dosen (Penandatangan)</div>
                            <div class="text-[10px] text-slate-500 truncate">bagus.wijaya@...</div>
                        </button>
                        <button type="button" onclick="fillCredentials('rini.maharani@pnj.ac.id', 'password123')" class="p-2 text-left bg-white border border-slate-200 rounded hover:border-blue-500 hover:bg-blue-50/50 transition">
                            <div class="text-[11px] font-bold text-slate-900">Staf Admin (Arsip)</div>
                            <div class="text-[10px] text-slate-500 truncate">rini.maharani@...</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Form Login Card -->
            <div class="md:col-span-6">
                <div class="bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900">Masuk ke Akun Anda</h2>
                    <p class="text-xs text-slate-500 mt-1 mb-5">Gunakan email institusi dan kata sandi yang telah terdaftar.</p>

                    <!-- Alert Notifikasi -->
                    <?php if ($errorMessage): ?>
                        <div class="mb-4 p-3 rounded bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium">
                            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($loggedOut): ?>
                        <div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium">
                            Anda telah berhasil keluar dari sistem.
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/actions/auth_action.php?action=login" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div>
                            <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email Kampus</label>
                            <input type="email" id="email" name="email" required
                                   placeholder="nama@pnj.ac.id atau nama@stu.pnj.ac.id"
                                   class="w-full px-3 py-2 text-xs border border-slate-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 bg-slate-50 focus:bg-white transition">
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi</label>
                            <input type="password" id="password" name="password" required
                                   placeholder="Masukkan kata sandi akun"
                                   class="w-full px-3 py-2 text-xs border border-slate-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 bg-slate-50 focus:bg-white transition">
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                    class="w-full py-2.5 px-4 bg-blue-800 hover:bg-blue-900 text-white font-semibold text-xs rounded shadow transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                                Masuk ke Sistem
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 pt-4 border-t border-slate-100 text-center">
                        <p class="text-[11px] text-slate-500">
                            Kendala akun? Hubungi Sub Bagian Tata Usaha Jurusan TIK.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-3 text-center text-xs text-slate-500">
        &copy; 2026 Jurusan Teknik Informatika dan Komputer - Politeknik Negeri Jakarta. Proyek PBL OneSign.
    </footer>

    <!-- Script Pengisian Otomatis Akun Demo -->
    <script>
        function fillCredentials(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
