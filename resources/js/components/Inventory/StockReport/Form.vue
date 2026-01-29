<template>
    <div class="container-fluid">
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm">
          <!-- Header -->
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ isEditMode ? 'Edit Monthly Stock Report' : 'Create Monthly Stock Report' }}</h4>
            <button type="button" class="btn btn-outline-light btn-sm" @click="goToList">Back</button>
          </div>

          <div class="card-body">
            <!-- Dates & Reference -->
            <div class="row mb-4">
              <div class="col-md-3">
                <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
                <input id="start_date" type="text" class="form-control" readonly required />
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">End Date <span class="text-danger">*</span></label>
                <input id="end_date" type="text" class="form-control" readonly required />
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">Reference No</label>
                <input type="text" class="form-control" :value="form.reference_no || 'Auto-generated'" readonly />
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">Warehouses <span class="text-danger">*</span></label>
                <select id="warehouseSelect" multiple class="form-control"></select>
              </div>
            </div>

            <datatable
              ref="datatableRef"
              :headers="datatableHeaders"
              :fetch-url="datatableFetchUrl"
              :fetch-params="datatableParams"
              :options="datatableOptions"
              :scrollable="true"
              @sort-change="handleSortChange"
              @page-change="handlePageChange"
              @length-change="handleLengthChange"
              @search-change="handleSearchChange"
              >
              </datatable>

            <!-- Warehouses & Remarks -->
            <div class="row mt-4 mb-4">
              <div class="col-md-12">
                <label class="form-label fw-bold">Remarks</label>
                <textarea v-model="form.remarks" class="form-control" rows="5" placeholder="Optional remarks..."></textarea>
              </div>
            </div>

            <!-- Approval Table -->
            <div class="border rounded p-4 bg-light">
              <h5 class="h5 fw-bold text-primary mb-3">Approval Assignments</h5>

              <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                  <thead class="table-light">
                    <tr>
                      <th width="35%">Approval Type</th>
                      <th width="55%">Assigned User</th>
                      <th width="10%" class="text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(approval, index) in form.approvals" :key="index">
                      <td>
                        <select
                          :ref="el => setTypeSelectRef(el, index)"
                          class="form-control form-control-sm"
                          :disabled="approval.isDefault"
                          v-model="approval.request_type"
                        >
                          <option value="">Select Type</option>
                          <option value="initial">Initial</option>
                          <option value="verify">Verify</option>
                          <option value="check">Check</option>
                          <option value="acknowledge">Acknowledge</option>
                        </select>
                      </td>
                      <td>
                        <select
                          :ref="el => setUserSelectRef(el, index)"
                          class="form-control form-control-sm"
                          :disabled="!approval.request_type"
                        >
                          <option value="">Select User</option>
                          <option v-for="user in approval.availableUsers" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.card_number || user.email }})
                          </option>
                        </select>
                      </td>
                      <td class="text-center">
                        <button
                          type="button"
                          class="btn btn-danger btn-sm"
                          @click="removeApproval(index)"
                          :disabled="approval.isDefault"
                          title="Remove"
                        >
                          Remove
                        </button>
                      </td>
                    </tr>
                    <tr v-if="form.approvals.length === 0">
                      <td colspan="3" class="text-center text-muted py-3">No approval assignments</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Submit -->
            <div class="text-end mt-4">
              <button type="button" class="btn btn-secondary me-2" @click="goToList">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="isSubmitting || !canSubmit">
                <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                {{ isEditMode ? 'Update Report' : 'Submit' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </template>

  <script setup>
  import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
  import axios from 'axios'
  import { showAlert } from '@/Utils/bootbox'
  import {initSelect2, destroySelect2} from '@/Utils/select2'

  const props = defineProps({
    stockReportId: { type: [String, Number], default: null }
  })

  const isEditMode = computed(() => !!props.stockReportId)
  const isSubmitting = ref(false)
  const isInitialized = ref(false)

  const form = reactive({
    id: null,
    start_date: '',
    end_date: '',
    warehouse_ids: [],
    remarks: '',
    reference_no: '',
    approver_name: '',
    approvals: []
  })

  const typeSelectRefs = ref([])
  const userSelectRefs = ref([])

  // Datatable configuration
  const datatableRef = ref(null)
  const datatableParams = reactive({
      sortColumn: 'item_code',
      sortDirection: 'asc',
      search: '',
      warehouse_ids: [],
      start_date: null,
      end_date: null,
      limit: 10,
      page: 1,
  })

  const datatableHeaders = [
    { text: 'Item Code', value: 'item_code', minWidth: '120px' },
    { text: 'Description', value: 'description', minWidth: '300px', sortable: false },
    { text: 'Unit', value: 'unit_name', minWidth: '60px', sortable: false },
    { text: 'Begin Qty', value: 'beginning_quantity', minWidth: '100px', sortable: false, format: 'number' },
    { text: 'Begin Price', value: 'beginning_price', minWidth: '120px', sortable: false, format: 'number' },
    { text: 'Begin Amount', value: 'beginning_total', minWidth: '120px', sortable: false, format: 'number' },
    { text: 'Stock In Qty', value: 'stock_in_quantity', minWidth: '100px', sortable: false, format: 'number' },
    { text: 'Stock In Amount', value: 'stock_in_total', minWidth: '120px', sortable: false, format: 'number' },
    { text: 'Available Qty', value: 'available_quantity', minWidth: '100px', sortable: false, format: 'number' },
    { text: 'Available Price', value: 'available_price', minWidth: '120px', sortable: false, format: 'number' },
    { text: 'Available Amount', value: 'available_total', minWidth: '120px', sortable: false, format: 'number' },
    { text: 'Stock Out Qty', value: 'stock_out_quantity', minWidth: '100px', sortable: false, format: 'number' },
    { text: 'Stock Out Amount', value: 'stock_out_total', minWidth: '120px', sortable: false, format: 'number' },
    { text: 'Ending Qty', value: 'ending_quantity', minWidth: '100px', sortable: false, format: 'number' },
    { text: 'Count Qty', value: 'counted_quantity', minWidth: '100px', sortable: false, format: 'number' },
    { text: 'Variance', value: 'variance_quantity', minWidth: '80px', sortable: false, format: 'number' },
    { text: 'Carried Qty', value: 'counted_quantity', minWidth: '100px', sortable: false, format: 'number' },
    { text: 'Avg Price', value: 'average_price', minWidth: '120px', sortable: false, format: 'number' },
    { text: 'Ending Amount', value: 'ending_total', minWidth: '120px', sortable: false, format: 'number' },
  ]

  const datatableFetchUrl = '/api/inventory/stock-reports'
  const datatableOptions = {
    autoWidth: false,
    responsive: false,
    pageLength: 10
  }

  const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
  const handlePageChange = (page) => { datatableParams.page = page }
  const handleLengthChange = (length) => { datatableParams.limit = length }
  const handleSearchChange = (search) => { datatableParams.search = search }

  // Watch for changes in form filters and auto-reload datatable
  watch(
    () => [form.start_date, form.end_date, form.warehouse_ids],
    ([newStartDate, newEndDate, newWarehouseIds]) => {
      // Only auto-reload if component is fully initialized
      if (!isInitialized.value) return

      // Update datatable params
      datatableParams.start_date = newStartDate || null
      datatableParams.end_date = newEndDate || null
      datatableParams.warehouse_ids = Array.isArray(newWarehouseIds) ? newWarehouseIds : []

      // Reload datatable with new filters
      if (datatableRef.value && typeof datatableRef.value.reload === 'function') {
        datatableRef.value.reload()
      }
    },
    { deep: true }
  )

  const defaultApprovalTypes = ['initial', 'verify', 'check', 'acknowledge']
  const sortApprovals = () => {
    form.approvals.sort((a, b) => {
      const aIndex = defaultApprovalTypes.indexOf(a.request_type)
      const bIndex = defaultApprovalTypes.indexOf(b.request_type)
      return aIndex - bIndex
    })
  }

  const setTypeSelectRef = (el, index) => { typeSelectRefs.value[index] = el }
  const setUserSelectRef = (el, index) => { userSelectRefs.value[index] = el }

  const warehouses = ref([])
  const usersByType = reactive({ initial: [], verify: [], check: [], acknowledge: [] })

  const canSubmit = computed(() => {
    const required = ['initial', 'verify', 'check', 'acknowledge']
    const assigned = form.approvals
      .filter(a => required.includes(a.request_type) && a.user_id)
      .map(a => a.request_type)
    return (
      form.start_date &&
      form.end_date &&
      form.warehouse_ids.length > 0 &&
      required.every(type => assigned.includes(type))
    )
  })

  const goToList = () => window.location.href = '/inventory/stock-reports/monthly-report'
  const goToDetails = (id) => window.location.href = `/inventory/stock-reports/monthly-report/${id}/show`

  const fetchMasterData = async () => {
    try {
      const [whRes, userRes] = await Promise.all([
        axios.get('/api/main-value-lists/get-warehouses'),
        axios.get('/api/inventory/stock-reports/get-approval-users')
      ])

      warehouses.value = whRes.data.data || whRes.data
      Object.assign(usersByType, userRes.data || {})

      const currentUserId = window.Laravel?.user?.id || null
      if (currentUserId) {
        Object.keys(usersByType).forEach(key => {
          usersByType[key] = usersByType[key].filter(u => u.id !== currentUserId)
        })
      }
    } catch (err) {
      console.error('Fetch master data error:', err)
      showAlert('Error', 'Failed to load warehouses or users.', 'danger')
    }
  }

  const loadReport = async (id) => {
    try {
      const { data } = await axios.get(`/api/inventory/stock-reports/${id}/edit`)
      const r = data.data
      Object.assign(form, {
        id: r.id,
        start_date: r.start_date,
        end_date: r.end_date,
        warehouse_ids: r.warehouse_ids || [],
        remarks: r.remarks || '',
        reference_no: r.reference_no,
        report_date: r.report_date,
      })

      // Always make approvals an array
      form.approvals = Array.isArray(r.approvals) ? r.approvals.map(a => ({
        id: a.id || null,
        request_type: a.request_type || '',
        user_id: a.user_id ? Number(a.user_id) : null,
        user_name: a.user_name || '',
        isDefault: true,
        availableUsers: usersByType[a.request_type] || []
      })) : []

      // Ensure all 4 types exist
      ['initial', 'verify', 'check', 'acknowledge'].forEach(type => {
        if (!form.approvals.some(a => a.request_type === type)) {
          form.approvals.push({
            id: null,
            request_type: type,
            user_id: null,
            user_name: '',
            isDefault: true,
            availableUsers: usersByType[type] || []
          })
        }
      })

    } catch (err) {
      console.error('Load report error:', err)
      showAlert('Error', 'Failed to load report.', 'danger')
    }
  }

  const addApproval = () => {
    form.approvals.push({
      id: null,
      request_type: '',
      user_id: null,
      isDefault: false,
      availableUsers: []
    })
  }

  const removeApproval = (index) => {
    if (form.approvals[index].isDefault) {
      showAlert('Cannot Remove', 'Default approval steps cannot be removed.', 'warning')
      return
    }
    form.approvals.splice(index, 1)
  }

  const refreshUserDropdown = async (index) => {
    const type = form.approvals[index].request_type
    const $select = userSelectRefs.value[index]

    if (!$select) return

    if ($select.select2) $select.select2('destroy')

    if (!type) {
      form.approvals[index].availableUsers = []
      form.approvals[index].user_id = null
      $($select).html('<option value="">Select User</option>')
      return
    }

    form.approvals[index].availableUsers = usersByType[type] || []

    await nextTick()

    $($select).select2({
      placeholder: 'Select User',
      allowClear: true,
      width: '100%',
      data: form.approvals[index].availableUsers.map(u => ({
        id: u.id,
        text: `${u.name} (${u.card_number || u.email})`
      }))
    })
    .val(form.approvals[index].user_id || null)
    .trigger('change')
    .on('change', e => {
      form.approvals[index].user_id = e.target.value ? Number(e.target.value) : null
    })
  }

  const initWidgets = async () => {
    // Initialize date pickers
    $('#start_date, #end_date').datepicker({
      format: 'yyyy-mm-dd',
      autoclose: true,
      todayHighlight: true
    }).on('changeDate', e => {
      const field = e.target.id
      const key = field === 'start_date' ? 'start_date' : 'end_date'
      const value = e.format()

      // Update form model (this will trigger the watcher)
      form[key] = value
    })

    // Initialize warehouse select
    $('#warehouseSelect').select2({
      placeholder: 'Select warehouses',
      allowClear: true,
      width: '100%',
      data: warehouses.value.map(w => ({ id: w.id, text: w.name || w.text }))
    }).on('change', () => {
      const selected = $('#warehouseSelect').val()
        ? $('#warehouseSelect').val().map(Number)
        : []

      // Update form model (this will trigger the watcher)
      form.warehouse_ids = selected
    })

    // Set initial values
    if (form.start_date) $('#start_date').datepicker('setDate', form.start_date)
    if (form.end_date) $('#end_date').datepicker('setDate', form.end_date)
    if (form.warehouse_ids.length) $('#warehouseSelect').val(form.warehouse_ids).trigger('change')

    // Initialize approval type selects
    typeSelectRefs.value.forEach((el, i) => {
      if (!el) return
      $(el).select2({
        placeholder: 'Select Type',
        width: '100%',
        allowClear: !form.approvals[i].isDefault
      })
      .val(form.approvals[i].request_type || null)
      .trigger('change')
      .on('change', () => {
        form.approvals[i].request_type = el.value
        refreshUserDropdown(i)
      })
    })

    form.approvals.forEach((_, i) => refreshUserDropdown(i))
  }

  const submitForm = async () => {
    if (!canSubmit.value) {
      showAlert('Validation Failed', 'Please fill all required fields.', 'warning')
      return
    }

    isSubmitting.value = true

    const payload = {
      ...form,
      warehouse_ids: form.warehouse_ids,
      approvals: form.approvals.map(a => ({
        id: a.id,
        request_type: a.request_type,
        user_id: a.user_id
      }))
    }

    try {
      const url = isEditMode.value
        ? `/api/inventory/stock-reports/${form.id}`
        : '/api/inventory/stock-reports'

      const res = await axios[isEditMode.value ? 'put' : 'post'](url, payload)

      await showAlert('Success', 'Monthly stock report saved successfully!', 'success')

      // Get report ID
      const reportId = isEditMode.value ? form.id : res.data.id

      // Go to detail page
      goToDetails(reportId)

    } catch (err) {
      console.error('Submit error:', err)
      showAlert('Error', err.response?.data?.message || 'Failed to save report.', 'danger')
    } finally {
      isSubmitting.value = false
    }
  }

  onMounted(async () => {
    await fetchMasterData()

    if (isEditMode.value) {
      await loadReport(props.stockReportId)
    } else {
      ['initial', 'check', 'verify', 'acknowledge'].forEach(type => {
        form.approvals.push({
          id: null,
          request_type: type,
          user_id: null,
          isDefault: true,
          availableUsers: usersByType[type] || []
        })
      })
    }

    // Initialize datatable filter params from form values
    datatableParams.start_date = form.start_date || null
    datatableParams.end_date = form.end_date || null
    datatableParams.warehouse_ids = form.warehouse_ids || []

    sortApprovals()
    await nextTick()
    await initWidgets()

    // Mark as initialized to enable auto-filtering
    isInitialized.value = true

    // Initial datatable load with current filter values
    if (datatableRef.value && typeof datatableRef.value.reload === 'function') {
      datatableRef.value.reload()
    }
  })
  </script>

  <style scoped>
  .badge { padding: 0.5em 0.9em; font-size: 0.9em; }
  small { font-size: 0.8em; }
  </style>
