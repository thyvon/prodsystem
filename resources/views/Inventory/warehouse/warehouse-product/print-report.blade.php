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
                margin: 8mm !important;
            }
            html, body {
                width: 297mm;
                height: 210mm;
                margin: 0 !important;
                padding: 0 !important;
            }
            .no-print { display: none !important; }
            .page { page-break-after: always; page-break-inside: avoid; }
        }

        body {
            font-family: 'Khmer OS Battambang', 'Noto Sans Khmer', 'TW Cen MT', sans-serif;
            font-size: 10.5px;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            color: #000;
        }

        .a4-page {
            width: 297mm;
            min-height: 210mm;
            background: white;
            margin: 15px auto;
            padding: 10mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
            position: relative;
            box-sizing: border-box;
            page-break-after: always;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            position: relative;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .logo-section {
            position: absolute;
            left: 8px;
            top: 8px;
        }
        .logo-section img {
            width: 120px;
            height: auto;
        }
        .title-section h3 {
            margin: 8px 0;
            font-size: 24px;
            font-weight: bold;
        }
        .date-range {
            color: red;
            font-weight: bold;
            font-size: 13px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-top: 12px;
        }
        table.items th, table.items td {
            border: 1px solid #333;
            padding: 6px 4px;
            vertical-align: middle;
            word-wrap: break-word;
            text-align: center;
        }
        table.items th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }

        td.currency {
            position: relative;
            padding-left: 20px !important;
        }
        td.currency::before {
            content: '$';
            position: absolute;
            left: 6px;
            font-weight: normal;
        }

        .page-footer {
            position: absolute;
            bottom: 12px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
            color: #555;
            font-family: 'TW Cen MT', sans-serif;
        }

        /* Never split a row */
        tr { page-break-inside: avoid; }

        /* Signature Section */
        .signature-section {
            margin-top: 45px;
            display: flex;
            justify-content: space-between;
            gap: 25px;
            page-break-inside: avoid;
        }
        .signature-box {
            flex: 1;
            text-align: center;
            min-width: 160px;
        }
        .signature-box:nth-child(2) {
            margin: 0 130px;
        }
        .signature-image {
            max-width: 140px;
            max-height: 95px;
            object-fit: contain;
            margin: 12px 0;
        }
        .signature-title {
            font-weight: bold;
            font-size: 13px;
            line-height: 1.4;
        }
    </style>

    <!-- Perfect Khmer Font -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&family=Khmer&display=swap" rel="stylesheet">
</head>
<body>

<div id="pages"></div>

<script>
// All data from Laravel
const items = @json($items);
const reportData = {
    warehouse_name: "{{ addslashes($warehouse_name ?? '') }}",
    report_date: "{{ \Carbon\Carbon::parse($report_date)->format('d M Y') }}",
    prepared_by: "{{ addslashes($prepared_by ?? '') }}",
    creator_position: "{{ addslashes($creator_position ?? '') }}",
    creator_signature: "{{ $creator_signature ? public_path('storage/' . $creator_signature) : '' }}",
    created_at: "{{ $created_at ?? now()->format('d M Y') }}",
    remarks: {!! json_encode($remarks ?? '') !!},
    approvals: @json($approvals)
};

const PAGE_HEIGHT = 720; // pixels available per page
let currentHeight = 0;
let pageCount = 0;
let currentPage = null;
let currentTbody = null;

function createPage() {
    pageCount++;
    const page = document.createElement("div");
    page.className = "a4-page page";
    page.innerHTML = `
        <div class="header">
            <div class="logo-section">
                <img src="{{ public_path('img/logo/logo-dark.png') }}" alt="Logo">
            </div>
            <div class="title-section">
                <h3>Stock Report</h3>
                ${reportData.warehouse_name ? `<h3><small style="font-weight:normal;color:#555;">(${reportData.warehouse_name})</small></h3>` : ''}
                <div class="date-range">${reportData.report_date}</div>
            </div>
        </div>

        <table class="items">
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
            <tbody></tbody>
        </table>

        <div class="page-footer">Page ${pageCount} of <span class="total-pages">${pageCount}</span></div>
    `;
    document.getElementById("pages").appendChild(page);
    currentPage = page;
    currentTbody = page.querySelector("tbody");
    currentHeight = 220; // header + thead
    return currentTbody;
}

let tbody = createPage();

