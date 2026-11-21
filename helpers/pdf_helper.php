<?php
/**
 * OneSign - PDF Processing Engine
 * Menggunakan FPDF dan FPDI untuk injeksi tanda tangan digital, QR Code, dan Dynamic Security Watermark
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Data\QRMatrix;
use PdfDecompressor\Normalizer;

class OneSignPdfEngine extends Fpdi {
    protected int $angle = 0;
    protected array $extgstates = [];

    /**
     * Mengatur derajat rotasi untuk efek watermark diagonal
     */
    public function Rotate(int $angle, int|float $x = -1, int|float $y = -1): void {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;

        if ($this->angle != 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;

        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    /**
     * Mengatur tingkat transparansi (alpha channel) elemen PDF secara dinamis
     */
    public function SetAlpha(float $alpha, string $bm = 'Normal'): void {
        $gs = $this->AddExtGState(['ca' => $alpha, 'CA' => $alpha, 'BM' => '/' . $bm]);
        $this->SetExtGState($gs);
    }

    public function AddExtGState(array $parms): int {
        $n = count($this->extgstates) + 1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    public function SetExtGState(int $gs): void {
        $this->_out(sprintf('/GS%d gs', $gs));
    }

    protected function _enddoc(): void {
        if (!empty($this->extgstates) && $this->PDFVersion < '1.4') {
            $this->PDFVersion = '1.4';
        }
        parent::_enddoc();
    }

    protected function _putextgstates(): void {
        for ($i = 1; $i <= count($this->extgstates); $i++) {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            $parms = $this->extgstates[$i]['parms'];
            $this->_put(sprintf('/ca %.3F', $parms['ca']));
            $this->_put(sprintf('/CA %.3F', $parms['CA']));
            $this->_put('/BM ' . $parms['BM']);
            $this->_put('>>');
            $this->_put('endobj');
        }
    }

    protected function _putresourcedict(): void {
        parent::_putresourcedict();
        if (!empty($this->extgstates)) {
            $this->_put('/ExtGState <<');
            foreach ($this->extgstates as $k => $extgstate) {
                $this->_put('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
            }
            $this->_put('>>');
        }
    }

    protected function _putresources(): void {
        $this->_putextgstates();
        parent::_putresources();
    }

    public function _beginpage($orientation, $size, $rotation): void {
        parent::_beginpage($orientation, $size, $rotation);
        if ($this->angle != 0) {
            $this->Rotate($this->angle);
        }
    }

    public function _endpage(): void {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    /**
     * Memastikan berkas PDF kompatibel dengan parser FPDI.
     * Mengonversi berkas PDF 1.5+ (Cross-Reference Streams / Object Streams)
     * menjadi struktur PDF standar agar dapat dibaca oleh FPDI tanpa error kompresi.
     */
    public static function prepareReadablePdf(string $sourcePath): string {
        if (!file_exists($sourcePath) || filesize($sourcePath) === 0) {
            return $sourcePath;
        }

        try {
            if (class_exists(Normalizer::class)) {
                $content = file_get_contents($sourcePath);
                if (Normalizer::isCompressed($content)) {
                    $normDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'onesign_norm';
                    if (!is_dir($normDir)) {
                        @mkdir($normDir, 0777, true);
                    }
                    $normalizedFile = $normDir . DIRECTORY_SEPARATOR . 'norm_' . md5($sourcePath . filemtime($sourcePath)) . '.pdf';
                    if (!file_exists($normalizedFile) || filesize($normalizedFile) === 0) {
                        $normalizer = new Normalizer();
                        $normalizer->normalizeFile($sourcePath, $normalizedFile);
                    }
                    if (file_exists($normalizedFile) && filesize($normalizedFile) > 0) {
                        return $normalizedFile;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('PDF Normalizer notice: ' . $e->getMessage());
        }

        return $sourcePath;
    }

    /**
     * Menghasilkan gambar QR code PNG dari sebuah URL
     */
    public static function generateQrCode(string $data, string $outputPath): void {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale'           => 8,
            'outputBase64'    => false,
            'quietzoneSize'   => 1,
            'moduleValues'    => [
                QRMatrix::M_DARKMODULE       => [0, 35, 111],
                QRMatrix::M_DATA_DARK        => [0, 35, 111],
                QRMatrix::M_FINDER_DARK      => [0, 35, 111],
                QRMatrix::M_ALIGNMENT_DARK   => [0, 35, 111],
                QRMatrix::M_TIMING_DARK      => [0, 35, 111],
                QRMatrix::M_FORMAT_DARK      => [0, 35, 111],
                QRMatrix::M_VERSION_DARK     => [0, 35, 111],
                QRMatrix::M_FINDER_DOT       => [0, 35, 111],
            ],
        ]);
        $qrData = (new QRCode($options))->render($data);
        file_put_contents($outputPath, $qrData);
    }

    /**
     * Menginjeksi gambar tanda tangan dan identitas penandatangan ke berkas PDF asli
     */
    public static function stampSignature(
        string $sourcePdfPath,
        string $outputSignedPath,
        int $targetPage,
        string $sigImagePath,
        float $posX,
        float $posY,
        float $boxWidth,
        float $boxHeight,
        string $signerName = '',
        string $signerNip = '',
        string $signedDate = '',
        string $qrImagePath = ''
    ): bool {
        try {
            $cleanSource = self::prepareReadablePdf($sourcePdfPath);

            $pdf = new self('P', 'pt'); // Satuan Points (72 DPI) sesuai PDF.js
            $pdf->SetAutoPageBreak(false); // Cegah FPDF membuat halaman baru otomatis saat stempel di dekat batas bawah
            $pageCount = $pdf->setSourceFile($cleanSource);
            $targetPage = max(1, min($targetPage, $pageCount));

            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tplId = $pdf->importPage($pageNum);
                $size  = $pdf->getTemplateSize($tplId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                // Suntikkan gambar TTD dan identitas penandatangan jika ini adalah halaman target
                if ($pageNum === $targetPage) {
                    $safeX = max(10, min($posX, $size['width'] - $boxWidth - 10));
                    $safeY = max(10, min($posY, $size['height'] - $boxHeight - 10));

                    $scaleFactor = $boxWidth / 170.0;
                    $sigHeight = $boxHeight * 0.58;

                    // 1. Gambar Tanda Tangan
                    if (!empty($sigImagePath) && file_exists($sigImagePath)) {
                        $pdf->Image(
                            $sigImagePath,
                            $safeX + (10 * $scaleFactor),
                            $safeY + (2 * $scaleFactor),
                            $boxWidth - (20 * $scaleFactor),
                            $sigHeight,
                            'PNG'
                        );
                    }

                    // 2. Garis Bawah Pembatas Tanda Tangan
                    $lineY = $safeY + $sigHeight + (2 * $scaleFactor);
                    $pdf->SetDrawColor(15, 23, 42); // Navy tua
                    $pdf->SetLineWidth(0.75 * $scaleFactor);
                    $pdf->Line(
                        $safeX + (5 * $scaleFactor),
                        $lineY,
                        $safeX + $boxWidth - (5 * $scaleFactor),
                        $lineY
                    );

                    // 3. Nama Terang & Gelar Penandatangan
                    $fontSizeName = max(6.5, min(10.0, 9.0 * $scaleFactor));
                    $pdf->SetFont('Helvetica', 'B', $fontSizeName);
                    $pdf->SetTextColor(15, 23, 42);
                    $pdf->SetXY($safeX, $lineY + (1.5 * $scaleFactor));
                    $pdf->Cell($boxWidth, 9 * $scaleFactor, $signerName, 0, 1, 'C');

                    // 4. NIP dan Tanggal Pengesahan
                    $fontSizeInfo = max(5.5, min(8.0, 7.5 * $scaleFactor));
                    $pdf->SetFont('Helvetica', '', $fontSizeInfo);
                    $infoText = (!empty($signerNip) ? 'NIP. ' . $signerNip : '') . (!empty($signerNip) ? '  |  ' : '') . $signedDate;
                    $pdf->SetTextColor(71, 85, 105); // Slate
                    $pdf->SetXY($safeX, $lineY + (1.5 * $scaleFactor) + (8.5 * $scaleFactor));
                    $pdf->Cell($boxWidth, 8 * $scaleFactor, $infoText, 0, 1, 'C');

                    // Tempelkan QR code verifikasi di samping tanda tangan (tinggi presisi sesuai batas tanda tangan)
                    if (!empty($qrImagePath) && file_exists($qrImagePath)) {
                        $qrGap = 6 * $scaleFactor;

                        // Batas vertikal tanda tangan sebenarnya: dari titik atas ($safeY) hingga dasar teks NIP
                        $sigTopY = $safeY;
                        $sigBottomY = $lineY + (18 * $scaleFactor); // Garis bawah teks NIP
                        $sigTotalHeight = $sigBottomY - $sigTopY;

                        // Kunci tinggi card QR agar berada tepat di dalam batas garis atas dan bawah tanda tangan
                        $cardHeight = $sigTotalHeight - (1.0 * $scaleFactor);
                        $qrY = $sigTopY + (0.5 * $scaleFactor);

                        $pad = 2 * $scaleFactor;
                        $labelH = 6 * $scaleFactor;
                        $sepGap = 1.5 * $scaleFactor;
                        $qrImgSize = max(25, $cardHeight - (2 * $pad) - $sepGap - $labelH);
                        $cardWidth = $qrImgSize + (2 * $pad);

                        $qrX = $safeX + $boxWidth + $qrGap;

                        // Jika QR melewati batas kanan halaman, tempatkan di sebelah kiri tanda tangan
                        if ($qrX + $cardWidth > $size['width'] - 10) {
                            $qrX = max(10, $safeX - $cardWidth - $qrGap);
                        }

                        // Container card QR berbingkai senada dengan pembatas tanda tangan
                        $pdf->SetDrawColor(180, 195, 220);
                        $pdf->SetFillColor(255, 255, 255);
                        $pdf->SetLineWidth(0.5 * $scaleFactor);
                        $pdf->Rect($qrX, $qrY, $cardWidth, $cardHeight, 'DF');

                        // Gambar QR Code di dalam card
                        $qrImgX = $qrX + $pad;
                        $qrImgY = $qrY + $pad;
                        $pdf->Image($qrImagePath, $qrImgX, $qrImgY, $qrImgSize, $qrImgSize, 'PNG');

                        // Garis pemisah tipis di bawah QR sebelum label
                        $qrLineY = $qrImgY + $qrImgSize + ($sepGap / 2);
                        $pdf->Line($qrX, $qrLineY, $qrX + $cardWidth, $qrLineY);

                        // Label teks verifikasi senada dengan gaya teks penandatangan
                        $qrLabelSize = max(4.5, round(5.5 * $scaleFactor, 1));
                        $pdf->SetFont('Helvetica', 'B', $qrLabelSize);
                        $pdf->SetTextColor(30, 58, 138); // Biru tua institusi
                        $pdf->SetXY($qrX, $qrLineY + (0.5 * $scaleFactor));
                        $pdf->Cell($cardWidth, $labelH, 'Scan Verifikasi', 0, 0, 'C');
                    }
                }
            }

            $pdf->Output('F', $outputSignedPath);
            return true;
        } catch (\Throwable $e) {
            error_log('Gagal menginjeksi tanda tangan ke PDF: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mengalirkan pratinjau PDF ke peramban dengan security watermark halus & elegan
     * Berfungsi sebagai pengaman tangkapan layar (screen capture prevention)
     */
    public static function streamPreviewWithWatermark(
        string $pdfPath,
        array $userData,
        array $docData,
        string $previewFilename
    ): void {
        try {
            $cleanPdfPath = self::prepareReadablePdf($pdfPath);

            $pdf = new self('P', 'pt');
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($cleanPdfPath);

            $namaPengunduh  = $userData['full_name'] ?? 'PENGGUNA';
            $identitasNomor = !empty($userData['identifier_number']) ? ' (' . $userData['identifier_number'] . ')' : '';
            $waktuAkses     = date('d/m/Y H:i') . ' WIB';

            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tplId = $pdf->importPage($pageNum);
                $size  = $pdf->getTemplateSize($tplId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                // Watermark Keamanan Pratinjau (Opacity Ditingkatkan ~25%)
                $pdf->SetAlpha(0.25); // Opacity 25% agar terlihat tegas namun tetap transparan
                $pdf->SetTextColor(71, 85, 105); // Slate netral berbobot

                $midX = $size['width'] / 2;
                $midY = $size['height'] / 2;

                $pdf->Rotate(35, $midX, $midY);

                // Baris 1: Header Pratinjau Institusi
                $pdf->SetFont('Helvetica', 'B', 15);
                $pdf->Text($midX - 210, $midY - 8, 'PRATINJAU DOKUMEN ONESIGN  |  TIK PNJ');

                // Baris 2: Identitas Pengakses & Timestamp
                $pdf->SetFont('Helvetica', 'B', 10.5);
                $pdf->Text($midX - 210, $midY + 11, 'DIAKSES OLEH: ' . strtoupper($namaPengunduh) . $identitasNomor . '  |  ' . $waktuAkses);

                // Reset rotasi dan transparansi
                $pdf->Rotate(0);
                $pdf->SetAlpha(1.0);
            }

            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $previewFilename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            $pdf->Output('I', $previewFilename);
            exit;

        } catch (\Throwable $e) {
            error_log('Gagal menyematkan preview watermark: ' . $e->getMessage());
            // Fallback: alirkan berkas langsung jika terjadi hambatan pada parser
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $previewFilename . '"');
            if (file_exists($pdfPath)) {
                header('Content-Length: ' . filesize($pdfPath));
            }
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($pdfPath);
            exit;
        }
    }

    /**
     * Memastikan berkas PDF bertanda tangan sah tersedia pada disk
     * Jika berkas fisik hilang atau rusak, otomatis menghasilkan ulang stempel tanda tangan dan QR Code
     */
    public static function resolveSignedDocumentPath(array $doc, ?\PDO $pdo = null): string {
        $origPath = resolveUploadPath(DIR_UPLOADS_ORIGINAL, $doc['file_path'] ?? '');
        $signedPath = resolveUploadPath(DIR_UPLOADS_SIGNED, $doc['signed_file_path'] ?? '');
        $sigPath = resolveUploadPath(DIR_UPLOADS_SIGNATURES, $doc['signature_image_path'] ?? '');

        $safeSignedName = !empty($origPath) ? 'SIGNED_' . basename($origPath) : '';
        $targetSignedPath = !empty($safeSignedName) ? DIR_UPLOADS_SIGNED . $safeSignedName : '';

        $isSignedValid = false;
        if (!empty($signedPath) && file_exists($signedPath) && filesize($signedPath) > 0) {
            try {
                $tester = new self('P', 'pt');
                $tester->setSourceFile($signedPath);
                $isSignedValid = true;
            } catch (\Throwable $e) {
                $isSignedValid = false;
            }
        }

        if (!$isSignedValid && !empty($origPath) && file_exists($origPath) && !empty($sigPath) && file_exists($sigPath)) {
            $qrFilePath = '';
            if (!empty($doc['verify_token'])) {
                $verifyUrl = BASE_URL . '/views/verify.php?token=' . $doc['verify_token'];
                $qrFileName = 'QR_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
                $qrFilePath = DIR_UPLOADS_SIGNATURES . $qrFileName;
                self::generateQrCode($verifyUrl, $qrFilePath);
            }

            $signedDateFormatted = !empty($doc['signed_at']) ? date('d/m/Y', strtotime($doc['signed_at'])) : date('d/m/Y');
            $stamped = self::stampSignature(
                $origPath,
                $targetSignedPath,
                (int)$doc['page_number'],
                $sigPath,
                (float)$doc['pos_x'],
                (float)$doc['pos_y'],
                (float)$doc['box_width'],
                (float)$doc['box_height'],
                $doc['signer_name'] ?? '',
                $doc['signer_nip'] ?? '',
                $signedDateFormatted,
                $qrFilePath
            );

            if (!empty($qrFilePath) && file_exists($qrFilePath)) {
                unlink($qrFilePath);
            }

            if ($stamped) {
                $signedPath = $targetSignedPath;
                if ($pdo) {
                    try {
                        $stmtUpd = $pdo->prepare("UPDATE documents SET signed_file_path = :path WHERE id = :id");
                        $stmtUpd->execute([':path' => $safeSignedName, ':id' => $doc['id']]);
                    } catch (\Throwable $e) {}
                }
            }
        }

        if (!empty($signedPath) && file_exists($signedPath)) {
            return $signedPath;
        }

        return '';
    }
}
