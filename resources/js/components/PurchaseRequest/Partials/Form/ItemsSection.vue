<template>
  <div class="border rounded p-3 mb-4">
    <div class="form-row mb-3">
      <div class="form-group col-md-4">
        <label class="font-weight-bold">📥 Import Items</label>
        <div class="input-group">
          <input
            type="file"
            class="d-none"
            ref="importFileInput"
            accept=".xlsx,.xls,.csv"
          />

          <button
            type="button"
            class="btn btn-outline-secondary flex-fill"
            @click="importFileInput?.click()"
          >
            Choose file
          </button>

          <button
            type="button"
            class="btn btn-primary ml-2"
            @click="$emit('import')"
            :disabled="isImporting || !importFileInput?.files?.length"
          >
            <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
            Import
          </button>

          <a
            class="btn btn-success ml-2"
            href="/sampleExcel/purchase_request_item_sample.xlsx"
            download
          >
            <i class="fal fa-file-excel"></i>
          </a>
        </div>
      </div>

      <div class="form-group col-md-8">
        <label class="font-weight-bold">➕ Add Product</label>
        <button
          type="button"
          class="btn btn-primary btn-block"
          @click="$emit('open-products')"
          :disabled="isLoadingProducts"
        >
          <span v-if="isLoadingProducts" class="spinner-border spinner-border-sm mr-2"></span>
          <i class="fal fa-plus"></i> Select Product
        </button>
      </div>
    </div>

    <h5 class="font-weight-bold mb-3 text-primary">
      📦 Items ({{ form.items.length }})
      <span v-if="totalAmount" class="badge badge-primary ml-2">{{ totalAmount }}</span>
    </h5>

    <div class="table-responsive">
      <table id="itemsDataTable" class="table table-bordered table-striped table-sm table-hover mb-0">
        <thead style="background: #1E90FF; text-align: center;">
          <tr>
            <th style="width: 120px;">Item Code</th>
            <th style="width: 200px;">Description</th>
            <th style="width: 140px;">Remarks</th>
            <th style="width: 60px;">UoM</th>
            <th style="width: 90px;">Currency</th>
            <th style="width: 100px;">Ex. Rate</th>
            <th style="width: 80px;">Qty</th>
            <th style="width: 100px;">Price</th>
            <th style="width: 100px;">Value USD</th>
            <th style="width: 120px;">Budget</th>
            <th style="width: 100px;">Campus</th>
            <th style="width: 100px;">Dept</th>
            <th style="width: 60px;">Action</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'

const props = defineProps({
  form: { type: Object, required: true },
  totalAmount: { type: String, default: null },
  isImporting: { type: Boolean, required: true },
  isLoadingProducts: { type: Boolean, required: true },
  formatItemValueUsd: { type: Function, required: true },
})

const emit = defineEmits(['import', 'open-products', 'remove-item'])

// ═══════════════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════════════
const importFileInput = ref(null)
let itemsDataTable = null
let storedSelectData = { budgetCodes: [], campuses: [], departments: [] }

// ═══════════════════════════════════════════════════════════════════════════
// DATATABLE INITIALIZATION
// ═══════════════════════════════════════════════════════════════════════════
const initializeItemsDataTable = () => {
  const table = $('#itemsDataTable')

  if (itemsDataTable) {
    itemsDataTable.destroy()
  }

  itemsDataTable = table.DataTable({
    data: transformedItems(),
    responsive: true,
    autoWidth: false,
    paging: true,
    pageLength: 10,
    searching: true,
    ordering: true,
    info: true,
    columnDefs: [{ targets: 12, orderable: false, searchable: false }],
    columns: [
      { data: 'product_code', title: 'Item Code' },
      { data: 'product_description', title: 'Description' },
      { data: 'description', title: 'Remarks', render: renderRemarks },
      { data: 'unit_name', title: 'UoM' },
      { data: 'currency', title: 'Currency', render: renderCurrency },
      { data: 'exchange_rate', title: 'Ex. Rate', render: renderExchangeRate },
      { data: 'quantity', title: 'Qty', render: renderQuantity },
      { data: 'unit_price', title: 'Price', render: renderPrice },
      { data: null, title: 'Value USD', render: renderValueUSD },
      { data: 'budget_code_id', title: 'Budget', render: renderBudget },
      { data: null, title: 'Campus', render: renderCampus },
      { data: null, title: 'Dept', render: renderDepartment },
      { data: null, title: 'Action', render: renderAction, className: 'text-center' }
    ],
    drawCallback: () => {
      attachEventHandlers()
      if (storedSelectData.budgetCodes.length > 0) {
        initializeSelect2ForItems(storedSelectData.budgetCodes, storedSelectData.campuses, storedSelectData.departments)
      }
    }
  })
}

// ═══════════════════════════════════════════════════════════════════════════
// DATATABLE RENDER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════
const transformedItems = () => {
  return props.form.items.map((item, index) => ({ ...item, _index: index }))
}
const renderRemarks = (data, type, row) => {
  return `<textarea class="form-control form-control-sm remarks-input" data-index="${row._index}">${data || ''}</textarea>`
}

const renderCurrency = (data, type, row) => {
  return `<select class="form-control form-control-sm currency-select" data-index="${row._index}">
    <option value="">Select</option>
    <option value="USD" ${data === 'USD' ? 'selected' : ''}>USD</option>
    <option value="KHR" ${data === 'KHR' ? 'selected' : ''}>KHR</option>
  </select>`
}

const renderExchangeRate = (data, type, row) => {
  return `<input type="number" step="0.01" min="0" class="form-control form-control-sm exchange-rate-input" data-index="${row._index}" value="${data || ''}" />`
}

