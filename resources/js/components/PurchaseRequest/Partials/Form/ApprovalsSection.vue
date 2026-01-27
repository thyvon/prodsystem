<template>
  <div class="border rounded p-3 mb-4">
    <h5 class="font-weight-bold mb-3 text-primary">
      ✅ Approvals ({{ form.approvals.length }})
    </h5>

    <div class="row">
      <div
        v-for="(approval, aIndex) in form.approvals"
        :key="aIndex"
        class="col-12 col-md-6 col-lg-4 mb-3"
      >
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div class="form-group mb-0" style="flex: 1;">
              <label class="small mb-1">Request Type</label>
              <select
                class="form-control approval-type-select"
                :data-index="aIndex"
                :name="`approvals[${aIndex}][request_type]`"
              ></select>
            </div>

            <button
              @click.prevent="$emit('remove-approval', aIndex)"
              class="btn btn-danger btn-sm ml-2"
              style="align-self: flex-end; margin-top: auto;"
            >
              <i class="fal fa-trash"></i>
            </button>
          </div>

          <div class="card-body">
            <div
              v-for="(user, uIndex) in approval.users"
              :key="user._uid"
              class="form-group mb-2 d-flex align-items-center"
            >
              <select
                class="form-control user-select mr-2"
                :data-aindex="aIndex"
                :data-uindex="uIndex"
                :name="`approvals[${aIndex}][users][${uIndex}]`"
                :disabled="!approval.request_type"
              ></select>

              <button
                @click.prevent="$emit('remove-user', aIndex, uIndex)"
                class="btn btn-danger btn-sm"
              >
                <i class="fal fa-trash"></i>
              </button>
            </div>

            <button
              @click.prevent="$emit('add-user', aIndex)"
              class="btn btn-outline-primary btn-sm mt-2"
            >
              <i class="fal fa-plus"></i> Add User
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="text-right mt-2">
      <button
        @click.prevent="$emit('add-approval')"
        class="btn btn-outline-primary btn-sm"
      >
        <i class="fal fa-plus"></i> Add Approval
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  form: { type: Object, required: true },
})

defineEmits(['add-approval', 'remove-approval', 'add-user', 'remove-user'])
</script>
