<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice</title>

    <style>
        @page { margin: 16pt; }

        body {
            font-family: Arial, Helvetica, DejaVu Sans, sans-serif;
            font-size: 11px;
            margin:0;
            color:#111;
        }

        .mono { font-family: "DejaVu Sans Mono", monospace; }

        /* ================= HEADER ================= */
        .header {
            background:#000;
            color:#fff;
            /* removed left/right padding as you requested */
            padding:6pt 0;
            margin:0;
        }
        .header table {
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
        }

        /* remove internal cell padding (logo + right side) */
        .header td { padding:0; }

        .logoCell {
            width:135pt;
            vertical-align:middle;
            text-align:left;   /* logo sits in left corner */
            padding-left:10pt;  /* tiny breathing room only */
        }
        .logoImg {
            width:95pt;        /* smaller logo */
            height:auto;
            display:block;
        }

        .centerCell {
            vertical-align:middle;
            text-align:center;
            padding:0 4pt;
        }

        .rightCell {
            width:155pt;
            vertical-align:middle;
            text-align:right;
            font-size:11px;
            line-height:1.2;
            padding-right:15pt;
        }

        .title {
            font-size:22px;
            font-weight:bold;
            white-space:nowrap;
        }
        .client {
            font-size:16px;
            font-weight:bold;
            color:#ffd800;
            margin-top:2pt;
            white-space:nowrap;
        }
        .email {
            font-size:10px;
            margin-top:2pt;
            white-space:nowrap; /* keep one line */
        }

        /* ================= DATE RANGE ================= */
        .dateRange {
            text-align:center;
            margin:8pt 0 4pt 0;
            font-weight:bold;
        }
        .dateRange span {
            display:inline-block;
            font-size:18px;
            white-space:nowrap;
        }
        .dateLabel {
            font-size:20px;
            margin-right:10pt;
        }

        /* ================= TOTALS STRIP (NOT REPEATED) ================= */
        table.totalsStrip {
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
            margin:4pt 0 6pt 0;
        }
        table.totalsStrip td {
            font-size:12px;
            padding:2pt 4pt;
            white-space:nowrap;
            text-align:center;
        }
        .totalsVal { font-weight:bold; font-size:13px; }

        /* ================= MAIN TABLE ================= */
        table.grid {
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
            font-size:10px; /* slightly smaller to avoid wrapping */
        }

        table.grid th,
        table.grid td {
            border:2px solid #333;
            padding:4pt 5pt;
            vertical-align:middle;
            white-space:nowrap; /* no wrapping anywhere */
        }

        table.grid th {
            background:#e6e6e6;
            font-weight:bold;
            text-align:center;
            font-size:11px;
        }

        td.center { text-align:center; }
        td.right  { text-align:center; }

        .muted {
            text-align:center;
            padding:10pt;
            color:#666;
        }
    </style>
</head>
<body>

@php
    $inv = $invoice ?? [];
    $rows = $rows ?? [];

    function fmt($d){
        if(!$d) return '';
        if(preg_match('/^\d{2}\/\d{2}\/\d{2}$/',$d)) return $d;
        if(preg_match('/^\d{4}-\d{2}-\d{2}/',$d)){
            return substr($d,5,2).'/'.substr($d,8,2).'/'.substr($d,2,2);
        }
        return $d;
    }

    function dateOnly($d){
        if(!$d) return '';
        $t=strtotime($d);
        return $t?date('m/d/Y',$t):$d;
    }

    function money($n){
        return '$'.number_format((float)$n,2,'.',',');
    }

    $invoiceNo = (string)($inv['invoice_number'] ?? '');
@endphp

<div class="header">
    <table>
        <tr>
            <td class="logoCell">
                @if(!empty($logo_data_uri))
                    <img src="{{ $logo_data_uri }}" class="logoImg" alt="Voldhaul Logo">
                @endif
            </td>

            <td class="centerCell">
                <div class="title">
                    Service Invoice&nbsp;&nbsp;--&nbsp;&nbsp;<span class="mono">{{ $invoiceNo }}</span>
                </div>
                <div class="client">{{ $inv['client_name'] ?? '' }}</div>
                <div class="email">Please email invoices@voldhaul.com with any changes or corrections.</div>
            </td>

            <td class="rightCell">
                <div style="font-weight:bold;">Voldhaul LLC</div>
                <div>5786 SEVEN RIVERS HWY</div>
                <div>ARTESIA, NM 88210</div>
            </td>
        </tr>
    </table>
</div>

<div class="dateRange">
    <span class="dateLabel" style="font-size: 25px">Date Range:</span>
    <span class="mono">{{ fmt($inv['invoice_startdate'] ?? '') }}</span>
    <span style="margin:0 10pt;">→</span>
    <span class="mono">{{ fmt($inv['invoice_enddate'] ?? '') }}</span>
</div>

<!-- Totals strip OUTSIDE thead so it will NOT repeat on every page -->
<table class="totalsStrip">
    <tr>
        <td style="width:24%;"></td>
        <td style="width:10%;">
        </td>
        <td style="width:7%;"></td>
        <td style="width:15%; white-space:nowrap;">
            Load&nbsp;Count&nbsp;<span class="totalsVal mono">{{ (int)($inv['invoice_loadcount'] ?? 0) }}</span>
        </td>
        <td style="width:9%;">


        </td >
        <td style="width:9%;">
            <span class="totalsVal mono">{{ number_format((float)($inv['invoice_tontotal'] ?? 0),2,'.','') }}</span>
        </td>
        <td style="width:8%;">
            <span class="totalsVal mono">{{ (int)($inv['invoice_milestotal'] ?? 0) }}</span>
        </td>
        <td style="width:7%;"></td>
        <td style="width:8%;">            <span class="totalsVal mono">{{ money($inv['invoice_total_amount'] ?? 0) }}</span>
        </td>
    </tr>
</table>

<table class="grid">
    <thead>
    <tr>
        <!-- Well/Job smaller, Driver bigger (fits driver details) -->
        <th style="width:24%;">Well/Job</th>
        <th style="width:10%;">Date</th>
        <th style="width:14%;">Driver</th>
        <th style="width:7%;">Load #</th>
        <th style="width:9%;">BOL</th>
        <th style="width:9%;">Tons</th>
        <th style="width:8%;">Miles</th>
        <th style="width:7%;">Rate/Ton</th>
        <th style="width:8%;">Load Pay</th>
    </tr>
    </thead>

    <tbody>
    @if(count($rows)===0)
        <tr><td colspan="9" class="muted">No rows for selected filters.</td></tr>
    @else
        @foreach($rows as $r)
            <tr>
                <td>{{ $r['well_job'] ?? '' }}</td>
                <td class="center mono">{{ dateOnly($r['date'] ?? '') }}</td>
                <td>{{ $r['driver'] ?? '' }}</td>
                <td class="center mono">{{ $r['load_no'] ?? '' }}</td>
                <td class="center mono">{{ $r['bol'] ?? '' }}</td>
                <td class="center mono">{{ number_format((float)($r['tons'] ?? 0),2,'.','') }}</td>
                <td class="center mono">{{ (int)($r['miles'] ?? 0) }}</td>
                <td class="center mono">{{ money($r['rate_ton'] ?? 0) }}</td>
                <td class="center mono">{{ money($r['load_pay'] ?? 0) }}</td>
            </tr>
        @endforeach
    @endif
    </tbody>
</table>

</body>
</html>