const renderQuantity = (data, type, row) => {
  return `<input type="number" class="form-control form-control-sm quantity-input" data-index="${row._index}" value="${data || 0}" />`
}

const renderPrice = (data, type, row) => {
  return `<input type="number" class="form-control form-control-sm price-input" data-index="${row._index}" value="${data || 0}" />`
}

const renderValueUSD = (data, type, row) => {
  return `<input type="text" class="form-control form-control-sm" value="${props.formatItemValueUsd(row)}" readonly />`
}

const renderBudget = (_, __, row) => {
  return `<select class="form-control form-control-sm budget-select" data-index="${row._index}"></select>`
}

const renderCampus = (data, type, row) => {
  return `<select multiple class="form-control form-control-sm campus-select" data-index="${row._index}"></select>`
}

const renderDepartment = (data, type, row) => {
  return `<select multiple class="form-control form-control-sm department-select" data-index="${row._index}"></select>`
}

const renderAction = (data, type, row) => {
  return `<button class="btn btn-danger btn-sm remove-item-btn" data-index="${row._index}">
    <i class="fal fa-trash"></i>
  </button>`
}

// ═══════════════════════════════════════════════════════════════════════════
// EVENT HANDLERS
// ═══════════════════════════════════════════════════════════════════════════
const attachEventHandlers = () => {
  const tbody = $('#itemsDataTable tbody')
  const items = props.form.items

  tbody
    .off('change', '.remarks-input')
    .on('change', '.remarks-input', function() {
      const index = $(this).data('index')
      if (items[index]) items[index].description = $(this).val()
    })

  tbody
    .off('change', '.currency-select')
    .on('change', '.currency-select', function() {
      const index = $(this).data('index')
      if (items[index]) items[index].currency = $(this).val()
    })

  tbody
    .off('change', '.exchange-rate-input')
    .on('change', '.exchange-rate-input', function() {
      const index = $(this).data('index')
      if (items[index]) items[index].exchange_rate = Number($(this).val())
    })

  tbody
    .off('change', '.quantity-input')
    .on('change', '.quantity-input', function() {
      const index = $(this).data('index')
      if (items[index]) items[index].quantity = Number($(this).val())
    })

  tbody
    .off('change', '.price-input')
    .on('change', '.price-input', function() {
      const index = $(this).data('index')
      if (items[index]) items[index].unit_price = Number($(this).val())
    })

  tbody
    .off('click', '.remove-item-btn')
    .on('click', '.remove-item-btn', function(e) {
      e.preventDefault()
      const index = $(this).data('index')
      emit('remove-item', index)
    })
}

// ═══════════════════════════════════════════════════════════════════════════
// SELECT2 INITIALIZATION
// ═══════════════════════════════════════════════════════════════════════════
const initializeSelect2ForItems = (budgetCodes, campuses, departments) => {
  storedSelectData = { budgetCodes, campuses, departments }
  const items = props.form.items

  // Budget selects
  $('#itemsDataTable tbody .budget-select').each(function() {
    const index = $(this).data('index')
    const select = $(this)
    if (!items[index]) return

    if (select.hasClass('select2-hidden-accessible')) {
      select.select2('destroy')
    }

    select.html(budgetCodes.map(b => `<option value="${b.id}">${b.code}</option>`).join(''))
      .select2({ width: '100%', allowClear: false, placeholder: 'Select Budget' })
      .val(String(items[index].budget_code_id))
      .trigger('change')
      .off('change')
      .on('change', function() {
        items[index].budget_code_id = Number($(this).val()) || null
      })
  })

  // Campus selects
  $('#itemsDataTable tbody .campus-select').each(function() {
    const index = $(this).data('index')
    const select = $(this)
    if (!items[index]) return

    if (select.hasClass('select2-hidden-accessible')) {
      select.select2('destroy')
    }

    const selectedIds = items[index].campus_ids || []
    select.html(campuses.map(c => `<option value="${c.id}">${c.text}</option>`).join(''))
      .select2({ width: '100%', multiple: true, allowClear: true, placeholder: 'Select Campus' })
      .val(selectedIds.map(String))
      .trigger('change')
      .off('change')
      .on('change', function() {
        items[index].campus_ids = ($(this).val() || []).map(Number)
      })
  })

  // Department selects
  $('#itemsDataTable tbody .department-select').each(function() {
    const index = $(this).data('index')
    const select = $(this)
    if (!items[index]) return

    if (select.hasClass('select2-hidden-accessible')) {
      select.select2('destroy')
    }

    const selectedIds = items[index].department_ids || []
    select.html(departments.map(d => `<option value="${d.id}">${d.text}</option>`).join(''))
      .select2({ width: '100%', multiple: true, allowClear: true, placeholder: 'Select Department' })
      .val(selectedIds.map(String))
      .trigger('change')
      .off('change')
      .on('change', function() {
        items[index].department_ids = ($(this).val() || []).map(Number)
      })
  })
}

// ═══════════════════════════════════════════════════════════════════════════
// WATCHERS & LIFECYCLE
// ═══════════════════════════════════════════════════════════════════════════
watch(
  () => props.form.items,
  () => {
    if (itemsDataTable) {
      nextTick(() => {
        itemsDataTable.clear()
        itemsDataTable.rows.add(transformedItems())
        itemsDataTable.draw(false)
      })
    }
  },
  { deep: true }
)

onMounted(async () => {
  await nextTick()
  initializeItemsDataTable()
})

defineExpose({ importFileInput, initializeSelect2ForItems })
</script>
