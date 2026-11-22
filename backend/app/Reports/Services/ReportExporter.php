<?php

namespace App\Reports\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

class ReportExporter
{
    public function download(array $t, string $format, string $basename)
    {
        $rows = array_map(function ($row) use ($t) {
            foreach ($t['money'] as $i) {
                if (isset($row[$i]) && is_int($row[$i])) {
                    $row[$i] = number_format($row[$i] / 100, 2, '.', '');
                }
            }

            return $row;
        }, $t['rows']);

        if ($format === 'pdf') {
            $html = '<h2 style="font-family:sans-serif">'.e($t['title']).'</h2><table width="100%" cellpadding="5" style="border-collapse:collapse;font-family:sans-serif;font-size:11px"><tr>'
                .collect($t['columns'])->map(fn ($c) => '<th align="left" style="border-bottom:1px solid #999">'.e($c).'</th>')->implode('')
                .'</tr>'.collect($rows)->map(fn ($r) => '<tr>'.collect($r)->map(fn ($v) => '<td style="border-bottom:1px solid #eee">'.e((string) $v).'</td>')->implode('').'</tr>')->implode('').'</table>';

            return Pdf::loadHTML($html)->setPaper('a4', count($t['columns']) > 5 ? 'landscape' : 'portrait')->download("{$basename}.pdf");
        }
        if ($format === 'xlsx') {
            $export = new class($t['columns'], $rows) implements FromArray, ShouldAutoSize, WithHeadings
            {
                public function __construct(private array $h, private array $r) {}

                public function array(): array
                {
                    return $this->r;
                }

                public function headings(): array
                {
                    return $this->h;
                }
            };

            return Excel::download($export, "{$basename}.xlsx");
        }

        return response()->streamDownload(function () use ($t, $rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $t['columns']);
            foreach ($rows as $r) {
                fputcsv($f, $r);
            }
            fclose($f);
        }, "{$basename}.csv", ['Content-Type' => 'text/csv']);
    }
}
