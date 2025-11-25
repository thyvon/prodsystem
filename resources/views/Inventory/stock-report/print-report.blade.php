<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Stock Report</title>

    <style type="text/css">
        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url("{{ public_path('fonts/KhmerOSBattambang-Regular.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'TW Cen MT';
            src: url("{{ public_path('fonts/TwCenMT.ttf') }}") format('truetype');
        }

        /* Page setup */
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

        /* Container */
        .page-container { width: 100%; box-sizing: border-box; }

        /* Header */
        .header { text-align: center; margin-bottom: 10px; position: relative; }
        .logo-section { position: absolute; left: 10px; top: 0; }
        .logo-section img { width: 100px; height: auto; }
        .title-section h3 { margin: 0; font-size: 15px; font-weight: bold; }
        .date-range { color: red; font-weight: bold; font-size: 11px; }

        /* Table */
        table.items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
        }
        table.items th, table.items td {
            border: 1px solid #333;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.items th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }

        .col-no { width: 2%; }
        .col-code { width: 8%; }
        .col-desc { width: 22%; }
        .col-unit { width: 5%; }
        .col-qty { width: 7%; }
        .col-amount { width: 9%; }
        .col-avg { width: 9%; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .highlight-available { background-color: #e6f7e6 !important; }
        .available-cell { background-color: #f8fff8; }
        .total-row td { font-weight: bold; background-color: #f0f0f0; font-size: 12px; }

        /* Repeating header fix */
        thead { display: table-header-group; }
        tbody { display: table-row-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }

        /* Signature section - 4 fixed boxes */
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
        .signature-image { max-width: 100px; max-height: 70px; }
        .signature-line { height: 1px; background-color: #333; width: 100%; margin: 5px 0; }
        .signature-info { font-size: 12px; line-height: 1.2; }

    </style>
</head>
<body>

@php
    $reportData = $report;
    function fmt($value, $decimals = 4) {
        return ($value === null || $value == 0) ? '-' : number_format($value, $decimals);
    }

    // Ensure exactly 4 boxes: 1 Prepared + 3 approvals
    $approvalBoxes = array_pad($approvals ?? [], 3, null);
@endphp

<div class="page-container">

    <!-- Header -->
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

    <!-- Table -->
    <table class="items">
        <thead>
            <tr>
                <th class="col-no" rowspan="2">ល.រ<br>No</th>
                <th class="col-code" rowspan="2">លេខកូដ<br>Item Code</th>
                <th class="col-desc" rowspan="2">បរិយាយ<br>Description</th>
                <th class="col-unit" rowspan="2">ឯកតា<br>Unit</th>
                <th colspan="2">ស្តុកដើមគ្រា<br>Beginning</th>
                <th colspan="2">ស្តុកចូល<br>Stock In</th>
                <th colspan="2" class="highlight-available">ស្តុកសរុបសម្រាប់លក់<br>Available for Sale</th>
                <th colspan="2">ស្តុកចេញ<br>Stock Out</th>
                <th colspan="2">ស្តុកចុងគ្រា<br>Ending</th>
                <th class="col-avg" rowspan="2">តម្លៃមធ្យម<br>Avg Price</th>
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
            <!-- Total row -->
            <tr class="total-row">
                <td colspan="4" class="text-center">សរុប<br>Total</td>
                <td class="text-center">{{ fmt($reportData->sum('beginning_quantity'), 2) }}</td>
                <td class="text-right">{{ fmt($reportData->sum('beginning_total'), 2) }}</td>
                <td class="text-center">{{ fmt($reportData->sum('stock_in_quantity'), 2) }}</td>
                <td class="text-right">{{ fmt($reportData->sum('stock_in_total'), 2) }}</td>
                <td class="text-center available-cell">{{ fmt($reportData->sum('available_quantity'), 2) }}</td>
                <td class="text-right available-cell">{{ fmt($reportData->sum('available_total'), 2) }}</td>
                <td class="text-center">{{ fmt($reportData->sum('stock_out_quantity'), 2) }}</td>
                <td class="text-right">{{ fmt($reportData->sum('stock_out_total'), 2) }}</td>
                <td class="text-center">{{ fmt($reportData->sum('ending_quantity'), 2) }}</td>
                <td class="text-right">{{ fmt($reportData->sum('ending_total'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Signature section: 1 Prepared + 3 approvals (fixed 4 boxes) -->
    <div class="signature-section">

        <!-- Prepared by -->
        <div class="signature-box">
            <div class="signature-image-box"></div>
            <div class="signature-line"></div>
            <div class="signature-info">
                <strong>Prepared by</strong><br>
                ឈ្មោះ/Name: {{$created_by ?? '-'}}<br>
                តួនាទី/Position: {{$creator_position ?? '-'}}<br>
                កាលបរិច្ឆេទ/Date: {{ $created_at ?? '-' }}
            </div>
        </div>

        <!-- 3 Approvals -->
        @foreach($approvalBoxes as $appr)
        <div class="signature-box">
            <div class="signature-image-box">
                @if(!empty($appr['approval_status']) && $appr['approval_status'] === 'approved' && !empty($appr['signature_url']))
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

</div>
</body>
</html>
