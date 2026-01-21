<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request</title>
    <style>
        body {
            font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 5px 5px;
        }

        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url("{{ asset('fonts/KhmerOSBattambang-Regular.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'TW Cen MT';
            src: url("{{ asset('fonts/TwCenMT.ttf') }}") format('truetype');
        }

        @page {
            size: A4 portrait;
            margin: 5mm;
            @bottom-center {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 10px;
                font-family: 'TW Cen MT', 'Khmer OS Battambang', sans-serif;
            }
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            padding-bottom: 2px;
            min-height: 50px;
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
            margin-bottom: 5px;
        }
        .title-section h3 { margin: 0; font-size: 14px; }
        .sub-title { color: red; font-weight: bold; }

        .form-info, .pr-info { font-size: 10px; }
        .form-info p, .pr-info p { margin: 0; line-height: 1.2; }

        .info-table { width: 100%; border-collapse: collapse; margin-top: 3px; }
        .info-table td { padding: 2px 4px; vertical-align: top; }
        .label { width: 18%; white-space: nowrap; }
        .value {
            display: inline-block;
            border-bottom: 1px dotted #000;
            width: 100%;
            font-weight: bold;
        }

        table.items { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table.items, .items th, .items td { border: 1px solid #333232ff; }
        .items th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
        .items th, .items td { padding: 2px 3px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .note {
            margin-top: 3px;
            font-size: 11px;
            line-height: 1.2;
            border: 1px solid #333232ff;
            padding: 5px;
        }

        .signature-section {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .signature-box {
            flex: 1 1 calc((100% / 3) - 10px);
            max-width: calc((100% / 3) - 10px);
            min-width: 150px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            height: 150px;
            box-sizing: border-box;
        }

        .signature-image-box {
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 3px;
        }
        .signature-image { max-width: 100px; max-height: 70px; }

        .signature-line {
            height: 1px;
            background-color: #333232ff;
            width: 100%;
            margin-bottom: 3px;
        }

        .signature-info { font-size: 10px; line-height: 1.2; }

    </style>
</head>
<body>

@php $pr = $purchaseRequest; @endphp

<div class="header">
    <div class="logo-section">
        <img src="{{ asset('img/logo/logo-dark.png') }}" class="logo" alt="MJQ Logo">
    </div>

    <div class="title-section">
        <h3>សំណើទិញសម្ភារ</h3>
        <h3>Purchase Request</h3>
        <div class="sub-title">{{ $pr['is_urgent'] ? 'Urgent' : '' }}</div>
    </div>

    <div style="position: absolute; right: 0; top: 0; display: flex; flex-direction: column; align-items: flex-end; font-size: 10px;">
        <div class="form-info">
            <p><strong>Code:</strong> MJQE0051</p>
            <p><strong>Version:</strong> 2.0</p>
        </div>
        <div class="pr-info">
            <p>លេខរៀង / PR Number: <strong>{{ $pr['reference_no'] }}</strong></p>
            <p>កាលបរិច្ឆេទស្នើសុំ / Request Date:<strong> {{ $pr['request_date'] }}</strong></p>
            <p>កាលបរិច្ឆេទយក / Deadline: <strong> {{ $pr['deadline_date'] }} </strong></p>
        </div>
    </div>
</div>

<div class="section" style="width: 100%; margin-top: 10px; box-sizing: border-box;">
    <table class="info-table" style="width: 100%; border-collapse: collapse;">
        <tr>
            <!-- Requester Info -->
            <td style="width: 40%; vertical-align: top; padding: 0;">
                <table class="info-table" style="width: 100%; border-collapse: collapse;">
                    @foreach([
                        'ឈ្មោះ / Name' => 'creator_name',
                        'អត្តលេខ / ID' => 'creator_id_card',
                        'នាយកដ្ឋាន / Dept' => 'creator_department',
                        'តួនាទី / Position' => 'creator_position',
                        'ទូរស័ព្ទ / Cell Phone' => 'creator_cellphone'
                    ] as $label => $key)
                        <tr>
                            <td class="label" style="padding: 3px 5px;">{{ $label }}:</td>
                            <td style="padding: 3px 5px;"><span class="value">{{ $pr[$key] }}</span></td>
                        </tr>
                    @endforeach
                </table>
            </td>

            <!-- Purpose -->
            <td style="width: 60%; vertical-align: top; padding-top: 5px;">
                <div >គោលបំណង / Purpose:</div>
                <div style="border:1px solid #333; min-height:96px; padding:2px; box-sizing:border-box;">
                    {{ $pr['purpose'] }}
                </div>
            </td>
        </tr>
    </table>
</div>

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
                <td class="text-center">{{ number_format($item['quantity'],2) }}</td>
                <td class="text-center">{{ $item['unit_name'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'],2) }}</td>
                <td class="text-right">{{ number_format($item['total_price'],2) }}</td>
                <td class="text-center">{{ $item['budget_code_ref'] }}</td>
            </tr>
        @endforeach

        @php
            $emptyCount = 10 - count($pr['items']);
        @endphp
        @if($emptyCount > 0)
            <tr style="height: {{ $emptyCount*40 }}px;">
                @for($i=0;$i<11;$i++)
                    <td>&nbsp;</td>
                @endfor
            </tr>
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td colspan="9" class="text-right"><strong>Total</strong></td>
            <td colspan="2" class="text-right"><strong>$ {{ number_format(collect($pr['items'])->sum('total_price'),2) }}</strong></td>
        </tr>
    </tfoot>
</table>

<div class="note">
    <strong>កំណត់ចំណាំ ៖ </strong>ការដាក់ទម្រង់សំណើទិញសម្ភារនេះ ត្រូវធ្វើឡើងឲ្យបានមុនប្រាំពីរ(២)ថ្ងៃ នៃថ្ងៃធ្វើការ។<br>
    <strong>Notice:</strong> The requisition form must be submitted at least seven (7)-working days ahead of time.<br>
    Be explicit, give accurate description and use catalog reference whenever possible.
</div>

<div class="signature-section">
    <div class="signature-box">
        <strong class="text-center">ស្នើសុំដោយ<br>Requested by</strong>
        <div class="signature-image-box">
            @if (!empty($pr['creator_signature_url']))
                <img src="{{ asset('storage/' . $pr['creator_signature_url']) }}" class="signature-image">
            @endif
        </div>
        <div class="signature-line"></div>
        <div class="signature-info">
            ឈ្មោះ/Name: {{ $pr['creator_name'] }}<br>
            តួនាទី/Position: {{ $pr['creator_position'] }}<br>
            កាលបរិច្ឆេទ/Date: {{ $pr['request_date'] }}
        </div>
    </div>

    @foreach(collect($pr['approvals'])->where('prod_action', '=', 0) as $approval)
        <div class="signature-box">
            <strong class="text-center">
                {!! $approval['request_type_label'] ?? 'Approved By<br>អនុម័តដោយ' !!}
            </strong>
            <div class="signature-image-box">
                @if(($approval['approval_status'] ?? '') === 'Approved' && !empty($approval['user_signature_url']))
                    <img src="{{ asset('storage/' . $approval['user_signature_url']) }}" class="signature-image">
                @endif
            </div>

            <div class="signature-line"></div>

            <div class="signature-info">
                ឈ្មោះ/Name: {{ $approval['name'] ?? '' }}<br>
                តួនាទី/Position: {{ $approval['position_title'] ?? '' }}<br>
                កាលបរិច្ឆេទ/Date: {{ $approval['responded_date']? \Carbon\Carbon::parse($approval['responded_date'])->format('M d, Y') : '' }}
            </div>
        </div>
    @endforeach
</div>
@foreach(collect($pr['approvals'])->where('prod_action', '!=', 0) as $approval)
<strong>
{!! $approval['request_type_label'] ?? "Approved By<br>អនុម័តដោយ" !!}
</strong>:
{{ $approval['name'] ?? '' }}, {{ $approval['position_title'] ?? '' }},
{{ $approval['responded_date'] ? \Carbon\Carbon::parse($approval['responded_date'])->format('M d, Y h:i A') : '' }}
<br><br>
@endforeach

</body>
</html>
