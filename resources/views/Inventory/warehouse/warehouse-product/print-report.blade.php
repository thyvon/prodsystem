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

    /* ================= SIGNATURE SECTION ================= */

        .signature-section {
            width: 100%;
            margin-top: 25px;
            text-align: center;
            page-break-inside: avoid;
        }

        .signature-row {
            display: inline-block;
            width: 100%;
            text-align: center;
        }

        .signature-box {
            display: inline-block;
            width: 260px;          /* FIXED WIDTH */
            height: 200px;         /* FIXED HEIGHT */
            margin: 0 80px;
            vertical-align: top;
            box-sizing: border-box;
            text-align: center;
            font-size: 14px;
        }

        .signature-title {
            font-weight: bold;
            line-height: 1.3;
            margin-bottom: 5px;
        }

        .signature-image-box {
            width: 100%;
            height: 90px;          /* FIXED IMAGE AREA */
            border: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .signature-image {
            max-width: 130px;
            max-height: 80px;
            object-fit: contain;
        }

        .signature-line {
            width: 100%;
            height: 1px;
            background-color: #999;
            margin: 8px 0;
        }

        .signature-info {
            text-align: left;
            line-height: 1.3;
            font-size: 14px;
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

    /* ================= INITIAL APPROVAL FOOTER ================= */

        .initial-approval-footer {
            position: fixed;
            bottom: 12mm;
            right: 10mm;
            width: 90px;
            text-align: right;
        }

        .initial-approval-footer img {
            max-width: 50px;
            max-height: 20px;
            object-fit: contain;
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

        <div class="signature-row">

            <!-- Requested By -->
            @if(!empty($prepared_by))
            <div class="signature-box">
                <div class="signature-title">
                    ស្នើសុំដោយ<br>Requested By
                </div>

                <div class="signature-image-box">
                    @if(!empty($creator_signature))
                        <img src="{{ public_path('storage/' . $creator_signature) }}" class="signature-image">
                    @endif
                </div>

                <div class="signature-line"></div>

                <div class="signature-info">
                    ឈ្មោះ / Name: {{ $prepared_by }}<br>
                    តួនាទី / Position: {{ $creator_position ?? '-' }}<br>
                    កាលបរិច្ឆេទ / Date: {{ $created_at ?? $fmtDate($report_date) }}
                </div>
            </div>
            @endif

            <!-- Approvals -->
            @foreach($approvals as $appr)
                @if(
                    !empty($appr['user_name']) &&
                    ($appr['request_type'] ?? null) !== 'initial'
                )
                <div class="signature-box">
                    <div class="signature-title">
                        {{ $appr['request_type_label_kh'] ?? 'Approved By' }}<br>
                        {{ $appr['request_type_label_en'] ?? 'Approved By' }}
                    </div>

                    <div class="signature-image-box">
                        @if($appr['approval_status'] === 'Approved' && !empty($appr['signature_url']))
                            <img src="{{ public_path('storage/' . $appr['signature_url']) }}" class="signature-image">
                        @endif
                    </div>

                    <div class="signature-line"></div>

                    <div class="signature-info">
                        ឈ្មោះ / Name: {{ $appr['user_name'] }}<br>
                        តួនាទី / Position: {{ $appr['position_name'] ?? '-' }}<br>
                        កាលបរិច្ឆេទ / Date: {{ $appr['responded_date'] ?? '-' }}
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    <!-- Initial Approval Footer -->
    @foreach($approvals as $appr)
        @if(
            ($appr['request_type'] ?? null) === 'check' &&
            $appr['approval_status'] === 'Approved' &&
            !empty($appr['signature_url'])
        )
        <div class="initial-approval-footer">
            <img src="{{ public_path('storage/' . $appr['signature_url']) }}">
        </div>
        @endif
    @endforeach

</div>
</body>
</html>
