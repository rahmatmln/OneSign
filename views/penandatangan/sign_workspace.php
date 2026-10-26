<?php
/**
 * OneSign - Workspace Penandatanganan Dokumen (Dosen)
 * Menampilkan PDF viewer berbasis PDF.js, kotak geser koordinat (FR-07), dan kanvas TTD (FR-02)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

$pageTitle = 'Workspace Penandatanganan Dokumen';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';

requireRole(['penandatangan']);

$user  = getActiveUser();
$docId = (int)($_GET['doc_id'] ?? 0);

if ($docId <= 0) {
    header('Location: ' . BASE_URL . '/views/penandatangan/dashboard.php');
    exit;
}

$pdo = Database::getConnection();

// Mengambil rincian dokumen dan penugasan penandatangan
$stmt = $pdo->prepare("
    SELECT 
        d.id, d.title, d.category, d.confidentiality, d.original_filename, 
        d.file_size, d.status AS doc_status, d.created_at,
        u.full_name AS requester_name, u.identifier_number AS requester_nim,
        ds.id AS signer_entry_id, ds.status AS sign_status, ds.page_number, ds.pos_x, ds.pos_y, ds.box_width, ds.box_height
    FROM document_signers ds
    JOIN documents d ON ds.document_id = d.id
    JOIN users u ON d.requester_id = u.id
    WHERE d.id = :doc_id AND ds.signer_id = :signer_id
    LIMIT 1
");
$stmt->execute([
    ':doc_id'    => $docId,
    ':signer_id' => $user['id']
]);
$doc = $stmt->fetch();

if (!$doc) {
    die('Dokumen tidak ditemukan atau Anda tidak ditugaskan sebagai penandatangan berkas ini.');
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace TTE - <?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Tailwind CSS (via CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Mozilla PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        .draggable-signature-box {
            cursor: grab;
            user-select: none;
            touch-action: none;
        }
        .draggable-signature-box:active {
            cursor: grabbing;
        }
        #pdf-canvas-container {
            position: relative;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            background-color: #ffffff;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-slate-200 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Header Workspace -->
    <header class="bg-blue-900 border-b border-blue-950 text-white sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
            
            <div class="flex items-center space-x-3">
                <a href="<?= BASE_URL ?>/views/penandatangan/dashboard.php" class="px-2.5 py-1 text-xs font-semibold bg-blue-950 border border-blue-800 rounded hover:bg-blue-800 transition">
                    &larr; Kembali
                </a>
                <span class="w-px h-5 bg-blue-800"></span>
                <div>
                    <span class="font-bold text-sm tracking-tight text-white truncate max-w-xs sm:max-w-md block">
                        <?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="text-[11px] text-blue-200">
                        Pemohon: <?= htmlspecialchars($doc['requester_name'], ENT_QUOTES, 'UTF-8') ?> (NIM: <?= htmlspecialchars($doc['requester_nim'] ?? '-', ENT_QUOTES, 'UTF-8') ?>)
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center space-x-2">
                <?php if ($doc['sign_status'] === 'menunggu'): ?>
                    <button type="button" onclick="openRejectModal()" 
                            class="px-3 py-1.5 text-xs font-semibold text-rose-200 bg-rose-950/80 border border-rose-800 rounded hover:bg-rose-900 hover:text-white transition">
                        Tolak Berkas
                    </button>
                    <button type="button" onclick="openSignatureModal()" 
                            class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 rounded hover:bg-blue-500 shadow transition">
                        Gores Tanda Tangan
                    </button>
                    <button type="button" id="btn-submit-sign" onclick="submitApproval()" disabled
                            class="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 rounded opacity-50 cursor-not-allowed shadow transition">
                        Sahkan Dokumen
                    </button>
                <?php else: ?>
                    <span class="px-3 py-1 rounded bg-slate-800 text-xs text-slate-300 font-semibold border border-slate-700">
                        Status: <?= ucfirst($doc['sign_status']) ?>
                    </span>
                <?php endif; ?>
            </div>

        </div>
    </header>

    <!-- Main Workspace Layout -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 w-full flex-1">
        
        <!-- Toolbar Kontrol PDF & Indikator Koordinat -->
        <div class="bg-white border border-slate-300 rounded-xl p-3 mb-4 flex flex-wrap items-center justify-between gap-3 shadow-sm text-xs">
            
            <!-- PDF Page Navigation -->
            <div class="flex items-center space-x-2">
                <button type="button" id="btn-prev-page" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded font-semibold text-slate-700">
                    &larr; Halaman Sebelumnya
                </button>
                <span class="font-mono font-medium text-slate-700 px-2">
                    Halaman <span id="current-page-num" class="font-bold text-blue-900">1</span> dari <span id="total-pages-num" class="font-bold">1</span>
                </span>
                <button type="button" id="btn-next-page" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded font-semibold text-slate-700">
                    Halaman Selanjutnya &rarr;
                </button>
            </div>

            <!-- Coordinate Readout Panel -->
            <div class="flex items-center space-x-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200 font-mono text-[11px]">
                <div>
                    <span class="text-slate-500">X:</span>
                    <span id="coord-x-val" class="font-bold text-blue-800">0</span> pt
                </div>
                <div>
                    <span class="text-slate-500">Y:</span>
                    <span id="coord-y-val" class="font-bold text-blue-800">0</span> pt
                </div>
                <div>
                    <span class="text-slate-500">Hal:</span>
                    <span id="target-page-val" class="font-bold text-slate-900">1</span>
                </div>
            </div>

            <!-- Kontrol Perbesar / Perkecil Ukuran Kotak TTD (Rasio Tetap) -->
            <div class="flex items-center space-x-1.5 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-200">
                <span class="text-slate-600 font-medium">Ukuran TTD:</span>
                <button type="button" id="btn-shrink-sig" class="w-6 h-6 rounded bg-white hover:bg-slate-200 border border-slate-300 font-bold text-slate-700 flex items-center justify-center transition" title="Perkecil ukuran tanda tangan">-</button>
                <span id="sig-scale-label" class="font-mono font-bold text-blue-900 min-w-[40px] text-center text-[11px]">100%</span>
                <button type="button" id="btn-grow-sig" class="w-6 h-6 rounded bg-white hover:bg-slate-200 border border-slate-300 font-bold text-slate-700 flex items-center justify-center transition" title="Perbesar ukuran tanda tangan">+</button>
                <button type="button" id="btn-reset-sig" class="px-2 py-0.5 text-[10px] rounded bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold transition" title="Kembalikan ke ukuran standar">Reset</button>
            </div>

            <div class="text-[11px] text-slate-500 italic hidden xl:block">
                Petunjuk: Geser kotak atau tarik lingkaran di sudut kanan bawah untuk memperbesar kotak TTD.
            </div>

        </div>

        <!-- Area Kanvas PDF & Overlay Kotak TTD -->
        <div class="flex justify-center overflow-x-auto py-2">
            
            <div id="pdf-canvas-container">
                <!-- Kanvas Render Halaman PDF oleh PDF.js -->
                <canvas id="pdf-render-canvas" class="block"></canvas>

                <!-- Kotak Geser Tanda Tangan (Draggable & Resizable Box) -->
                <?php if ($doc['sign_status'] === 'menunggu'): ?>
                    <div id="draggable-sig-box" 
                         class="draggable-signature-box absolute border-2 border-dashed border-blue-600 bg-blue-50/85 rounded-lg p-2 flex flex-col justify-between text-center shadow-md select-none"
                         style="width: 230px; height: 108px; left: 100px; top: 100px; z-index: 30;">
                        
                        <!-- State 1: Placeholder Sebelum Gores TTD -->
                        <div id="sig-box-placeholder" class="w-full h-full flex flex-col items-center justify-center pointer-events-none select-none">
                            <span id="sig-ph-title" class="font-bold text-blue-900 block tracking-tight transition-all" style="font-size: 11px;">KOTAK PENEMPATAN TTD</span>
                            <span id="sig-ph-sub" class="text-blue-700 block mt-0.5 transition-all" style="font-size: 9px;">Geser & perbesar kotak sesuai kebutuhan</span>
                        </div>

                        <!-- State 2: Preview Setelah TTD Diterapkan -->
                        <div id="sig-box-preview" class="hidden w-full h-full flex flex-col items-center justify-between pointer-events-none select-none">
                            <div class="flex-1 w-full flex items-center justify-center overflow-hidden min-h-0 py-0.5">
                                <img id="sig-preview-img" src="" alt="Citra TTD" class="max-h-full max-w-full object-contain filter drop-shadow">
                            </div>
                            <div class="w-full border-t border-blue-300/80 pt-0.5 mt-0.5 shrink-0">
                                <span id="sig-signer-name" class="font-bold text-blue-950 block truncate leading-tight transition-all" style="font-size: 10px;">
                                    <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span id="sig-signer-nip" class="text-slate-600 font-mono block truncate leading-tight transition-all mt-0.5" style="font-size: 8px;">
                                    NIP. <?= htmlspecialchars($user['identifier_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?> | TTE Sah
                                </span>
                            </div>
                        </div>

                        <!-- Handle Tarik Perbesar / Perkecil Sudut Kanan Bawah (Resizer Handle) -->
                        <div id="sig-box-resizer" 
                             class="absolute -right-2 -bottom-2 w-5 h-5 bg-blue-700 hover:bg-blue-800 text-white rounded-full border-2 border-white shadow-md cursor-nwse-resize flex items-center justify-center transition select-none z-40"
                             title="Tarik untuk memperbesar / memperkecil tanda tangan (rasio tetap)">
                            <svg class="w-2.5 h-2.5 text-white pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 19L5 5m14 0v14H5"/>
                            </svg>
                        </div>

                    </div>
                <?php endif; ?>

            </div>

        </div>

    </main>

    <!-- Hidden Form untuk Pengiriman Data Persetujuan -->
    <form id="approval-form" action="<?= BASE_URL ?>/actions/sign_action.php?action=approve" method="POST" class="hidden">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
        <input type="hidden" name="page_number" id="form-page-num" value="1">
        <input type="hidden" name="pos_x" id="form-pos-x" value="0">
        <input type="hidden" name="pos_y" id="form-pos-y" value="0">
        <input type="hidden" name="box_width" id="form-box-width" value="170">
        <input type="hidden" name="box_height" id="form-box-height" value="80">
        <input type="hidden" name="signature_data" id="form-signature-data" value="">
    </form>

    <!-- Modal Kanvas Gores Tanda Tangan -->
    <div id="modal-canvas" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full border border-slate-300 shadow-2xl p-6 space-y-4">
            
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Goreskan Tanda Tangan Elektronik</h3>
                    <p class="text-[11px] text-slate-500">Bubuhkan goresan tanda tangan Anda menggunakan mouse atau layar sentuh.</p>
                </div>
                <button type="button" onclick="closeSignatureModal()" class="text-slate-400 hover:text-slate-600 text-sm font-semibold">Tutup</button>
            </div>

            <!-- Kanvas Gambar TTD -->
            <div class="border-2 border-slate-200 rounded-lg overflow-hidden bg-slate-50">
                <canvas id="signing-pad" width="380" height="180" class="w-full bg-white cursor-crosshair"></canvas>
            </div>

            <div class="flex items-center justify-between text-xs">
                <button type="button" onclick="clearSignaturePad()" class="font-semibold text-rose-700 hover:text-rose-900 transition">
                    Bersihkan Kanvas
                </button>
                <span class="text-[11px] text-slate-400">Warna tinta: Biru Institusi</span>
            </div>

            <div class="pt-3 border-t border-slate-200 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeSignatureModal()" class="px-4 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded border border-slate-300 transition">
                    Batal
                </button>
                <button type="button" onclick="applySignatureToBox()" class="px-4 py-2 text-xs font-bold text-white bg-blue-800 hover:bg-blue-900 rounded shadow transition">
                    Terapkan ke Kotak TTD
                </button>
            </div>

        </div>
    </div>

    <!-- Modal Konfirmasi Penolakan Dokumen -->
    <div id="modal-reject" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full border border-slate-300 shadow-2xl p-6 space-y-4">
            
            <div class="border-b border-slate-200 pb-3">
                <h3 class="text-sm font-bold text-rose-900">Konfirmasi Penolakan Dokumen</h3>
                <p class="text-[11px] text-slate-500">Berikan catatan alasan penolakan agar pemohon dapat melakukan perbaikan.</p>
            </div>

            <form action="<?= BASE_URL ?>/actions/sign_action.php?action=reject" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">

                <div>
                    <label for="rejection_note" class="block text-xs font-bold text-slate-700 mb-1">
                        Alasan Penolakan <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="rejection_note" name="rejection_note" required rows="4"
                              placeholder="Tuliskan poin perbaikan atau alasan naskah ditolak..."
                              class="w-full px-3 py-2 text-xs border border-slate-300 rounded focus:outline-none focus:ring-1 focus:ring-rose-600 focus:border-rose-600 bg-slate-50 focus:bg-white transition"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end space-x-2">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded border border-slate-300 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-rose-700 hover:bg-rose-800 rounded shadow transition">
                        Kirim Penolakan
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Script Logika PDF.js, Draggable Box & Canvas Pad -->
    <script>
        // Konfigurasi Worker PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const pdfUrl = '<?= BASE_URL ?>/actions/view_pdf.php?doc_id=<?= $doc['id'] ?>';
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.35; // Skala render di layar

        const canvas = document.getElementById('pdf-render-canvas');
        const ctx = canvas.getContext('2d');
        const container = document.getElementById('pdf-canvas-container');
        const dragBox = document.getElementById('draggable-sig-box');

        // Render Halaman Spesifik
        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                container.style.width = viewport.width + 'px';
                container.style.height = viewport.height + 'px';

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                const renderTask = page.render(renderContext);

                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                    updateCoordinateValues();
                });
            });

            document.getElementById('current-page-num').innerText = num;
            document.getElementById('target-page-val').innerText = num;
            document.getElementById('form-page-num').value = num;
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        document.getElementById('btn-prev-page').addEventListener('click', function() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        });

        document.getElementById('btn-next-page').addEventListener('click', function() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        });

        // Muat Dokumen PDF
        pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            document.getElementById('total-pages-num').innerText = pdfDoc.numPages;
            renderPage(pageNum);
        }).catch(function(error) {
            console.error('Gagal merender PDF:', error);
            alert('Gagal memuat dokumen PDF. Pastikan koneksi stabil.');
        });

        // ==========================================
        // Logika Draggable & Resizable Box (FR-07)
        // ==========================================
        const basePtW = 170;
        const basePtH = 80;
        const aspectRatio = basePtW / basePtH; // 2.125
        let currentScaleMult = 1.0;

        function updateCoordinateValues() {
            if (!dragBox) return;
            const pixelX = dragBox.offsetLeft;
            const pixelY = dragBox.offsetTop;

            // Konversi koordinat layar (skala 1.35) kembali ke ukuran standar PDF (Points, 72 DPI)
            const pointX = Math.round(pixelX / scale);
            const pointY = Math.round(pixelY / scale);
            const pointW = Math.round(dragBox.offsetWidth / scale);
            const pointH = Math.round(dragBox.offsetHeight / scale);

            const cxElem = document.getElementById('coord-x-val');
            const cyElem = document.getElementById('coord-y-val');
            if (cxElem) cxElem.innerText = pointX;
            if (cyElem) cyElem.innerText = pointY;

            const fxElem = document.getElementById('form-pos-x');
            const fyElem = document.getElementById('form-pos-y');
            const fwElem = document.getElementById('form-box-width');
            const fhElem = document.getElementById('form-box-height');
            if (fxElem) fxElem.value = pointX;
            if (fyElem) fyElem.value = pointY;
            if (fwElem) fwElem.value = pointW;
            if (fhElem) fhElem.value = pointH;
        }

        function applySigBoxScale(multiplier) {
            if (!dragBox) return;
            // Batas minimal 70% dan maksimal 250%
            multiplier = Math.max(0.7, Math.min(2.5, multiplier));
            currentScaleMult = multiplier;

            const basePixelW = basePtW * scale;
            let newW = Math.round(basePixelW * multiplier);
            let newH = Math.round(newW / aspectRatio);

            // Batasi agar tidak meluap dari kanvas dokumen
            if (container.clientWidth > 0 && newW > container.clientWidth) {
                newW = container.clientWidth;
                newH = Math.round(newW / aspectRatio);
                currentScaleMult = newW / basePixelW;
            }
            if (container.clientHeight > 0 && newH > container.clientHeight) {
                newH = container.clientHeight;
                newW = Math.round(newH * aspectRatio);
                currentScaleMult = newW / basePixelW;
            }

            // Geser posisi jika perbesaran melewati batas kanan atau bawah
            const maxLeft = Math.max(0, container.clientWidth - newW);
            const maxTop  = Math.max(0, container.clientHeight - newH);

            if (dragBox.offsetLeft > maxLeft) {
                dragBox.style.left = maxLeft + 'px';
            }
            if (dragBox.offsetTop > maxTop) {
                dragBox.style.top = maxTop + 'px';
            }

            dragBox.style.width  = newW + 'px';
            dragBox.style.height = newH + 'px';

            // Skala ukuran font teks identitas dan placeholder secara proporsional
            const phTitle = document.getElementById('sig-ph-title');
            const phSub   = document.getElementById('sig-ph-sub');
            const sigName = document.getElementById('sig-signer-name');
            const sigNip  = document.getElementById('sig-signer-nip');

            if (phTitle) phTitle.style.fontSize = Math.round(11 * currentScaleMult) + 'px';
            if (phSub)   phSub.style.fontSize   = Math.max(8, Math.round(9 * currentScaleMult)) + 'px';
            if (sigName) sigName.style.fontSize = Math.max(9, Math.round(10.5 * currentScaleMult)) + 'px';
            if (sigNip)  sigNip.style.fontSize  = Math.max(7, Math.round(8.5 * currentScaleMult)) + 'px';

            const scaleLabel = document.getElementById('sig-scale-label');
            if (scaleLabel) {
                scaleLabel.innerText = Math.round(currentScaleMult * 100) + '%';
            }

            updateCoordinateValues();
        }

        if (dragBox) {
            let isDragging = false;
            let startX, startY, initialLeft, initialTop;

            dragBox.addEventListener('mousedown', startDrag);
            dragBox.addEventListener('touchstart', startDragTouch, { passive: false });

            function startDrag(e) {
                if (e.target.closest('#sig-box-resizer')) return;
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                initialLeft = dragBox.offsetLeft;
                initialTop = dragBox.offsetTop;
                document.addEventListener('mousemove', onDrag);
                document.addEventListener('mouseup', stopDrag);
            }

            function startDragTouch(e) {
                if (e.target.closest('#sig-box-resizer')) return;
                if (e.touches.length === 1) {
                    isDragging = true;
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                    initialLeft = dragBox.offsetLeft;
                    initialTop = dragBox.offsetTop;
                    document.addEventListener('touchmove', onDragTouch, { passive: false });
                    document.addEventListener('touchend', stopDragTouch);
                }
            }

            function onDrag(e) {
                if (!isDragging) return;
                updatePosition(e.clientX - startX, e.clientY - startY);
            }

            function onDragTouch(e) {
                if (!isDragging) return;
                e.preventDefault();
                updatePosition(e.touches[0].clientX - startX, e.touches[0].clientY - startY);
            }

            function updatePosition(dx, dy) {
                const maxLeft = Math.max(0, container.clientWidth - dragBox.offsetWidth);
                const maxTop  = Math.max(0, container.clientHeight - dragBox.offsetHeight);

                let newLeft = Math.max(0, Math.min(initialLeft + dx, maxLeft));
                let newTop  = Math.max(0, Math.min(initialTop + dy, maxTop));

                dragBox.style.left = newLeft + 'px';
                dragBox.style.top  = newTop + 'px';

                updateCoordinateValues();
            }

            function stopDrag() {
                isDragging = false;
                document.removeEventListener('mousemove', onDrag);
                document.removeEventListener('mouseup', stopDrag);
            }

            function stopDragTouch() {
                isDragging = false;
                document.removeEventListener('touchmove', onDragTouch);
                document.removeEventListener('touchend', stopDragTouch);
            }

            // Event Listener Resizer Sudut Kanan Bawah
            const resizer = document.getElementById('sig-box-resizer');
            if (resizer) {
                let isResizing = false;
                let resizeStartX = 0;
                let resizeStartWidth = 0;

                resizer.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    isResizing = true;
                    resizeStartX = e.clientX;
                    resizeStartWidth = dragBox.offsetWidth;
                    document.addEventListener('mousemove', onResizeMove);
                    document.addEventListener('mouseup', stopResize);
                });

                resizer.addEventListener('touchstart', function(e) {
                    e.stopPropagation();
                    if (e.touches.length === 1) {
                        isResizing = true;
                        resizeStartX = e.touches[0].clientX;
                        resizeStartWidth = dragBox.offsetWidth;
                        document.addEventListener('touchmove', onResizeTouch, { passive: false });
                        document.addEventListener('touchend', stopResizeTouch);
                    }
                }, { passive: false });

                function onResizeMove(e) {
                    if (!isResizing) return;
                    const dx = e.clientX - resizeStartX;
                    const targetWidth = resizeStartWidth + dx;
                    const basePixelW = basePtW * scale;
                    applySigBoxScale(targetWidth / basePixelW);
                }

                function onResizeTouch(e) {
                    if (!isResizing) return;
                    e.preventDefault();
                    const dx = e.touches[0].clientX - resizeStartX;
                    const targetWidth = resizeStartWidth + dx;
                    const basePixelW = basePtW * scale;
                    applySigBoxScale(targetWidth / basePixelW);
                }

                function stopResize() {
                    isResizing = false;
                    document.removeEventListener('mousemove', onResizeMove);
                    document.removeEventListener('mouseup', stopResize);
                }

                function stopResizeTouch() {
                    isResizing = false;
                    document.removeEventListener('touchmove', onResizeTouch);
                    document.removeEventListener('touchend', stopResizeTouch);
                }
            }

            // Tombol Toolbar Skala (+, -, Reset)
            const btnGrow = document.getElementById('btn-grow-sig');
            const btnShrink = document.getElementById('btn-shrink-sig');
            const btnReset = document.getElementById('btn-reset-sig');

            if (btnGrow) {
                btnGrow.addEventListener('click', function() {
                    applySigBoxScale(currentScaleMult + 0.15);
                });
            }
            if (btnShrink) {
                btnShrink.addEventListener('click', function() {
                    applySigBoxScale(currentScaleMult - 0.15);
                });
            }
            if (btnReset) {
                btnReset.addEventListener('click', function() {
                    applySigBoxScale(1.0);
                });
            }

            // Inisialisasi awal ukuran kotak
            applySigBoxScale(1.0);
        }

        // ==========================================
        // Logika Kanvas Tanda Tangan (HTML5 Canvas)
        // ==========================================
        const pad = document.getElementById('signing-pad');
        const padCtx = pad.getContext('2d');
        let isDrawing = false;
        let signatureDrawn = false;

        padCtx.strokeStyle = '#1e3a8a'; // Tinta biru tua resmi
        padCtx.lineWidth = 2.5;
        padCtx.lineCap = 'round';
        padCtx.lineJoin = 'round';

        function getPadCoords(e) {
            const rect = pad.getBoundingClientRect();
            const scaleX = pad.width / rect.width;
            const scaleY = pad.height / rect.height;
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        }

        pad.addEventListener('mousedown', function(e) {
            isDrawing = true;
            signatureDrawn = true;
            const c = getPadCoords(e);
            padCtx.beginPath();
            padCtx.moveTo(c.x, c.y);
        });

        pad.addEventListener('mousemove', function(e) {
            if (!isDrawing) return;
            const c = getPadCoords(e);
            padCtx.lineTo(c.x, c.y);
            padCtx.stroke();
        });

        window.addEventListener('mouseup', function() { isDrawing = false; });

        // Touch support
        pad.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
                e.preventDefault();
                isDrawing = true;
                signatureDrawn = true;
                const c = getPadCoords(e.touches[0]);
                padCtx.beginPath();
                padCtx.moveTo(c.x, c.y);
            }
        }, { passive: false });

        pad.addEventListener('touchmove', function(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const c = getPadCoords(e.touches[0]);
            padCtx.lineTo(c.x, c.y);
            padCtx.stroke();
        }, { passive: false });

        pad.addEventListener('touchend', function() { isDrawing = false; });

        function clearSignaturePad() {
            padCtx.clearRect(0, 0, pad.width, pad.height);
            signatureDrawn = false;
        }

        function openSignatureModal() {
            document.getElementById('modal-canvas').classList.remove('hidden');
        }

        function closeSignatureModal() {
            document.getElementById('modal-canvas').classList.add('hidden');
        }

        function applySignatureToBox() {
            if (!signatureDrawn) {
                alert('Silakan goreskan tanda tangan Anda pada kanvas terlebih dahulu.');
                return;
            }

            const dataUrl = pad.toDataURL('image/png');
            document.getElementById('sig-preview-img').src = dataUrl;
            document.getElementById('sig-box-placeholder').classList.add('hidden');
            document.getElementById('sig-box-preview').classList.remove('hidden');

            document.getElementById('form-signature-data').value = dataUrl;

            // Aktifkan tombol pengesahan dokumen
            const btnSubmit = document.getElementById('btn-submit-sign');
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');

            closeSignatureModal();
        }

        function submitApproval() {
            if (!signatureDrawn) {
                alert('Tanda tangan belum digoreskan.');
                return;
            }
            if (confirm('Konfirmasi: Apakah Anda yakin ingin mengesahkan dokumen ini secara elektronik? Berkas yang telah disahkan akan terkunci secara permanen.')) {
                document.getElementById('approval-form').submit();
            }
        }

        // Modal Penolakan
        function openRejectModal() {
            document.getElementById('modal-reject').classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('modal-reject').classList.add('hidden');
        }
    </script>

</body>
</html>
