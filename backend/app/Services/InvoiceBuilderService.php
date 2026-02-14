<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceBuilderService
{
    public function buildPreview(array $params): array
    {
        $clientId = (int)($params['client_id'] ?? 0);
        $start = trim((string)($params['start_date'] ?? ''));
        $end = trim((string)($params['end_date'] ?? ''));
        $invoiceNumber = trim((string)($params['invoice_number'] ?? ''));

        if ($clientId <= 0) throw new \InvalidArgumentException('client_id is required');
        if ($start === '' || $end === '') throw new \InvalidArgumentException('start_date and end_date are required');

        $startDt = Carbon::parse($start)->startOfDay();
        $endExclusive = Carbon::parse($end)->addDay()->startOfDay();

        $rows = DB::table('fatloads')
            ->select([
                'id_load',
                'delivery_time',
                'client_id',
                'client_name',
                'pl_job',
                'load_number',
                'ticket_number',
                'tons',
                'miles',
                'client_pay',
                'first_name',
                'last_name',
            ])
            ->where('client_id', $clientId)
            ->whereNotNull('delivery_time')
            ->where('delivery_time', '>=', $startDt->toDateTimeString())
            ->where('delivery_time', '<', $endExclusive->toDateTimeString())
            ->orderBy('delivery_time')
            ->orderBy('id_load')
            ->get();

        $clientName = $rows->count() > 0 ? (string)($rows->first()->client_name ?? '') : '';

        $loadcount = 0;
        $tontotal = 0.0;
        $milestotal = 0.0;
        $totalAmount = 0.0;

        $uiRows = [];
        $seenLoads = [];

        foreach ($rows as $r) {
            $idLoad = (int)$r->id_load;
            if (!isset($seenLoads[$idLoad])) {
                $seenLoads[$idLoad] = true;
                $loadcount++;
            }

            $tons = (float)($r->tons ?? 0);
            $miles = (float)($r->miles ?? 0);

            $clientPay = (float)($r->client_pay ?? 0);
            $clientRate = ($tons > 0.000001) ? ($clientPay / $tons) : 0.0;
            $loadPayCalc = $clientRate * $tons;

            $tontotal += $tons;
            $milestotal += $miles;
            $totalAmount += $loadPayCalc;

            $driver = trim((string)($r->first_name ?? '') . ' ' . (string)($r->last_name ?? ''));

            $uiRows[] = [
                'well_job' => (string)($r->pl_job ?? ''),
                'date' => $r->delivery_time,
                'driver' => $driver,
                'load_no' => (string)($r->load_number ?? ''),
                'bol' => (string)($r->ticket_number ?? ''),
                'tons' => round($tons, 2),
                'miles' => (int)round($miles),
                'rate_ton' => round($clientRate, 4),
                'load_pay' => round($loadPayCalc, 2),
                'id_load' => $idLoad,
            ];
        }

        if ($invoiceNumber === '') {
            $invoiceNumber = Carbon::now()->format('ymd') . '-' . $clientId;
        }

        return [
            'invoice' => [
                'id_client' => $clientId,
                'client_name' => $clientName,
                'invoice_startdate' => $startDt->toDateString(),
                'invoice_enddate' => Carbon::parse($end)->toDateString(),
                'invoice_number' => $invoiceNumber,
                'invoice_loadcount' => $loadcount,
                'invoice_tontotal' => round($tontotal, 2),
                'invoice_milestotal' => (int)round($milestotal),
                'invoice_total_amount' => round($totalAmount, 2),
            ],
            'rows' => $uiRows,
            'misc_rows' => [],
        ];
    }

    /**
     * Saves:
     *  - bill_invoices (header)
     *  - bill_invoiceloads (links to loads)
     */
    public function saveInvoice(array $p): int
    {
        $clientId = (int)($p['id_client'] ?? 0);
        $start = (string)($p['invoice_startdate'] ?? '');
        $end = (string)($p['invoice_enddate'] ?? '');

        if ($clientId <= 0) throw new \InvalidArgumentException('id_client is required');
        if ($start === '' || $end === '') throw new \InvalidArgumentException('invoice_startdate and invoice_enddate are required');

        $startDt = Carbon::parse($start)->startOfDay();
        $endExclusive = Carbon::parse($end)->addDay()->startOfDay();

        $loadIds = DB::table('fatloads')
            ->where('client_id', $clientId)
            ->whereNotNull('delivery_time')
            ->where('delivery_time', '>=', $startDt->toDateTimeString())
            ->where('delivery_time', '<', $endExclusive->toDateTimeString())
            ->distinct()
            ->pluck('id_load')
            ->map(fn ($x) => (int)$x)
            ->values()
            ->all();

        return DB::transaction(function () use ($p, $loadIds) {
            $invoiceId = DB::table('bill_invoices')->insertGetId([
                'id_client' => (int)$p['id_client'],
                'invoice_startdate' => $p['invoice_startdate'],
                'invoice_enddate' => $p['invoice_enddate'],
                'invoice_number' => (string)$p['invoice_number'],
                'invoice_total_amount' => (float)$p['invoice_total_amount'],
                'invoice_loadcount' => (int)$p['invoice_loadcount'],
                'invoice_tontotal' => (float)$p['invoice_tontotal'],
                'invoice_milestotal' => (float)$p['invoice_milestotal'],
            ]);

            if (!empty($loadIds)) {
                $now = Carbon::now()->toDateTimeString();

                $rows = [];
                foreach ($loadIds as $idLoad) {
                    $rows[] = [
                        'id_bill_invoice' => (int)$invoiceId,
                        'id_load' => (int)$idLoad,
                        'created_at' => $now,
                    ];
                }

                DB::table('bill_invoiceloads')->insert($rows);
            }

            return (int)$invoiceId;
        });
    }

    /**
     * Build invoice + rows from saved invoice id (for PDF/XLS).
     */
    public function buildFromInvoiceId(int $invoiceId): array
    {
        $inv = DB::table('bill_invoices')
            ->where('id_bill_invoices', $invoiceId)
            ->first();

        if (!$inv) {
            throw new \InvalidArgumentException("Invoice not found: {$invoiceId}");
        }

        $clientName = (string)DB::table('fatloads')
            ->where('client_id', (int)$inv->id_client)
            ->whereNotNull('client_name')
            ->value('client_name');

        $loadIds = DB::table('bill_invoiceloads')
            ->where('id_bill_invoice', $invoiceId)
            ->orderBy('idbill_invoice_loads')
            ->pluck('id_load')
            ->map(fn ($x) => (int)$x)
            ->values()
            ->all();

        $rows = [];
        if (!empty($loadIds)) {
            $fat = DB::table('fatloads')
                ->select([
                    'id_load',
                    'delivery_time',
                    'pl_job',
                    'load_number',
                    'ticket_number',
                    'tons',
                    'miles',
                    'client_pay',
                    'first_name',
                    'last_name',
                ])
                ->whereIn('id_load', $loadIds)
                ->whereNotNull('delivery_time')
                ->orderBy('delivery_time')
                ->orderBy('id_load')
                ->get();

            foreach ($fat as $r) {
                $tons = (float)($r->tons ?? 0);
                $miles = (float)($r->miles ?? 0);
                $clientPay = (float)($r->client_pay ?? 0);

                $rate = ($tons > 0.000001) ? ($clientPay / $tons) : 0.0;
                $loadPay = $rate * $tons;

                $driver = trim((string)($r->first_name ?? '') . ' ' . (string)($r->last_name ?? ''));

                $rows[] = [
                    'well_job' => (string)($r->pl_job ?? ''),
                    'date' => (string)($r->delivery_time ?? ''),
                    'driver' => $driver,
                    'load_no' => (string)($r->load_number ?? ''),
                    'bol' => (string)($r->ticket_number ?? ''),
                    'tons' => round($tons, 2),
                    'miles' => (int)round($miles),
                    'rate_ton' => round($rate, 4),
                    'load_pay' => round($loadPay, 2),
                    'id_load' => (int)$r->id_load,
                ];
            }
        }

        // base64 logo for dompdf (dompdf can't load Vite asset URL)
        $logoPath = public_path('brand/voldhaul-logo.png');
        $logoDataUri = null;

        if (is_file($logoPath)) {
            $bin = file_get_contents($logoPath);
            $logoDataUri = 'data:image/png;base64,' . base64_encode($bin);
        }

        return [
            'invoice' => [
                'id_bill_invoices' => (int)$inv->id_bill_invoices,
                'id_client' => (int)$inv->id_client,
                'client_name' => $clientName,
                'invoice_startdate' => (string)$inv->invoice_startdate,
                'invoice_enddate' => (string)$inv->invoice_enddate,
                'invoice_number' => (string)$inv->invoice_number,
                'invoice_total_amount' => (float)$inv->invoice_total_amount,
                'invoice_loadcount' => (int)$inv->invoice_loadcount,
                'invoice_tontotal' => (float)$inv->invoice_tontotal,
                'invoice_milestotal' => (float)$inv->invoice_milestotal,
            ],
            'rows' => $rows,
            'logo_data_uri' => $logoDataUri,
        ];
    }
}
