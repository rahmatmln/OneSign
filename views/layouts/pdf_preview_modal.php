<?php
/**
 * OneSign - Reusable In-Page PDF Preview Modal (Protected Read-Only Viewer)
 * Menampilkan pratinjau dokumen langsung di jendela yang sama (in-page modal)
 * Menggunakan Mozilla PDF.js pada elemen Canvas tanpa tombol unduh/cetak
 * Mencegah pengunduhan di jendela pratinjau (Screen Protection & Read-Only)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */
?>

<!-- Script Mozilla PDF.js (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<!-- Modal Overlay Pratinjau Terproteksi -->
<div id="pdfPreviewModal" 
     class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4 md:p-6 hidden transition-opacity duration-200"
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="pdfPreviewTitle">

    <div class="bg-surface-container-lowest border border-outline-variant/30 rounded-2xl shadow-2xl w-full max-w-5xl h-[94vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Header Toolbar Modal -->
        <div class="px-4 py-3 bg-surface-container-low border-b border-outline-variant/20 flex flex-wrap items-center justify-between gap-3 shrink-0">
            
            <!-- Sisi Kiri: Judul Dokumen & Badge Read-Only -->
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                </div>
                <div class="min-w-0">
                    <h3 id="pdfPreviewTitle" class="font-headline-sm text-headline-sm font-bold text-on-surface truncate text-sm sm:text-base leading-tight">
                        Pratinjau Dokumen
                    </h3>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-950 border border-amber-300/60">
                            <span class="material-symbols-outlined text-[13px] text-amber-800">lock</span>
                            Hanya Baca (Read-Only)
                        </span>
                        <span class="text-secondary text-[11px] hidden sm:inline">• Di jendela yang sama tanpa opsi unduh</span>
                    </div>
                </div>
            </div>

            <!-- Sisi Tengah: Kontrol Navigasi Halaman & Zoom -->
            <div class="flex items-center gap-1 bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-2 py-1 shadow-xs">
                
                <!-- Tombol Prev -->
                <button type="button" 
                        id="btnPdfPrevPage" 
                        class="w-7 h-7 rounded-lg hover:bg-surface-container-high flex items-center justify-center text-on-surface transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        title="Halaman Sebelumnya (Tombol Panah Kiri)">
                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                </button>
                
                <!-- Indikator Halaman -->
                <div class="flex items-center gap-1 font-code-audit text-xs font-semibold px-1 text-on-surface whitespace-nowrap">
                    <input type="number" id="inputPdfPreviewPage" min="1" max="1" value="1" class="w-12 h-6 text-center font-bold text-primary bg-surface-container-low border border-outline-variant/30 rounded focus:ring-1 focus:ring-primary focus:outline-none font-code-audit text-xs" title="Ketik nomor halaman lalu tekan Enter">
                    <span class="text-secondary font-normal">/</span>
                    <span id="pdfPreviewTotalPages">1</span>
                </div>
                
                <!-- Tombol Next -->
                <button type="button" 
                        id="btnPdfNextPage" 
                        class="w-7 h-7 rounded-lg hover:bg-surface-container-high flex items-center justify-center text-on-surface transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        title="Halaman Selanjutnya (Tombol Panah Kanan)">
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </button>

                <div class="w-px h-4 bg-outline-variant/30 mx-1"></div>

                <!-- Zoom Out -->
                <button type="button" 
                        id="btnPdfZoomOut" 
                        class="w-7 h-7 rounded-lg hover:bg-surface-container-high flex items-center justify-center text-secondary hover:text-on-surface transition-colors"
                        title="Perkecil Tampilan (-)">
                    <span class="material-symbols-outlined text-[17px]">zoom_out</span>
                </button>

                <!-- Zoom Level -->
                <span id="pdfPreviewZoomLabel" class="font-code-audit text-[11px] text-secondary font-medium w-11 text-center select-none">
                    100%
                </span>

                <!-- Zoom In -->
                <button type="button" 
                        id="btnPdfZoomIn" 
                        class="w-7 h-7 rounded-lg hover:bg-surface-container-high flex items-center justify-center text-secondary hover:text-on-surface transition-colors"
                        title="Perbesar Tampilan (+)">
                    <span class="material-symbols-outlined text-[17px]">zoom_in</span>
                </button>

                <!-- Fit Width -->
                <button type="button" 
                        id="btnPdfFitWidth" 
                        class="w-7 h-7 rounded-lg hover:bg-surface-container-high flex items-center justify-center text-secondary hover:text-on-surface transition-colors"
                        title="Sesuaikan Lebar">
                    <span class="material-symbols-outlined text-[17px]">fit_screen</span>
                </button>
            </div>

            <!-- Sisi Kanan: Tombol Tutup (X) -->
            <button type="button" 
                    onclick="closePdfPreview()" 
                    class="w-8 h-8 rounded-lg hover:bg-rose-100 hover:text-rose-700 flex items-center justify-center text-secondary transition-colors"
                    title="Tutup Pratinjau (Esc)">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Body Modal: Canvas Area PDF (Right-click & Shortcut Disabled) -->
        <div id="pdfCanvasScrollWrapper" 
             class="flex-1 overflow-auto bg-slate-900/95 flex items-start justify-center p-3 sm:p-6 select-none relative"
             oncontextmenu="return false;">
            
            <!-- Loading Indicator -->
            <div id="pdfPreviewLoading" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-900/90 text-white z-10 transition-opacity">
                <div class="w-10 h-10 border-3 border-emerald-500/30 border-t-emerald-500 rounded-full animate-spin"></div>
                <p class="font-body-sm text-body-sm text-slate-300">Memuat pratinjau dokumen terproteksi...</p>
            </div>

            <!-- Pesan Error -->
            <div id="pdfPreviewError" class="hidden absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-900/95 text-white z-10 p-6 text-center">
                <span class="material-symbols-outlined text-rose-500 text-[40px]">error_outline</span>
                <p id="pdfPreviewErrorText" class="font-body-md text-slate-200 max-w-md">Gagal memuat dokumen.</p>
                <button type="button" onclick="closePdfPreview()" class="px-4 py-2 bg-surface-container-high hover:bg-surface-container-highest text-white rounded-lg font-label-sm text-xs font-semibold mt-2">
                    Tutup Pratinjau
                </button>
            </div>

            <!-- Canvas Element Rendered by PDF.js -->
            <div class="relative shadow-2xl rounded border border-slate-700/50 bg-white inline-block">
                <canvas id="pdfPreviewCanvas" class="block"></canvas>
            </div>
        </div>

        <!-- Footer Modal: Keterangan Keamanan & Tombol Tutup -->
        <div class="px-4 py-2.5 bg-surface-container-low border-t border-outline-variant/20 flex items-center justify-between text-xs text-secondary shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[15px] text-emerald-600">verified_user</span>
                <span class="font-body-sm text-[11px] text-secondary">
                    Dokumen dilindungi watermark dinamis. Untuk mengunduh berkas sah, gunakan tombol <strong>Unduh</strong> pada tabel.
                </span>
            </div>
            <button type="button" 
                    onclick="closePdfPreview()" 
                    class="px-3 py-1 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-semibold text-xs transition-colors">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- JavaScript Logika PDF.js In-Page Preview -->
<script>
(function() {
    // Inisialisasi konfigurasi worker PDF.js
    if (window.pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    let currentPdfDoc = null;
    let currentPageNum = 1;
    let isPageRendering = false;
    let pendingPageNum = null;
    let currentScale = 1.25; // Skala default agar nyaman dibaca

    const modal = document.getElementById('pdfPreviewModal');
    const canvas = document.getElementById('pdfPreviewCanvas');
    const ctx = canvas.getContext('2d');
    const titleEl = document.getElementById('pdfPreviewTitle');
    const curPageInput = document.getElementById('inputPdfPreviewPage');
    const totPageEl = document.getElementById('pdfPreviewTotalPages');
    const zoomLabel = document.getElementById('pdfPreviewZoomLabel');
    const loadingEl = document.getElementById('pdfPreviewLoading');
    const errorEl = document.getElementById('pdfPreviewError');
    const errorTextEl = document.getElementById('pdfPreviewErrorText');
    const btnPrev = document.getElementById('btnPdfPrevPage');
    const btnNext = document.getElementById('btnPdfNextPage');
    const scrollWrapper = document.getElementById('pdfCanvasScrollWrapper');

    // Fungsi membuka modal pratinjau langsung di jendela yang sama
    window.openPdfPreview = function(docId, docTitle) {
        if (!docId) return;

        titleEl.textContent = docTitle || 'Pratinjau Dokumen';
        errorEl.classList.add('hidden');
        loadingEl.classList.remove('hidden');
        loadingEl.style.opacity = '1';

        // Tampilkan modal
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Kunci scroll halaman belakang

        currentPageNum = 1;
        currentScale = 1.25;
        updateZoomLabel();

        // Alamat pratinjau terproteksi (dilengkapi security watermark)
        const previewUrl = '<?= BASE_URL ?>/actions/preview_action.php?doc_id=' + encodeURIComponent(docId);

        // Muat berkas via PDF.js
        pdfjsLib.getDocument({
            url: previewUrl,
            withCredentials: true
        }).promise.then(function(pdfDoc_) {
            currentPdfDoc = pdfDoc_;
            totPageEl.textContent = currentPdfDoc.numPages;
            if (curPageInput) curPageInput.max = currentPdfDoc.numPages;
            loadingEl.classList.add('hidden');
            renderPreviewPage(currentPageNum);
        }).catch(function(err) {
            console.error('PDF.js Preview Error:', err);
            loadingEl.classList.add('hidden');
            errorEl.classList.remove('hidden');
            errorTextEl.textContent = 'Gagal memuat pratinjau: ' + (err.message || 'Berkas tidak dapat diakses.');
        });
    };

    // Fungsi menutup modal pratinjau
    window.closePdfPreview = function() {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Kembalikan scroll
        if (currentPdfDoc) {
            try { currentPdfDoc.destroy(); } catch(e) {}
            currentPdfDoc = null;
        }
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    // Fungsi render halaman ke canvas
    function renderPreviewPage(num) {
        if (!currentPdfDoc) return;
        isPageRendering = true;

        btnPrev.disabled = (num <= 1);
        btnNext.disabled = (num >= currentPdfDoc.numPages);

        currentPdfDoc.getPage(num).then(function(page) {
            // High-DPI Display Sharpness (Retina Ready)
            const dpr = window.devicePixelRatio || 1;
            const viewport = page.getViewport({ scale: currentScale });

            canvas.height = viewport.height * dpr;
            canvas.width = viewport.width * dpr;
            canvas.style.height = viewport.height + 'px';
            canvas.style.width = viewport.width + 'px';

            const renderContext = {
                canvasContext: ctx,
                transform: [dpr, 0, 0, dpr, 0, 0],
                viewport: viewport
            };

            const renderTask = page.render(renderContext);

            renderTask.promise.then(function() {
                isPageRendering = false;
                if (pendingPageNum !== null) {
                    renderPreviewPage(pendingPageNum);
                    pendingPageNum = null;
                }
            });
        }).catch(function(err) {
            console.error('Page render error:', err);
            isPageRendering = false;
        });

        if (curPageInput) {
            curPageInput.value = num;
            if (currentPdfDoc) curPageInput.max = currentPdfDoc.numPages;
        }
    }

    function queueRender(num) {
        if (isPageRendering) {
            pendingPageNum = num;
        } else {
            renderPreviewPage(num);
        }
    }

    function updateZoomLabel() {
        zoomLabel.textContent = Math.round(currentScale * 100 / 1.25) + '%';
    }

    // Input Halaman Manual
    if (curPageInput) {
        const handleJumpPage = function() {
            if (!currentPdfDoc) return;
            let val = parseInt(curPageInput.value, 10);
            if (isNaN(val)) return;
            if (val < 1) val = 1;
            if (val > currentPdfDoc.numPages) val = currentPdfDoc.numPages;
            curPageInput.value = val;
            if (val !== currentPageNum) {
                currentPageNum = val;
                queueRender(currentPageNum);
            }
        };
        curPageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleJumpPage();
            }
        });
        curPageInput.addEventListener('change', handleJumpPage);
    }

    // Navigasi Tombol
    btnPrev.addEventListener('click', function() {
        if (currentPageNum <= 1) return;
        currentPageNum--;
        queueRender(currentPageNum);
    });

    btnNext.addEventListener('click', function() {
        if (!currentPdfDoc || currentPageNum >= currentPdfDoc.numPages) return;
        currentPageNum++;
        queueRender(currentPageNum);
    });

    // Zoom Controls
    document.getElementById('btnPdfZoomIn').addEventListener('click', function() {
        if (currentScale >= 3.0) return;
        currentScale += 0.25;
        updateZoomLabel();
        queueRender(currentPageNum);
    });

    document.getElementById('btnPdfZoomOut').addEventListener('click', function() {
        if (currentScale <= 0.6) return;
        currentScale -= 0.25;
        updateZoomLabel();
        queueRender(currentPageNum);
    });

    document.getElementById('btnPdfFitWidth').addEventListener('click', function() {
        if (!currentPdfDoc) return;
        currentPdfDoc.getPage(currentPageNum).then(function(page) {
            const wrapperWidth = scrollWrapper.clientWidth - 48;
            const baseViewport = page.getViewport({ scale: 1.0 });
            currentScale = Math.max(0.6, Math.min(2.5, wrapperWidth / baseViewport.width));
            updateZoomLabel();
            queueRender(currentPageNum);
        });
    });

    // Keyboard Shortcuts (Arrow keys untuk ganti halaman, Esc untuk tutup)
    // Mencegah Ctrl+S / Ctrl+P saat modal pratinjau aktif
    window.addEventListener('keydown', function(e) {
        if (modal.classList.contains('hidden')) return;

        // Cegah shortcut unduh / cetak browser
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'S' || e.key === 'P')) {
            e.preventDefault();
            return false;
        }

        if (e.key === 'Escape') {
            closePdfPreview();
        } else if (e.key === 'ArrowRight' || e.key === 'PageDown') {
            if (currentPdfDoc && currentPageNum < currentPdfDoc.numPages) {
                currentPageNum++;
                queueRender(currentPageNum);
            }
        } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
            if (currentPageNum > 1) {
                currentPageNum--;
                queueRender(currentPageNum);
            }
        }
    });

    // Klik di luar kartu modal untuk menutup
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closePdfPreview();
        }
    });
})();
</script>
