<template>
  <div>
    <!-- Additional header slot -->
    <div v-if="$slots['additional-header']" class="datatable-header mb-2">
      <slot name="additional-header" />
    </div>

    <!-- Outer container with horizontal padding -->
    <div>
      <div
        class="shadow p-2"
      >

        <!-- Scroll wrapper only if scrollable = true -->
        <div :style="scrollable ? 'overflow-x: auto;' : ''">

          <table
            ref="table"
            class="table table-bordered table-sm table-hover table-striped w-100"
            style="cursor: pointer;"
          >
            <thead>
              <tr>
                <th style="width: 30px; text-align:center;">#</th>

                <th
                  v-for="(h, i) in headers"
                  :key="i"
                  :style="{ width: h.width, minWidth: h.minWidth }"
                >
                  {{ h.text }}
                </th>

                <th
                  v-if="actions.length"
                  style="width: 80px; text-align:center;"
                >
                  Actions
                </th>
              </tr>
            </thead>

              <tbody>
                <tr
                  v-for="(row, index) in rows"
                  :key="index"
                  @click="$emit('row-click', row)"
                  style="cursor: pointer;"
                >
                  <!-- Row number -->
                  <td style="text-align:center">{{ index + 1 }}</td>

                  <!-- Data columns -->
                  <td v-for="(h, i) in headers" :key="i">
                    {{ row[h.value] }}
                  </td>

                  <!-- Action buttons -->
                  <td v-if="actions.length" class="text-center">
                    <button
                      v-for="act in actions"
                      :key="act"
                      @click.stop="handlers[act](row)"
                      class="btn btn-sm btn-outline-primary"
                    >
                      {{ act }}
                    </button>
                  </td>
                </tr>
              </tbody>

          </table>

        </div>
      </div>
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
  options: { type: Object, default: () => ({
    // responsive: true,
  pageLength: 20, }) },
  fetchUrl: String,
  totalRecords: Number,
  fetchParams: Object,
  rows: Array,
  scrollable: { type: Boolean, default: false } // ⭐ added
});

