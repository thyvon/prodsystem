<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Stock Report - {{ $warehouse_name ?? '' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style type="text/css">
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
            .page { page-break-after: always; }
        }

        body {
            font-family: 'Khmer OS Battambang', 'Noto Sans Khmer', sans-serif;
            font-size: 10px;
            line-height: 1.3;
            margin: 0;
            padding: 8mm;
            background: #f9f9f9;
        }

        .a4-page {
            width: 297mm;
            min-height: 200mm;
            background: white;
            margin: 0 auto 20px auto;
            padding: 10mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            position: relative;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }
        .logo-section {
            position: absolute;
            left: 5px; top: 5px;
        }
        .logo-section img { width: 120px; height: auto; }
        .title-section h3 { margin: 5px 0; font-size: 22px; font-weight: bold; }
        .date-range { color: red; font-weight: bold; font-size: 12px; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-top: 10px;
        }
        table.items th, table.items td {
            border: 1px solid #333;
            padding: 5px 3px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.items th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }

        td.currency { position: relative; padding-left: 18px; }
        td.currency::before { content: '$'; position: absolute; left: 5px; }

        .page-footer {
            position: absolute;
            bottom: 10px;
            left: 0; right: 0;
            text-align: center;
            font-size: 11px;
            color: #555;
        }

        /* Dynamic page break: prevent row split */
        tr { page-break-inside: avoid; }
        tbody { display: table-row-group; }

        /* Signature Section */
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            page-break-inside: avoid;
        }
        .signature-box {
            flex: 1;
            text-align: center;
            min-width: 160px;
        }
        .signature-box:nth-child(2) { margin: 0 120px; } /* Your middle spacing */
        .signature-image {
            max-width: 130px;
            max-height: 90px;
            object-fit: contain;
            margin: 10px 0;
        }
        .signature-title {
            font-weight: bold;
            font-size: 12px;
            line-height: 1.4;
        }
    </style>

    <!-- Load Khmer font from Google (works offline if cached) -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&family=Khmer&display=swap" rel="stylesheet">
</head>
<body>

<div id="all-pages"></div>

<!-- Main Content (will be split dynamically) -->
<div id="content" style="display: none;">
    <div class="header">
        <div class="logo-section">
            <img src="{{ public_path('img/logo/logo-dark.png') }}" alt="Logo">
        </div>
        <div class="title-section">
            <h3>Stock Report</h3>
            <h3>
                @if(!empty($warehouse_name))
                    <small style="font-weight:normal; color:#555;">({{ $warehouse_name }})</small>
                @endif
            </h3>
            <div class="date-range">{{ \Carbon\Carbon::parse($report_date)->format('d M Y') }}</div>
        </div>
    </div>

    <table class="items" id="report-table">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th style="width:80px;">Item Code</th>
                <th>Description</th>
                <th style="width:50px;">UoM</th>
                <th style="width:70px;">Unit Price</th>
                <th style="width:60px;">6M Avg</th>
                <th style="width:60px;">Last M</th>
                <th style="width:60px;">Begin</th>
                <th style="width:60px;">Order Plan</th>
                <th style="width:60px;">Forecast</th>
                <th style="width:60px;">Ending</th>
                <th style="width:60px;">Cover Day</th>
                <th style="width:60px;">Target SS</th>
                <th style="width:70px;">Value</th>
                <th style="width:60px;">Reorder Qty</th>
                <th style="width:60px;">Reorder Lvl</th>
                <th style="width:60px;">Max Level</th>
                <th style="width:60px;">Max Day</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item['product_code'] }}</td>
                <td class="text-left">{!! nl2br(e($item['description'] ?? '-')) !!}</td>
                <td>{{ $item['unit_name'] ?? '-' }}</td>
                <td class="currency">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-right">{{ $item['avg_6_month_usage'] > 0 ? number_format($item['avg_6_month_usage']) : '-' }}</td>
                <td class="text-right">{{ $item['last_month_usage'] > 0 ? number_format($item['last_month_usage']) : '-' }}</td>
                <td class="text-right">{{ $item['stock_beginning'] > 0 ? number_format($item['stock_beginning']) : '-' }}</td>
                <td class="text-right">{{ $item['order_plan_qty'] > 0 ? number_format($item['order_plan_qty']) : '-' }}</td>
                <td class="text-right">{{ $item['demand_forecast'] > 0 ? number_format($item['demand_forecast']) : '-' }}</td>
                <td class="text-right">{{ $item['stock_ending'] > 0 ? number_format($item['stock_ending']) : '-' }}</td>
                <td class="text-right">{{ $item['ending_stock_cover_day'] > 0 ? number_format($item['ending_stock_cover_day']) : '-' }}</td>
                <td class="text-right">{{ $item['target_safety_stock_day'] > 0 ? number_format($item['target_safety_stock_day']) : '-' }}</td>
                <td class="currency">{{ number_format($item['stock_value'], 2) }}</td>
                <td class="text-right">{{ $item['inventory_reorder_quantity'] > 0 ? number_format($item['inventory_reorder_quantity']) : '-' }}</td>
                <td class="text-right">{{ $item['reorder_level_qty'] > 0 ? number_format($item['reorder_level_qty']) : '-' }}</td>
                <td class="text-right">{{ $item['max_inventory_level_qty'] > 0 ? number_format($item['max_inventory_level_qty']) : '-' }}</td>
                <td class="text-right">{{ $item['max_inventory_usage_day'] > 0 ? number_format($item['max_inventory_usage_day']) : '-' }}</td>
                <td class="text-left">{!! nl2br(e($item['remarks'] ?? '-')) !!}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Signature Section (will be added to last page) -->
    <div id="signature-section" style="margin-top: 40px; page-break-inside: avoid;">
        @if(!empty($remarks))
            <p><strong>Remarks:</strong> {{ $remarks }}</p>
        @endif

        <div class="signature-section">
            @if(!empty($prepared_by))
            <div class="signature-box">
                <div class="signature-title">ស្នើសុំដោយ<br>Requested By</div>
                @if($creator_signature)
                    <img src="{{ public_path('storage/' . $creator_signature) }}" class="signature-image">
                @endif
                <br><br>
                <strong>{{ $prepared_by }}</strong><br>
                {{ $creator_position ?? '' }}<br>
                Date: {{ $created_at ?? now()->format('d M Y') }}
            </div>
            @endif

            @foreach($approvals as $appr)
                @if(!empty($appr['user_name']))
                <div class="signature-box">
                    <div class="signature-title">
                        {{ $appr['request_type_label_kh'] ?? 'Approved By' }}<br>
                        {{ $appr['request_type_label_en'] ?? 'Approved By' }}
                    </div>
                    @if($appr['approval_status'] === 'Approved' && !empty($appr['signature_url']))
                        <img src="{{ public_path('storage/' . $appr['signature_url']) }}" class="signature-image">
                    @endif
                    <br><br>
                    <strong>{{ $appr['user_name'] }}</strong><br>
                    {{ $appr['position_name'] ?? '' }}<br>
                    Date: {{ $appr['responded_date'] ?? '' }}
                </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

