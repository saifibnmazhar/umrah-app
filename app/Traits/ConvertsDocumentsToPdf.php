<?php

namespace App\Traits;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

trait ConvertsDocumentsToPdf
{
    private function convertToPdf(string $filePath, string $outputPath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            copy($filePath, $outputPath);
            return $outputPath;
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $pdf = new \FPDF();
            $pdf->AddPage();
            list($imgW, $imgH) = getimagesize($filePath);
            $scale = min($pdf->GetPageWidth() / $imgW, $pdf->GetPageHeight() / $imgH);
            $w = $imgW * $scale;
            $h = $imgH * $scale;
            $x = ($pdf->GetPageWidth() - $w) / 2;
            $y = ($pdf->GetPageHeight() - $h) / 2;
            $pdf->Image($filePath, $x, $y, $w, $h);
            $pdf->Output('F', $outputPath);
            return $outputPath;
        }

        throw new \RuntimeException('Unsupported file type: ' . $ext);
    }

    private function resolveDocumentPath(Document $doc): ?string
    {
        if (Storage::disk('public')->exists($doc->file_path)) {
            return Storage::disk('public')->path($doc->file_path);
        }
        if (Storage::exists($doc->file_path)) {
            return Storage::path($doc->file_path);
        }
        return null;
    }

    private function mergePdfs(array $pdfFiles, string $outputPath): void
    {
        $pdf = new Fpdi();

        foreach ($pdfFiles as $file) {
            $pageCount = $pdf->setSourceFile($file);
            for ($i = 1; $i <= $pageCount; $i++) {
                $templateId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($templateId);

                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        }

        $pdf->Output('F', $outputPath);
    }
}
