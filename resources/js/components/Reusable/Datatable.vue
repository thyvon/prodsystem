<template>
  <div>
    <div v-if="$slots['additional-header']" class="datatable-header mb-2">
      <slot name="additional-header" />
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded shadow text-gray-900 dark:text-gray-100">
      <table ref="table" class="table table-bordered w-100 table-sm">
        <thead>
          <tr>
            <th style="width: 30px; text-align: center;">#</th>
            <th v-for="(h, i) in headers" :key="i" :style="{ width: h.width }">
              {{ h.text }}
            </th>
            <th v-if="actions.length" style="width: 80px; text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

// Global handler for dropdown actions (works on both desktop and mobile)
window.__datatableActionHandler = function(action, rowEncoded) {
  const event = window.event;
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  try {
    const rowData = JSON.parse(decodeURIComponent(rowEncoded));
    if (window.__datatableVueHandlers && window.__datatableVueHandlers[action]) {
      window.__datatableVueHandlers[action](rowData);
    }
  } catch (err) {
    console.error('Error parsing row data:', err);
  }
};

const props = defineProps({
  headers: Array,
  rows: Array, // not used for server-side, but kept for compatibility
  actions: { type: Array, default: () => [] },
  handlers: { type: Object, default: () => ({}) },
  options: { type: Object, default: () => ({ responsive: true, pageLength: 20 }) },
  fetchUrl: String,
  totalRecords: Number,
  fetchParams: Object,
});

const emit = defineEmits([
  'sort-change',
  'page-change',
  'length-change',
  'search-change',
]);

const table = ref(null);
let dataTableInstance = null;

// Utility functions
const formatDate = (dateString) => {
  return dateString
    ? new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' })
    : '';
};

const formatDateTime = (dateString) => {
  return dateString
    ? new Date(dateString).toLocaleString('en-US', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true })
    : '';
};

const capitalize = (str) => str.charAt(0).toUpperCase() + str.slice(1);

// Renderers
const renderColumnData = (key, val) => {
  if (!val) return '';

  // Date formatting
  if (key === 'created_at' || key === 'updated_at' || key === 'beginning_date' || /_date$/.test(key)) {
    return key === 'updated_at' ? formatDateTime(val) : formatDate(val);
  }

  // Status badges
  const statusKeys = {
    is_active: { true: ['badge-success', 'Active'], false: ['badge-secondary', 'Inactive'] },
    has_variants: { true: ['badge-primary', 'Yes'], false: ['badge-danger', 'No'] },
  };

  if (statusKeys[key] !== undefined) {
    const [badgeClass, text] = statusKeys[key][!!val];
    return `<span class="badge ${badgeClass} text-center">${text}</span>`;
  }

  // Request type badge
  if (key === 'request_type') {
    const map = { review: 'badge-info', check: 'badge-primary', approve: 'badge-success' };
    const status = (val || '').toLowerCase();
    const badgeClass = map[status] || 'badge-secondary';
    const text = capitalize(val);
    return `<span class="badge ${badgeClass} text-center">${text}</span>`;
  }

  // Approval status badge
  if (key === 'approval_status') {
    let badgeClass = 'badge badge-secondary';
    const status = (val || '').toLowerCase();
    if (status.includes('rejected')) badgeClass = 'badge badge-danger';
    else if (status === 'pending') badgeClass = 'badge badge-warning';
    else if (status === 'approved') badgeClass = 'badge badge-success';
    else if (status === 'reviewed') badgeClass = 'badge badge-info';
    else if (status === 'checked') badgeClass = 'badge badge-primary';
    const text = capitalize(val);
    return `<span class="badge ${badgeClass} text-center">${text}</span>`;
  }

  // Receivers
  if (key === 'receivers' && Array.isArray(val)) {
    return `<ul class="mb-0">
      ${val.map(r => `
        <li>
          ${r.name} 
          <span class="badge ${r.status === 'Pending' ? 'badge-warning' : 'badge-success'}">${r.status}</span>
          ${r.received_date ? ` - <small class="text-muted">${r.received_date}</small>` : ''}
        </li>
      `).join('')}
    </ul>`;
  }

  if (key === 'approvals' && Array.isArray(val)) {
    return `<ul class="mb-0">
      ${val.map(a => `
        <li>
          ${a.approver_name} (${capitalize(a.request_type)}) 
          <span class="badge ${a.approval_status === 'Pending' ? 'badge-warning' : (a.approval_status === 'Rejected' ? 'badge-danger' : 'badge-success')}">
            ${a.approval_status}
          </span>
          ${a.approved_date ? ` - <small class="text-muted">${formatDateTime(a.approved_date)}</small>` : ''}
        </li>
      `).join('')}
    </ul>`;
  }


  // Document Status
  if (key === 'status') {
    let badgeClass = 'badge badge-secondary';
    const status = (val || '').toLowerCase();
    if (status === 'pending') badgeClass = 'badge badge-warning';
    else if (status === 'completed') badgeClass = 'badge badge-success';
    const text = capitalize(val);
    return `<span class="badge ${badgeClass} text-center">${text}</span>`;
  }

  // Document is send back
  if (key === 'is_send_back') {
    const badgeClass = val ? 'badge badge-danger' : 'badge badge-success';
    const text = val ? 'Yes' : 'No';
    return `<span class="badge ${badgeClass} text-center">${text}</span>`;
  }

  // Images
  if (key === 'image') return `<div class="text-center"><img src="/storage/${val}" style="max-width:60px;max-height:60px;" /></div>`;
  if (key === 'profile_url') return `<div class="text-center"><img class="rounded-circle" src="/storage/${val}" style="max-width:60px;max-height:60px;" /></div>`;

  // Default
  return val ?? '';
};


