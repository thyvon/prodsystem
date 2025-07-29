<!DOCTYPE html>
<html>
<head>
    <title>Invoice</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        h1 { margin-bottom: 0; }
        .line { border-top: 1px solid #ccc; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Invoice #{{ $invoice['number'] }}</h1>
    <p>Date: {{ $invoice['date'] }}</p>

    <div class="line"></div>

    <p><strong>To:</strong> {{ $invoice['customer'] }}</p>
    <p><strong>Amount:</strong> ${{ number_format($invoice['amount'], 2) }}</p>

    <div class="line"></div>

    <p>Thank you for your business!</p>
</body>
</html>
