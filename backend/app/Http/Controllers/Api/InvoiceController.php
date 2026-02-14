<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class InvoiceController extends Controller
{
    public function clients()
    {
        $rows = DB::table('fatloads')
            ->selectRaw('client_id as id, client_name as name')
            ->whereNotNull('client_id')
            ->whereNotNull('client_name')
            ->groupBy('client_id', 'client_name')
            ->orderBy('client_name')
            ->get();

        return response()->json($rows);
    }

    public function preview(Request $request, InvoiceBuilderService $svc)
    {
        $clientId = (int)$request->query('client_id', 0);
        $start = (string)$request->query('start', '');
        $end = (string)$request->query('end', '');
        $invoiceNumber = trim((string)$request->query('invoice_number', ''));

        if ($clientId <= 0) return response()->json(['message' => 'client_id is required'], 422);
        if ($start === '' || $end === '') return response()->json(['message' => 'start and end are required (YYYY-MM-DD)'], 422);

        return response()->json($svc->buildPreview([
            'client_id' => $clientId,
            'start_date' => $start,
            'end_date' => $end,
            'invoice_number' => $invoiceNumber,
        ]));
    }

    public function save(Request $request, InvoiceBuilderService $svc)
    {
        $p = $request->all();

        $required = [
            'id_client',
            'invoice_startdate',
            'invoice_enddate',
            'invoice_number',
            'invoice_total_amount',
            'invoice_loadcount',
            'invoice_tontotal',
            'invoice_milestotal',
        ];

        foreach ($required as $k) {
            if (!array_key_exists($k, $p)) {
                return response()->json(['message' => "Missing field: $k"], 422);
            }
        }

        try {
            $id = $svc->saveInvoice($p);
            return response()->json(['ok' => true, 'id_bill_invoices' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function pdf(int $id, InvoiceBuilderService $svc)
    {
        $data = $svc->buildFromInvoiceId($id);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $data['invoice'],
            'rows' => $data['rows'],
            'logo_data_uri' => $data['logo_data_uri'],
        ])->setPaper('letter', 'portrait');

        $name = 'invoice-' . ($data['invoice']['invoice_number'] ?? $id) . '.pdf';
        return $pdf->download($name);
    }

    public function xls(int $id, InvoiceBuilderService $svc)
    {
        $data = $svc->buildFromInvoiceId($id);
        $inv = $data['invoice'] ?? [];
        $rows = $data['rows'] ?? [];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Invoice');

        $set = function(int $col, int $row, $value) use ($sheet) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
            $sheet->setCellValue($cell, $value);
        };

        // Page setup
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0);

        $m = $sheet->getPageMargins();
        $m->setTop(0.25)->setBottom(0.25)->setLeft(0.25)->setRight(0.25);

        // Column widths A..I
        $widths = [28, 14, 22, 12, 18, 10, 10, 12, 14];
        foreach ($widths as $i => $w) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $BLACK = '000000';
        $WHITE = 'FFFFFF';
        $GRAY  = 'E6E6E6';
        $YELL  = 'FFD000';
        $moneyFmt = '$#,##0.00';

        $invoiceNo = (string)($inv['invoice_number'] ?? $id);

        $start = (string)($inv['invoice_startdate'] ?? '');
        $end   = (string)($inv['invoice_enddate'] ?? '');

        // MM/DD/YY for header line
        $startTxt = $start ? \Carbon\Carbon::parse($start)->format('m/d/y') : '';
        $endTxt   = $end   ? \Carbon\Carbon::parse($end)->format('m/d/y') : '';

        // ================= HEADER =================
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(34);
        $sheet->getRowDimension(3)->setRowHeight(22);

        // EXACTLY as requested:
        $sheet->mergeCells('A1:B3'); // Logo block
        $sheet->mergeCells('C1:G1'); // Title
        $sheet->mergeCells('C2:G2'); // Client
        $sheet->mergeCells('C3:G3'); // Email
        $sheet->mergeCells('H1:I3'); // Address

        // Black background for header bar
        $sheet->getStyle('A1:I3')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $BLACK],
            ],
        ]);

        // Title
        $sheet->setCellValue('C1', "Service Invoice  --  {$invoiceNo}");
        $sheet->getStyle('C1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 24, 'color' => ['rgb' => $WHITE]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        // Client
        $sheet->setCellValue('C2', (string)($inv['client_name'] ?? ''));
        $sheet->getStyle('C2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => $YELL]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        // Email
        $sheet->setCellValue('C3', 'Please email invoices@voldhaul.com with any changes or corrections.');
        $sheet->getStyle('C3')->applyFromArray([
            'font' => ['size' => 11, 'color' => ['rgb' => $WHITE]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        // Address
        $sheet->setCellValue('H1', "Voldhaul LLC\n5786 SEVEN RIVERS HWY\nARTESIA, NM 88210");
        $sheet->getStyle('H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $WHITE]],
            'alignment' => ['horizontal' => 'right', 'vertical' => 'top', 'wrapText' => true],
        ]);

        // Logo
        $logoPath = public_path('brand/voldhaul-logo.png');
        if (is_file($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setPath($logoPath);
            $drawing->setHeight(70);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
        }

        // ================= DATE RANGE =================
        $sheet->mergeCells('A5:I5');
        $sheet->getRowDimension(5)->setRowHeight(40);

        $sheet->setCellValue('A5', "Date Range:   {$startTxt}   →   {$endTxt}");
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 26, 'color' => ['rgb' => '111111']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        // ================= TOTALS =================
        $sheet->getRowDimension(6)->setRowHeight(24);

        $loadCount = (int)($inv['invoice_loadcount'] ?? count($rows));
        $tons      = (float)($inv['invoice_tontotal'] ?? 0);
        $miles     = (int)($inv['invoice_milestotal'] ?? 0);
        $total     = (float)($inv['invoice_total_amount'] ?? 0);

        $sheet->mergeCells('C6:D6');
        $sheet->setCellValue('C6', "Load  Count  {$loadCount}");
        $sheet->getStyle('C6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        $sheet->setCellValue('F6', $tons);
        $sheet->getStyle('F6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
        ]);
        $sheet->getStyle('F6')->getNumberFormat()->setFormatCode('0.00');

        $sheet->setCellValue('G6', $miles);
        $sheet->getStyle('G6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
        ]);
        $sheet->getStyle('G6')->getNumberFormat()->setFormatCode('0');

        $sheet->setCellValue('I6', $total);
        $sheet->getStyle('I6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
        ]);
        $sheet->getStyle('I6')->getNumberFormat()->setFormatCode($moneyFmt);

        // ================= TABLE HEADER =================
        $headers = ['Well/Job','Date','Driver','Load #','BOL','Tons','Miles','Rate/Ton','Load Pay'];
        $headerRow = 8;
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        foreach ($headers as $i => $h) $set($i + 1, $headerRow, $h);

        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $GRAY],
            ],
            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
            'borders' => [
                'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK],
            ],
        ]);

        // ================= DATA (WITH BORDERS) =================
        $r = $headerRow + 1;
        foreach ($rows as $x) {
            $sheet->getRowDimension($r)->setRowHeight(22);

            $d = (string)($x['date'] ?? '');
            $t = $d ? strtotime($d) : false;
            $dateOut = $t ? date('m/d/Y', $t) : $d;

            $set(1, $r, (string)($x['well_job'] ?? ''));
            $set(2, $r, $dateOut);
            $set(3, $r, (string)($x['driver'] ?? ''));
            $set(4, $r, (string)($x['load_no'] ?? ''));
            $set(5, $r, (string)($x['bol'] ?? ''));
            $set(6, $r, (float)($x['tons'] ?? 0));
            $set(7, $r, (int)($x['miles'] ?? 0));
            $set(8, $r, (float)($x['rate_ton'] ?? 0));
            $set(9, $r, (float)($x['load_pay'] ?? 0));

            // ✅ THIS IS THE PART YOU WERE MISSING: DATA ROW GRID BORDERS
            $sheet->getStyle("A{$r}:I{$r}")->applyFromArray([
                'font' => ['size' => 11],
                'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);

            // right align numeric columns
            $sheet->getStyle("F{$r}:I{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("G{$r}")->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle("I{$r}")->getNumberFormat()->setFormatCode($moneyFmt);

            $r++;
        }

        $lastRow = max($r - 1, $headerRow + 1);
        $sheet->getPageSetup()->setPrintArea("A1:I{$lastRow}");

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'invoice-' . ($inv['invoice_number'] ?? $id) . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