const createActionButtons = (row) => {
  const encodedRow = encodeURIComponent(JSON.stringify(row));
  return `
    <div class="dropdown d-inline-block dropleft">
      <button type="button" class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-0" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fal fa-ellipsis-v"></i>
      </button>
      <div class="dropdown-menu">
        ${props.actions
          .map(
            (action) => `
              <a class="dropdown-item" href="#" onclick="window.__datatableActionHandler('${action}', '${encodedRow}')">
                ${capitalize(action)}
              </a>
            `
          )
          .join('')}
      </div>
    </div>
  `;
};

// DataTable columns config
const dtColumns = computed(() => {
  const cols = [
    {
      data: null,
      orderable: false,
      searchable: false,
      className: 'text-center',
      width: '30px',
      render: (data, type, row, meta) => meta.row + 1 + meta.settings._iDisplayStart,
    },
    ...props.headers.map((h) => ({
      data: h.value,
      width: h.width || undefined,
      render: (val, type, row) => renderColumnData(h.value, val),
      orderable: h.sortable !== false,
    }))
  ];

  if (props.actions.length) {
    cols.push({
      data: null,
      orderable: false,
      searchable: false,
      className: 'text-center',
      width: '80px',
      render: (data, type, row) => createActionButtons(row),
    });
  }

  return cols;
});


// Fetch data from server
const fetchData = async (params) => {
  try {
    const { data } = await axios.get(props.fetchUrl, { params });
    return data;
  } catch (e) {
    console.error('Fetch error:', e);
    return { data: [], recordsTotal: 0, recordsFiltered: 0 };
  }
};

// Initialize DataTable
const initDataTable = () => {
  if (!window.$ || !table.value) return;

  if ($.fn.DataTable.isDataTable(table.value)) {
    $(table.value).DataTable().destroy();
  }

  dataTableInstance = $(table.value).DataTable({
    ...props.options,
    processing: true,
    serverSide: true,
    dom:
      "<'row mb-3'<'col-sm-12 col-md-6 d-flex align-items-center justify-content-start'l><'col-sm-12 col-md-6 d-flex align-items-center justify-content-end'Bf>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    buttons: [
      {
        extend: 'colvis',
        text: 'Column Visibility',
        titleAttr: 'Col visibility',
        className: 'btn-outline-default'
      },
      {
        extend: 'csvHtml5',
        text: 'CSV',
        titleAttr: 'Generate CSV',
        className: 'btn-outline-default'
      },
      {
        extend: 'copyHtml5',
        text: 'Copy',
        titleAttr: 'Copy to clipboard',
        className: 'btn-outline-default'
      },
      {
        extend: 'print',
        text: 'Print',
        titleAttr: 'Print Table',
        className: 'btn-outline-default'
      }
    ],
  ajax: async (data, callback) => {
    const orderIndex = data.order?.[0]?.column;
    const orderDir = data.order?.[0]?.dir || 'asc';

    // Get actual column config from the dtColumns
    const columnDef = dtColumns.value[orderIndex];
    const sortColumn = columnDef?.data || 'created_at';

    emit('sort-change', { column: sortColumn, direction: orderDir });
    emit('page-change', Math.ceil(data.start / data.length) + 1);
    emit('length-change', data.length);
    emit('search-change', data.search.value);

    const params = {
      ...props.fetchParams,
      page: Math.ceil(data.start / data.length) + 1,
      limit: data.length,
      sortColumn,
      sortDirection: orderDir,
      search: data.search.value,
    };

    const { data: responseData, recordsTotal, recordsFiltered } = await fetchData(params);

    callback({
      draw: data.draw,
      recordsTotal,
      recordsFiltered,
      data: responseData,
    });
  },
    columns: dtColumns.value,
  });
};

// Expose reload method for parent to refresh data
defineExpose({
  reload: () => {
    if (dataTableInstance) {
      dataTableInstance.ajax.reload(null, false); // false = keep current page
    }
  }
});

// No watcher for rows: let DataTables handle server-side data

// Lifecycle hooks
onMounted(async () => {
  await nextTick();
  initDataTable();
  window.__datatableVueHandlers = props.handlers;
});

onUnmounted(() => {
  if (dataTableInstance) {
    dataTableInstance.destroy(true);
    dataTableInstance = null;
  }
  window.__datatableVueHandlers = null;
});
</script>
