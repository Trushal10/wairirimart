<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Zero-dependency export helper used by the Reports module.
 *
 * - CSV : streams UTF-8 with BOM
 * - XLSX: emits Excel-openable HTML with the .xls extension and MS-Excel MIME
 *         (Excel opens it as a native worksheet; no PHP dep required)
 * - PDF : renders the same HTML wrapped in a printable Blade template; the
 *         browser handles Save-as-PDF (same pattern used by invoices)
 */
class ReportExporter
{
    /**
     * Stream a CSV from a query iterator with a header row + per-row mapper.
     * The mapper receives the model/row and must return an array of values.
     *
     * @param string       $filename   Base name (no extension)
     * @param array        $headers    Header row
     * @param iterable     $chunkable  ->chunk(500, fn) compatible builder
     * @param Closure      $mapper     fn($row) => array
     */
    public function streamCsv(string $filename, array $headers, $chunkable, Closure $mapper): StreamedResponse
    {
        $name = $filename . '-' . now()->format('Ymd-His') . '.csv';
        return response()->stream(function () use ($headers, $chunkable, $mapper) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM — helps Excel with ₹ / ₨
            fputcsv($out, $headers);
            $chunkable->chunk(500, function ($chunk) use ($out, $mapper) {
                foreach ($chunk as $row) {
                    fputcsv($out, $mapper($row));
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store, no-cache',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Stream Excel-openable HTML. Excel treats the file as a real .xls sheet.
     */
    public function streamXlsx(string $filename, array $headers, $chunkable, Closure $mapper): StreamedResponse
    {
        $name = $filename . '-' . now()->format('Ymd-His') . '.xls';
        return response()->stream(function () use ($headers, $chunkable, $mapper, $filename) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "<html xmlns:x=\"urn:schemas-microsoft-com:office:excel\">");
            fwrite($out, "<head><meta charset=\"UTF-8\"><title>" . e($filename) . "</title>");
            fwrite($out, "<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>" . e($filename) . "</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->");
            fwrite($out, "</head><body><table border=\"1\" cellspacing=\"0\" cellpadding=\"4\"><thead><tr>");
            foreach ($headers as $h) {
                fwrite($out, "<th style=\"background:#eef2ff;font-weight:bold;\">" . e($h) . "</th>");
            }
            fwrite($out, "</tr></thead><tbody>");
            $chunkable->chunk(500, function ($chunk) use ($out, $mapper) {
                foreach ($chunk as $row) {
                    fwrite($out, "<tr>");
                    foreach ($mapper($row) as $cell) {
                        // Prefix numeric-looking strings with tab so Excel keeps
                        // leading zeros / long IDs intact.
                        if (is_string($cell) && ctype_digit(ltrim($cell, '0'))) {
                            fwrite($out, "<td style=\"mso-number-format:'\\@';\">" . e($cell) . "</td>");
                        } else {
                            fwrite($out, "<td>" . e((string) ($cell ?? '')) . "</td>");
                        }
                    }
                    fwrite($out, "</tr>");
                }
            });
            fwrite($out, "</tbody></table></body></html>");
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store, no-cache',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Render a printable HTML page (the caller supplies the payload).
     * Used by the browser-based "Print" flow — the view's own JS opens
     * the print dialog when ?autoprint=1 is present.
     */
    public function renderPrintable(string $view, array $data): Response
    {
        return response()
            ->view($view, $data)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Render a real PDF file (via DomPDF) using the same Blade template as
     * the printable view. Streams as a download with a timestamped filename.
     */
    public function streamPdf(string $view, array $data, string $filename): Response
    {
        // The template checks `$__pdfMode` to skip browser-only chrome
        // (toolbar buttons, autoprint script) that shouldn't appear in the PDF.
        $data['__pdfMode'] = true;

        $name = $filename . '-' . now()->format('Ymd-His') . '.pdf';

        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans', // supports ₹, €, etc.
                'dpi'                  => 96,
            ])
            ->download($name);
    }
}
