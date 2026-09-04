<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports tabular reports as CSV / Excel / PDF.
 * $sections = [ ['title' => 'Traffic', 'headers' => [...], 'rows' => [[...], ...]], ... ]
 */
class ReportExportService
{
    public function export(string $format, string $reportTitle, array $sections, string $filenameBase): mixed
    {
        return match ($format) {
            'xlsx', 'excel' => $this->xlsx($reportTitle, $sections, $filenameBase),
            'pdf' => $this->pdf($reportTitle, $sections, $filenameBase),
            default => $this->csv($reportTitle, $sections, $filenameBase),
        };
    }

    public function csv(string $title, array $sections, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $sections) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
            fputcsv($out, [$title]);
            fputcsv($out, ['Generated', now()->toDateTimeString()]);
            foreach ($sections as $s) {
                fputcsv($out, []);
                fputcsv($out, [$s['title']]);
                fputcsv($out, $s['headers']);
                foreach ($s['rows'] as $r) {
                    fputcsv($out, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $r));
                }
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(string $title, array $sections, string $filename): StreamedResponse
    {
        $ss = new Spreadsheet;
        $ss->removeSheetByIndex(0);
        foreach ($sections as $i => $s) {
            $sheet = $ss->createSheet($i);
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $s['title']), 0, 31) ?: 'Sheet'.($i + 1));
            $sheet->setCellValue('A1', $title.' — '.$s['title']);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $sheet->fromArray($s['headers'], null, 'A3');
            $sheet->getStyle('A3:'.$sheet->getHighestColumn().'3')->getFont()->setBold(true);
            if ($s['rows']) {
                $sheet->fromArray(array_map(fn ($r) => array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $r), $s['rows']), null, 'A4');
            }
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $ss->setActiveSheetIndex(0);
        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(string $title, array $sections, string $filename)
    {
        $pdf = Pdf::loadView('admin.reports.pdf', ['title' => $title, 'sections' => $sections, 'site' => setting('site.name')])->setPaper('a4', 'portrait');
        return $pdf->download($filename.'.pdf');
    }
}
