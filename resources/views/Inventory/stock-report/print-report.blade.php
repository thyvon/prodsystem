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
            font-size: 12px;
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

        td.currency {
            text-align: right;           /* number aligned right */
            position: relative;
            padding-left: 20px;          /* space for currency symbol */
        }

        td.currency::before {
            content: '$';                /* your currency symbol */
            position: absolute;
            left: 5px;                   /* symbol on the left edge */
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
            justify-content: center;
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

        /* ===== FOOTER INITIAL SIGNATURE ===== */
        .footer-initial-signature {
            position: fixed;
            bottom: 12mm;
            right: 10mm;
            width: 90px;
            text-align: right;
        }

        .footer-initial-signature img {
            max-width: 50px;
            max-height: 20px;
            object-fit: contain;
        }

    </style>
</head>
<body>

@php
    $reportData = $report;

    // Ensure exactly 3 approval boxes
    $approvalBoxes = array_pad($approvals ?? [], 3, null);

    // Helper closures for formatting
    $fmt = function($value, $decimals = 4) {
        return ($value === null || $value == 0) ? '-' : number_format($value, $decimals);
    };
    $fmtDate = function($dateStr) {
        return $dateStr ? \Carbon\Carbon::parse($dateStr)->format('M d, Y') : '-';
    };
@endphp

<div class="page-container">

    <!-- Header -->
    <div class="header">
        <div class="logo-section">
            <img src="{{ public_path('img/logo/logo-dark.png') }}" alt="Logo">
        </div>
        <div class="title-section">
            <h3>របាយការណ៍ស្តុក</h3>
            <h3>Stock Report
                @if($warehouseNames !== 'All Warehouses')
                    <small style="font-weight:normal; color:#555;">({{ $warehouseNames }})</small>
                @else
                    <small style="font-weight:normal; color:#555;">(All Warehouses)</small>
                @endif
            </h3>
            <div class="date-range">{{ $fmtDate($start_date) }}  -  {{ $fmtDate($end_date) }}</div>
        </div>
    </div>

    <!-- Table -->
    <table class="items">
        <thead>
            <tr>
                <th class="col-no" rowspan="2">ល.រ<br>No</th>
                <th style="min-width: 70px;" rowspan="2">Item Code</th>
                <th style="min-width: 200px;" rowspan="2">Description</th>
                <th class="col-unit" rowspan="2">Unit</th>
                <th colspan="3">Beginning</th>
                <th colspan="2">Stock In</th>
                <th colspan="3" class="highlight-available">Available for Sale</th>
                <th colspan="2">Stock Out</th>
                <th colspan="6">Ending</th>
            </tr>
            <tr>
                <th>Qty</th><th>Unit Price</th><th>Amount</th>
                <th>Qty</th><th>Amount</th>
                <th class="highlight-available">Qty</th><th class="highlight-available">Unit Price</th><th class="highlight-available">Amount</th>
                <th>Qty</th><th>Amount</th>
                <th>Qty</th><th>Physical Count</th><th>Variance</th><th>Carried forward</th><th>Unit Price</th><th>Amount (USD)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $i => $item)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td class="text-center">{{ $item['item_code'] }}</td>
                <td style="text-align:left; padding-left: 3px">{{ $item['description'] ?? '-' }}</td>
                <td class="text-center">{{ $item['unit_name'] ?? '-' }}</td>
                <td class="text-start">{{ $fmt($item['beginning_quantity'], 2) }}</td>
                <td class="text-start currency">{{ $fmt($item['beginning_price']) }}</td>
                <td class="text-start currency">{{ $fmt($item['beginning_total']) }}</td>
                <td class="text-start">{{ $fmt($item['stock_in_quantity'], 2) }}</td>
                <td class="text-start currency">{{ $fmt($item['stock_in_total']) }}</td>
                <td class="text-start ">{{ $fmt($item['available_quantity'], 2) }}</td>
                <td class="text-start currency">{{ $fmt($item['available_price']) }}</td>
                <td class="text-start currency">{{ $fmt($item['available_total']) }}</td>
                <td class="text-start">{{ $fmt($item['stock_out_quantity'], 2) }}</td>
                <td class="text-start currency">{{ $fmt($item['stock_out_total']) }}</td>
                <td class="text-start">{{ $fmt($item['ending_quantity'], 2) }}</td>
                <td class="text-start">{{ $fmt($item['counted_quantity'], 2) }}</td> <!-- Physical Count column is intentionally left blank -->
                <td class="text-start">{{ $fmt($item['variance_quantity'], 2) }}</td> <!-- Variance column is intentionally left blank -->
                <td class="text-start">{{ $fmt($item['counted_quantity'], 2) }}</td> <!-- Carried forward column is intentionally left blank -->
                <td class="text-start currency">{{ $fmt($item['average_price']) }}</td>
                <td class="text-start currency">{{ $fmt($item['ending_total']) }}</td>
            </tr>
            @endforeach

            <!-- Total row -->
            <tr class="total-row">
                <td colspan="4" class="text-center">សរុប<br>Total</td>
                <td class="text-center">{{ $fmt($reportData->sum('beginning_quantity'), 2) }}</td>
                <td class="text-center">-</td>
                <td class="text-right">{{ $fmt($reportData->sum('beginning_total'), 4) }}</td>
                <td class="text-center">{{ $fmt($reportData->sum('stock_in_quantity'), 2) }}</td>
                <td class="text-right">{{ $fmt($reportData->sum('stock_in_total'), 4) }}</td>
                <td class="text-center available-cell">{{ $fmt($reportData->sum('available_quantity'), 2) }}</td>
                <td class="text-center available-cell">-</td>
                <td class="text-right available-cell">{{ $fmt($reportData->sum('available_total'), 4) }}</td>
                <td class="text-center">{{ $fmt($reportData->sum('stock_out_quantity'), 2) }}</td>
                <td class="text-right">{{ $fmt($reportData->sum('stock_out_total'), 4) }}</td>
                <td class="text-center">{{ $fmt($reportData->sum('ending_quantity'), 2) }}</td>
                <td class="text-right">{{ $fmt($reportData->sum('counted_quantity'), 2) }}</td>
                <td class="text-center">{{ $fmt($reportData->sum('variance_quantity'), 2) }}</td>
                <td class="text-right">{{ $fmt($reportData->avg('counted_quantity'), 2) }}</td>
                <td class="text-right">-</td>
                <td class="text-right">{{ $fmt($reportData->sum('ending_total'), 4) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Signature section -->
    @if(!empty($created_by) || !empty($approvalBoxes))
    <div class="signature-section">

        <!-- Prepared by -->
        @if(!empty($created_by))
        <div class="signature-box">
            {{-- Title above the image --}}
            <div class="signature-info" style="text-align:center; margin-bottom:5px;">
                <strong>Prepared & counted by</strong>
            </div>

            {{-- Signature image --}}
            <div class="signature-image-box">
                <img src="{{ public_path('storage/' . $signature_url) }}" class="signature-image">
            </div>

            <div class="signature-line"></div>

            {{-- Info below the image --}}
            <div class="signature-info">
                ឈ្មោះ/Name: {{$created_by}}<br>
                តួនាទី/Position: {{$creator_position ?? '-'}}<br>
                កាលបរិច្ឆេទ/Date: {{ $created_at ?? '-' }}
            </div>
        </div>
        @endif


        <!-- Approvals -->
        @foreach($approvalBoxes as $appr)
            @if(
                !empty($appr['user_name']) &&
                ($appr['request_type'] ?? null) !== 'initial'
            )
            <div class="signature-box">
                {{-- Approval title above the image --}}
                <div class="signature-info" style="text-align:center; margin-bottom:5px;">
                    <strong>{{ $appr['request_type_label'] ?? 'Approved By' }}</strong>
                </div>

                {{-- Signature image --}}
                <div class="signature-image-box">
                    @if(
                        !empty($appr['approval_status']) &&
                        $appr['approval_status'] === 'Approved' &&
                        !empty($appr['signature_url'])
                    )
                        <img src="{{ public_path('storage/' . $appr['signature_url']) }}"
                            class="signature-image">
                    @endif
                </div>

                <div class="signature-line"></div>

                {{-- Approval info below the image --}}
                <div class="signature-info">
                    ឈ្មោះ/Name: {{ $appr['user_name'] }}<br>
                    តួនាទី/Position: {{ $appr['position_name'] ?? '-' }}<br>
                    កាលបរិច្ឆេទ/Date: {{ $appr['responded_date'] ?? '-' }}
                </div>
            </div>
            @endif
        @endforeach
    </div>
    @endif

    @foreach($approvalBoxes as $appr)
        @if(
            ($appr['request_type'] ?? null) === 'check' &&
            $appr['approval_status'] === 'Approved' &&
            !empty($appr['signature_url'])
        )
        <div class="footer-initial-signature">
            <img src="{{ public_path('storage/' . $appr['signature_url']) }}">
        </div>
        @endif
    @endforeach

</div>
</body>
</html>
