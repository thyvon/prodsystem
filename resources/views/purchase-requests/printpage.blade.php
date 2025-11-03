<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request</title>

    <style>
        /* @page { margin: 1px } */
        body {
            font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 15px 25px;
        }

        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url("{{ storage_path('fonts/KhmerOSbattambang.ttf') }}") format('truetype');
        }

        @font-face {
            font-family: 'TW Cen MT';
            src: url("{{ storage_path('fonts/TwCenMT.ttf') }}") format('truetype');
        }

        .header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 0px; }
        .logo-section { display: flex; align-items: center; gap: 8px; }
        .logo { width: 110px; height: auto; }
        .form-info { font-size: 10px; text-align: right;}
        .form-info p { margin: 0; line-height: 1.4; }
        .pr-info { font-size: 10px; text-align: left; }
        .pr-info p { margin: 0; line-height: 1.4; }
        .title-section { text-align: center; line-height: 1; margin-bottom: 10px; }
        .title-section h3 { margin: 0; font-size: 14px; }
        .sub-title { color: red; font-weight: bold; }
        .section { margin-top: 10px; }
        .info-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .info-table td { padding: 3px 5px; vertical-align: top; }
        .label { width: 18%; white-space: nowrap; }
        .value { border-bottom: 1px dotted #000; width: 30%; display: inline-block; padding: 0 3px; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items, .items th, .items td { border: 1px solid #333232ff; }
        .items th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
        .items th, .items td { padding: 4px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .note { margin-top: 5px; font-size: 12px; line-height: 1.4; border: 1px solid #333232ff; padding: 5px; }
        .signature-section {
            margin-top: 30px;
            display: flex;
            flex-wrap: wrap; /* allows boxes to wrap to next row */
            gap: 20px; /* spacing between boxes */
        }

        .signature-box {
            flex: 1 1 calc((100% / 3) - 20px); /* 3 columns per row */
            max-width: calc((100% / 3) - 20px); /* prevents boxes from exceeding 1/3 width */
            min-width: 150px; /* ensures boxes aren’t too small on mobile */
            text-align: left; /* align text to start */
            margin-bottom: 20px; /* spacing for wrapped rows */
        }

        .signature-line {
            margin-top: 50px;
            height: 1px; /* fixed line thickness */
            background-color: #413f3fff; /* line color */
            width: 100%; /* always match box width */
        }

        .signature-image {
            width: 100px;
            height: auto;
            display: block;
            margin-bottom: 10px;
        }

    </style>
</head>
<body>

@php $pr = $purchaseRequest; @endphp

<div class="header" style="position: relative; width: 100%; padding-bottom: 10px; min-height: 80px;">
    <!-- Left: Logo -->
    <div class="logo-section" style="position: absolute; left: 0; top: 0;">
        <img src="{{ public_path('img/logo/logo-dark.png') }}" class="logo" alt="MJQ Logo">
    </div>

    <!-- Center: Title -->
    <div class="title-section" style="text-align: center; position: absolute; left: 50%; top: 0; transform: translateX(-50%);">
        <h3>សំណើទិញសម្ភារ</h3>
        <h3>Purchase Request</h3>
        <div class="sub-title">{{ $pr['is_urgent'] == 1 ? 'Urgent' : 'Non-Urgent' }}</div>
    </div>

    <!-- Right: Form Info + PR Info stacked -->
    <div style="position: absolute; right: 0; top: 0; display: flex; flex-direction: column; align-items: flex-end; font-size: 10px;">
        <!-- Form Info -->
        <div class="form-info" style="margin-bottom: 5px; text-align: right;">
            <p><strong>Code:</strong> MJQE0051</p>
            <p><strong>Version:</strong> 2.0</p>
        </div>

        <!-- PR Info -->
        <div class="pr-info" style="margin-top: 5px;">
            <p>លេខរៀង / PR Number: <strong>{{ $pr['reference_no'] }}</strong></p>
            <p>កាលបរិច្ឆេទស្នើសុំ / Request Date:<strong> {{ $pr['request_date'] }}</strong></p>
            <p>កាលបរិច្ឆេទយក / Deadline: <strong> {{ $pr['deadline_date'] }} </strong></p>
        </div>
    </div>
</div>


<!-- Centered Requester Info and Purpose section -->
<div class="section" style="width: 100%; margin: 0; padding: 0; box-sizing: border-box;">
    <table class="info-table" style="width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; padding: 0;">
        <tr>
            <!-- Left: Requester Info -->
            <td style="width: 40%; vertical-align: top; padding: 0; margin: 0;">
                <table class="info-table" style="width: 100%; border-collapse: collapse; margin: 0; padding: 0;">
                    @foreach([
                        'ឈ្មោះ / Name' => 'creator_name',
                        'អត្តលេខ / ID' => 'creator_id_card',
                        'នាយកដ្ឋាន / Dept' => 'creator_department',
                        'តួនាទី / Position' => 'creator_position',
                        'ទូរស័ព្ទ / Cell Phone' => 'creator_cellphone'
                    ] as $label => $key)
                        <tr>
                            <td class="label" style="width: 40%; padding: 3px 0;">{{ $label }}:</td>
                            <td style="padding: 3px 0;">
                                <span class="value" style="display: inline-block; border-bottom: 1px dotted #333232ff; width: 100%; font-weight: bold;">
                                    {{ $pr[$key] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>

            <!-- Right: Purpose -->
            <td style="width: 60%; vertical-align: top; padding: 0 0 0 10px; margin: 0;">
                <!-- Label on top -->
                <div style="margin-bottom: 4px;">គោលបំណង / Purpose:</div>
                <!-- Box -->
                <div style="border: 1px solid #333232ff; min-height: 90px; padding: 5px; box-sizing: border-box;">
                    {{ $pr['purpose'] }}
                </div>
            </td>
        </tr>
    </table>
</div>

<!-- Items -->
<table class="items">
    <thead>
        <tr>
            <th>ល.រ<br>No</th>
            <th>លេខកូដ<br>Item Code</th>
            <th>ការពិពណ៌នា<br>Description</th>
            <th>ផ្នែក<br>Division</th>
            <th>នាយកដ្ឋាន<br>Department</th>
            <th>សាខា<br>Campus</th>
            <th>បរិមាណ<br>QTY</th>
            <th>ឯកតា<br>Unit</th>
            <th>តម្លៃឯកតា<br>Unit Price</th>
            <th>តម្លៃសរុប<br>Total Est. Cost</th>
            <th>លេខ<br>Budget Ref.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pr['items'] as $i => $item)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $item['product_code'] }}</td>
                <td>{{ $item['product_description'] }}</td>
                <td class="text-center">{{ $item['division_short_names'] }}</td>
                <td class="text-center">{{ $item['department_short_names'] }}</td>
                <td class="text-center">{{ $item['campus_short_names'] }}</td>
                <td class="text-center">{{ $item['quantity'] }}</td>
                <td class="text-center">{{ $item['unit_name'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'],2) }}</td>
                <td class="text-right">{{ number_format($item['total_price'],2) }}</td>
                <td class="text-center">{{ $item['budget_code_ref'] }}</td>
            </tr>
        @endforeach

@php
    $emptyCount = 5 - count($pr['items']);
    $totalCols = 11; // total number of columns
    $rowHeight = 40; // height of each row
@endphp

@if($emptyCount > 0)
<tr style="height: {{ $emptyCount * $rowHeight }}px;">
    @for($i = 0; $i < $totalCols; $i++)
        <td style="border: 1px solid #000;">&nbsp;</td>
    @endfor
</tr>
@endif


    </tbody>

    <tfoot style="display: table-row-group;">
        <tr>
            <td colspan="9" class="text-right"><strong>Total</strong></td>
            <td colspan="2" class="text-right"><strong>$ {{ number_format(collect($pr['items'])->sum('total_price'),2) }}</strong></td>
        </tr>
    </tfoot>
</table>


<!-- Note -->
<div class="note">
    <strong>កំណត់ចំណាំ ៖ </strong>ការដាក់ទម្រង់សំណើទិញសម្ភារនេះ ត្រូវធ្វើឡើងឲ្យបានមុនប្រាំពីរ(២)ថ្ងៃ នៃថ្ងៃធ្វើការ។<br>
    <strong>Notice:</strong> The requisition form must be submitted at least seven (7)-working days ahead of time.<br>
    Be explicit, give accurate description and use catalog reference whenever possible.
</div>

<!-- Signature Section -->
<div class="signature-section">
    <!-- Requested by -->
    <div class="signature-box">
        @if(!empty($pr['creator_signature_url']))
            <img src="{{ $pr['creator_signature_url'] }}" alt="Signature" class="signature-image">
        @else
            <div class="signature-line"></div>
        @endif

        <strong>Requested by</strong><br>
        Name: {{ $pr['creator_name'] }}<br>
        Position: {{ $pr['creator_position'] }}<br>
        Date: {{ $pr['request_date'] }}
    </div>

    @foreach($pr['approvals'] as $approval)
        <div class="signature-box">
            @if(($approval['approval_status'] ?? '') === 'Approve' && !empty($approval['signature_url']))
                <img src="{{ $approval['signature_url'] }}" alt="Signature" class="signature-image">
            @else
                <div class="signature-line"></div>
            @endif

            <strong>{{ $approval['request_type_label'] ?? 'Approved by' }}</strong><br>
            Name: {{ $approval['name'] ?? '' }}<br>
            Position: {{ $approval['position_title'] ?? '' }}<br>
            Date: {{ $approval['responded_date'] ?? '' }}
        </div>
    @endforeach
</div>

</body>
</html>
