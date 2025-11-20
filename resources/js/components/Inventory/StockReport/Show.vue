<template>
  <div class="card mb-0 shadow">
    <!-- Header -->
    <div class="card-header bg-light py-2 d-flex justify-between items-center">
      <button class="btn btn-sm btn-outline-success" @click="goBack">
        <i class="fal fa-backward"></i> Back
      </button>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" @click="fetchStockReport">
          <i class="fal fa-sync"></i> Refresh
        </button>
        <button class="btn btn-sm btn-outline-primary" @click="openPdfModal">
          <i class="fal fa-print"></i> Print PDF
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="card-body p-3" style="font-family: 'TW Cen MT', 'Khmer OS Battambang';">
      <h4 class="text-center font-weight-bold mb-3">Monthly Stock Report</h4>
      <h6 class="text-center text-muted mb-4">
        {{ formatDate(reportParams.start_date) }} - {{ formatDate(reportParams.end_date) }}
        ({{ warehouseNames }})
      </h6>

      <div class="table-responsive">
        <table class="items">
          <colgroup>
            <col class="col-no"><col class="col-code"><col class="col-desc"><col class="col-unit">
            <col class="col-qty"><col class="col-amount"><col class="col-qty"><col class="col-amount">
            <col class="col-qty"><col class="col-amount"><col class="col-qty"><col class="col-amount">
            <col class="col-qty"><col class="col-amount"><col class="col-avg">
          </colgroup>

          <thead>
            <tr>
              <th rowspan="2">#</th>
              <th rowspan="2">Item Code</th>
              <th rowspan="2">Description</th>
              <th rowspan="2">Unit</th>
              <th colspan="2">Beginning</th>
              <th colspan="2">Stock In</th>
              <th colspan="2" class="highlight-available">Available</th>
              <th colspan="2">Stock Out</th>
              <th colspan="2">Ending</th>
              <th rowspan="2">Avg Price</th>
            </tr>
            <tr>
              <th>Qty</th><th>Amount</th>
              <th>Qty</th><th>Amount</th>
              <th class="highlight-available">Qty</th><th class="highlight-available">Amount</th>
              <th>Qty</th><th>Amount</th>
              <th>Qty</th><th>Amount</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(item, i) in stockItems" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td class="text-center">{{ item.item_code }}</td>
              <td class="pl-1">{{ item.description || item.product_name || '-' }}</td>
              <td class="text-center">{{ item.unit_name }}</td>

              <td class="text-center">{{ format(item.beginning_quantity) }}</td>
              <td class="text-end">{{ format(item.beginning_total) }}</td>
              <td class="text-center">{{ format(item.stock_in_quantity) }}</td>
              <td class="text-end">{{ format(item.stock_in_total) }}</td>
              <td class="text-center available-cell">{{ format(item.available_quantity) }}</td>
              <td class="text-end available-cell">{{ format(item.available_total) }}</td>
              <td class="text-center">{{ format(item.stock_out_quantity) }}</td>
              <td class="text-end">{{ format(item.stock_out_total) }}</td>
              <td class="text-center">{{ format(item.ending_quantity) }}</td>
              <td class="text-end">{{ format(item.ending_total) }}</td>
              <td class="text-end">{{ format(item.average_price) }}</td>
            </tr>

            <tr class="total-row">
              <td colspan="4" class="text-end"><strong>Total</strong></td>
              <td class="text-center"><strong>{{ format(total('beginning_quantity')) }}</strong></td>
              <td class="text-end"><strong>{{ format(total('beginning_total')) }}</strong></td>
              <td class="text-center"><strong>{{ format(total('stock_in_quantity')) }}</strong></td>
              <td class="text-end"><strong>{{ format(total('stock_in_total')) }}</strong></td>
              <td class="text-center available-cell"><strong>{{ format(total('available_quantity')) }}</strong></td>
              <td class="text-end available-cell"><strong>{{ format(total('available_total')) }}</strong></td>
              <td class="text-center"><strong>{{ format(total('stock_out_quantity')) }}</strong></td>
              <td class="text-end"><strong>{{ format(total('stock_out_total')) }}</strong></td>
              <td class="text-center"><strong>{{ format(total('ending_quantity')) }}</strong></td>
              <td class="text-end"><strong>{{ format(total('ending_total')) }}</strong></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <StockReportModal ref="pdfViewer" title="Monthly Stock Report PDF" />
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import axios from 'axios'
import StockReportModal from '@/components/Reusable/StockReportModal.vue'
import { formatDateShort } from '@/Utils/dateFormat'
import { showAlert } from '@/Utils/bootbox'

const props = defineProps({ monthlyStockReportId: Number })

const stockItems = ref([])
const reportParams = ref({ start_date: '', end_date: '', warehouse_ids: [] })
const warehouseNames = ref('All Warehouses')
const pdfViewer = ref(null)

const format = val => (!val || Number(val) === 0 ? '-' : Number(val).toLocaleString(undefined, { minimumFractionDigits: 2 }))
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()

const fetchStockReport = async () => {
  try {
    const res = await axios.get(`/api/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/details`)
    Object.assign(reportParams.value, res.data)
    warehouseNames.value = res.data.warehouse_names || 'All Warehouses'
    stockItems.value = res.data.report || []

    await nextTick()
    setStickyHeaderHeight()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to load stock report.', 'danger')
  }
}

const total = key => stockItems.value.reduce((sum, i) => sum + (i[key] || 0), 0)
const openPdfModal = () => pdfViewer.value.open(`/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/show`, {})

const setStickyHeaderHeight = () => {
  const firstRow = document.querySelector('.items thead tr:first-child')
  if (firstRow) {
    document.querySelector('.items').style.setProperty('--first-row-height', `${firstRow.offsetHeight}px`)
  }
}

onMounted(() => {
  fetchStockReport()
  window.addEventListener('resize', setStickyHeaderHeight)
})
</script>

<style scoped>
.items { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
.items th, .items td { border: 1px solid #333; vertical-align: middle; word-wrap: break-word; font-size: 11px; }
.items thead th { background: #f2f2f2; text-align: center; font-weight: bold; position: sticky; z-index: 3; }
.items thead tr:first-child th { top: 0; z-index: 4; }
.items thead tr:nth-child(2) th { top: var(--first-row-height,28px); z-index: 3; }

.col-no { width:4%; } .col-code { width:8%; } .col-desc { width:25%; } .col-unit { width:5%; }
.col-qty { width:7%; } .col-amount { width:9%; } .col-avg { width:9%; }

.text-center { text-align:center !important; }
.text-end { text-align:right !important; }

.highlight-available th,.highlight-available td{background:#e6f7e6 !important;}
.available-cell { background:#f8fff8 !important; }

.total-row td { font-weight:bold; background:#f0f0f0; font-size:12px; }
.table-responsive { max-height:600px; overflow-y:auto; position:relative; }
.total-row { position:sticky; bottom:0; z-index:2; }
.pl-1 { padding-left:5px; }
</style>
