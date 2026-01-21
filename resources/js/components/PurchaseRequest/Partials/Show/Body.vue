<template>
  <div class="card-body bg-white p-3">

    <!-- 1️⃣ General Info Section -->
    <div class="row mb-2">
      <div class="col-3">
        <div class="row mb-1">
          <div class="col-6 text-muted">Requester / អ្នកស្នើសុំ:</div>
          <div class="col-6 font-weight-bold">{{ purchaseRequest.creator_name ?? 'N/A' }}</div>
        </div>
        <div class="row mb-1">
          <div class="col-6 text-muted">ID Card / អត្តលេខ:</div>
          <div class="col-6 font-weight-bold">{{ purchaseRequest.creator_id_card ?? 'N/A' }}</div>
        </div>
        <div class="row mb-1">
          <div class="col-6 text-muted">Position / មុខតំណែង:</div>
          <div class="col-6 font-weight-bold">{{ purchaseRequest.creator_position ?? 'N/A' }}</div>
        </div>
      </div>

      <div class="col-6 text-center">
        <h4 class="font-weight-bold text-dark">Purchase Request</h4>
        <h4 class="font-weight-bold text-dark">សំណើទិញសម្ភារ</h4>
        <h4 v-if="purchaseRequest.is_urgent" class="font-weight-bold text-danger">Urgent</h4>
      </div>

      <div class="col-3 text-end">
        <p class="text-muted mb-1">
          REF. / លេខយោង: <span class="font-weight-bold">{{ purchaseRequest.reference_no ?? 'N/A' }}</span>
        </p>
        <p class="text-muted mb-1">
          DATE REQUESTED / កាលបរិច្ឆេទ: <span class="font-weight-bold">{{ formatDate(purchaseRequest.request_date) ?? 'N/A' }}</span>
        </p>
        <p class="text-muted mb-1">
          DEADLINE / ថ្ងៃផុតកំណត់: <span class="font-weight-bold">{{ formatDate(purchaseRequest.deadline_date) ?? 'N/A' }}</span>
        </p>
        <h4>
            <span
                class="badge"
                :class="{
                    'badge-danger': purchaseRequest.approval_status === 'Rejected',
                    'badge-warning': purchaseRequest.approval_status === 'Returned',
                    'badge-success': !['Rejected', 'Returned'].includes(purchaseRequest.approval_status)
                }"
            >
                {{ purchaseRequest.approval_status }}
            </span>
        </h4>
      </div>
    </div>

    <!-- 2️⃣ Purpose Section -->
    <div class="row mb-3">
      <div class="col-12">
        <p class="mb-2">
          PURPOSE/គោលបំណង: <span class="font-weight-bold">{{ purchaseRequest.purpose ?? 'N/A' }}</span>
        </p>
      </div>
    </div>

    <!-- 3️⃣ Line Items Table Section -->
    <div class="table-responsive">
      <table class="table table-bordered table-sm">
        <thead class="table-dark">
          <tr>
            <th class="text-center">#</th>
            <th>Product Code</th>
            <th>Product Description</th>
            <th>Unit</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Total Price</th>
            <th>Division</th>
            <th>Department</th>
            <th>Campus</th>
            <th>Budget Code</th>
            <th>Assigned Purchaser</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in purchaseRequest.items" :key="i">
            <td class="text-center">{{ i + 1 }}</td>
            <td>{{ item.product_code ?? 'N/A' }}</td>
            <td>{{ item.product_description ?? 'N/A' }}</td>
            <td>{{ item.unit_name ?? 'N/A' }}</td>
            <td class="text-center">{{ format(item.quantity) }}</td>
            <td class="text-end">{{ format(item.unit_price) }}</td>
            <td class="text-end">{{ format(item.total_price) }} {{ item.currency }}</td>
            <td>{{ item.division_short_names }}</td>
            <td>{{ item.department_short_names }}</td>
            <td>{{ item.campus_short_names }}</td>
            <td>{{ item.budget_code_ref ?? 'N/A' }}</td>
            <td>{{ item.purchaser_name ?? 'N/A' }}</td>
          </tr>
          <tr>
            <td colspan="6" class="text-end font-weight-bold">Total (USD)</td>
            <td class="text-end font-weight-bold">{{ format(purchaseRequest.total_value_usd) }}</td>
            <td colspan="5"></td>
          </tr>
          <tr>
            <td colspan="6" class="text-end font-weight-bold">Total (KHR)</td>
            <td class="text-end font-weight-bold">{{ format(purchaseRequest.total_value_khr) }}</td>
            <td colspan="5"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 4️⃣ Attachments Section -->
    <div class="mt-4" v-if="purchaseRequest.files && purchaseRequest.files.length > 0">
      <h5 class="text-primary font-weight-bold mb-3">Attachments</h5>
      <div class="card border shadow-sm">
        <div class="card-body">
          <button
            v-for="file in purchaseRequest.files"
            :key="file.id"
            type="button"
            class="btn btn-sm btn-outline-info m-1"
            @click="openFileViewer(file.url, file.name)"
          >
            📄 {{ file.name }}
          </button>
        </div>
      </div>
    </div>

    <!-- 5️⃣ Requested & Approval Cards Section -->
    <div class="mt-4 row">
      <!-- Requested By Card -->
      <div class="col-md-3 mb-3">
        <div class="card border shadow-sm h-100">
          <div class="card-body">
            <label class="font-weight-bold d-block text-center">ស្នើសុំដោយ<br>Requested By</label>
            <div class="d-flex mb-2">
              <img
                :src="purchaseRequest.creator_profile_url ? '/storage/' + purchaseRequest.creator_profile_url : '/images/default-avatar.png'"
                class="rounded-circle"
                width="50"
                height="50"
              />
            </div>
            <hr>
            <div class="font-weight-bold mb-1">{{ purchaseRequest.creator_name ?? 'N/A' }}</div>
            <p class="mb-1">Status: <span class="badge badge-primary">Requested</span></p>
            <p class="mb-1">Position: {{ purchaseRequest.creator_position ?? 'N/A' }}</p>
            <p class="mb-0">Date: {{ formatDate(purchaseRequest.request_date) }}</p>
          </div>
        </div>
      </div>

      <!-- Approval Cards -->
      <div
        v-for="(approval, i) in purchaseRequest.approvals"
        :key="i"
        class="col-md-3 mb-3"
      >
        <ApprovalCard
          :approval="approval"
          :format-date="formatDate"
          :capitalize="capitalize"
        />
      </div>
    </div>

    <!-- 6️⃣ Timeline Section -->
    <!-- <div class="col-12 mt-4">
      <h5 class="text-center mb-3">Timeline</h5>
      <div class="d-flex justify-content-center align-items-center mx-auto" style="overflow-x:auto; white-space: nowrap; gap: 20px; padding: 10px 0;">
        <div class="d-flex flex-column align-items-center" style="min-width: 120px;">
          <div class="mb-1 font-weight-bold text-dark">1</div>
          <span class="badge bg-success text-white px-3 py-2 mb-1">Requested</span>
          <small class="text-muted text-start">
            {{ purchaseRequest.creator_name ?? 'N/A' }}<br>
            {{ formatDate(purchaseRequest.request_date) ?? 'N/A' }}
          </small>
        </div>

        <TimelineItem
          v-for="(approval, i) in purchaseRequest.approvals"
          :key="'timeline-'+i"
          :approval="approval"
          :index="i+1"
          :request_date="purchaseRequest.request_date"
          :next-approval="purchaseRequest.approvals[i+1]"
          :format-date="formatDate"
          :days-between="daysBetween"
        />
      </div>
    </div> -->

  </div>
</template>

<script setup>
import ApprovalCard from '@/components/PurchaseRequest/Partials/Show/ApprovalCard.vue'
// import TimelineItem from '@/components/PurchaseRequest/Partials/Show/TimelineItem.vue'

const props = defineProps({
  purchaseRequest: Object,
  formatDate: Function,
  format: Function,
  capitalize: Function,
  daysBetween: Function,
  openFileViewer: Function
})

const { purchaseRequest, formatDate, format, capitalize, daysBetween, openFileViewer } = props
</script>
