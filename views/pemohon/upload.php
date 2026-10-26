<?php
/**
 * OneSign - Form Pengajuan Dokumen PDF (Pemohon)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Ajukan Dokumen PDF Baru';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

requireRole(['pemohon']);

$user = getActiveUser();
$pdo  = Database::getConnection();

// Mengambil daftar dosen penandatangan sah yang terdaftar di basis data
$stmt = $pdo->prepare("
    SELECT id, full_name, identifier_number, department 
    FROM users 
    WHERE role = 'penandatangan' 
    ORDER BY full_name ASC
");
$stmt->execute();
$signers = $stmt->fetchAll();

$errorMessage = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="space-y-6">

    <!-- Header Section -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-lg font-bold text-slate-900">Ajukan Dokumen PDF untuk Ditandatangani</h1>
            <p class="text-xs text-slate-500 mt-0.5">Unggah berkas naskah akademik (PDF) dan tentukan dosen penandatangan tujuan.</p>
        </div>
        <a href="<?= BASE_URL ?>/views/pemohon/dashboard.php" 
           class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded border border-slate-300 transition">
            Kembali ke Dashboard
        </a>
    </div>

    <!-- Error Alert -->
    <?php if ($errorMessage): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Form Pengajuan -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-sm">
        <form action="<?= BASE_URL ?>/actions/upload_action.php" method="POST" enctype="multipart/form-data" class="space-y-6" id="upload-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <!-- 1. Metadata: Judul & Kategori -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="block text-xs font-bold text-slate-700 mb-1">
                        Judul Permohonan Dokumen <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" required
                           placeholder="Contoh: Pengesahan Laporan Tugas Akhir"
                           class="w-full px-3 py-2 text-xs border border-slate-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 bg-slate-50 focus:bg-white transition">
                    <p class="text-[11px] text-slate-400 mt-1">Gunakan judul deskriptif yang memudahkan identifikasi oleh dosen.</p>
                </div>

                <div>
                    <label for="category" class="block text-xs font-bold text-slate-700 mb-1">
                        Kategori Dokumen <span class="text-rose-500">*</span>
                    </label>
                    <select id="category" name="category" required
                            class="w-full px-3 py-2 text-xs border border-slate-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 bg-slate-50 focus:bg-white transition">
                        <option value="">-- Pilih Kategori Dokumen --</option>
                        <option value="Proposal / Tugas Akhir">Proposal / Tugas Akhir</option>
                        <option value="Surat Rekomendasi / Magang">Surat Rekomendasi / Magang</option>
                        <option value="Lembar Pengesahan Laporan">Lembar Pengesahan Laporan</option>
                        <option value="Surat Keterangan Bebas Kompen">Surat Keterangan Bebas Kompen</option>
                        <option value="Administrasi Akademik Lainnya">Administrasi Akademik Lainnya</option>
                    </select>
                </div>
            </div>

            <!-- 2. Status Kerahasiaan Dokumen -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">
                    Tingkat Kerahasiaan Berkas <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="p-3 border border-slate-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50/30 transition flex items-start space-x-3">
                        <input type="radio" name="confidentiality" value="internal" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Internal TIK</span>
                            <span class="block text-[11px] text-slate-500 leading-normal">Hanya untuk civitas akademika Jurusan TIK PNJ.</span>
                        </div>
                    </label>

                    <label class="p-3 border border-slate-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50/30 transition flex items-start space-x-3">
                        <input type="radio" name="confidentiality" value="publik" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Publik</span>
                            <span class="block text-[11px] text-slate-500 leading-normal">Dapat diunduh secara umum setelah disahkan.</span>
                        </div>
                    </label>

                    <label class="p-3 border border-slate-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50/30 transition flex items-start space-x-3">
                        <input type="radio" name="confidentiality" value="rahasia" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Rahasia</span>
                            <span class="block text-[11px] text-slate-500 leading-normal">Sensitif. Akses unduh dibatasi hanya pemohon, penandatangan, dan admin.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 3. Pemilihan Penandatangan -->
            <div>
                <label for="signer_id" class="block text-xs font-bold text-slate-700 mb-1">
                    Dosen Penandatangan Tujuan <span class="text-rose-500">*</span>
                </label>
                <select id="signer_id" name="signer_id" required
                        class="w-full px-3 py-2 text-xs border border-slate-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 bg-slate-50 focus:bg-white transition">
                    <option value="">-- Pilih Dosen Penandatangan --</option>
                    <?php foreach ($signers as $signer): ?>
                        <option value="<?= $signer['id'] ?>">
                            <?= htmlspecialchars($signer['full_name'], ENT_QUOTES, 'UTF-8') ?> 
                            (NIP: <?= htmlspecialchars($signer['identifier_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($signer['department'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Dokumen akan masuk ke dalam antrean persetujuan dosen yang Anda pilih.</p>
            </div>

            <!-- 4. Unggah File PDF -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih Berkas PDF <span class="text-rose-500">*</span>
                </label>
                <div class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center bg-slate-50 hover:bg-slate-100/60 transition">
                    <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf" required class="hidden" onchange="displaySelectedFile(this)">
                    <label for="pdf_file" class="cursor-pointer">
                        <div class="text-xs font-bold text-blue-800 hover:text-blue-900">
                            Klik di sini untuk memilih berkas PDF
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1">
                            Format wajib berkas: <strong>.pdf</strong> (Maksimal ukuran berkas: <strong>10 MB</strong>)
                        </div>
                    </label>
                    <div id="file-info" class="mt-3 text-xs font-medium text-slate-800 hidden">
                        Berkas Terpilih: <span id="file-name" class="font-bold text-blue-700"></span> (<span id="file-size" class="text-slate-500"></span>)
                    </div>
                </div>
            </div>

            <!-- Tombol Aksi Form -->
            <div class="pt-4 border-t border-slate-200 flex items-center justify-end space-x-3">
                <a href="<?= BASE_URL ?>/views/pemohon/dashboard.php" class="px-4 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded border border-slate-300 transition">
                    Batal
                </a>
                <button type="submit" id="btn-submit"
                        class="px-5 py-2 text-xs font-bold text-white bg-blue-800 hover:bg-blue-900 rounded shadow transition focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Kirim Permohonan Pengesahan
                </button>
            </div>

        </form>
    </div>

</div>

<script>
    function displaySelectedFile(input) {
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');

        if (input.files && input.files[0]) {
            const file = input.files[0];

            // Validasi format
            if (file.type !== 'application/pdf') {
                alert('Peringatan: Berkas yang dipilih harus berupa file PDF (.pdf).');
                input.value = '';
                fileInfo.classList.add('hidden');
                return;
            }

            // Validasi ukuran (10 MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('Peringatan: Ukuran berkas melebihi batas maksimal 10 MB.');
                input.value = '';
                fileInfo.classList.add('hidden');
                return;
            }

            fileName.innerText = file.name;
            fileSize.innerText = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            fileInfo.classList.remove('hidden');
        } else {
            fileInfo.classList.add('hidden');
        }
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
