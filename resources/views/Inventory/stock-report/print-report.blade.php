<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Stock Report</title>
    <style type="text/css" media="all">
        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url("{{ storage_path('fonts/KhmerOSbattambang.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'TW Cen MT';
            src: url("{{ storage_path('fonts/TwCenMT.ttf') }}") format('truetype');
        }
        @page {
            margin: 4mm 4mm 4mm 4mm; 
            size: A4 landscape;
        }

        body {
            margin: 0;
            padding: 5px;
            font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            font-size: 9.5px;
            line-height: 1.4;
            color: #000;
        }

        .page-container {
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }

        /* Header */
        .header {
            position: relative;
            min-height: 70px;
            margin-bottom: 10px;
            text-align: center;
        }
        .logo-section {
            position: absolute;
            left: 10px;
            top: 0;
        }
        .logo-section img { width: 100px; height: auto; }
        .title-section h3 { margin: 0 0 3px; font-size: 15px; font-weight: bold; }
        .date-range { color: red; font-weight: bold; font-size: 11px; }

        /* Table */
        table.items {
            width: 100% !important;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12px;
        }
        table.items th, table.items td {
            border: 1px solid #333;
            font-size: 11px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.items th { background-color: #f2f2f2; text-align: center; font-weight: bold; }

        /* Column widths */
        .col-no     { width: 4%; }
        .col-code   { width: 8%; }
        .col-desc   { width: 25%; }
        .col-unit  { width: 5%; }
        .col-qty    { width: 7%; }
        .col-amount { width: 9%; }
        .col-avg    { width: 9%; }

        .text-center { text-align: center !important; }
        .text-right  { text-align: right !important; }
        .highlight-available th, .highlight-available td { background-color: #e6f7e6 !important; }
        .available-cell { background-color: #f8fff8 !important; }
        .total-row td { font-weight: bold; background-color: #f0f0f0 !important; font-size: 12px; min-height: 30px; }

        /* Approval (PR Style) */
        .signature-section {
            margin-top: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            page-break-inside: avoid;
        }
        .signature-box {
            flex: 1 1 calc((100% / 3) - 20px);
            max-width: calc((100% / 3) - 20px);
            min-width: 150px;
            display: flex;
            flex-direction: column;
            height: 150px;
            box-sizing: border-box;
        }
        .signature-image-box {
            height: 55px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .signature-image {
            max-width: 100px;
            max-height: 70px;
        }
        .signature-line {
            height: 1px;
            background-color: #333;
            width: 100%;
            margin: 5px 0;
        }
        .signature-info {
            font-size: 10px;
            line-height: 1.3;
        }

    </style>

    <style media="print">
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.items { font-size: 9px !important; }
    </style>
</head>
<body>

@php
    $reportData = $report;

    function fmt($value, $decimals = 4) {
        return ($value == 0 || $value === null) ? '-' : number_format($value, $decimals);
    }
@endphp

<div class="page-container">

    <div class="header">
        <div class="logo-section">
            <img src="{{ public_path('img/logo/logo-dark.png') }}" alt="Logo">
        </div>
        <div class="title-section">
            <h3>របាយការណ៍ស្តុកទំនិញ</h3>
            <h3>Stock Report 
                @if($warehouseNames !== 'All Warehouses')
                    <small style="font-weight:normal; color:#555;">({{ $warehouseNames }})</small>
                @else
                    <small style="font-weight:normal; color:#555;">(All Warehouses)</small>
                @endif
            </h3>
            <div class="date-range">{{ $start_date }} - {{ $end_date }}</div>
        </div>
    </div>

    <table class="items">
        <colgroup>
            <col class="col-no"><col class="col-code"><col class="col-desc"><col class="col-unit">
            <col class="col-qty"><col class="col-amount">
            <col class="col-qty"><col class="col-amount">
            <col class="col-qty"><col class="col-amount">
            <col class="col-qty"><col class="col-amount">
            <col class="col-qty"><col class="col-amount">
            <col class="col-avg">
        </colgroup>

        <thead>
            <tr>
                <th rowspan="2">ល.រ<br>No</th>
                <th rowspan="2">លេខកូដ<br>Item Code</th>
                <th rowspan="2">បរិយាយ<br>Description</th>
                <th rowspan="2">ឯកតា<br>Unit</th>
                <th colspan="2">ស្តុកដើមគ្រា<br>Beginning</th>
                <th colspan="2">ស្តុកចូល<br>Stock In</th>
                <th colspan="2" class="highlight-available">ស្តុកសរុបសម្រាប់លក់<br>Available for Sale</th>
                <th colspan="2">ស្តុកចេញ<br>Stock Out</th>
                <th colspan="2">ស្តុកចុងគ្រា<br>Ending</th>
                <th rowspan="2">តម្លៃមធ្យម<br>Avg Price</th>
            </tr>
            <tr>
                <th>Qty</th><th>Amount</th>
                <th>Qty</th><th>Amount</th>
                <th class="highlight-available">Qty</th><th class="highlight-available">Amount</th>
                <th>Qty</th><th>Amount</th>
                <th>Qty</th><th>Amount</th>
            </tr>
        </thead>

        <tbody>

            @foreach($reportData as $i => $item)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td class="text-center">{{ $item['item_code'] }}</td>
                <td style="text-align:left;padding-left:5px;">{{ $item['description'] ?? '-' }}</td>
                <td class="text-center">{{ $item['unit_name'] ?? '-' }}</td>

                <td class="text-center">{{ fmt($item['beginning_quantity']) }}</td>
                <td class="text-right">{{ fmt($item['beginning_total']) }}</td>

                <td class="text-center">{{ fmt($item['stock_in_quantity']) }}</td>
                <td class="text-right">{{ fmt($item['stock_in_total']) }}</td>

                <td class="text-center available-cell">{{ fmt($item['available_quantity']) }}</td>
                <td class="text-right available-cell">{{ fmt($item['available_total']) }}</td>

                <td class="text-center">{{ fmt($item['stock_out_quantity']) }}</td>
                <td class="text-right">{{ fmt($item['stock_out_total']) }}</td>

                <td class="text-center">{{ fmt($item['ending_quantity']) }}</td>
                <td class="text-right">{{ fmt($item['ending_total']) }}</td>

                <td class="text-right">{{ fmt($item['average_price']) }}</td>
            </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4"><strong>សរុប<br>Total</strong></td>
                <td class="text-center"><strong>{{ fmt($reportData->sum('beginning_quantity'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ fmt($reportData->sum('beginning_total'), 2) }}</strong></td>
                <td class="text-center"><strong>{{ fmt($reportData->sum('stock_in_quantity'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ fmt($reportData->sum('stock_in_total'), 2) }}</strong></td>
                <td class="text-center available-cell"><strong>{{ fmt($reportData->sum('available_quantity'), 2) }}</strong></td>
                <td class="text-right available-cell"><strong>{{ fmt($reportData->sum('available_total'), 2) }}</strong></td>
                <td class="text-center"><strong>{{ fmt($reportData->sum('stock_out_quantity'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ fmt($reportData->sum('stock_out_total'), 2) }}</strong></td>
                <td class="text-center"><strong>{{ fmt($reportData->sum('ending_quantity'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ fmt($reportData->sum('ending_total'), 2) }}</strong></td>
                <td></td>
            </tr>

        </tbody>
    </table>

    <!-- =============================== -->
    <!-- NEW APPROVAL SECTION (PR STYLE) -->
    <!-- =============================== -->

    @if(!empty($approvals))

    <div class="signature-section">

        @foreach($approvals as $appr)
        <div class="signature-box">

            <div class="signature-image-box">
                @if(($appr['approval_status'] ?? '') === 'approved' && !empty($appr['signature_url']))
                    <img src="{{ public_path('storage/' . $appr['signature_url']) }}" class="signature-image">
                @endif
            </div>

            <div class="signature-line"></div>

            <div class="signature-info">
                <strong>{{ $appr['request_type_label'] ?? 'Approved By' }}</strong><br>
                ឈ្មោះ/Name: {{ $appr['user_name'] ?? '-' }}<br>
                តួនាទី/Position: {{ $appr['position_name'] ?? '-' }}<br>
                កាលបរិច្ឆេទ/Date: {{ $appr['responded_date'] ?? '-' }}
            </div>

        </div>
        @endforeach

    </div>

    @endif

</div>
</body>
</html>
