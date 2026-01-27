<template>
  <div class="col-md-6 d-flex">
    <div class="border rounded p-3 flex-fill">
      <h5 class="text-primary font-weight-bold mb-3">📋 PR Information</h5>

      <div class="form-row">
        <div class="form-group col-md-6">
          <label class="font-weight-bold">
            Deadline <span class="text-danger">*</span>
          </label>
          <input
            id="deadline_date"
            v-model="form.deadline_date"
            type="text"
            class="form-control"
            placeholder="yyyy-mm-dd"
          />
        </div>

        <div class="form-group col-md-6">
          <label class="font-weight-bold">🚨 Urgent</label>
          <div class="custom-control custom-switch">
            <input
              type="checkbox"
              class="custom-control-input"
              id="isUrgent"
              name="is_urgent"
              v-model="form.is_urgent"
            />
            <label class="custom-control-label" for="isUrgent">
              <span :class="form.is_urgent ? 'text-danger' : 'text-muted'">
                {{ form.is_urgent ? 'YES' : 'NO' }}
              </span>
            </label>
          </div>
        </div>

        <div class="form-group col-12 mt-2">
          <label for="purpose" class="font-weight-bold">🎯 Purpose</label>
          <textarea
            id="purpose"
            name="purpose"
            v-model="form.purpose"
            class="form-control"
            rows="2"
            required
          ></textarea>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  form: { type: Object, required: true },
})
</script>
