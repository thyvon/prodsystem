<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request</title>

    <style>
        /* Khmer font */
        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url("{{ storage_path('fonts/KhmerOSbattambang.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        /* English font */
        @font-face {
            font-family: 'TW Cen MT';
            src: url("{{ storage_path('fonts/TwCenMT.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            font-family: 
                'TW Cen MT',       /* fallback English */
                'Khmer OS Battambang', /* fallback Khmer */
                sans-serif;
            font-size: 10px;
            /* line-height: 1.4; */
        }

        h3 {
            text-align: center;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
        }

        table, th, td {
            border: 1px solid #333;
        }

        th, td {
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #f0f0f0;
        }

        .text-center { text-align: center; }
        .text-end { text-align: right; }
    </style>
</head>
<body>
    <h3>Purchase </h3>
    <h3>សំណើរប្រាក់</h3>
    

    <p><strong>Requester / អ្នកស្នើសុំ:</strong> {{ $purchaseRequest->creator->name }}</p>
    <p><strong>Purpose / គោលបំណង:</strong> {{ $purchaseRequest->purpose }}</p>
    <p><strong>Date / កាលបរិច្ឆេទ:</strong> {{ $purchaseRequest->request_date }}</p>

    <table>
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Product / ផលិតផល</th>
                <th class="text-center">Qty / បរិមាណ</th>
                <th>Unit / អង្គភាព</th>
                <th class="text-end">Price / តម្លៃ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseRequest->items as $i => $item)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $item->product->product->name }} - {{ $item->product->description }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td>{{ $item->product->product->unit->name }}</td>
                <td class="text-end">{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
