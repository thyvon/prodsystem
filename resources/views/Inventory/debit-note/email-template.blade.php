<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debit Note Notification</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
            line-height: 1.6;
        }
        .container {
            background-color: #ffffff;
            padding: 20px;
            margin: 20px auto;
            border-radius: 8px;
            max-width: 700px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        h2 {
            color: #007bff;
            margin-bottom: 5px;
        }
        .details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .details th, .details td {
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: left;
        }
        .details th {
            background-color: #e9ecef;
        }
        .footer {
            font-size: 0.9rem;
            color: #6c757d;
            text-align: center;
            margin-top: 20px;
        }
        .btn-primary {
            display: inline-block;
            padding: 8px 15px;
            background-color: #007bff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Debit Note Notification</h2>
            <p>Reference No: <strong>{{ $note->reference_number }}</strong></p>
        </div>

        <table class="details">
            <tr>
                <th>Warehouse</th>
                <td>{{ $note->warehouse->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Department</th>
                <td>{{ $note->department->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Start Date</th>
                <td>{{ $note->start_date }}</td>
            </tr>
            <tr>
                <th>End Date</th>
                <td>{{ $note->end_date }}</td>
            </tr>
            <tr>
                <th>Total Items</th>
                <td>{{ $note->items->count() }}</td>
            </tr>
            <tr>
                <th>Total Amount</th>
                <td>{{ number_format($note->items->sum(fn($i) => $i->stockIssueItem->total_price ?? 0), 4) }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $note->status }}</td>
            </tr>
            <tr>
                <th>Created By</th>
                <td>{{ $note->creator->name ?? '-' }}</td>
            </tr>
        </table>

        <p>Please review the debit note and process accordingly.</p>
        <p>
            <a href="{{ url("/inventory/debit-notes/{$note->id}/show") }}" class="btn-primary">View Debit Note</a>
        </p>

        <div class="footer">
            This is an automated email. Please do not reply.
        </div>
    </div>
</body>
</html>
