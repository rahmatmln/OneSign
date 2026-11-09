<?php
/**
 * OneSign - Workspace Penandatanganan Dokumen (Dosen)
 * Menampilkan PDF viewer berbasis PDF.js, kotak TTD terkunci (posisi ditentukan pemohon), dan kanvas TTD (FR-02)
 * REVISI: penandatangan tidak lagi mengatur posisi; hanya menggores TTD lalu mengesahkan / menolak.
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
    
    <!-- Google Fonts & Material Symbols (Stitch Design System) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (via CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
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
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "full": "9999px"
                    },
                    spacing: {
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
                    fontFamily: {
                        "sans": ["Plus Jakarta Sans", "sans-serif"],
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
                    fontSize: {
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

    <!-- Mozilla PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        #pdf-canvas-container {
            position: relative;
            box-shadow: 0 10px 30px -5px rgba(0, 35, 111, 0.15), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-background font-body-md text-on-surface antialiased selection:bg-primary selection:text-on-primary min-h-screen flex flex-col justify-between">

    <!-- Header Workspace (Institutional Modernism) -->
    <header class="bg-surface-container-lowest border-b border-outline-variant/30 sticky top-0 z-40 shadow-sm backdrop-blur-md">
        <div class="w-full px-space-lg flex items-center justify-between h-16">
            
            <!-- Left: Back Button & Document Identity -->
            <div class="flex items-center gap-space-md">
                <a href="<?= BASE_URL ?>/views/penandatangan/dashboard.php" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-label-md font-label-md text-on-surface-variant hover:text-on-surface bg-surface-container hover:bg-surface-container-high rounded-lg transition-colors"
                   title="Kembali ke Antrean">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    <span class="hidden sm:inline">Kembali</span>
                </a>
                
                <div class="h-6 w-px bg-outline-variant/30 hidden sm:block"></div>
                
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <span class="font-headline-sm text-headline-sm font-bold text-on-surface truncate max-w-xs sm:max-w-md lg:max-w-lg">
                            <?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($doc['sign_status'] === 'menunggu'): ?>
                            <span class="hidden md:inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-secondary-container text-on-secondary-container font-label-sm text-label-sm font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                Menunggu Pengesahan
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary truncate">
                        Pemohon: <strong class="text-on-surface font-semibold"><?= htmlspecialchars($doc['requester_name'], ENT_QUOTES, 'UTF-8') ?></strong> 
                        (NIM: <span class="font-code-audit-sm"><?= htmlspecialchars($doc['requester_nim'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>) • <?= htmlspecialchars($doc['category'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- Right: Action Buttons -->
            <div class="flex items-center gap-space-sm">
                <?php if ($doc['sign_status'] === 'menunggu'): ?>
                    <button type="button" onclick="openRejectModal()" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-label-md font-label-md font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-[18px]">rule</span>
                        <span class="hidden sm:inline">Tolak Berkas</span>
                    </button>
                    
                    <button type="button" onclick="openSignatureModal()" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-label-md font-label-md font-semibold text-on-primary bg-primary hover:bg-primary-container rounded-lg shadow-sm transition-colors">
                        <span class="material-symbols-outlined text-[18px]">edit_draw</span>
                        <span>Gores TTD</span>
                    </button>
                    
                    <button type="button" id="btn-submit-sign" onclick="submitApproval()" disabled
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-label-md font-label-md font-bold text-white bg-emerald-700 rounded-lg opacity-50 cursor-not-allowed shadow-sm transition-all">
                        <span class="material-symbols-outlined text-[18px]">verified</span>
                        <span>Sahkan Dokumen</span>
                    </button>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-surface-container text-label-md font-label-md font-semibold text-on-surface border border-outline-variant/30">
                        <span class="material-symbols-outlined text-[18px] text-emerald-700">task_alt</span>
                        <span>Status: <?= ucfirst($doc['sign_status']) ?></span>
                    </span>
                <?php endif; ?>
            </div>

        </div>
    </header>

    <!-- Main Workspace Area -->
    <main class="w-full px-space-md sm:px-space-lg py-space-md flex-1 flex flex-col">
        
        <!-- Toolbar Navigasi Halaman & Info Posisi (read-only) -->
        <div class="bg-surface-container-lowest border border-outline-variant/20 rounded-xl p-space-sm mb-space-md flex flex-wrap items-center justify-between gap-3 shadow-sm">
            
            <!-- Page Navigation -->
            <div class="flex items-center gap-space-xs">
                <div class="flex items-center bg-surface-container-low rounded-lg p-0.5 border border-outline-variant/20">
                    <button type="button" id="btn-prev-page" class="p-1.5 rounded hover:bg-surface-container text-on-surface-variant transition-colors" title="Halaman Sebelumnya">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </button>
                    <div class="px-3 font-body-sm text-body-sm text-on-surface flex items-center gap-1.5">
                        <span class="text-secondary">Halaman</span>
                        <span id="current-page-num" class="font-bold text-primary font-code-audit">1</span>
                        <span class="text-secondary">dari</span>
                        <span id="total-pages-num" class="font-bold font-code-audit">1</span>
                    </div>
                    <button type="button" id="btn-next-page" class="p-1.5 rounded hover:bg-surface-container text-on-surface-variant transition-colors" title="Halaman Berikutnya">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>
                </div>
            </div>

            <!-- Info posisi TTD yang ditentukan pemohon -->
            <div class="flex items-center gap-space-md bg-surface-container-low px-3 py-1.5 rounded-lg border border-outline-variant/20 font-code-audit text-code-audit text-secondary">
                <div class="flex items-center gap-1">
                    <span class="text-secondary font-medium">X:</span>
                    <span id="coord-x-val" class="font-bold text-primary">0</span> <span class="text-[10px]">pt</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-secondary font-medium">Y:</span>
                    <span id="coord-y-val" class="font-bold text-primary">0</span> <span class="text-[10px]">pt</span>
                </div>
                <div class="flex items-center gap-1 border-l border-outline-variant/30 pl-2">
                    <span class="text-secondary font-medium">Hal TTD:</span>
                    <span id="target-page-val" class="font-bold text-on-surface"><?= (int)$doc['page_number'] ?></span>
                </div>
            </div>

            <div class="hidden lg:flex items-center gap-1.5 text-secondary font-body-sm text-body-sm">
                <span class="material-symbols-outlined text-[16px] text-primary">lock</span>
                <span>Posisi tanda tangan sudah ditentukan oleh pemohon. Klik <b>Gores TTD</b> lalu <b>Sahkan Dokumen</b>.</span>
            </div>

        </div>

        <!-- Area Kanvas PDF & Overlay Kotak TTD (terkunci) -->
        <div class="flex-1 flex justify-center overflow-x-auto py-2 bg-surface-container-low/50 rounded-xl p-4 border border-outline-variant/10">
            
            <div id="pdf-canvas-container" class="rounded-lg relative">
                <!-- Kanvas Render Halaman PDF oleh PDF.js -->
                <canvas id="pdf-render-canvas" class="block"></canvas>

                <!-- Kotak Tanda Tangan TERKUNCI (posisi dari pemohon) -->
                <?php if ($doc['sign_status'] === 'menunggu'): ?>
                    <div id="draggable-sig-box" 
                         class="absolute border-2 border-dashed border-primary bg-surface-container-lowest/90 rounded-xl p-2.5 flex-col justify-between text-center shadow-lg select-none pointer-events-none"
                         style="display:none; z-index:30;">
                        
                        <!-- State 1: Placeholder Sebelum Gores TTD -->
                        <div id="sig-box-placeholder" class="w-full h-full flex flex-col items-center justify-center pointer-events-none select-none text-center">
                            <span class="material-symbols-outlined text-primary text-[22px] mb-0.5">draw</span>
                            <span id="sig-ph-title" class="font-headline-sm text-headline-sm font-bold text-primary block tracking-tight leading-tight transition-all" style="font-size: 11px;">
                                AREA TANDA TANGAN
                            </span>
                            <span id="sig-ph-sub" class="font-body-sm text-body-sm text-secondary block mt-0.5 transition-all" style="font-size: 9px;">
                                Tanda tangan di area ini
                            </span>
                        </div>

                        <!-- State 2: Preview Setelah TTD Diterapkan -->
                        <div id="sig-box-preview" class="hidden w-full h-full flex flex-col items-center justify-between pointer-events-none select-none">
                            <div class="flex-1 w-full flex items-center justify-center overflow-hidden min-h-0 py-0.5">
                                <img id="sig-preview-img" src="" alt="Citra TTD" class="max-h-full max-w-full object-contain filter drop-shadow">
                            </div>
                            <div class="w-full border-t border-outline-variant/30 pt-1 mt-0.5 shrink-0 bg-surface-container-low/50 rounded-b">
                                <span id="sig-signer-name" class="font-bold text-primary block truncate leading-tight font-label-md transition-all" style="font-size: 10px;">
                                    <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span id="sig-signer-nip" class="text-secondary font-code-audit-sm block truncate leading-tight transition-all mt-0.5" style="font-size: 8px;">
                                    NIP. <?= htmlspecialchars($user['identifier_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?> • Sah BSrE PNJ
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </main>

    <!-- Hidden Form untuk Pengiriman Data Persetujuan (posisi diambil dari database di sisi server) -->
    <form id="approval-form" action="<?= BASE_URL ?>/actions/sign_action.php?action=approve" method="POST" class="hidden">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
        <input type="hidden" name="signature_data" id="form-signature-data" value="">
    </form>

    <!-- Modal Kanvas Gores Tanda Tangan (Stitch Dialog Style) -->
    <div id="modal-canvas" class="fixed inset-0 z-50 bg-on-surface/40 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full border border-outline-variant/30 shadow-2xl p-6 space-y-4">
            
            <div class="flex items-center justify-between border-b border-outline-variant/20 pb-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">draw</span>
                    <div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Goreskan Tanda Tangan</h3>
                        <p class="font-body-sm text-body-sm text-secondary">Bubuhkan goresan tanda tangan digital Anda menggunakan mouse atau layar sentuh.</p>
                    </div>
                </div>
                <button type="button" onclick="closeSignatureModal()" class="text-secondary hover:text-on-surface p-1 rounded-lg hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Kanvas Gambar TTD -->
            <div class="border-2 border-outline-variant/30 rounded-xl overflow-hidden bg-surface-container-lowest shadow-inner">
                <canvas id="signing-pad" width="380" height="180" class="w-full bg-surface-container-lowest cursor-crosshair"></canvas>
            </div>

            <div class="flex items-center justify-between text-xs">
                <button type="button" onclick="clearSignaturePad()" class="font-label-md text-label-md font-semibold text-rose-700 hover:text-rose-900 transition flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                    <span>Bersihkan Kanvas</span>
                </button>
                <span class="font-code-audit-sm text-code-audit-sm text-secondary">Tinta: Biru Institusi (#1e3a8a)</span>
            </div>

            <div class="pt-3 border-t border-outline-variant/20 flex items-center justify-end gap-2">
                <button type="button" onclick="closeSignatureModal()" class="px-4 py-2 text-label-md font-label-md font-semibold text-secondary hover:text-on-surface bg-surface-container hover:bg-surface-container-high rounded-lg transition-colors">
                    Batal
                </button>
                <button type="button" onclick="applySignatureToBox()" class="px-4 py-2 text-label-md font-label-md font-bold text-on-primary bg-primary hover:bg-primary-container rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">check</span>
                    <span>Terapkan ke Dokumen</span>
                </button>
            </div>

        </div>
    </div>

    <!-- Modal Konfirmasi Penolakan Dokumen (Stitch Dialog Style) -->
    <div id="modal-reject" class="fixed inset-0 z-50 bg-on-surface/40 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full border border-outline-variant/30 shadow-2xl p-6 space-y-4">
            
            <div class="border-b border-outline-variant/20 pb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-rose-700 text-[22px]">rule</span>
                <div>
                    <h3 class="font-headline-sm text-headline-sm font-bold text-rose-900">Konfirmasi Penolakan Dokumen</h3>
                    <p class="font-body-sm text-body-sm text-secondary">Berikan catatan alasan penolakan agar pemohon dapat melakukan perbaikan.</p>
                </div>
            </div>

            <form action="<?= BASE_URL ?>/actions/sign_action.php?action=reject" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">

                <div>
                    <label for="rejection_note" class="block font-label-md text-label-md text-on-surface font-semibold mb-1.5">
                        Alasan Penolakan <span class="text-rose-600">*</span>
                    </label>
                    <textarea id="rejection_note" name="rejection_note" required rows="4"
                              placeholder="Tuliskan poin perbaikan atau alasan naskah ditolak..."
                              class="w-full px-3.5 py-2.5 font-body-md text-body-md border border-outline-variant/30 rounded-xl bg-surface-container-low text-on-surface focus:outline-none focus:bg-surface-container-lowest focus:ring-1 focus:ring-rose-600 transition"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2 border-t border-outline-variant/20">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-label-md font-label-md font-semibold text-secondary hover:text-on-surface bg-surface-container hover:bg-surface-container-high rounded-lg transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 text-label-md font-label-md font-bold text-white bg-rose-700 hover:bg-rose-800 rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">send</span>
                        <span>Kirim Penolakan</span>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Script Logika PDF.js, Kotak TTD Terkunci & Canvas Pad -->
    <script>
        // Konfigurasi Worker PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Posisi tanda tangan yang ditentukan pemohon (satuan point PDF, dari database)
        const SIG = {
            page: <?= (int)$doc['page_number'] ?>,
            x: <?= (float)$doc['pos_x'] ?>,
            y: <?= (float)$doc['pos_y'] ?>,
            w: <?= (float)$doc['box_width'] ?>,
            h: <?= (float)$doc['box_height'] ?>
        };

        const pdfUrl = '<?= BASE_URL ?>/actions/view_pdf.php?doc_id=<?= $doc['id'] ?>';
        let pdfDoc = null;
        let pageNum = SIG.page;
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
                    placeLockedBox();
                });
            });

            document.getElementById('current-page-num').innerText = num;
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
            if (pageNum > pdfDoc.numPages) pageNum = 1;
            renderPage(pageNum);
        }).catch(function(error) {
            console.error('Gagal merender PDF:', error);
            alert('Gagal memuat dokumen PDF. Pastikan koneksi stabil.');
        });

        // ==========================================
        // Kotak TTD terkunci (posisi dari pemohon)
        // ==========================================
        function placeLockedBox() {
            if (!dragBox) return;

            // Kotak hanya tampil pada halaman yang ditentukan pemohon
            if (pageNum !== SIG.page) {
                dragBox.style.display = 'none';
                return;
            }

            dragBox.style.display = 'flex';
            dragBox.style.left   = Math.round(SIG.x * scale) + 'px';
            dragBox.style.top    = Math.round(SIG.y * scale) + 'px';
            dragBox.style.width  = Math.round(SIG.w * scale) + 'px';
            dragBox.style.height = Math.round(SIG.h * scale) + 'px';

            // Skala ukuran font teks secara proporsional terhadap lebar kotak
            const k = SIG.w / 170;
            const setFont = function(id, px, min) {
                const el = document.getElementById(id);
                if (el) el.style.fontSize = Math.max(min, Math.round(px * k)) + 'px';
            };
            setFont('sig-ph-title', 11, 8);
            setFont('sig-ph-sub', 9, 7);
            setFont('sig-signer-name', 10.5, 9);
            setFont('sig-signer-nip', 8.5, 7);

            document.getElementById('coord-x-val').innerText = Math.round(SIG.x);
            document.getElementById('coord-y-val').innerText = Math.round(SIG.y);
        }

        // ==========================================
        // Logika Kanvas Tanda Tangan (HTML5 Canvas)
        // ==========================================
        const pad = document.getElementById('signing-pad');
        const padCtx = pad.getContext('2d');
        let isDrawing = false;
        let signatureDrawn = false;

        padCtx.strokeStyle = '#1e3a8a'; // Tinta biru tua resmi institusi
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