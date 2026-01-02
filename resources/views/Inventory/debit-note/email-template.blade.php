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
            max-width: 100%;
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
            text-align: left;
        }
        .footer img {
            max-width: 150px; /* adjust as needed */
            margin-bottom: 10px;
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
            <h2>Monthly Debit Note</h2>
            <p>From Warehouse: <strong>{{ $note->warehouse->name }}</strong></p>
        </div>
        
        <p>Dear <strong>{{ $note->debitNoteEmail->receiver_name ?? '-' }}</strong>,</p>

        <p>I hope this message finds you well.</p>

        <p>
            Please find attached the Monthly Debit Note for 
            <strong>{{ $note->department->short_name ?? '-' }}</strong> 
            for the period from 
            <strong>{{ $note->start_date ? \Carbon\Carbon::parse($note->start_date)->format('M d, Y') : '-' }}</strong> 
            to 
            <strong>{{ $note->end_date ? \Carbon\Carbon::parse($note->end_date)->format('M d, Y') : '-' }}</strong>. 
            This document details all materials requested from stock during the month for operational usage. 
            The debit note includes quantities, item descriptions, and relevant references to help you verify the records efficiently.
        </p>

        <p>Kindly review the attached file at your earliest convenience. Should you have any questions, discrepancies, or require additional supporting information, please do not hesitate to contact me. I am happy to provide clarification or any further documentation needed.</p>

        <p>Thank you for your time and attention to this matter. I appreciate your cooperation and prompt review.</p>
        
        <div class="footer">
            <!-- Footer Image -->
            <p>Best regards,<br>
            {{$note->creator->name ?? '-'}}<br>
            {{$note->creator->defaultPosition->title ?? '-'}}<br>
            {{$note->creator->phone ?? '-'}}<br>
            {{$note->creator->email ?? '-'}}<br>
            </p>
        <img src="https://ci3.googleusercontent.com/mail-sig/AIorK4zsFWN0XTmb1CVNaUS-BqiFPyZpKwge_qnFJ5x7vfn77RaF1FldZ8ebYBrhuszIuQHYxgi8l4BB7ojF" alt="Company Logo" style="max-width: 400px; margin-bottom: 10px;">
        </div>
    </div>
</body>
</html>
