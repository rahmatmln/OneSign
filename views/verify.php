<?php
/**
 * OneSign - Halaman Verifikasi Tanda Tangan Digital (Publik)
 * Halaman ini dapat diakses tanpa login untuk memverifikasi keaslian tanda tangan
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

$token = trim($_GET['token'] ?? '');
$verifyData = null;
$isValid = false;

if (!empty($token) && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT 
            ds.signed_at,
            ds.signer_ip,
            ds.verify_token,
            u.full_name AS signer_name,
            u.email AS signer_email,
            u.identifier_number AS signer_nip,
            d.title AS doc_title
        FROM document_signers ds
        JOIN users u ON ds.signer_id = u.id
        JOIN documents d ON ds.document_id = d.id
        WHERE ds.verify_token = :token AND ds.status = 'disetujui'
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $verifyData = $stmt->fetch();

    if ($verifyData) {
        $isValid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?= $isValid ? 'Tanda Tangan Terverifikasi' : 'Verifikasi Gagal' ?> - OneSign</title>
    
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <style>
        @layer base {
            html, body { margin: 0; padding: 0; }
            body { overscroll-behavior: none; }
        }
        ::-webkit-scrollbar { display: none; }
    </style>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "background": "#f8f9ff",
                        "surface": "#f8f9ff",
                        "outline-variant": "#c5c5d3",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#eff4ff",
                        "primary": "#00236f",
                        "surface-container": "#e5eeff",
                        "surface-container-highest": "#d3e4fe",
                        "error": "#ba1a1a",
                        "error-container": "#ffdad6",
                        "outline": "#757682",
                        "surface-container-high": "#dce9ff",
                        "on-surface": "#0b1c30",
                        "on-error-container": "#93000a",
                        "secondary": "#565e74",
                        "primary-container": "#1e3a8a",
                        "on-primary": "#ffffff"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "fontFamily": {
                        "headline-lg": ["Plus Jakarta Sans"],
                        "headline-md": ["Plus Jakarta Sans"],
                        "headline-sm": ["Plus Jakarta Sans"],
                        "body-md": ["Plus Jakarta Sans"],
                        "body-sm": ["Plus Jakarta Sans"],
                        "label-md": ["Plus Jakarta Sans"],
                        "label-sm": ["Plus Jakarta Sans"]
                    },
                    "fontSize": {
                        "headline-md": ["20px", {"lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "label-md": ["13px", {"lineHeight": "18px", "fontWeight": "600"}],
                        "body-sm": ["12px", {"lineHeight": "16px", "fontWeight": "400"}],
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
<main class="min-h-screen w-full flex flex-col items-center justify-center p-4">

<?php if ($isValid): ?>
    <div class="w-full max-w-md bg-surface-container-lowest rounded-xl shadow-xl border border-surface-container overflow-hidden">
        
        <!-- Status Header -->
        <div class="bg-emerald-50 border-b border-emerald-200 px-6 py-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-emerald-700 text-[24px]" style="font-variation-settings: 'FILL' 1;">verified</span>
            </div>
            <div>
                <h1 class="font-headline-sm text-headline-sm text-emerald-800">Tanda Tangan Sah</h1>
                <p class="font-body-sm text-body-sm text-emerald-600">Dokumen ini telah ditandatangani secara digital melalui OneSign</p>
            </div>
        </div>

        <!-- Detail Informasi -->
        <div class="px-6 py-5 flex flex-col gap-4">
            
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Detail Penandatangan</div>

            <!-- NIP -->
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary text-[20px] mt-0.5 flex-shrink-0">badge</span>
                <div class="min-w-0">
                    <div class="font-label-sm text-label-sm text-secondary">Nomor Induk Pegawai (NIP)</div>
                    <div class="font-body-md text-on-surface break-all"><?= !empty($verifyData['signer_nip']) ? htmlspecialchars($verifyData['signer_nip']) : '-' ?></div>
                </div>
            </div>

            <hr class="border-surface-container-high">

            <!-- Nama -->
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary text-[20px] mt-0.5 flex-shrink-0">person</span>
                <div class="min-w-0">
                    <div class="font-label-sm text-label-sm text-secondary">Nama Penandatangan</div>
                    <div class="font-headline-sm text-headline-sm text-on-surface break-words"><?= htmlspecialchars($verifyData['signer_name']) ?></div>
                </div>
            </div>

            <hr class="border-surface-container-high">



            <!-- Email -->
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary text-[20px] mt-0.5 flex-shrink-0">mail</span>
                <div class="min-w-0">
                    <div class="font-label-sm text-label-sm text-secondary">Email</div>
                    <div class="font-body-md text-on-surface break-all"><?= htmlspecialchars($verifyData['signer_email']) ?></div>
                </div>
            </div>

            <hr class="border-surface-container-high">

            <!-- Waktu -->
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary text-[20px] mt-0.5 flex-shrink-0">schedule</span>
                <div class="min-w-0">
                    <div class="font-label-sm text-label-sm text-secondary">Waktu Penandatanganan</div>
                    <div class="font-body-md text-on-surface">
                        <?php 
                            $dt = new DateTime($verifyData['signed_at'], new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
                            echo $dt->format('d F Y, H:i:s') . ' WIB';
                        ?>
                    </div>
                </div>
            </div>

            <hr class="border-surface-container-high">

            <!-- IP Address -->
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary text-[20px] mt-0.5 flex-shrink-0">language</span>
                <div class="min-w-0">
                    <div class="font-label-sm text-label-sm text-secondary">Alamat IP Penandatangan</div>
                    <div class="font-body-md text-on-surface font-mono"><?= htmlspecialchars($verifyData['signer_ip'] ?? '-') ?></div>
                </div>
            </div>

            <hr class="border-surface-container-high">

            <!-- Dokumen -->
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary text-[20px] mt-0.5 flex-shrink-0">description</span>
                <div class="min-w-0">
                    <div class="font-label-sm text-label-sm text-secondary">Judul Dokumen</div>
                    <div class="font-body-md text-on-surface break-words"><?= htmlspecialchars($verifyData['doc_title']) ?></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-surface-container-low px-6 py-3 border-t border-surface-container">
            <p class="font-body-sm text-body-sm text-secondary text-center">
                OneSign &middot; Jurusan Teknik Informatika dan Komputer &middot; Politeknik Negeri Jakarta
            </p>
        </div>
    </div>

<?php else: ?>
    <div class="w-full max-w-md bg-surface-container-lowest rounded-xl shadow-xl border border-surface-container overflow-hidden">
        
        <!-- Status Header -->
        <div class="bg-red-50 border-b border-red-200 px-6 py-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-red-700 text-[24px]" style="font-variation-settings: 'FILL' 1;">gpp_bad</span>
            </div>
            <div>
                <h1 class="font-headline-sm text-headline-sm text-red-800">Verifikasi Gagal</h1>
                <p class="font-body-sm text-body-sm text-red-600">Tanda tangan tidak dikenali atau token tidak valid</p>
            </div>
        </div>

        <div class="px-6 py-5">
            <p class="font-body-md text-secondary">
                Dokumen yang Anda periksa tidak terdaftar dalam sistem OneSign, atau kode verifikasi yang dipindai sudah tidak berlaku. 
                Pastikan QR code yang Anda scan berasal dari dokumen resmi yang ditandatangani melalui sistem OneSign.
            </p>
        </div>

        <!-- Footer -->
        <div class="bg-surface-container-low px-6 py-3 border-t border-surface-container">
            <p class="font-body-sm text-body-sm text-secondary text-center">
                OneSign &middot; Jurusan Teknik Informatika dan Komputer &middot; Politeknik Negeri Jakarta
            </p>
        </div>
    </div>
<?php endif; ?>

</main>
</body>
</html>
