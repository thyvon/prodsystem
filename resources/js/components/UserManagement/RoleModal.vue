<template>
  <BaseModal
    v-model="showModal"
    id="roleModal"
    :title="isEditing ? 'Edit Role' : 'Create Role'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="form-group mb-3">
          <label>Name</label>
          <input v-model="form.name" type="text" class="form-control" required />
        </div>
        <div class="form-group mb-3">
          <label>Guard Name</label>
          <input v-model="form.guard_name" type="text" class="form-control" required />
        </div>
        <div class="form-group mb-3">
          <label>Permissions</label>
          <div class="row">
            <div
              v-for="(permission, idx) in allPermissions"
              :key="permission.id"
              class="col-6"
            >
              <div class="custom-control custom-checkbox mb-2">
                <input
                  class="custom-control-input"
                  type="checkbox"
                  :id="`perm-${permission.id}-${idx}`"
                  :value="Number(permission.id)"
                  v-model="form.permissions"
                />
                <label class="custom-control-label" :for="`perm-${permission.id}-${idx}`">
                  {{ permission.name }}
                </label>
              </div>
            </div>
          </div>
        </div>
      </form>
    </template>
    <template #footer>
      <button type="button" class="btn btn-secondary" @click="hideModal">Cancel</button>
      <button type="submit" class="btn btn-primary" @click="submitForm">
        {{ isEditing ? 'Update' : 'Create' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'

const props = defineProps({
  isEditing: Boolean,
})
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const allPermissions = ref([])
const form = ref({
  id: null,
  name: '',
  guard_name: 'web',
  permissions: [],
})

const show = async (role = {}) => {
  showModal.value = true
  await loadPermissions()
  if (role.id) {
    const { data } = await axios.get(`/api/roles/${role.id}/permissions`)
    form.value = {
      id: data.id,
      name: data.name,
      guard_name: data.guard_name,
      // Ensure permissions are numbers for v-model binding
      permissions: data.permissions ? data.permissions.map(id => Number(id)) : [],
    }
  } else {
    form.value = {
      id: null,
      name: '',
      guard_name: 'web',
      permissions: [],
    }
  }
}

const hideModal = () => {
  showModal.value = false
}

const resetForm = () => {
  form.value = {
    id: null,
    name: '',
    guard_name: 'web',
    permissions: [],
  }
}

const loadPermissions = async () => {
  try {
    const { data } = await axios.get('/api/role-permissions')
    allPermissions.value = data
  } catch (e) {
    allPermissions.value = []
  }
}

const submitForm = async () => {
  try {
    const payload = {
      name: form.value.name,
      guard_name: form.value.guard_name,
      permissions: form.value.permissions, // array of IDs
    }
    let url, method
    if (props.isEditing && form.value.id) {
      url = `/api/roles/${form.value.id}`
      method = 'put'
    } else {
      url = '/api/roles'
      method = 'post'
    }
    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Role ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
    resetForm()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to save role.', 'danger')
  }
}

defineExpose({ show })
</script>
