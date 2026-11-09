<?php
/**
 * OneSign - Form Pengajuan Dokumen PDF (Pemohon)
 * Design Stitch: Pengajuan Dokumen OneSign (100% Identik)
 * REVISI: Pemohon menentukan posisi tanda tangan penandatangan pada pratinjau PDF.
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
        <div class="flex items-center gap-space-xs text-secondary font-label-md text-label-md">
            <span class="w-6 h-6 rounded-full bg-surface-container text-secondary flex items-center justify-center text-label-sm font-bold">2</span>
            <span>Detail &amp; Penandatangan</span>
        </div>
        <span class="text-outline-variant font-bold text-xs">/</span>
        <div class="flex items-center gap-space-xs text-secondary font-label-md text-label-md">
            <span class="w-6 h-6 rounded-full bg-surface-container text-secondary flex items-center justify-center text-label-sm font-bold">3</span>
            <span>Atur Posisi TTD</span>
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

    <!-- ============================================================ -->
    <!-- REVISI: Atur Posisi Tanda Tangan oleh Pemohon                 -->
    <!-- ============================================================ -->
    <input type="hidden" name="page_number" id="form-page-num" value="1">
    <input type="hidden" name="pos_x" id="form-pos-x" value="0">
    <input type="hidden" name="pos_y" id="form-pos-y" value="0">
    <input type="hidden" name="box_width" id="form-box-width" value="170">
    <input type="hidden" name="box_height" id="form-box-height" value="80">

    <div id="position-card" class="hidden bg-surface-container-lowest p-space-lg rounded-xl shadow-sm border border-outline-variant/20 space-y-space-md">
        <div class="flex flex-wrap items-center justify-between gap-space-sm">
            <div class="flex items-center gap-space-xs text-on-surface font-headline-sm text-headline-sm font-semibold">
                <span class="material-symbols-outlined text-primary text-[20px]">draw</span>
                <span>Atur Posisi Tanda Tangan</span>
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
                    <button type="button" id="btn-shrink-sig" class="w-7 h-7 rounded bg-surface-container-lowest hover:bg-surface-container border border-outline-variant/30 flex items-center justify-center" title="Perkecil">
                        <span class="material-symbols-outlined text-[16px]">remove</span>
                    </button>
                    <span id="sig-scale-label" class="font-bold text-primary min-w-[45px] text-center">100%</span>
                    <button type="button" id="btn-grow-sig" class="w-7 h-7 rounded bg-surface-container-lowest hover:bg-surface-container border border-outline-variant/30 flex items-center justify-center" title="Perbesar">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                    </button>
                </div>

                <!-- Indikator Koordinat -->
                <div class="flex items-center gap-2 bg-surface-container-low px-3 py-1.5 rounded-lg border border-outline-variant/20 text-secondary" style="font-family:'JetBrains Mono',monospace;font-size:12px;">
                    <span>X: <b id="coord-x-val" class="text-primary">0</b></span>
                    <span>Y: <b id="coord-y-val" class="text-primary">0</b></span>
                </div>
            </div>
        </div>

        <p class="font-body-sm text-body-sm text-secondary">
            Geser kotak ke tempat dosen harus menandatangani, atau tarik pojok kanan bawah untuk mengubah ukuran. Pindah halaman jika tanda tangan harus berada di halaman lain.
        </p>

        <div class="flex justify-center overflow-x-auto bg-surface-container-low/50 rounded-xl p-4 border border-outline-variant/10">
            <div id="pdf-canvas-container" style="position:relative;background:#ffffff;box-shadow:0 10px 30px -5px rgba(0,35,111,.15);">
                <canvas id="pdf-render-canvas" class="block"></canvas>

                <div id="draggable-sig-box"
                     class="absolute border-2 border-dashed border-primary bg-surface-container-lowest/90 rounded-xl flex flex-col items-center justify-center text-center shadow-lg"
                     style="left:100px;top:100px;width:230px;height:108px;z-index:30;cursor:grab;touch-action:none;user-select:none;">
                    <span class="material-symbols-outlined text-primary text-[22px] pointer-events-none">draw</span>
                    <span class="font-bold text-primary pointer-events-none" style="font-size:11px;">AREA TANDA TANGAN</span>
                    <span class="text-secondary pointer-events-none" style="font-size:9px;">Dosen menandatangani di sini</span>

                    <div id="sig-box-resizer"
                         class="absolute -right-2.5 -bottom-2.5 w-6 h-6 bg-primary text-on-primary rounded-full border-2 border-white shadow-md flex items-center justify-center"
                         style="cursor:nwse-resize;touch-action:none;"
                         title="Tarik untuk mengubah ukuran">
                        <span class="material-symbols-outlined text-[13px] pointer-events-none">drag_pan</span>
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

<!-- Mozilla PDF.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    // ==========================================
    // Bagian 1: Pemilihan berkas (logika asli)
    // ==========================================
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
                window.hideSignaturePreview();
                return;
            }

            // Validasi ukuran maksimal 10 MB
            if (file.size > 10 * 1024 * 1024) {
                alert('Peringatan: Ukuran berkas melebihi batas maksimal 10 MB.');
                input.value = '';
                fileInfo.classList.add('hidden');
                window.hideSignaturePreview();
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

            // REVISI: tampilkan pratinjau PDF untuk mengatur posisi TTD
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
    // Bagian 2: Pratinjau PDF & kotak posisi TTD
    // ==========================================
    (function () {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const SCALE  = 1.35;      // skala render di layar
        const BASE_W = 170;       // lebar dasar kotak (pt)
        const BASE_H = 80;        // tinggi dasar kotak (pt)
        const RATIO  = BASE_W / BASE_H;

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
            box.style.left = Math.min(box.offsetLeft, Math.max(0, container.clientWidth - w)) + 'px';
            box.style.top  = Math.min(box.offsetTop,  Math.max(0, container.clientHeight - h)) + 'px';

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
                box.style.left = '100px';
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

        // Geser kotak
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
                alert('Pratinjau dokumen belum siap. Pilih ulang berkas PDF lalu atur posisi tanda tangan.');
                return;
            }
            updateCoords();
        });
    })();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>