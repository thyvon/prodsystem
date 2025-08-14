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
  if (key === 'created_at') {
    return formatDate(val);
  }
  if (key === 'updated_at') {
    return formatDateTime(val);
  }
  if (key === 'beginning_date') {
    return formatDate(val);
  }
    if (/_date$/.test(key) && val) {
    return formatDate(val);
  }
    if (key === 'is_active') {
    const badgeClass = val ? 'badge badge-success' : 'badge badge-secondary';
    const text = val ? 'Active' : 'Inactive';
    return `<span class="${badgeClass} text-center">${text}</span>`;
  }
  
  if (key === 'request_type') {
  const status = (val || '').toString().trim().toLowerCase();
  let badgeClass = 'badge badge-secondary';
  let text = val || 'Unknown';

  switch (status) {
    case 'review':
      badgeClass = 'badge badge-info';
      text = 'Review';
      break;
    case 'check':
      badgeClass = 'badge badge-primary';
      text = 'Check';
      break;
    case 'approve':
      badgeClass = 'badge badge-success';
      text = 'Approve';
      break;
  }

  return `<span class="${badgeClass} text-center">${text}</span>`;
}
  

  if (key === 'approval_status') {
    const status = (val || '').toString().trim().toLowerCase();
    let badgeClass = 'badge badge-secondary';
    let text = val || 'Unknown'; // keep original full text by default

    if (status.includes('rejected')) {
      badgeClass = 'badge badge-danger';
      // text = val;  // keep full original text, already assigned above
    } else {
      switch (status) {
        case 'pending':
          badgeClass = 'badge badge-warning';
          text = 'Pending';
          break;
        case 'approved':
          badgeClass = 'badge badge-success';
          text = 'Approved';
          break;
        case 'reviewed':
          badgeClass = 'badge badge-info';
          text = 'Reviewed';
          break;
        case 'checked':
          badgeClass = 'badge badge-primary';
          text = 'Checked';
          break;
        default:
          badgeClass = 'badge badge-secondary';
          // text already set to val
      }
    }

    return `<span class="${badgeClass} text-center">${text}</span>`;
  }
    if (key === 'has_variants') {
    const badgeClass = val ? 'badge badge-primary' : 'badge badge-danger';
    const text = val ? 'Yes' : 'No';
    return `<span class="${badgeClass} text-center">${text}</span>`;
  }
    if (key === 'toca_amounts' && Array.isArray(val)) {
      return `<div class="text-start">` + 
        val.map(amount => `<div class="mb-1">${amount}</div>`).join('') + 
        `</div>`;
    }
    if (key === 'image' && val) {
      return `<div class="text-center"><img src="/storage/${val}" alt="Product Image" style="max-width:60px;max-height:60px;" /></div>`;
    }
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