<!-- Dynamic Page Splitting Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const content = document.getElementById("content");
    const table = document.getElementById("report-table");
    const rows = Array.from(table.querySelectorAll("tbody tr"));
    const headerHtml = document.querySelector(".header").outerHTML;
    const signatureHtml = document.getElementById("signature-section").outerHTML;

    const PAGE_HEIGHT = 680; // pixels available per page (A4 landscape ≈ 680px after margins)
    let currentHeight = 0;
    let pageCount = 0;
    let currentPage = null;

    function createNewPage() {
        pageCount++;
        const page = document.createElement("div");
        page.className = "a4-page page";
        page.innerHTML = headerHtml +
            `<table class="items">
                <thead><tr>${table.querySelector("thead tr").innerHTML}</tr></thead>
                <tbody></tbody>
            </table>`;
        document.getElementById("all-pages").appendChild(page);

        // Add footer
        const footer = document.createElement("div");
        footer.className = "page-footer";
        footer.innerHTML = `Page ${pageCount} of <span id="total-pages">${pageCount}</span>`;
        page.appendChild(footer);

        currentPage = page;
        currentHeight = 180; // header + thead height
        return page.querySelector("tbody");
    }

    let tbody = createNewPage();

    rows.forEach((row, index) => {
        const rowHeight = row.offsetHeight || 40;
        if (currentHeight + rowHeight > PAGE_HEIGHT && rows.length > 10) {
            tbody = createNewPage();
        }
        tbody.appendChild(row.cloneNode(true));
        currentHeight += rowHeight;
    });

    // Add signature to last page
    currentPage.insertAdjacentHTML("beforeend", signatureHtml);

    // Update total pages
    document.querySelectorAll("#total-pages").forEach(el => el.textContent = pageCount);

    // Show result
    document.getElementById("content").style.display = "none";
    document.getElementById("all-pages").style.display = "block";
});
</script>

<!-- Print Button -->
<div class="no-print" style="text-align:center; padding:30px; background:#f0f0f0;">
    <button onclick="window.print()" style="padding:15px 30px; font-size:18px; cursor:pointer;">
        Print or Save as PDF
    </button>
</div>

</body>
</html>