items.forEach((item, i) => {
    const row = document.createElement("tr");
    row.innerHTML = `
        <td>${i + 1}</td>
        <td>${item.product_code || ''}</td>
        <td class="text-left">${(item.description || '-').replace(/\n/g, '<br>')}</td>
        <td>${item.unit_name || '-'}</td>
        <td class="currency">${parseFloat(item.unit_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        <td class="text-right">${item.avg_6_month_usage > 0 ? Number(item.avg_6_month_usage).toLocaleString() : '-'}</td>
        <td class="text-right">${item.last_month_usage > 0 ? Number(item.last_month_usage).toLocaleString() : '-'}</td>
        <td class="text-right">${item.stock_beginning > 0 ? Number(item.stock_beginning).toLocaleString() : '-'}</td>
        <td class="text-right">${item.order_plan_qty > 0 ? Number(item.order_plan_qty).toLocaleString() : '-'}</td>
        <td class="text-right">${item.demand_forecast > 0 ? Number(item.demand_forecast).toLocaleString() : '-'}</td>
        <td class="text-right">${item.stock_ending > 0 ? Number(item.stock_ending).toLocaleString() : '-'}</td>
        <td class="text-right">${item.ending_stock_cover_day > 0 ? Number(item.ending_stock_cover_day).toFixed(1) : '-'}</td>
        <td class="text-right">${item.target_safety_stock_day > 0 ? Number(item.target_safety_stock_day).toLocaleString() : '-'}</td>
        <td class="currency">${parseFloat(item.stock_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        <td class="text-right">${item.inventory_reorder_quantity > 0 ? Number(item.inventory_reorder_quantity).toLocaleString() : '-'}</td>
        <td class="text-right">${item.reorder_level_qty > 0 ? Number(item.reorder_level_qty).toLocaleString() : '-'}</td>
        <td class="text-right">${item.max_inventory_level_qty > 0 ? Number(item.max_inventory_level_qty).toLocaleString() : '-'}</td>
        <td class="text-right">${item.max_inventory_usage_day > 0 ? Number(item.max_inventory_usage_day).toLocaleString() : '-'}</td>
        <td class="text-left">${(item.remarks || '-').replace(/\n/g, '<br>')}</td>
    `;

    const temp = document.createElement("div");
    temp.appendChild(row);
    const rowHeight = row.offsetHeight || 48;

    if (currentHeight + rowHeight > PAGE_HEIGHT && items.length > 15) {
        tbody = createPage();
    }

    tbody.appendChild(row);
    currentHeight += rowHeight;
});

// Add Remarks + Signatures to LAST PAGE only
currentPage.insertAdjacentHTML("beforeend", `
    ${reportData.remarks ? `<p style="margin-top:40px; font-size:11px;"><strong>Remarks:</strong> ${reportData.remarks}</p>` : ''}

    <div class="signature-section">
        ${reportData.prepared_by ? `
        <div class="signature-box">
            <div class="signature-title">ស្នើសុំដោយ<br>Requested By</div>
            ${reportData.creator_signature ? `<img src="${reportData.creator_signature}" class="signature-image">` : ''}
            <br><br>
            <strong>${reportData.prepared_by}</strong><br>
            ${reportData.creator_position || ''}<br>
            Date: ${reportData.created_at}
        </div>` : ''}

        ${reportData.approvals.map(appr => appr && appr.user_name ? `
        <div class="signature-box">
            <div class="signature-title">
                ${appr.request_type_label_kh || 'Approved By'}<br>
                ${appr.request_type_label_en || 'Approved By'}
            </div>
            ${appr.approval_status === 'Approved' && appr.signature_url ? 
                `<img src="${public_path('storage/' + appr.signature_url)}" class="signature-image">` : ''}
            <br><br>
            <strong>${appr.user_name}</strong><br>
            ${appr.position_name || ''}<br>
            Date: ${appr.responded_date || ''}
        </div>` : '').join('')}
    </div>
`);

// Update total pages
document.querySelectorAll(".total-pages").forEach(el => el.textContent = pageCount);
</script>

<!-- Print Button -->
<div class="no-print" style="text-align:center; padding:40px; background:#f0f0f0;">
    <button onclick="window.print()" style="padding:18px 50px; font-size:20px; background:#007bff; color:white; border:none; border-radius:8px; cursor:pointer;">
        Print or Save as PDF
    </button>
</div>

</body>
</html>