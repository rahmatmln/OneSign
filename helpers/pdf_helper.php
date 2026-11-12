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

    public function RotatedText(int|float $x, int|float $y, string $txt, int $angle): void {
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    public function _beginpage($orientation, $size, $rotation): void {
        parent::_beginpage($orientation, $size, $rotation);
        if ($this->angle != 0) {
            $this->Rotate($this->angle);
        }
    }

    public function RotatePage(int $angle, int|float $x = -1, int|float $y = -1): void {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        $this->angle = $angle;
        if ($angle != 0) {
            $rad = $angle * M_PI / 180;
            $c   = cos($rad);
            $s   = sin($rad);
            $cx  = $x * $this->k;
            $cy  = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
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
            'scale'           => 6,
            'outputBase64'    => false,
            'quietzoneSize'   => 2,
        ]);
        (new QRCode($options))->render($data, $outputPath);
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
                    $infoText = (!empty($signerNip) ? 'NIP. ' . $signerNip : '') . ' • ' . $signedDate;
                    $pdf->SetTextColor(71, 85, 105); // Slate
                    $pdf->SetXY($safeX, $lineY + (1.5 * $scaleFactor) + (8.5 * $scaleFactor));
                    $pdf->Cell($boxWidth, 8 * $scaleFactor, $infoText, 0, 1, 'C');

                    // 5. Tempelkan QR code verifikasi di samping tanda tangan
                    if (!empty($qrImagePath) && file_exists($qrImagePath)) {
                        $qrSize = max(35, min(65, $boxHeight * 0.55));
                        $qrX = $safeX + $boxWidth + (6 * $scaleFactor);
                        $qrY = max(10, min($safeY + ($boxHeight - $qrSize) / 2, $size['height'] - $qrSize - 15));

                        // Jika QR melewati batas kanan halaman, tempatkan di sebelah kiri tanda tangan
                        if ($qrX + $qrSize > $size['width'] - 10) {
                            $qrX = max(10, $safeX - $qrSize - (6 * $scaleFactor));
                        }

                        $pdf->Image($qrImagePath, $qrX, $qrY, $qrSize, $qrSize, 'PNG');

                        // Label kecil di bawah QR
                        $qrLabelSize = max(5, round(5.5 * $scaleFactor, 1));
                        $pdf->SetFont('Helvetica', '', $qrLabelSize);
                        $pdf->SetTextColor(100, 116, 139);
                        $pdf->SetXY($qrX, $qrY + $qrSize + 1);
                        $pdf->Cell($qrSize, 6 * $scaleFactor, 'Scan Verifikasi', 0, 0, 'C');
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
     * Menyematkan Dynamic Security Watermarking secara streaming saat diunduh
     */
    public static function streamWithDynamicWatermark(
        string $pdfPath,
        array $userData,
        array $docData,
        string $downloadFilename
    ): void {
        try {
            $cleanPdfPath = self::prepareReadablePdf($pdfPath);

            $pdf = new self('P', 'pt');
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($cleanPdfPath);

            $labelKerahasiaan = strtoupper($docData['confidentiality'] ?? 'INTERNAL');
            $namaPengunduh    = $userData['full_name'] ?? 'PENGGUNA';
            $identitasNomor   = !empty($userData['identifier_number']) ? ' (' . $userData['identifier_number'] . ')' : '';
            $emailKampus      = $userData['email'] ?? '';
            $ipAddress        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $waktuUnduh       = date('d/m/Y H:i:s') . ' WIB';

            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tplId = $pdf->importPage($pageNum);
                $size  = $pdf->getTemplateSize($tplId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                // Pengaturan Gaya Teks Watermark
                $pdf->SetFont('Helvetica', 'B', 13);
                $pdf->SetTextColor(180, 20, 20); // Merah pudar resmi
                $pdf->SetAlpha(0.25); // Transparansi 25% - 30%

                $midX = $size['width'] / 2;
                $midY = $size['height'] / 2;

                // Blok Watermark Tengah
                $pdf->Rotate(40, $midX, $midY);
                $pdf->Text($midX - 220, $midY - 14, 'SALINAN RESMI TIK PNJ  |  STATUS: ' . $labelKerahasiaan);
                
                $pdf->SetFont('Helvetica', '', 10);
                $pdf->SetTextColor(30, 40, 60);
                $pdf->Text($midX - 220, $midY + 2, 'DIUNDUH OLEH: ' . $namaPengunduh . $identitasNomor . '  |  EMAIL: ' . $emailKampus);
                $pdf->Text($midX - 220, $midY + 16, 'WAKTU AKSES: ' . $waktuUnduh . '  |  IP: ' . $ipAddress);
                
                // Blok Watermark Atas (Repetisi untuk perlindungan potongan tangkapan layar)
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->SetTextColor(180, 20, 20);
                $pdf->Text($midX - 220, $midY - 180, 'SALINAN RESMI TIK PNJ  |  STATUS: ' . $labelKerahasiaan);
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetTextColor(30, 40, 60);
                $pdf->Text($midX - 220, $midY - 166, 'DIUNDUH OLEH: ' . $namaPengunduh . '  |  ' . $waktuUnduh);

                // Blok Watermark Bawah
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->SetTextColor(180, 20, 20);
                $pdf->Text($midX - 220, $midY + 160, 'SALINAN RESMI TIK PNJ  |  STATUS: ' . $labelKerahasiaan);
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetTextColor(30, 40, 60);
                $pdf->Text($midX - 220, $midY + 174, 'DIUNDUH OLEH: ' . $namaPengunduh . '  |  ' . $waktuUnduh);

                // Reset Rotasi dan Alpha
                $pdf->Rotate(0);
                $pdf->SetAlpha(1.0);
            }

            // Bersihkan buffer output liar sebelum streaming file biner PDF
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Output streaming ke peramban
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            $pdf->Output('I', $downloadFilename);
            exit;

        } catch (\Throwable $e) {
            error_log('Gagal menyematkan watermark: ' . $e->getMessage());
            die('Gagal menghasilkan berkas ber-watermark: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
}
