<?php
/**
 * OneSign - Form Pengajuan Dokumen PDF (Pemohon)
 * Design Stitch: Pengajuan Dokumen OneSign (100% Identik)
 * TERPADU: Sinkronisasi Master Data Dokumen & Pengaturan Posisi Tanda Tangan Interaktif
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Pengajuan Dokumen';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

requireRole(['pemohon']);

$user = getActiveUser();
$pdo  = Database::getConnection();

// Mengambil daftar dosen penandatangan aktif yang terdaftar di basis data
$stmt = $pdo->prepare("
    SELECT id, full_name, identifier_number, department 
    FROM users 
    WHERE role = 'penandatangan' AND is_active = 1
    ORDER BY full_name ASC
");
$stmt->execute();
$signers = $stmt->fetchAll();

// Mengambil kategori dokumen aktif dari master data
$stmtCat = $pdo->query("
    SELECT name, code, default_classification 
    FROM document_categories 
    WHERE is_active = 1 
    ORDER BY name ASC
");
$categories = $stmtCat->fetchAll();

// Mengambil klasifikasi dokumen aktif dari master data
$stmtClass = $pdo->query("
    SELECT code, name, badge_label, tier_code 
    FROM document_classifications 
    WHERE is_active = 1 
    ORDER BY tier_level ASC
");
$classifications = $stmtClass->fetchAll();

$errorMessage = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../layouts/header.php';
?>

<!-- Mozilla PDF.js Library untuk Pratinjau Interaktif -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<!-- Header Section (Stitch 100% Identik) -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md pb-space-xs">
    <div class="space-y-1">
        <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface tracking-tight">
            Pengajuan Dokumen
        </h1>
        <p class="font-body-md text-body-md text-secondary">
            Unggah berkas naskah PDF, tentukan pejabat penandatangan, dan atur posisi tanda tangan pada dokumen.
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
        <div class="flex items-center gap-space-xs text-primary font-label-md text-label-md font-semibold">
            <span class="w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center text-label-sm font-bold">2</span>
            <span>Metadata &amp; Penandatangan</span>
        </div>
        <span class="text-outline-variant font-bold text-xs">/</span>
        <div class="flex items-center gap-space-xs text-secondary font-label-md text-label-md">
            <span class="w-6 h-6 rounded-full bg-surface-container text-secondary flex items-center justify-center text-label-sm font-bold">3</span>
            <span>Atur Posisi TTD</span>
        </div>
    </div>
    <span class="text-body-sm text-secondary hidden sm:inline">Langkah Pengajuan Terpadu</span>
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
        
        <!-- Kolom Kiri: Unggah Berkas & Pratinjau (lg:col-span-5) -->
        <div class="lg:col-span-5 space-y-space-md">
            <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm border border-outline-variant/20 space-y-space-md">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-space-xs font-headline-sm text-headline-sm font-bold text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[20px]">upload_file</span>
                        <span>Berkas Dokumen</span>
                    </div>
                    <span class="font-code-audit-sm text-code-audit-sm text-secondary font-semibold">Maks. 10 MB</span>
                </div>

                <!-- Drag & Drop Zone -->
                <div id="drop-zone" 
                     class="border-2 border-dashed border-outline-variant/40 hover:border-primary/60 bg-surface-container-low/30 hover:bg-surface-container-low/60 rounded-xl p-space-lg text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-space-sm group">
                    <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[28px]">cloud_upload</span>
                    </div>
                    <div class="space-y-1">
                        <p class="font-label-md text-label-md font-semibold text-on-surface">
                            Tarik berkas PDF ke sini atau <span class="text-primary hover:underline">Pilih Berkas</span>
                        </p>
                        <p class="font-body-sm text-body-sm text-secondary">
                            Format berkas resmi .pdf dengan ukuran maksimal 10 MB
                        </p>
                    </div>
                    <input type="file" 
                           id="pdf_file" 
                           name="pdf_file" 
                           accept="application/pdf,.pdf" 
                           required 
                           class="hidden" 
                           onchange="displaySelectedFile(this)">
                </div>

                <!-- Card File Terpilih -->
                <div id="file-info" class="hidden p-space-md rounded-lg bg-surface-container-low border border-outline-variant/30 flex items-center justify-between">
                    <div class="flex items-center gap-space-sm overflow-hidden">
                        <div class="w-9 h-9 rounded-lg bg-error-container text-error flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>
                        </div>
                        <div class="truncate">
                            <p id="file-name" class="font-label-md text-label-md font-bold text-on-surface truncate">nama_berkas.pdf</p>
                            <p id="file-size" class="font-body-sm text-body-sm text-secondary">0.0 MB • Siap diajukan</p>
                        </div>
                    </div>
                    <button type="button" 
                            onclick="clearSelectedFile()" 
                            class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-error-container/20 transition-colors"
                            title="Hapus berkas terpilih">
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                    </button>
                </div>

                <!-- Guidance Info Box -->
                <div class="p-space-md rounded-lg bg-surface-container-low/50 border border-outline-variant/20 flex gap-space-sm text-secondary">
                    <span class="material-symbols-outlined text-[20px] text-primary shrink-0">verified_user</span>
                    <p class="font-body-sm text-body-sm leading-relaxed">
                        Naskah yang diajukan akan diberi stempel tanda tangan resmi beserta <strong>QR Code verifikasi keaslian</strong> dan <strong>Dynamic Security Watermark</strong>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Metadata Naskah & Penugasan Penandatangan (lg:col-span-7) -->
        <div class="lg:col-span-7 space-y-space-md">
            <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm border border-outline-variant/20 space-y-space-md">
                <div class="flex items-center gap-space-xs font-headline-sm text-headline-sm font-bold text-on-surface border-b border-outline-variant/10 pb-space-sm">
                    <span class="material-symbols-outlined text-primary text-[20px]">assignment</span>
                    <span>Metadata &amp; Otorisasi</span>
                </div>

                <div class="space-y-space-md">
                    <!-- Judul Naskah / Dokumen -->
                    <div class="space-y-space-xs">
                        <label for="title" class="font-label-md text-label-md text-on-surface font-semibold">
                            Judul Dokumen Akademik <span class="text-error">*</span>
                        </label>
                        <input type="text" 
                               id="title" 
                               name="title" 
                               required 
                               placeholder="Contoh: Pengesahan Laporan Tugas Akhir - Rahmat Maulana" 
                               class="w-full h-10 px-space-md bg-surface-container-lowest rounded-lg border border-outline-variant/30 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm placeholder:text-outline">
                    </div>

                    <!-- Kategori Dokumen & Tingkat Kerahasiaan (Dinamis dari Master Data) -->
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
                                    <?php if (empty($categories)): ?>
                                        <option value="Administrasi Akademik">Administrasi Akademik</option>
                                    <?php else: ?>
                                        <?php foreach ($categories as $index => $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                                    <?php if (empty($classifications)): ?>
                                        <option value="internal" selected>Internal Kampus (Civitas TIK)</option>
                                        <option value="publik">Publik Terbuka</option>
                                        <option value="rahasia">Rahasia Terbatas</option>
                                    <?php else: ?>
                                        <?php foreach ($classifications as $cls): ?>
                                            <option value="<?= htmlspecialchars($cls['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $cls['code'] === 'internal' ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cls['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($cls['badge_label'], ENT_QUOTES, 'UTF-8') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                        <label for="notes" class="font-label-md text-label-md text-on-surface font-semibold">Catatan Pengajuan (Opsional)</label>
                        <textarea id="notes" 
                                  name="notes" 
                                  rows="2" 
                                  placeholder="Keterangan singkat atau perihal surat untuk dosen penandatangan..." 
                                  class="w-full p-space-md bg-surface-container-lowest rounded-lg border border-outline-variant/30 font-body-md text-body-md text-on-surface placeholder:text-secondary focus:outline-none focus:ring-2 focus:ring-primary shadow-sm resize-none"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ATUR POSISI TANDA TANGAN OLEH PEMOHON (Interactive PDF.js)   -->
    <!-- ============================================================ -->
    <input type="hidden" name="page_number" id="form-page-num" value="1">
    <input type="hidden" name="pos_x" id="form-pos-x" value="0">
    <input type="hidden" name="pos_y" id="form-pos-y" value="0">
    <input type="hidden" name="box_width" id="form-box-width" value="170">
    <input type="hidden" name="box_height" id="form-box-height" value="80">

    <div id="position-card" class="hidden bg-surface-container-lowest p-space-lg rounded-xl shadow-sm border border-outline-variant/20 space-y-space-md">
        <div class="flex flex-wrap items-center justify-between gap-space-sm border-b border-outline-variant/10 pb-space-sm">
            <div class="flex items-center gap-space-xs text-on-surface font-headline-sm text-headline-sm font-bold">
                <span class="material-symbols-outlined text-primary text-[22px]">draw</span>
                <span>Tentukan Posisi Tanda Tangan pada Dokumen</span>
            </div>

            <div class="flex flex-wrap items-center gap-space-sm">
                <!-- Navigasi Halaman -->
                <div class="flex items-center bg-surface-container-low rounded-lg p-0.5 border border-outline-variant/20">
                    <button type="button" id="btn-prev-page" class="p-1.5 rounded hover:bg-surface-container text-on-surface-variant transition-colors" title="Halaman Sebelumnya">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </button>
                    <div class="px-3 font-body-sm text-body-sm text-on-surface flex items-center gap-1.5">
                        <span class="text-secondary">Halaman</span>
                        <span id="current-page-num" class="font-bold text-primary">1</span>
                        <span class="text-secondary">dari</span>
                        <span id="total-pages-num" class="font-bold">1</span>
                    </div>
                    <button type="button" id="btn-next-page" class="p-1.5 rounded hover:bg-surface-container text-on-surface-variant transition-colors" title="Halaman Berikutnya">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>
                </div>

                <!-- Kontrol Ukuran Kotak -->
                <div class="flex items-center gap-1.5 bg-surface-container-low px-2.5 py-1 rounded-lg border border-outline-variant/20">
                    <span class="text-secondary font-label-sm text-label-sm font-semibold">Ukuran:</span>
                    <button type="button" id="btn-shrink-sig" class="w-7 h-7 rounded bg-surface-container-lowest hover:bg-surface-container border border-outline-variant/30 flex items-center justify-center text-on-surface transition-colors" title="Perkecil">
                        <span class="material-symbols-outlined text-[16px]">remove</span>
                    </button>
                    <span id="sig-scale-label" class="font-bold text-primary font-code-audit text-xs min-w-[45px] text-center">100%</span>
                    <button type="button" id="btn-grow-sig" class="w-7 h-7 rounded bg-surface-container-lowest hover:bg-surface-container border border-outline-variant/30 flex items-center justify-center text-on-surface transition-colors" title="Perbesar">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                    </button>
                </div>

                <!-- Koordinat Realtime -->
                <div class="hidden sm:flex items-center gap-2 bg-surface-container-low px-3 py-1 rounded-lg border border-outline-variant/20 font-code-audit text-xs text-secondary">
                    <span>X: <strong id="coord-x-val" class="text-primary font-bold">0</strong></span>
                    <span>Y: <strong id="coord-y-val" class="text-primary font-bold">0</strong></span>
                </div>
            </div>
        </div>

        <p class="font-body-sm text-body-sm text-secondary">
            Geser kotak biru di bawah ke area lembar naskah yang harus ditandatangani oleh pejabat. Anda juga dapat memperbesar/memperkecil kotak dengan tombol ukuran atau menarik pojok kanan bawah kotak.
        </p>

        <!-- Area Viewport Dokumen PDF -->
        <div class="overflow-auto max-h-[700px] bg-surface-container-low/60 rounded-xl p-4 flex justify-center border border-outline-variant/20">
            <div id="pdf-canvas-container" class="relative inline-block bg-white shadow-lg border border-outline-variant/30 select-none">
                <canvas id="pdf-render-canvas" class="block"></canvas>

                <!-- Kotak Tanda Tangan Interaktif -->
                <div id="draggable-sig-box"
                     class="absolute border-2 border-primary bg-primary/10 rounded-md cursor-grab active:cursor-grabbing flex flex-col items-center justify-center p-1 select-none z-20"
                     style="left: 100px; top: 100px; width: 170px; height: 80px;">
                    <div class="w-full text-center pointer-events-none">
                        <span class="block text-[11px] font-bold text-primary leading-tight">Area Tanda Tangan</span>
                        <span class="block text-[9px] text-primary/80 leading-tight">Pejabat Penandatangan</span>
                    </div>
                    <!-- Sudut Resizer -->
                    <div id="sig-box-resizer"
                         class="absolute -right-1 -bottom-1 w-4 h-4 bg-primary rounded-sm cursor-nwse-resize flex items-center justify-center shadow-sm"
                         title="Tarik untuk mengubah ukuran">
                        <span class="material-symbols-outlined text-white text-[10px] pointer-events-none">aspect_ratio</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Bottom Action Bar (Stitch 100% Identik) -->
    <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm border border-outline-variant/20 flex flex-col sm:flex-row items-center justify-between gap-space-md">
        <div class="flex items-center gap-space-sm">
            <a href="<?= BASE_URL ?>/views/pemohon/dashboard.php" 
               class="px-space-lg h-10 rounded-lg text-secondary hover:bg-surface-container-low font-label-md text-label-md transition-colors flex items-center justify-center font-medium">
                Batal
            </a>
        </div>
        <div class="flex items-center gap-space-sm">
            <button type="submit" 
                    id="submitBtn" 
                    class="px-space-lg h-10 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md transition-all shadow-sm flex items-center justify-center gap-space-xs font-semibold">
                <span class="material-symbols-outlined text-[18px]">send</span>
                <span>Kirim Permohonan Pengesahan</span>
            </button>
        </div>
    </div>
</form>

<script>
    // ==========================================
    // Bagian 1: Penanganan Unggah Berkas & Drag Drop
    // ==========================================
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('pdf_file');

    if (dropZone && fileInput) {
        dropZone.addEventListener('click', () => fileInput.click());

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('border-primary', 'bg-surface-container-low');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('border-primary', 'bg-surface-container-low');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                displaySelectedFile(fileInput);
            }
        });
    }

    function displaySelectedFile(input) {
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');

        if (input.files && input.files[0]) {
            const file = input.files[0];

            if (file.type !== 'application/pdf') {
                alert('Peringatan: Berkas yang dipilih harus berupa file PDF (.pdf).');
                input.value = '';
                fileInfo.classList.add('hidden');
                window.hideSignaturePreview();
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert('Peringatan: Ukuran berkas melebihi batas maksimal 10 MB.');
                input.value = '';
                fileInfo.classList.add('hidden');
                window.hideSignaturePreview();
                return;
            }

            fileName.innerText = file.name;
            fileName.title = file.name;
            fileSize.innerText = (file.size / (1024 * 1024)).toFixed(1) + ' MB • Berkas siap';
            fileInfo.classList.remove('hidden');

            const titleInput = document.getElementById('title');
            if (titleInput && !titleInput.value.trim()) {
                const cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/_/g, " ");
                titleInput.value = cleanName;
            }

            // Tampilkan pratinjau PDF interaktif untuk menentukan posisi TTD
            window.loadSignaturePreview(file);
        } else {
            fileInfo.classList.add('hidden');
            window.hideSignaturePreview();
        }
    }

    function clearSelectedFile() {
        const input = document.getElementById('pdf_file');
        const fileInfo = document.getElementById('file-info');
        if (input) input.value = '';
        if (fileInfo) fileInfo.classList.add('hidden');
        window.hideSignaturePreview();
    }

    // ==========================================
    // Bagian 2: Pratinjau PDF & Posisi Tanda Tangan
    // ==========================================
    (function () {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const SCALE = 1.0;
        const BASE_W = 170;
        const RATIO = 170 / 80;

        let pdfDoc = null;
        let pageNum = 1;
        let mult = 1;
        let busy = false;

        const posCard   = document.getElementById('position-card');
        const canvas    = document.getElementById('pdf-render-canvas');
        const ctx       = canvas.getContext('2d');
        const container = document.getElementById('pdf-canvas-container');
        const box       = document.getElementById('draggable-sig-box');
        const resizer   = document.getElementById('sig-box-resizer');

        function updateCoords() {
            const px = Math.round(box.offsetLeft / SCALE);
            const py = Math.round(box.offsetTop / SCALE);
            document.getElementById('form-page-num').value   = pageNum;
            document.getElementById('form-pos-x').value      = px;
            document.getElementById('form-pos-y').value      = py;
            document.getElementById('form-box-width').value  = Math.round(box.offsetWidth / SCALE);
            document.getElementById('form-box-height').value = Math.round(box.offsetHeight / SCALE);
            document.getElementById('coord-x-val').innerText = px;
            document.getElementById('coord-y-val').innerText = py;
        }

        function setBoxScale(m) {
            mult = Math.max(0.7, Math.min(2.5, m));
            let w = Math.round(BASE_W * SCALE * mult);
            let h = Math.round(w / RATIO);

            if (container.clientWidth && w > container.clientWidth) {
                w = container.clientWidth;
                h = Math.round(w / RATIO);
                mult = w / (BASE_W * SCALE);
            }

            box.style.width  = w + 'px';
            box.style.height = h + 'px';
            box.style.left   = Math.min(box.offsetLeft, Math.max(0, container.clientWidth - w)) + 'px';
            box.style.top    = Math.min(box.offsetTop,  Math.max(0, container.clientHeight - h)) + 'px';

            document.getElementById('sig-scale-label').innerText = Math.round(mult * 100) + '%';
            updateCoords();
        }

        async function renderPage(num) {
            if (!pdfDoc || busy) return;
            busy = true;
            try {
                const page = await pdfDoc.getPage(num);
                const vp = page.getViewport({ scale: SCALE });
                canvas.width  = vp.width;
                canvas.height = vp.height;
                container.style.width  = vp.width + 'px';
                container.style.height = vp.height + 'px';
                await page.render({ canvasContext: ctx, viewport: vp }).promise;
                pageNum = num;
                document.getElementById('current-page-num').innerText = num;
            } finally {
                busy = false;
            }
            setBoxScale(mult);
        }

        window.loadSignaturePreview = async function (file) {
            try {
                const buf = await file.arrayBuffer();
                pdfDoc = await pdfjsLib.getDocument({ data: buf }).promise;
                document.getElementById('total-pages-num').innerText = pdfDoc.numPages;
                posCard.classList.remove('hidden');
                mult = 1;
                pageNum = 1;
                box.style.left = '80px';
                box.style.top  = '100px';
                await renderPage(1);
            } catch (err) {
                console.error('Gagal memuat pratinjau PDF:', err);
                pdfDoc = null;
                posCard.classList.add('hidden');
                alert('PDF tidak dapat ditampilkan untuk mengatur posisi tanda tangan. Pastikan berkas PDF valid.');
            }
        };

        window.hideSignaturePreview = function () {
            pdfDoc = null;
            posCard.classList.add('hidden');
        };

        // Navigasi halaman
        document.getElementById('btn-prev-page').addEventListener('click', function () {
            if (pdfDoc && pageNum > 1) renderPage(pageNum - 1);
        });
        document.getElementById('btn-next-page').addEventListener('click', function () {
            if (pdfDoc && pageNum < pdfDoc.numPages) renderPage(pageNum + 1);
        });

        // Ukuran kotak
        document.getElementById('btn-grow-sig').addEventListener('click', function () { setBoxScale(mult + 0.15); });
        document.getElementById('btn-shrink-sig').addEventListener('click', function () { setBoxScale(mult - 0.15); });

        // Geser kotak (Pointer Drag)
        let drag = null;
        box.addEventListener('pointerdown', function (e) {
            if (e.target.closest('#sig-box-resizer')) return;
            box.setPointerCapture(e.pointerId);
            drag = { x: e.clientX, y: e.clientY, l: box.offsetLeft, t: box.offsetTop };
            box.style.cursor = 'grabbing';
        });
        box.addEventListener('pointermove', function (e) {
            if (!drag) return;
            const maxL = Math.max(0, container.clientWidth  - box.offsetWidth);
            const maxT = Math.max(0, container.clientHeight - box.offsetHeight);
            box.style.left = Math.max(0, Math.min(drag.l + e.clientX - drag.x, maxL)) + 'px';
            box.style.top  = Math.max(0, Math.min(drag.t + e.clientY - drag.y, maxT)) + 'px';
            updateCoords();
        });
        function endDrag() { drag = null; box.style.cursor = 'grab'; }
        box.addEventListener('pointerup', endDrag);
        box.addEventListener('pointercancel', endDrag);

        // Ubah ukuran dari pojok kanan bawah
        let rs = null;
        resizer.addEventListener('pointerdown', function (e) {
            e.stopPropagation();
            e.preventDefault();
            resizer.setPointerCapture(e.pointerId);
            rs = { x: e.clientX, w: box.offsetWidth };
        });
        resizer.addEventListener('pointermove', function (e) {
            if (!rs) return;
            setBoxScale((rs.w + e.clientX - rs.x) / (BASE_W * SCALE));
        });
        resizer.addEventListener('pointerup', function () { rs = null; });
        resizer.addEventListener('pointercancel', function () { rs = null; });

        // Pastikan posisi sudah ditentukan sebelum form dikirim
        document.getElementById('upload-form').addEventListener('submit', function (e) {
            if (!pdfDoc) {
                e.preventDefault();
                alert('Pratinjau dokumen belum siap. Pilih ulang berkas PDF lalu tentukan posisi tanda tangan.');
                return;
            }
            updateCoords();
        });
    })();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
