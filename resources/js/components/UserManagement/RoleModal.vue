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
          <div v-for="(group, featureName, index) in groupedPermissions" :key="featureName" class="mb-4">
            <div class="d-flex align-items-center mb-2">
              <h5 class="text-primary mb-0">{{ featureName || 'Uncategorized' }}</h5>
              <button
                type="button"
                class="btn btn-outline-primary btn-sm ms-3 ml-2"
                @click="toggleSelectAll(featureName)"
              >
                {{ isAllSelected(featureName) ? 'Deselect All' : 'Select All' }}
              </button>
            </div>
            <div class="row">
              <div
                v-for="(permission, idx) in group"
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
            <hr v-if="index < Object.keys(groupedPermissions).length - 1" class="my-3" />
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
import { ref, computed } from 'vue';
import axios from 'axios';
import BaseModal from '@/components/Reusable/BaseModal.vue';
import { showAlert } from '@/Utils/bootbox';

const props = defineProps({
  isEditing: Boolean,
});
const emit = defineEmits(['submitted']);

const showModal = ref(false);
const allPermissions = ref([]);
const form = ref({
  id: null,
  name: '',
  guard_name: 'web',
  permissions: [],
});

// Group permissions by feature_name
const groupedPermissions = computed(() => {
  const grouped = {};
  allPermissions.value.forEach((permission) => {
    const featureName = permission.feature_name || 'Uncategorized';
    if (!grouped[featureName]) {
      grouped[featureName] = [];
    }
    grouped[featureName].push(permission);
  });
  return grouped;
});

// Check if all permissions in a feature group are selected
const isAllSelected = (featureName) => {
  const group = groupedPermissions.value[featureName] || [];
  return group.length > 0 && group.every(permission => form.value.permissions.includes(Number(permission.id)));
};

// Toggle select/deselect all permissions for a feature group
const toggleSelectAll = (featureName) => {
  const group = groupedPermissions.value[featureName] || [];
  const permissionIds = group.map(permission => Number(permission.id));
  if (isAllSelected(featureName)) {
    // Deselect all
    form.value.permissions = form.value.permissions.filter(id => !permissionIds.includes(id));
  } else {
    // Select all
    form.value.permissions = [...new Set([...form.value.permissions, ...permissionIds])];
  }
};

const show = async (role = {}) => {
  showModal.value = true;
  await loadPermissions();
  if (role.id) {
    const { data } = await axios.get(`/api/roles/${role.id}/permissions`);
    form.value = {
      id: data.id,
      name: data.name,
      guard_name: data.guard_name,
      permissions: data.permissions ? data.permissions.map(id => Number(id)) : [],
    };
  } else {
    form.value = {
      id: null,
      name: '',
      guard_name: 'web',
      permissions: [],
    };
  }
};

const hideModal = () => {
  showModal.value = false;
};

const resetForm = () => {
  form.value = {
    id: null,
    name: '',
    guard_name: 'web',
    permissions: [],
  };
};

const loadPermissions = async () => {
  try {
    const { data } = await axios.get('/api/role-permissions');
    allPermissions.value = data;
  } catch (e) {
    allPermissions.value = [];
    showAlert('Error', 'Failed to load permissions.', 'danger');
  }
};

const submitForm = async () => {
  try {
    const payload = {
      name: form.value.name,
      guard_name: form.value.guard_name,
      permissions: form.value.permissions,
    };
    let url, method;
    if (props.isEditing && form.value.id) {
      url = `/api/roles/${form.value.id}`;
      method = 'put';
    } else {
      url = '/api/roles';
      method = 'post';
    }
    await axios[method](url, payload);
    emit('submitted');
    hideModal();
    showAlert('Success', `Role ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success');
    resetForm();
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to save role.', 'danger');
  }
};

defineExpose({ show });
</script>