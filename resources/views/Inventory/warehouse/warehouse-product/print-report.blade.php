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
                font-size: 10px;
                font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            }
        }

        body {
            font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            font-size: 10px;
            line-height: 1.3;
            margin: 0;
            padding: 5px;
        }

        .page-container { width: 100%; box-sizing: border-box; }

        .header { text-align: center; margin-bottom: 10px; position: relative; }
        .logo-section { position: absolute; left: 0px; top: 0; }
        .logo-section img { width: 100px; height: auto; }
        .title-section h3 { margin: 0; font-size: 15px; font-weight: bold; }
        .date-range { color: red; font-weight: bold; font-size: 11px; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.items th, table.items td {
            border: 1px solid #333;
            vertical-align: middle;
            word-wrap: break-word;
            white-space: normal;
        }
        table.items th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }

        .col-desc { max-width: 300px; }

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
            flex: 1 1 calc((100% / 4) - 20px);
            max-width: calc((100% / 4) - 20px);
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
        .signature-image { max-width: 100px; max-height: 90px; }
        .signature-line { height: 1px; background-color: #333; width: 100%; margin: 5px 0; }
        .signature-info { font-size: 12px; line-height: 1.2; }

        .text-right {
            text-align: right !important;
        }
    </style>
</head>
<body>

@php
    $items = collect($items ?? []);
    $approvals = array_pad($approvals ?? [], 3, null);

    $fmt = function($v, $d=2){ return is_numeric($v) ? number_format($v,$d) : ($v ?: '-'); };
    $fmtDate = fn($d)=> $d ? \Carbon\Carbon::parse($d)->format('M d, Y') : '-';
@endphp

<div class="page-container">

    <!-- Header -->
    <div class="header">
        <div class="logo-section">
            <img src="{{ public_path('img/logo/logo-dark.png') }}" alt="Logo">
        </div>
        <div class="title-section">
            <h3>របាយការណ៍ផលិតផលស្តុក</h3>
            <h3>Warehouse Product Report
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
                <th rowspan="2" style="width:2%;">#</th>
                <th rowspan="2" style="width:7%;">Item Code</th>
                <th rowspan="2" style="width:20%;">Description</th>
                <th rowspan="2" style="width:4%;">UoM</th>
                <th style="width:5%;">Unit Price</th>
                <th style="width:5%;">6-Month Avg Usage</th>
                <th style="width:5%;">Last Month Usage</th>
                <th style="width:5%;">Stock Beginning</th>
                <th style="width:5%;">Order Plan Qty</th>
                <th style="width:5%;">Demand Forecast</th>
                <th style="width:5%;">Stock Ending</th>
                <th style="width:5%;">Ending Cover Day</th>
                <th style="width:5%;">Target SS. Day</th>
                <th style="width:5%;">Stock Value</th>
                <th style="width:5%;">Inv. Reorder Qty</th>
                <th style="width:5%;">Reorder Level Qty</th>
                <th style="width:5%;">Max Inv. Level Qty</th>
                <th style="width:7%;">Max Inv. Usage Day</th>
                <th style="width:10%;">Remarks</th>
            </tr>

        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td class="text-center">{{ $i+1 }}</td>
                <td class="text-center">{{ $item['product_code'] }}</td>
                <td class="text-left">{{ $item['description'] ?? '-' }}</td>
                <td class="text-center">{{ $item['unit_name'] ?? '-' }}</td>
                <td class="text-right">{{ $fmt($item['unit_price']) }}</td>
                <td class="text-right">{{ $fmt($item['avg_6_month_usage']) }}</td>
                <td class="text-right">{{ $fmt($item['last_month_usage']) }}</td>
                <td class="text-right">{{ $fmt($item['stock_beginning']) }}</td>
                <td class="text-right">{{ $fmt($item['order_plan_qty']) }}</td>
                <td class="text-right">{{ $fmt($item['demand_forecast']) }}</td>
                <td class="text-right">{{ $fmt($item['stock_ending']) }}</td>
                <td class="text-right">{{ $fmt($item['ending_stock_cover_day']) }}</td>
                <td class="text-right">{{ $fmt($item['target_safety_stock_day']) }}</td>
                <td class="text-right">{{ $fmt($item['stock_value']) }}</td>
                <td class="text-right">{{ $fmt($item['inventory_reorder_quantity']) }}</td>
                <td class="text-right">{{ $fmt($item['reorder_level_qty']) }}</td>
                <td class="text-right">{{ $fmt($item['max_inventory_level_qty']) }}</td>
                <td class="text-right">{{ $fmt($item['max_inventory_usage_day']) }}</td>
                <td class="text-right">{{ $item['remarks'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="signature-section">

        <!-- Prepared by -->
        @if(!empty($prepared_by))
        <div class="signature-box">
            <div class="signature-image-box">
                <img src="{{ $creator_signature ? public_path('storage/' . $creator_signature) : '' }}" class="signature-image">
            </div>
            <div class="signature-line"></div>
            <div class="signature-info">
                <strong>Prepared By</strong><br>
                Name: {{ $prepared_by }}<br>
                Position: {{ $creator_position ?? '-' }}<br>
                Date: {{ $created_at ?? $fmtDate($report_date) }}
            </div>
        </div>
        @endif

        <!-- Approvals -->
        @foreach($approvals as $appr)
            @if(!empty($appr['user_name']))
            <div class="signature-box">
                <div class="signature-image-box">
                    @if(!empty($appr['signature_url']))
                        <img src="{{ public_path('storage/' . $appr['signature_url']) }}" class="signature-image">
                    @endif
                </div>
                <div class="signature-line"></div>
                <div class="signature-info">
                    <strong>{{ $appr['request_type_label'] ?? 'Approved By' }}</strong><br>
                    Name: {{ $appr['user_name'] }}<br>
                    Position: {{ $appr['position_name'] ?? '-' }}<br>
                    Date: {{ $appr['responded_date'] ?? '-' }}
                </div>
            </div>
            @endif
        @endforeach

    </div>

</div>
</body>
</html>
