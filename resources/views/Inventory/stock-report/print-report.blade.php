<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Stock Report</title>
    <style>
        body {
            font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 15px 20px;
        }

        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url("{{ storage_path('fonts/KhmerOSbattambang.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'TW Cen MT';
            src: url("{{ storage_path('fonts/TwCenMT.ttf') }}") format('truetype');
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            padding-bottom: 5px;
            min-height: 60px;
        }
        .logo-section {
            position: absolute;
            left: 0; top: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logo { width: 110px; }

        .title-section {
            position: absolute;
            left: 50%; top: 0;
            transform: translateX(-50%);
            text-align: center;
            line-height: 1;
            margin-bottom: 10px;
        }
        .title-section h3 { margin: 0; font-size: 14px; }
        .sub-title { color: red; font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        table.items, .items th, .items td { border: 1px solid #333; }
        .items th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
        .items th, .items td { padding: 4px; font-size: 10px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body style="margin-bottom: 50px;">
@php $reportData = $report; @endphp

<div class="header">
    <div class="logo-section">
        <img src="{{ public_path('img/logo/logo-dark.png') }}" class="logo" alt="MJQ Logo">
    </div>
    <div class="title-section">
        <h3>របាយការណ៍ស្តុកទំនិញ</h3>
        <h3>Stock Report</h3>
        <div class="sub-title">{{ $start_date }} - {{ $end_date }}</div>
    </div>
</div>

<table class="items">
    <thead>
        <tr>
            <th>ល.រ<br>No</th>
            <th>លេខកូដ<br>Item Code</th>
            <th>បរិយាយ<br>Description</th>
            <th>ចាប់ផ្តើម Qty<br>Begin Qty</th>
            <th>ចាប់ផ្តើម តម្លៃ<br>Begin Amount</th>
            <th>Stock In Qty</th>
            <th>Stock In Amount</th>
            <th>Stock Out Qty</th>
            <th>Stock Out Amount</th>
            <th>Ending Qty</th>
            <th>Average Price</th>
            <th>Ending Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($reportData as $i => $item)
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td>{{ $item['item_code'] }}</td>
            <td>{{ $item['description'] ?? '-' }}</td>
            <td class="text-center">{{ number_format($item['beginning_quantity'], 4) }}</td>
            <td class="text-right">{{ number_format($item['beginning_total'], 4) }}</td>
            <td class="text-center">{{ number_format($item['stock_in_quantity'], 4) }}</td>
            <td class="text-right">{{ number_format($item['stock_in_total'], 4) }}</td>
            <td class="text-center">{{ number_format($item['stock_out_quantity'], 4) }}</td>
            <td class="text-right">{{ number_format($item['stock_out_total'], 4) }}</td>
            <td class="text-center">{{ number_format($item['ending_quantity'], 4) }}</td>
            <td class="text-right">{{ number_format($item['average_price'], 4) }}</td>
            <td class="text-right">{{ number_format($item['ending_total'], 4) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-right"><strong>Total</strong></td>
            <td class="text-center"><strong>{{ number_format($reportData->sum('beginning_quantity'),2) }}</strong></td>
            <td class="text-right"><strong>{{ number_format($reportData->sum('beginning_total'),2) }}</strong></td>
            <td class="text-center"><strong>{{ number_format($reportData->sum('stock_in_quantity'),2) }}</strong></td>
            <td class="text-right"><strong>{{ number_format($reportData->sum('stock_in_total'),2) }}</strong></td>
            <td class="text-center"><strong>{{ number_format($reportData->sum('stock_out_quantity'),2) }}</strong></td>
            <td class="text-right"><strong>{{ number_format($reportData->sum('stock_out_total'),2) }}</strong></td>
            <td class="text-center"><strong>{{ number_format($reportData->sum('ending_quantity'),2) }}</strong></td>
            <td class="text-right"><strong>-</strong></td>
            <td class="text-right"><strong>{{ number_format($reportData->sum('ending_total'),2) }}</strong></td>
        </tr>
    </tfoot>
</table>
</body>
</html> 