const emit = defineEmits([
  'sort-change',
  'page-change',
  'length-change',
  'search-change',
  'row-click',
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
  const capitalize = (str) => str?.charAt(0).toUpperCase() + str?.slice(1) || '';
  const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' }) : '';
  const formatDateTime = (d) => d ? new Date(d).toLocaleString('en-US', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true }) : '';

  // Helper for badges
  const badge = (cls, text) => `<span class="badge ${cls} text-center">${text}</span>`;

  // Status mapping
  const statusKeys = {
    is_active: { true: ['badge-success', 'Active'], false: ['badge-secondary', 'Inactive'] },
    is_urgent: { true: ['badge-danger', 'Yes'], false: ['badge-secondary', 'No'] },
    has_variants: { true: ['badge-primary', 'Yes'], false: ['badge-danger', 'No'] },
  };
const getBadgeClass = (status) => {
    switch (status?.toLowerCase()) {
        case 'pending': return 'badge-pending';
        case 'rejected': return 'badge-rejected';
        case 'done': return 'badge-done';
        case 'reviewed': return 'badge-reviewed';
        case 'checked': return 'badge-checked';
        case 'approved': return 'badge-approved';
        case 'verified': return 'badge-verified';
        case 'acknowledged': return 'badge-acknowledged';
        case 'received': return 'badge-received';
        case 'returned': return 'badge-returned';
        default: return 'badge-default';
    }
};

  switch (key) {
    // Dates
    case 'created_at':
    case 'beginning_date': return formatDate(val);
    case 'updated_at': return formatDateTime(val);
    case 'transaction_date': return formatDate(val);
    case 'report_date': return formatDate(val);
    case 'responded_date': return formatDateTime(val);
    case 'request_date': return formatDate(val);
    case 'deadline_date': return formatDate(val);
    case 'start_date': return formatDate(val);
    case 'end_date': return formatDate(val);

    // Boolean status badges
    case 'is_active':
    case 'is_urgent':
    case 'has_variants':
      const [cls, text] = statusKeys[key][String(!!val)];
      return badge(cls, text);

    // Request type badge
    case 'request_type': {
      const map = {
      review: 'badge-info',
      check: 'badge-primary',
      approve: 'badge-success',
      reject: 'badge-danger',
      verify: 'badge-warning',
      acknowledge: 'badge-secondary',
      };
      return badge(map[val?.toLowerCase()] || 'badge-secondary', capitalize(val));
    }

// Single approval status
case 'approval_status': {
    const status = val;
    const cls = getBadgeClass(status);
    return badge(cls, capitalize(status));
}

// Approvals array
case 'approvals': {
    if (!Array.isArray(val)) break;

    return `<ul class="mb-0 ps-2">
        ${val.map(a => {
            const status = a.approval_status || 'Pending';
            const cls = getBadgeClass(status);
            const date = a.responded_date
                ? ` - <small class="text-muted">${formatDateTime(a.responded_date)}</small>`
                : '';
            const name = a.responder_name || a.requester_name || 'Unknown';
            // const requestType = a.request_type ? capitalize(a.request_type) : '';
            return `<li>${name} ${badge(cls, status)}${date}</li>`;
        }).join('')}
    </ul>`;
}

    // Sharepoint file
    case 'sharepoint_file_ui_url':
      return `<a href="${val}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fal fa-folder"></i> View Document</a>`;

    // Document status
    case 'status': {
      const cls = val?.toLowerCase() === 'pending' ? 'badge-warning' : val?.toLowerCase() === 'completed' ? 'badge-success' : 'badge-secondary';
      return badge(cls, capitalize(val));
    }

    // Send back
    case 'is_send_back':
      return badge(val ? 'badge-danger' : 'badge-success', val ? 'Yes' : 'No');

    // Receivers array
    case 'receivers':
      if (Array.isArray(val)) {
        return `<ul class="mb-0">
          ${val.map(r => `<li>${r.name} ${badge(r.status === 'Pending' ? 'badge-warning' : 'badge-success', r.status)}${r.received_date ? ` - <small class="text-muted">${r.received_date}</small>` : ''}</li>`).join('')}
        </ul>`;
      }
      break;

    // Images
    case 'image': return `<div class="text-center"><img src="/storage/${val}" style="max-width:60px;max-height:60px;" /></div>`;
    case 'profile_url': return `<div class="text-center"><img class="rounded-circle" src="/storage/${val}" style="max-width:60px;max-height:60px;" /></div>`;

    // Default
    default: return val ?? '';
  }
};
const formatNumber = (value) => {
  if (value === null || value === undefined || isNaN(value)) return '0.00';
  return parseFloat(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const createActionButtons = (row) => {
  const encodedRow = encodeURIComponent(JSON.stringify(row));

  // Determine which actions to show
  const actionsToShow = props.actions.filter((action) => {
    if (row.deleted_at) {
      // If row is deleted, only show restore & forceDelete
      return action === 'restore' || action === 'forceDelete';
    } else {
      // If row is not deleted, hide restore & forceDelete
      return action !== 'restore' && action !== 'forceDelete';
    }
  });

  if (!actionsToShow.length) return ''; // nothing to show

  return `
    <div class="dropdown d-inline-block dropleft">
      <button type="button" class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-0" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fal fa-ellipsis-v"></i>
      </button>
      <div class="dropdown-menu">
        ${actionsToShow
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
      render: (val, type, row) => {
        // Call formatNumber only if this column has h.format === 'number'
        if (h.format === 'number') {
          return formatNumber(val);
        }
        return renderColumnData(h.value, val);
      },
      orderable: h.sortable !== false,
      createdCell: (td) => {
     td.style.minWidth = h.minWidth || '80px'; // default fallback
   }
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
      // {
      //   extend: 'csvHtml5',
      //   text: 'CSV',
      //   titleAttr: 'Generate CSV',
      //   className: 'btn-outline-default'
      // },
      // {
      //   extend: 'copyHtml5',
      //   text: 'Copy',
      //   titleAttr: 'Copy to clipboard',
      //   className: 'btn-outline-default'
      // },
      // {
      //   extend: 'print',
      //   text: 'Print',
      //   titleAttr: 'Print Table',
      //   className: 'btn-outline-default'
      // }
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

  $(table.value).on('click', 'tbody tr', function (event) {
    const rowData = dataTableInstance.row(this).data();

    // Check if click is inside an action cell
    if (
      $(event.target).closest('td').index() === dtColumns.value.length - 1 || // last column = Actions
      $(event.target).closest('button').length ||
      $(event.target).closest('.dropdown').length ||
      $(event.target).closest('a').length
    ) {
      return; // Ignore clicks in Actions column or buttons/dropdowns
    }

    emit("row-click", rowData);
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

<style>
/* Custom badge colors inside scoped styles */
.badge-pending { background-color: orange; color: white; }
.badge-rejected { background-color: red; color: white; }
.badge-done { background-color: green; color: white; }
.badge-reviewed { background-color: deepskyblue; color: white; }
.badge-checked { background-color: purple; color: white; }
.badge-approved { background-color: green; color: white; }
.badge-verified { background-color: teal; color: white; }
.badge-acknowledged { background-color: gray; color: white; }
.badge-received { background-color: lightblue; color: black; }
.badge-returned { background-color: darkred; color: white; }
.badge-default { background-color: darkgray; color: white; }

/* Optional: add some padding and rounded corners like Bootstrap badges */
.badge {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.25rem;
    line-height: 1;
}
</style>

