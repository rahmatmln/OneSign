<?php
/**
 * OneSign - PDF Processing Engine (FPDI + FPDF Extension)
 * Menangani injeksi stempel tanda tangan (FR-02) dan penyematan Dynamic Security Watermarking (FR-04)
 * Politeknik Negeri Jakarta - Jurusan TIK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;

class OneSignPdfEngine extends Fpdi {
    protected int $angle = 0;
    protected array $extgstates = [];

    /**
     * Mengatur transparansi (alpha) untuk watermark
     */
    public function SetAlpha(float $alpha, string $bm = 'Normal'): void {
        $gs = $this->AddExtGState(['ca' => $alpha, 'CA' => $alpha, 'BM' => '/' . $bm]);
        $this->SetExtGState($gs);
    }

    protected function AddExtGState(array $parms): int {
        $n = count($this->extgstates) + 1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    protected function SetExtGState(int $gs): void {
        $this->_out(sprintf('/GS%d gs', $gs));
    }

    public function _enddoc() {
        if (!empty($this->extgstates) && $this->PDFVersion < '1.4') {
            $this->PDFVersion = '1.4';
        }
        parent::_enddoc();
    }

    protected function _putextgstates() {
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

    protected function _putresources() {
        $this->_putextgstates();
        parent::_putresources();
    }

    protected function _putresourcedict() {
        parent::_putresourcedict();
        $this->_put('/ExtGState <<');
        foreach ($this->extgstates as $k => $extgstate) {
            $this->_put('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
        }
        $this->_put('>>');
    }

    /**
     * Memutar kanvas sebesar derajat tertentu pada titik pusat (x, y)
     */
    public function Rotate(int $angle, float $x = -1, float $y = -1): void {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        if ($this->angle != 0) $this->_out('Q');
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

    public function _endpage() {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
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
            $pdf = new self('P', 'pt'); // Satuan Points (72 DPI) sesuai PDF.js
            $pdf->SetAutoPageBreak(false); // Cegah FPDF membuat halaman baru otomatis saat stempel di dekat batas bawah
            $pageCount = $pdf->setSourceFile($sourcePdfPath);

            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tplId = $pdf->importPage($pageNum);
                $size  = $pdf->getTemplateSize($tplId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                // Suntikkan gambar TTD dan identitas penandatangan jika ini adalah halaman target
                if ($pageNum === $targetPage && file_exists($sigImagePath)) {
                    // Beri batas aman agar tidak keluar halaman
                    $safeX = max(10, min($posX, $size['width'] - $boxWidth - 10));
                    $safeY = max(10, min($posY, $size['height'] - $boxHeight - 10));

                    // Tinggi porsi gambar goresan tanda tangan (sekitar 62% dari total kotak)
                    $imgHeight = $boxHeight * 0.62;
                    $pdf->Image($sigImagePath, $safeX, $safeY, $boxWidth, $imgHeight, 'PNG');

                    // Skala pengali proporsional relatif terhadap ukuran standar (170 pt)
                    $scaleFactor = max(0.7, min(3.0, $boxWidth / 170.0));

                    // Garis pembatas tipis di bawah tanda tangan
                    $lineY = $safeY + $imgHeight + (2 * $scaleFactor);
                    $pdf->SetDrawColor(180, 195, 220);
                    $pdf->SetLineWidth(0.5 * $scaleFactor);
                    $pdf->Line($safeX, $lineY, $safeX + $boxWidth, $lineY);

                    // Cetak Nama Lengkap Penandatangan (Skala font dan posisi dinamis)
                    if (!empty($signerName)) {
                        $nameFontSize = max(7, round(8.5 * $scaleFactor, 1));
                        $pdf->SetFont('Helvetica', 'B', $nameFontSize);
                        $pdf->SetTextColor(30, 58, 138); // Biru tua institusi
                        $pdf->SetXY($safeX, $lineY + (1.5 * $scaleFactor));
                        $pdf->Cell($boxWidth, 9 * $scaleFactor, $signerName, 0, 1, 'C');
                    }

                    // Cetak NIP dan Keterangan Keabsahan (Skala font dinamis)
                    $infoParts = [];
                    if (!empty($signerNip)) {
                        $infoParts[] = 'NIP. ' . $signerNip;
                    }
                    $infoParts[] = 'TTE Sah: ' . (!empty($signedDate) ? $signedDate : date('d/m/Y'));
                    $infoText = implode(' | ', $infoParts);

                    $subFontSize = max(6, round(7 * $scaleFactor, 1));
                    $pdf->SetFont('Helvetica', '', $subFontSize);
                    $pdf->SetTextColor(71, 85, 105); // Slate
                    $pdf->SetXY($safeX, $lineY + (1.5 * $scaleFactor) + (8.5 * $scaleFactor));
                    $pdf->Cell($boxWidth, 8 * $scaleFactor, $infoText, 0, 1, 'C');

                    // Tempelkan QR code verifikasi di samping tanda tangan
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
        } catch (Exception $e) {
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
            $pdf = new self('P', 'pt');
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($pdfPath);

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

        } catch (Exception $e) {
            error_log('Gagal menyematkan watermark: ' . $e->getMessage());
            die('Gagal menghasilkan berkas ber-watermark. Silakan coba kembali.');
        }
    }
}
