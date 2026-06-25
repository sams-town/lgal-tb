<?php

class DocumentCompressor
{
    /**
     * Kompresi PDF menggunakan Ghostscript
     *
     * @param string $inputPath Path berkas asli
     * @param string $outputPath Path berkas hasil kompresi
     * @return bool True jika sukses, False jika gagal
     */
    public static function compressPdf($inputPath, $outputPath)
    {
        // Deteksi sistem operasi (Windows vs Linux)
        // Windows (Laragon) biasanya menggunakan 'gswin64c' sedangkan Linux menggunakan 'gs'
        $gsCmd = (stripos(PHP_OS, 'WIN') === 0) ? 'gswin64c' : 'gs';

        // Opsi PDFSETTINGS:
        // /screen: Kualitas paling rendah (72 dpi) - ukuran sangat kecil
        // /ebook: Kualitas menengah (150 dpi) - seimbang untuk dibaca & dicetak (DIREKOMENDASIKAN)
        // /printer: Kualitas cetak (300 dpi)
        $cmd = sprintf(
            '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
            escapeshellcmd($gsCmd),
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );

        exec($cmd, $output, $returnVar);

        // Jika berhasil, returnVar bernilai 0
        if ($returnVar === 0) {
            return true;
        }

        // Tulis log error jika gagal
        error_log("Ghostscript compression failed. Command: " . $cmd . " Output: " . implode("\n", $output));
        return false;
    }

    /**
     * Kompresi Gambar (JPEG/PNG) menggunakan PHP GD Library
     *
     * @param string $inputPath Path berkas asli
     * @param string $outputPath Path berkas hasil kompresi
     * @param int $quality Tingkat kualitas (0 - 100)
     * @return bool True jika sukses, False jika gagal
     */
    public static function compressImage($inputPath, $outputPath, $quality = 75)
    {
        $info = getimagesize($inputPath);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($inputPath);
                if ($image) {
                    $result = imagejpeg($image, $outputPath, $quality);
                    imagedestroy($image);
                    return $result;
                }
                break;

            case 'image/png':
                $image = @imagecreatefrompng($inputPath);
                if ($image) {
                    // Skala kompresi PNG: 0 (tanpa kompresi) hingga 9 (kompresi penuh)
                    $pngQuality = 9 - round(($quality * 9) / 100);
                    $result = imagepng($image, $outputPath, $pngQuality);
                    imagedestroy($image);
                    return $result;
                }
                break;
        }
        return false;
    }
}
?>
