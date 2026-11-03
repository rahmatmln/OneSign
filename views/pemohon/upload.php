<?php
/**
 * OneSign - Form Pengajuan Dokumen PDF (Pemohon)
 * Design Stitch: Pengajuan Dokumen OneSign (100% Identik)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Pengajuan Dokumen';
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

<!-- Header Section (Stitch 100% Identik) -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md pb-space-xs">
    <div class="space-y-1">
        <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface tracking-tight">
            Pengajuan Dokumen
        </h1>
        <p class="font-body-md text-body-md text-secondary">
            Unggah berkas naskah PDF dan tentukan pejabat penandatangan pengesahan.
        </p>
    </div>
</div>

<!-- Stepper Navigation (Stitch 100% Identik) -->
<div class="bg-surface-container-lowest px-space-lg py-space-md rounded-xl shadow-sm border border-outline-variant/20 flex items-center justify-between">
    <div class="flex items-center gap-space-md">
        <div class="flex items-center gap-space-xs text-primary font-label-md text-label-md font-semibold">
            <span class="w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center text-label-sm font-bold">1</span>
            <span>Unggah Dokumen</span>
        </div>
        <span class="text-outline-variant font-bold text-xs">/</span>
        <div class="flex items-center gap-space-xs text-secondary font-label-md text-label-md">
            <span class="w-6 h-6 rounded-full bg-surface-container text-secondary flex items-center justify-center text-label-sm font-bold">2</span>
            <span>Detail &amp; Penandatangan</span>
        </div>
        <span class="text-outline-variant font-bold text-xs">/</span>
        <div class="flex items-center gap-space-xs text-secondary font-label-md text-label-md">
            <span class="w-6 h-6 rounded-full bg-surface-container text-secondary flex items-center justify-center text-label-sm font-bold">3</span>
            <span>Konfirmasi</span>
        </div>
    </div>
    <span class="text-body-sm text-secondary hidden sm:inline">Langkah 1 dari 3</span>
</div>

<!-- Alert Pesan Error -->
<?php if ($errorMessage): ?>
    <div class="p-4 rounded-xl bg-error-container text-on-error-container text-xs font-semibold flex items-center gap-2.5 shadow-sm">
        <span class="material-symbols-outlined text-error text-[20px]">error</span>
        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- Form Pengajuan -->
<form action="<?= BASE_URL ?>/actions/upload_action.php" method="POST" enctype="multipart/form-data" id="upload-form" class="space-y-space-lg">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Main 2-Column Form Layout (Stitch 100% Identik) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg items-start">
        
        <!-- Left Column: Document Upload & Integrity Preview (5 cols) -->
        <div class="lg:col-span-5 space-y-space-md">
            <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm border border-outline-variant/20 space-y-space-md">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-space-xs text-on-surface font-headline-sm text-headline-sm font-semibold">
                        <span class="material-symbols-outlined text-primary text-[20px]">upload_file</span>
                        <span>Berkas Dokumen</span>
                    </div>
                    <span class="font-code-audit-sm text-code-audit-sm text-secondary font-semibold">Maks. 10 MB</span>
                </div>

                <!-- Drag & Drop Zone -->
                <div class="group relative flex flex-col items-center justify-center p-space-lg rounded-xl border border-dashed border-outline-variant hover:border-primary bg-surface-container-low/50 hover:bg-surface-container-low transition-all cursor-pointer text-center">
                    <input type="file" 
                           id="pdf_file" 
                           name="pdf_file" 
                           accept="application/pdf" 
                           required 
                           class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" 
                           onchange="displaySelectedFile(this)">
                    
                    <div class="w-12 h-12 rounded-xl bg-surface-container-lowest text-primary shadow-sm flex items-center justify-center mb-space-xs group-hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined text-[28px]">cloud_upload</span>
                    </div>
                    <p class="font-label-md text-label-md text-on-surface font-medium">
                        Tarik dan lepas file PDF, atau <span class="text-primary hover:underline font-semibold">pilih berkas</span>
                    </p>
                    <p class="font-body-sm text-body-sm text-secondary mt-0.5">
                        Mendukung dokumen format PDF
                    </p>
                </div>

                <!-- Selected File Card Preview (Tampil setelah file dipilih) -->
                <div id="file-info" class="hidden bg-surface-container-low p-space-md rounded-xl space-y-space-xs border border-outline-variant/20">
                    <div class="flex items-center justify-between gap-space-sm">
                        <div class="flex items-center gap-space-sm min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest text-error flex items-center justify-center flex-shrink-0 shadow-sm">
                                <span class="material-symbols-outlined text-[22px]">description</span>
                            </div>
                            <div class="min-w-0 flex flex-col">
                                <span id="file-name" class="font-label-md text-label-md text-on-surface truncate font-semibold"></span>
                                <span id="file-size" class="font-code-audit-sm text-code-audit-sm text-secondary"></span>
                            </div>
                        </div>
                        <button type="button" onclick="clearSelectedFile()" class="text-secondary hover:text-error transition-colors p-1 rounded" title="Batalkan pilihan berkas">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                    <div class="w-full h-1.5 bg-surface-container-highest rounded-full overflow-hidden mt-space-xs">
                        <div class="h-full bg-primary rounded-full w-full"></div>
                    </div>
                </div>

                <!-- Watermark Dinamis Feature -->
                <label class="flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-low cursor-pointer hover:bg-surface-container transition-colors border border-outline-variant/10">
                    <input checked type="checkbox" name="enable_watermark" value="1" class="w-4 h-4 mt-0.5 rounded text-primary focus:ring-primary accent-primary">
                    <div class="flex flex-col">
                        <span class="font-label-md text-label-md text-on-surface font-semibold">Beri Watermark Keamanan Dinamis</span>
                        <span class="font-body-sm text-body-sm text-secondary leading-snug">Menyematkan Nama, NIM, dan waktu akses secara otomatis pada setiap berkas sah yang diunduh.</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Right Column: Document Metadata & Workflow Routing (7 cols) -->
        <div class="lg:col-span-7 space-y-space-md">
            <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm border border-outline-variant/20 space-y-space-lg">
                <div class="flex items-center gap-space-xs text-on-surface font-headline-sm text-headline-sm font-semibold">
                    <span class="material-symbols-outlined text-primary text-[20px]">edit_note</span>
                    <span>Informasi &amp; Penandatangan</span>
                </div>

                <div class="space-y-space-md">
                    <!-- Judul Dokumen -->
                    <div class="space-y-space-xs">
                        <label for="title" class="font-label-md text-label-md text-on-surface flex items-center justify-between font-semibold">
                            <span>Judul Dokumen <span class="text-error">*</span></span>
                        </label>
                        <input type="text" 
                               id="title" 
                               name="title" 
                               required
                               placeholder="Contoh: Lembar Pengesahan Sidang Skripsi - Raden Muhammad Iqbal" 
                               class="w-full h-10 px-space-md bg-surface-container-lowest rounded-lg border border-outline-variant/30 font-body-md text-body-md text-on-surface placeholder:text-secondary focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <!-- Kategori Dokumen & Tingkat Kerahasiaan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                        <!-- Kategori -->
                        <div class="space-y-space-xs">
                            <label for="category" class="font-label-md text-label-md text-on-surface font-semibold">
                                Kategori Dokumen <span class="text-error">*</span>
                            </label>
                            <div class="relative">
                                <select id="category" 
                                        name="category" 
                                        required 
                                        class="w-full h-10 px-space-md bg-surface-container-lowest rounded-lg border border-outline-variant/30 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary appearance-none cursor-pointer">
                                    <option value="Tugas Akhir / Skripsi" selected>Tugas Akhir / Skripsi</option>
                                    <option value="Surat Rekomendasi / Magang">Surat Rekomendasi / Magang</option>
                                    <option value="Lembar Pengesahan Laporan">Lembar Pengesahan Laporan</option>
                                    <option value="Nota Dinas Dekanat">Nota Dinas Dekanat</option>
                                    <option value="Berita Acara Sidang">Berita Acara Sidang</option>
                                    <option value="Administrasi Akademik Lainnya">Administrasi Akademik Lainnya</option>
                                </select>
                                <span class="material-symbols-outlined text-secondary absolute right-3 top-2.5 pointer-events-none text-[20px]">expand_more</span>
                            </div>
                        </div>

                        <!-- Klasifikasi Kerahasiaan -->
                        <div class="space-y-space-xs">
                            <label for="confidentiality" class="font-label-md text-label-md text-on-surface font-semibold">
                                Klasifikasi Berkas <span class="text-error">*</span>
                            </label>
                            <div class="relative">
                                <select id="confidentiality" 
                                        name="confidentiality" 
                                        required 
                                        class="w-full h-10 px-space-md bg-surface-container-lowest rounded-lg border border-outline-variant/30 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary appearance-none cursor-pointer">
                                    <option value="internal" selected>Internal Kampus (Civitas TIK)</option>
                                    <option value="publik">Publik Terbuka</option>
                                    <option value="rahasia">Rahasia TIK</option>
                                </select>
                                <span class="material-symbols-outlined text-secondary absolute right-3 top-2.5 pointer-events-none text-[20px]">expand_more</span>
                            </div>
                        </div>
                    </div>

                    <!-- Pejabat Penandatangan -->
                    <div class="space-y-space-xs">
                        <label for="signer_id" class="font-label-md text-label-md text-on-surface font-semibold">
                            Pejabat Penandatangan <span class="text-error">*</span>
                        </label>
                        <div class="relative">
                            <select id="signer_id" 
                                    name="signer_id" 
                                    required 
                                    class="w-full h-10 px-space-md bg-surface-container-lowest rounded-lg border border-outline-variant/30 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary appearance-none cursor-pointer">
                                <option value="">-- Pilih Pejabat / Dosen Pembimbing --</option>
                                <?php foreach ($signers as $signer): ?>
                                    <option value="<?= $signer['id'] ?>">
                                        <?= htmlspecialchars($signer['full_name'], ENT_QUOTES, 'UTF-8') ?> (NIP: <?= htmlspecialchars($signer['identifier_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?>) - <?= htmlspecialchars($signer['department'] ?? 'TIK', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="material-symbols-outlined text-secondary absolute right-3 top-2.5 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>

                    <!-- Catatan Tambahan (Opsional) -->
                    <div class="space-y-space-xs">
                        <label for="notes" class="font-label-md text-label-md text-on-surface font-semibold">Catatan Tambahan (Opsional)</label>
                        <textarea id="notes" 
                                  name="notes" 
                                  rows="3" 
                                  placeholder="Tulis catatan disposisi atau keterangan singkat untuk dosen penandatangan..." 
                                  class="w-full p-space-md bg-surface-container-lowest rounded-lg border border-outline-variant/30 font-body-md text-body-md text-on-surface placeholder:text-secondary focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Bottom Action Bar (Stitch 100% Identik) -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm border border-outline-variant/20 flex flex-col sm:flex-row items-center justify-between gap-space-md">
        <div class="flex items-center gap-space-sm">
            <a href="<?= BASE_URL ?>/views/pemohon/dashboard.php" 
               class="px-space-lg h-10 rounded-lg text-secondary hover:bg-surface-container-low font-label-md text-label-md transition-colors flex items-center justify-center">
                Batal
            </a>
        </div>
        <div class="flex items-center gap-space-sm">
            <button type="submit" 
                    id="submitBtn" 
                    class="px-space-lg h-10 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md transition-all shadow-sm flex items-center justify-center gap-space-xs font-semibold">
                <span class="material-symbols-outlined text-[18px]">send</span>
                <span>Kirim Pengajuan</span>
            </button>
        </div>
    </div>
</form>

<script>
    function displaySelectedFile(input) {
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');

        if (input.files && input.files[0]) {
            const file = input.files[0];

            // Validasi format file harus PDF
            if (file.type !== 'application/pdf') {
                alert('Peringatan: Berkas yang dipilih harus berupa file PDF (.pdf).');
                input.value = '';
                fileInfo.classList.add('hidden');
                return;
            }

            // Validasi ukuran maksimal 10 MB
            if (file.size > 10 * 1024 * 1024) {
                alert('Peringatan: Ukuran berkas melebihi batas maksimal 10 MB.');
                input.value = '';
                fileInfo.classList.add('hidden');
                return;
            }

            fileName.innerText = file.name;
            fileName.title = file.name;
            fileSize.innerText = (file.size / (1024 * 1024)).toFixed(1) + ' MB • Siap diajukan';
            fileInfo.classList.remove('hidden');

            // Auto-fill judul dokumen jika masih kosong
            const titleInput = document.getElementById('title');
            if (titleInput && !titleInput.value.trim()) {
                const cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/_/g, " ");
                titleInput.value = cleanName;
            }
        } else {
            fileInfo.classList.add('hidden');
        }
    }

    function clearSelectedFile() {
        const input = document.getElementById('pdf_file');
        const fileInfo = document.getElementById('file-info');
        if (input) input.value = '';
        if (fileInfo) fileInfo.classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
