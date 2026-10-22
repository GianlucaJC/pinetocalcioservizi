<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Smalot\PdfParser\Parser;

class PayslipPdfService
{
    public function analyze(string $sourcePath): array
    {
        $pdf = (new Parser())->parseFile($sourcePath);
        $segments = [];
        $currentSegment = null;

        foreach ($pdf->getPages() as $index => $page) {
            $taxCodes = $this->taxCodesFromText($page->getText());
            $taxCode = count($taxCodes) === 1 ? $taxCodes[0] : null;

            if ($currentSegment !== null && $currentSegment['tax_code'] === $taxCode) {
                $currentSegment['last_page'] = $index + 1;
                continue;
            }

            if ($currentSegment !== null) {
                $segments[] = $currentSegment;
            }

            $currentSegment = [
                'tax_code' => $taxCode,
                'first_page' => $index + 1,
                'last_page' => $index + 1,
                'issue' => $taxCode ? null : (count($taxCodes) === 0 ? 'Codice fiscale non trovato nella pagina.' : 'Sono stati trovati più codici fiscali nella pagina.'),
            ];
        }

        if ($currentSegment !== null) {
            $segments[] = $currentSegment;
        }

        return $segments;
    }

    public function split(string $sourcePath, array $pages, string $destinationPath): void
    {
        Storage::disk('local')->makeDirectory(dirname($destinationPath));

        $pdf = new Fpdi();
        $pdf->setSourceFile($sourcePath);

        foreach ($pages as $page) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }

        $pdf->Output(Storage::disk('local')->path($destinationPath), 'F');
    }

    private function taxCodesFromText(string $text): array
    {
        preg_match_all('/\b[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]\b/i', $text, $matches);

        return array_values(array_unique(array_map('strtoupper', $matches[0])));
    }
}
