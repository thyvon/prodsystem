<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Product Report</title>

    <style type="text/css">
        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url("{{ public_path('fonts/KhmerOSBattambang-Regular.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'TW Cen MT';
            src: url("{{ public_path('fonts/TwCenMT.ttf') }}") format('truetype');
        }

        @page {
            size: A4 landscape;
            margin: 5mm;
            @bottom-center {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 12px;
                font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            }
        }

        body {
            font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            font-size: 10px;
            line-height: 1;
            margin: 0;
            padding: 5px;
        }

        .page-container { width: 100%; box-sizing: border-box; }

        .header { text-align: center; margin-bottom: 10px; position: relative; }
        .logo-section { position: absolute; left: 0px; top: 0; }
        .logo-section img { width: 120px; height: auto; }
        .title-section h3 { margin: 0; font-size: 20px; font-weight: bold; }
        .date-range { color: red; font-weight: bold; font-size: 11px; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table.items th, table.items td {
            border: 1px solid #333;
            vertical-align: middle;
            word-wrap: break-word;
            white-space: normal;
            padding: 3px;
        }
        table.items th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .highlight-available { background-color: #e6f7e6 !important; }
        .available-cell { background-color: #f8fff8; }
        .total-row td { font-weight: bold; background-color: #f0f0f0; font-size: 12px; }

        thead { display: table-header-group; }
        tbody { display: table-row-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }

        .signature-section {
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            page-break-inside: avoid;
        }

        .signature-box {
            flex: 1 1 auto;
            max-width: 30%;      /* 3 boxes per row */
            min-width: 150px;
            display: flex;
            flex-direction: column;
            height: 190px;
            box-sizing: border-box;
        }

        /* Add spacing to center box ONLY */
        .signature-section .signature-box:nth-child(2) {
            margin: 0 200px;   /* space on left + right */
        }

        .signature-title{
            font-size: 12px;
            text-align: center;
            line-height: 1.3;
            margin-bottom: 2px; /* Better spacing */
        }

        .signature-image-box {
            min-height: 80px;              /* Ensure space for signature */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .signature-image {
            max-width: 130px;
            max-height: 130px;
            object-fit: contain;
        }

        .signature-line {
            height: 1px;
            background-color: #9b9a9aff;
            margin: 10px 0;           /* Better spacing */
        }

        .signature-info {
            font-size: 14px;
            line-height: 1.3;
        }

        .text-right {
            text-align: right !important;
        }
        .text-left{
            text-align: left !important;
        }
        td.currency {
            text-align: right;           /* number to the right */
            position: relative;
            padding-left: 20px;          /* space for currency symbol */
        }

        td.currency::before {
            content: '$';                /* your currency symbol */
            position: absolute;
            left: 5px;                   /* symbol on the left edge */
        }
    </style>
</head>
<body>

@php
    $items = collect($items ?? []);
    $approvals = array_pad($approvals ?? [], 3, null);

    $fmt = function($v, $d=2) { 
        return (is_numeric($v) && $v != 0) ? number_format($v, $d) : '-'; 
    };
    $fmtStock = function($v) {
        return (is_numeric($v) && $v != 0) ? number_format($v, 2, '.', ',') : '-';
    };
    $fmtDate = fn($d)=> $d ? \Carbon\Carbon::parse($d)->format('M d, Y') : '-';
@endphp

<div class="page-container">

    <!-- Header -->
    <div class="header">
        <div class="logo-section">
            <img src="{{ public_path('img/logo/logo-dark.png') }}" alt="Logo">
        </div>
        <div class="title-section">
            <h3>Stock Report</h3>
            <h3>
                @if(!empty($warehouse_name))
                    <small style="font-weight:normal; color:#555;">({{ $warehouse_name }})</small>
                @endif
            </h3>
            <div class="date-range">{{ $fmtDate($report_date) }}</div>
        </div>
    </div>

    <!-- Table -->
    <table class="items">
        <thead>
            <tr>
                <th style="min-width:25px;">#</th>
                <th style="min-width:80px;">Item Code</th>
                <th style="min-width:290px;">Description</th>
                <th style="min-width:40px;">UoM</th>
                <th style="min-width:60px;">Unit Price</th>
                <th style="min-width:50px;">6-Month Avg Usage</th>
                <th style="min-width:50px;">Last Month Usage</th>
                <th style="min-width:50px;">Stock Beginning</th>
                <th style="min-width:50px;">Order Plan Qty</th>
                <th style="min-width:50px;">Demand Forecast</th>
                <th style="min-width:50px;">Stock Ending</th>
                <th style="min-width:50px;">Ending Cover Day</th>
                <th style="min-width:50px;">Target SS. Day</th>
                <th style="min-width:50px;">Stock Value</th>
                <th style="min-width:50px;">Inv. Reorder Qty</th>
                <th style="min-width:50px;">Reorder Level Qty</th>
                <th style="min-width:50px;">Max Inv. Level Qty</th>
                <th style="min-width:50px;">Max Inv. Usage Day</th>
                <th style="min-width:120px;">Remarks</th>
            </tr>
        </thead>

        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td class="text-center">{{ $i+1 }}</td>
                <td class="text-center">{{ $item['product_code'] }}</td>
                <td class="text-left">{{ $item['description'] ?? '-' }}</td>
                <td class="text-center">{{ $item['unit_name'] ?? '-' }}</td>
                <td class="currency">{{ $fmtStock($item['unit_price']) }}</td>
                <td class="text-right">{{ $fmt($item['avg_6_month_usage']) }}</td>
                <td class="text-right">{{ $fmt($item['last_month_usage']) }}</td>
                <td class="text-right">{{ $fmt($item['stock_beginning']) }}</td>
                <td class="text-right">{{ $fmt($item['order_plan_qty']) }}</td>
                <td class="text-right">{{ $fmt($item['demand_forecast']) }}</td>
                <td class="text-right">{{ $fmt($item['stock_ending']) }}</td>
                <td class="text-right">{{ $fmt($item['ending_stock_cover_day']) }}</td>
                <td class="text-right">{{ $fmt($item['target_safety_stock_day']) }}</td>
                <td class="currency">{{ $fmtStock($item['stock_value']) }}</td>
                <td class="text-right">{{ $fmt($item['inventory_reorder_quantity']) }}</td>
                <td class="text-right">{{ $fmt($item['reorder_level_qty']) }}</td>
                <td class="text-right">{{ $fmt($item['max_inventory_level_qty']) }}</td>
                <td class="text-right">{{ $fmt($item['max_inventory_usage_day']) }}</td>
                <td class="text-right">{{ $item['remarks'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(!empty($remarks))
    <p>Remarks: {{$remarks}}</p>
    @endif
    <!-- Signatures -->
    <div class="signature-section">

        <!-- Prepared by -->
        @if(!empty($prepared_by))
        <div class="signature-box">
            <strong class="signature-title">
                ស្នើសុំដោយ<br>Requested By
            </strong>

            <div class="signature-image-box">
                @if($creator_signature)
                    <img src="{{ public_path('storage/' . $creator_signature) }}" class="signature-image">
                @endif
            </div>

            <div class="signature-line"></div>

            <div class="signature-info">
                ឈ្មោះ /Name: {{ $prepared_by }}<br>
                តួនាទី /Position: {{ $creator_position ?? '-' }}<br>
                កាលបរិច្ឆេទ /Date: {{ $created_at ?? $fmtDate($report_date) }}
            </div>
        </div>
        @endif

        <!-- Approvals -->
        @foreach($approvals as $appr)
            @if(!empty($appr['user_name']))
            <div class="signature-box">

                <strong class="signature-title">
                    {{ $appr['request_type_label_kh'] ?? 'Approved By' }}<br>
                    {{ $appr['request_type_label_en'] ?? 'Approved By' }}
                </strong>

                <div class="signature-image-box">
                    @if($appr['approval_status'] === 'Approved' && !empty($appr['signature_url']))
                        <img src="{{ public_path('storage/' . $appr['signature_url']) }}" class="signature-image">
                    @endif
                </div>

                <div class="signature-line"></div>

                <div class="signature-info">
                    ឈ្មោះ /Name: {{ $appr['user_name'] }}<br>
                    តួនាទី /Position: {{ $appr['position_name'] ?? '-' }}<br>
                    កាលបរិច្ឆេទ /Date: {{ $appr['responded_date'] ?? '-' }}
                </div>
            </div>
            @endif
        @endforeach

    </div>

</div>
</body>
</html>
