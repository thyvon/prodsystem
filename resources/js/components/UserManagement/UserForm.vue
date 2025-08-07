<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit User' : 'Create User' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-arrow-left"></i> Back
          </button>
        </div>

        <div class="card-body">
          <!-- User Details -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">👤 User Details</h5>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="name" class="font-weight-bold">Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  id="name"
                  required
                  placeholder="Enter full name"
                />
              </div>
              <div class="form-group col-md-4">
                <label for="email" class="font-weight-bold">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  class="form-control"
                  id="email"
                  required
                  placeholder="Enter email address"
                />
              </div>
              <div class="form-group col-md-4">
                <label for="password" class="font-weight-bold">Password</label>
                <input
                  v-model="form.password"
                  type="password"
                  class="form-control"
                  id="password"
                  :required="!isEditMode"
                  placeholder="Enter password"
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="card_number" class="font-weight-bold">Card Number</label>
                <input
                  v-model="form.card_number"
                  type="text"
                  class="form-control"
                  id="card_number"
                  placeholder="Enter card number"
                />
              </div>
              <div class="form-group col-md-4">
                <label for="phone" class="font-weight-bold">Phone</label>
                <input
                  v-model="form.phone"
                  type="text"
                  class="form-control"
                  id="phone"
                  placeholder="Enter phone number"
                />
              </div>
              <div class="form-group col-md-4">
                <label for="telegram_id" class="font-weight-bold">Telegram ID</label>
                <input
                  v-model="form.telegram_id"
                  type="text"
                  class="form-control"
                  id="telegram_id"
                  placeholder="Enter Telegram ID"
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="profile_url" class="font-weight-bold">Profile URL</label>
                <input
                  v-model="form.profile_url"
                  type="text"
                  class="form-control"
                  id="profile_url"
                  placeholder="Enter profile URL"
                />
              </div>
              <div class="form-group col-md-4">
                <label for="signature_url" class="font-weight-bold">Signature URL</label>
                <input
                  v-model="form.signature_url"
                  type="text"
                  class="form-control"
                  id="signature_url"
                  placeholder="Enter signature URL"
                />
              </div>
              <div class="form-group col-md-2">
                <label for="building_id" class="font-weight-bold">Building</label>
                <select
                  ref="buildingSelect"
                  v-model="form.building_id"
                  class="form-control"
                  id="building_id"
                >
                  <option value="">Select Building</option>
                  <option
                    v-for="building in buildings"
                    :key="building.id"
                    :value="building.id"
                  >
                    {{ building.short_name }}
                  </option>
                </select>
              </div>
              <div class="form-group col-md-2">
                <label for="is-active-checkbox" class="font-weight-bold">Active Status</label>
                <div class="custom-control custom-checkbox mb-2">
                  <input
                    class="custom-control-input"
                    type="checkbox"
                    id="is-active-checkbox"
                    v-model="isActiveChecked"
                  />
                  <label class="custom-control-label" for="is-active-checkbox">
                    Active
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Organizational Details -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏢 Organizational Details</h5>
            <!-- Departments -->
            <div class="border rounded p-3 mb-3">
              <h6 class="font-weight-bold mb-3">Departments</h6>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover">
                  <thead class="thead-light">
                    <tr>
                      <th style="min-width: 200px;">Department</th>
                      <th style="min-width: 100px;">Default</th>
                      <th style="min-width: 100px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(dept, index) in form.departments" :key="index">
                      <td>
                        <select
                          v-model="form.departments[index].id"
                          class="form-control department-select"
                          :data-row="index"
                          required
                        >
                          <option value="">Select Department</option>
                          <option
                            v-for="department in availableDepartments(index)"
                            :key="department.id"
                            :value="department.id"
                          >
                            {{ department.name }}
                          </option>
                        </select>
                      </td>
                      <td>
                        <div class="custom-control custom-checkbox">
                          <input
                            class="custom-control-input"
                            type="checkbox"
                            :id="`dept-default-${index}`"
                            v-model="form.departments[index].is_default"
                            :disabled="!form.departments[index].id"
                            @change="setDefaultDepartment(index)"
                          />
                          <label class="custom-control-label" :for="`dept-default-${index}`">
                            Default
                          </label>
                        </div>
                      </td>
                      <td>
                        <button
                          type="button"
                          class="btn btn-danger btn-sm"
                          @click="removeDepartment(index)"
                        >
                          <i class="fal fa-trash-alt"></i> Remove
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button
                type="button"
                class="btn btn-outline-primary btn-sm mt-2"
                @click="addDepartment"
              >
                <i class="fal fa-plus"></i> Add Department
              </button>
            </div>
            <!-- Campuses -->
            <div class="border rounded p-3 mb-3">
              <h6 class="font-weight-bold mb-3">Campuses</h6>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover">
                  <thead class="thead-light">
                    <tr>
                      <th style="min-width: 200px;">Campus</th>
                      <th style="min-width: 100px;">Default</th>
                      <th style="min-width: 100px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(campus, index) in form.campus" :key="index">
                      <td>
                        <select
                          v-model="form.campus[index].id"
                          class="form-control campus-select"
                          :data-row="index"
                          required
                        >
                          <option value="">Select Campus</option>
                          <option
                            v-for="campusOption in availableCampuses(index)"
                            :key="campusOption.id"
                            :value="campusOption.id"
                          >
                            {{ campusOption.name }}
                          </option>
                        </select>
                      </td>
                      <td>
                        <div class="custom-control custom-checkbox">
                          <input
                            class="custom-control-input"
                            type="checkbox"
                            :id="`campus-default-${index}`"
                            v-model="form.campus[index].is_default"
                            :disabled="!form.campus[index].id"
                            @change="setDefaultCampus(index)"
                          />
                          <label class="custom-control-label" :for="`campus-default-${index}`">
                            Default
                          </label>
                        </div>
                      </td>
                      <td>
                        <button
                          type="button"
                          class="btn btn-danger btn-sm"
                          @click="removeCampus(index)"
                        >
                          <i class="fal fa-trash-alt"></i> Remove
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button
                type="button"
                class="btn btn-outline-primary btn-sm mt-2"
                @click="addCampus"
              >
                <i class="fal fa-plus"></i> Add Campus
              </button>
            </div>
            <!-- Warehouses -->
            <div class="border rounded p-3 mb-3">
              <h6 class="font-weight-bold mb-3">Warehouses</h6>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover">
                  <thead class="thead-light">
                    <tr>
                      <th style="min-width: 200px;">Warehouse</th>
                      <th style="min-width: 100px;">Default</th>
                      <th style="min-width: 100px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(warehouse, index) in form.warehouses" :key="index">
                      <td>
                        <select
                          v-model="form.warehouses[index].id"
                          class="form-control warehouse-select"
                          :data-row="index"
                        >
                          <option value="">Select Warehouse</option>
                          <option
                            v-for="warehouseOption in availableWarehouses(index)"
                            :key="warehouseOption.id"
                            :value="warehouseOption.id"
                          >
                            {{ warehouseOption.name }}
                          </option>
                        </select>
                      </td>
                      <td>
                        <div class="custom-control custom-checkbox">
                          <input
                            class="custom-control-input"
                            type="checkbox"
                            :id="`warehouse-default-${index}`"
                            v-model="form.warehouses[index].is_default"
                            :disabled="!form.warehouses[index].id"
                            @change="setDefaultWarehouse(index)"
                          />
                          <label class="custom-control-label" :for="`warehouse-default-${index}`">
                            Default
                          </label>
                        </div>
                      </td>
                      <td>
                        <button
                          type="button"
                          class="btn btn-danger btn-sm"
                          @click="removeWarehouse(index)"
                        >
                          <i class="fal fa-trash-alt"></i> Remove
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button
                type="button"
                class="btn btn-outline-primary btn-sm mt-2"
                @click="addWarehouse"
              >
                <i class="fal fa-plus"></i> Add Warehouse
              </button>
            </div>
            <!-- Positions -->
            <div class="border rounded p-3 mb-3">
              <h6 class="font-weight-bold mb-3">Positions</h6>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover">
                  <thead class="thead-light">
                    <tr>
                      <th style="min-width: 200px;">Position</th>
                      <th style="min-width: 100px;">Default</th>
                      <th style="min-width: 100px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(position, index) in form.positions" :key="index">
                      <td>
                        <select
                          v-model="form.positions[index].id"
                          class="form-control position-select"
                          :data-row="index"
                        >
                          <option value="">Select Position</option>
                          <option
                            v-for="positionOption in availablePositions(index)"
                            :key="positionOption.id"
                            :value="positionOption.id"
                          >
                            {{ positionOption.title }}
                          </option>
                        </select>
                      </td>
                      <td>
                        <div class="custom-control custom-checkbox">
                          <input
                            class="custom-control-input"
                            type="checkbox"
                            :id="`position-default-${index}`"
                            v-model="form.positions[index].is_default"
                            :disabled="!form.positions[index].id"
                            @change="setDefaultPosition(index)"
                          />
                          <label class="custom-control-label" :for="`position-default-${index}`">
                            Default
                          </label>
                        </div>
                      </td>
                      <td>
                        <button
                          type="button"
                          class="btn btn-danger btn-sm"
                          @click="removePosition(index)"
                        >
                          <i class="fal fa-trash-alt"></i> Remove
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button
                type="button"
                class="btn btn-outline-primary btn-sm mt-2"
                @click="addPosition"
              >
                <i class="fal fa-plus"></i> Add Position
              </button>
            </div>
          </div>

          <!-- Role Assignments -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🛡️ Role Assignments</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 200px;">Role</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(role, index) in form.roles" :key="index">
                    <td>
                      <select
                        v-model="form.roles[index]"
                        class="form-control role-select"
                        :data-row="index"
                        required
                      >
                        <option value="">Select Role</option>
                        <option
                          v-for="roleOption in availableRoles"
                          :key="roleOption"
                          :value="roleOption"
                          :disabled="form.roles.includes(roleOption) && form.roles[index] !== roleOption"
                        >
                          {{ roleOption }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeRole(index)"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button
              type="button"
              class="btn btn-outline-primary btn-sm mt-2"
              @click="addRole"
            >
              <i class="fal fa-plus"></i> Add Role
            </button>
          </div>

          <!-- Permission Assignments -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🔑 Permission Assignments</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 200px;">Permission</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(permission, index) in form.permissions" :key="index">
                    <td>
                      <select
                        v-model="form.permissions[index]"
                        class="form-control permission-select"
                        :data-row="index"
                        required
                      >
                        <option value="">Select Permission</option>
                        <option
                          v-for="permissionOption in availablePermissions"
                          :key="permissionOption"
                          :value="permissionOption"
                          :disabled="form.permissions.includes(permissionOption) && form.permissions[index] !== permissionOption"
                        >
                          {{ permissionOption }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removePermission(index)"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button
              type="button"
              class="btn btn-outline-primary btn-sm mt-2"
              @click="addPermission"
            >
              <i class="fal fa-plus"></i> Add Permission
            </button>
          </div>

          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting"
            >
              <span
                v-if="isSubmitting"
                class="spinner-border spinner-border-sm mr-1"
                role="status"
                aria-hidden="true"
              ></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              @click="goToIndex"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import axios from 'axios';
import { showAlert } from '@/Utils/bootbox';
import { initSelect2, destroySelect2 } from '@/Utils/select2';

// Props
const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
});

// Emits
const emit = defineEmits(['submitted']);

// Reactive state
const isSubmitting = ref(false);
const buildings = ref([]);
const departments = ref([]);
const campus = ref([]);
const positions = ref([]);
const warehouses = ref([]);
const availableRoles = ref([]);
const availablePermissions = ref([]);
const buildingSelect = ref(null);
const isEditMode = ref(!!props.initialData?.user?.id);
const userId = ref(props.initialData?.user?.id || null);
const isAddingRole = ref(false);
const isAddingPermission = ref(false);
const isAddingDepartment = ref(false);
const isAddingCampus = ref(false);
const isAddingWarehouse = ref(false);
const isAddingPosition = ref(false);

// Form state
const form = ref({
  name: '',
  email: '',
  password: '',
  card_number: null,
  profile_url: null,
  signature_url: null,
  telegram_id: null,
  phone: null,
  is_active: 1,
  building_id: null,
  departments: [],
  campus: [],
  warehouses: [],
  positions: [],
  email_verified_at: null,
  roles: [],
  permissions: [],
});

// Computed property for is_active checkbox
const isActiveChecked = computed({
  get: () => form.value.is_active === 1,
  set: (value) => {
    form.value.is_active = value ? 1 : 0;
  },
});

// Computed properties for unique selections
const availableDepartments = computed(() => (index) => {
  const selectedIds = form.value.departments
    .filter((_, i) => i !== index)
    .map((dept) => dept.id)
    .filter((id) => id);
  return departments.value.filter((dept) => !selectedIds.includes(dept.id));
});

const availableCampuses = computed(() => (index) => {
  const selectedIds = form.value.campus
    .filter((_, i) => i !== index)
    .map((campus) => campus.id)
    .filter((id) => id);
  return campus.value.filter((campus) => !selectedIds.includes(campus.id));
});

const availableWarehouses = computed(() => (index) => {
  const selectedIds = form.value.warehouses
    .filter((_, i) => i !== index)
    .map((warehouse) => warehouse.id)
    .filter((id) => id);
  return warehouses.value.filter((warehouse) => !selectedIds.includes(warehouse.id));
});

const availablePositions = computed(() => (index) => {
  const selectedIds = form.value.positions
    .filter((_, i) => i !== index)
    .map((position) => position.id)
    .filter((id) => id);
  return positions.value.filter((position) => !selectedIds.includes(position.id));
});

// Navigation
const goToIndex = () => {
  window.location.href = '/users';
};

// Data fetching
const fetchBuildings = async () => {
  try {
    const response = await axios.get('/api/users/buildings');
    buildings.value = Array.isArray(response.data) ? response.data : response.data.data;
  } catch (err) {
    console.error('Failed to load buildings:', err);
    showAlert('Error', 'Failed to load buildings.', 'danger');
  }
};

const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/users/departments');
    departments.value = Array.isArray(response.data.data) ? response.data.data : response.data;
  } catch (err) {
    console.error('Failed to load departments:', err);
    showAlert('Error', 'Failed to load departments.', 'danger');
  }
};

const fetchCampuses = async () => {
  try {
    const response = await axios.get('/api/campuses');
    campus.value = Array.isArray(response.data.data) ? response.data.data : response.data;
  } catch (err) {
    console.error('Failed to load campuses:', err);
    showAlert('Error', 'Failed to load campuses.', 'danger');
  }
};

const fetchWarehouses = async () => {
  try {
    const response = await axios.get('/api/users/warehouses');
    warehouses.value = Array.isArray(response.data.data) ? response.data.data : response.data;
  } catch (err) {
    console.error('Failed to load warehouses:', err);
    showAlert('Error', 'Failed to load warehouses.', 'danger');
  }
};

const fetchPositions = async () => {
  try {
    const response = await axios.get('/api/users/positions');
    positions.value = Array.isArray(response.data.data) ? response.data.data : response.data;
  } catch (err) {
    console.error('Failed to load positions:', err);
    showAlert('Error', 'Failed to load positions.', 'danger');
  }
};

const fetchRoles = async () => {
  try {
    const response = await axios.get('/api/roles-name');
    availableRoles.value = Array.isArray(response.data) ? response.data : response.data.data;
  } catch (err) {
    console.error('Failed to load roles:', err);
    showAlert('Error', 'Failed to load roles.', 'danger');
  }
};

const fetchPermissions = async () => {
  try {
    const response = await axios.get('/api/permissions-name');
    availablePermissions.value = Array.isArray(response.data) ? response.data : response.data.data;
  } catch (err) {
    console.error('Failed to load permissions:', err);
    showAlert('Error', 'Failed to load permissions.', 'danger');
  }
};

// Department methods
const addDepartment = async () => {
  if (isAddingDepartment.value) return;
  isAddingDepartment.value = true;
  try {
    form.value.departments.push({ id: '', is_default: false });
    await nextTick();
    const index = form.value.departments.length - 1;
    const departmentSelect = document.querySelector(`.department-select[data-row="${index}"]`);
    if (!departmentSelect) {
      console.warn(`DOM element for department row ${index} not found`);
      showAlert('Error', 'Failed to initialize department dropdown.', 'danger');
      return;
    }
    initSelect2(departmentSelect, {
      placeholder: 'Select Department',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.departments[index].id = value ? Number(value) : '';
    });
    $(departmentSelect).val(form.value.departments[index].id || '').trigger('change.select2');
  } catch (err) {
    console.error('Error adding department:', err);
    showAlert('Error', 'Failed to add department assignment.', 'danger');
  } finally {
    isAddingDepartment.value = false;
  }
};

const removeDepartment = async (index) => {
  try {
    const departmentSelect = document.querySelector(`.department-select[data-row="${index}"]`);
    if (departmentSelect) destroySelect2(departmentSelect);
    form.value.departments.splice(index, 1);
  } catch (err) {
    console.error('Error removing department:', err);
    showAlert('Error', 'Failed to remove department assignment.', 'danger');
  }
};

const setDefaultDepartment = (index) => {
  form.value.departments.forEach((dept, i) => {
    dept.is_default = i === index && form.value.departments[index].is_default;
  });
};

// Campus methods
const addCampus = async () => {
  if (isAddingCampus.value) return;
  isAddingCampus.value = true;
  try {
    form.value.campus.push({ id: '', is_default: false });
    await nextTick();
    const index = form.value.campus.length - 1;
    const campusSelect = document.querySelector(`.campus-select[data-row="${index}"]`);
    if (!campusSelect) {
      console.warn(`DOM element for campus row ${index} not found`);
      showAlert('Error', 'Failed to initialize campus dropdown.', 'danger');
      return;
    }
    initSelect2(campusSelect, {
      placeholder: 'Select Campus',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.campus[index].id = value ? Number(value) : '';
    });
    $(campusSelect).val(form.value.campus[index].id || '').trigger('change.select2');
  } catch (err) {
    console.error('Error adding campus:', err);
    showAlert('Error', 'Failed to add campus assignment.', 'danger');
  } finally {
    isAddingCampus.value = false;
  }
};

const removeCampus = async (index) => {
  try {
    const campusSelect = document.querySelector(`.campus-select[data-row="${index}"]`);
    if (campusSelect) destroySelect2(campusSelect);
    form.value.campus.splice(index, 1);
  } catch (err) {
    console.error('Error removing campus:', err);
    showAlert('Error', 'Failed to remove campus assignment.', 'danger');
  }
};

const setDefaultCampus = (index) => {
  form.value.campus.forEach((campus, i) => {
    campus.is_default = i === index && form.value.campus[index].is_default;
  });
};

const addWarehouse = async () => {
  if (isAddingWarehouse.value) return;
  isAddingWarehouse.value = true;
  try {
    form.value.warehouses.push({ id: '', is_default: false });
    await nextTick();
    const index = form.value.warehouses.length - 1;
    const warehouseSelect = document.querySelector(`.warehouse-select[data-row="${index}"]`);
    if (!warehouseSelect) {
      console.warn(`DOM element for warehouse row ${index} not found`);
      showAlert('Error', 'Failed to initialize warehouse dropdown.', 'danger');
      return;
    }
    initSelect2(warehouseSelect, {
      placeholder: 'Select Warehouse',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.warehouses[index].id = value ? Number(value) : '';
    });
    $(warehouseSelect).val(form.value.warehouses[index].id || '').trigger('change.select2');
  } catch (err) {
    console.error('Error adding warehouse:', err);
    showAlert('Error', 'Failed to add warehouse assignment.', 'danger');
  } finally {
    isAddingWarehouse.value = false;
  }
}

const removeWarehouse = async (index) => {
  try {
    const warehouseSelect = document.querySelector(`.warehouse-select[data-row="${index}"]`);
    if (warehouseSelect) destroySelect2(warehouseSelect);
    form.value.warehouses.splice(index, 1);
  } catch (err) {
    console.error('Error removing warehouse:', err);
    showAlert('Error', 'Failed to remove warehouse assignment.', 'danger');
  }
}

const setDefaultWarehouse = (index) => {
  form.value.warehouses.forEach((warehouse, i) => {
    warehouse.is_default = i === index && form.value.warehouses[index].is_default;
  });
}

// Position methods
const addPosition = async () => {
  if (isAddingPosition.value) return;
  isAddingPosition.value = true;
  try {
    form.value.positions.push({ id: '', is_default: false });
    await nextTick();
    const index = form.value.positions.length - 1;
    const positionSelect = document.querySelector(`.position-select[data-row="${index}"]`);
    if (!positionSelect) {
      console.warn(`DOM element for position row ${index} not found`);
      showAlert('Error', 'Failed to initialize position dropdown.', 'danger');
      return;
    }
    initSelect2(positionSelect, {
      placeholder: 'Select Position',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.positions[index].id = value ? Number(value) : '';
    });
    $(positionSelect).val(form.value.positions[index].id || '').trigger('change.select2');
  } catch (err) {
    console.error('Error adding position:', err);
    showAlert('Error', 'Failed to add position assignment.', 'danger');
  } finally {
    isAddingPosition.value = false;
  }
};

const removePosition = async (index) => {
  try {
    const positionSelect = document.querySelector(`.position-select[data-row="${index}"]`);
    if (positionSelect) destroySelect2(positionSelect);
    form.value.positions.splice(index, 1);
  } catch (err) {
    console.error('Error removing position:', err);
    showAlert('Error', 'Failed to remove position assignment.', 'danger');
  }
};

const setDefaultPosition = (index) => {
  form.value.positions.forEach((position, i) => {
    position.is_default = i === index && form.value.positions[index].is_default;
  });
};

// Role methods
const addRole = async () => {
  if (isAddingRole.value) return;
  isAddingRole.value = true;
  try {
    form.value.roles.push('');
    await nextTick();
    const index = form.value.roles.length - 1;
    const roleSelect = document.querySelector(`.role-select[data-row="${index}"]`);
    if (!roleSelect) {
      console.warn(`DOM element for role row ${index} not found`);
      showAlert('Error', 'Failed to initialize role dropdown.', 'danger');
      return;
    }
    initSelect2(roleSelect, {
      placeholder: 'Select Role',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.roles[index] = value || '';
    });
    $(roleSelect).val(form.value.roles[index] || '').trigger('change.select2');
  } catch (err) {
    console.error('Error adding role:', err);
    showAlert('Error', 'Failed to add role assignment.', 'danger');
  } finally {
    isAddingRole.value = false;
  }
};

const removeRole = async (index) => {
  try {
    const roleSelect = document.querySelector(`.role-select[data-row="${index}"]`);
    if (roleSelect) destroySelect2(roleSelect);
    form.value.roles.splice(index, 1);
  } catch (err) {
    console.error('Error removing role:', err);
    showAlert('Error', 'Failed to remove role assignment.', 'danger');
  }
};

// Permission methods
const addPermission = async () => {
  if (isAddingPermission.value) return;
  isAddingPermission.value = true;
  try {
    form.value.permissions.push('');
    await nextTick();
    const index = form.value.permissions.length - 1;
    const permissionSelect = document.querySelector(`.permission-select[data-row="${index}"]`);
    if (!permissionSelect) {
      console.warn(`DOM element for permission row ${index} not found`);
      showAlert('Error', 'Failed to initialize permission dropdown.', 'danger');
      return;
    }
    initSelect2(permissionSelect, {
      placeholder: 'Select Permission',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.permissions[index] = value || '';
    });
    $(permissionSelect).val(form.value.permissions[index] || '').trigger('change.select2');
  } catch (err) {
    console.error('Error adding permission:', err);
    showAlert('Error', 'Failed to add permission assignment.', 'danger');
  } finally {
    isAddingPermission.value = false;
  }
};

const removePermission = async (index) => {
  try {
    const permissionSelect = document.querySelector(`.permission-select[data-row="${index}"]`);
    if (permissionSelect) destroySelect2(permissionSelect);
    form.value.permissions.splice(index, 1);
  } catch (err) {
    console.error('Error removing permission:', err);
    showAlert('Error', 'Failed to remove permission assignment.', 'danger');
  }
};

// Form validation
const validateForm = () => {
  if (!form.value.name.trim()) {
    showAlert('Error', 'Name is required.', 'danger');
    return false;
  }
  if (!form.value.email.trim()) {
    showAlert('Error', 'Email is required.', 'danger');
    return false;
  }
  if (!isEditMode.value && !form.value.password.trim()) {
    showAlert('Error', 'Password is required for new users.', 'danger');
    return false;
  }
  if (form.value.departments.some((dept) => !dept.id)) {
    showAlert('Error', 'All department assignments must have a department selected.', 'danger');
    return false;
  }
  if (form.value.campus.some((campus) => !campus.id)) {
    showAlert('Error', 'All campus assignments must have a campus selected.', 'danger');
    return false;
  }
  if (form.value.warehouses.some((warehouse) => !warehouse.id)) {
    showAlert('Error', 'All warehouse assignments must have a warehouse selected.', 'danger');
    return false;
  }
  if (form.value.positions.some((position) => !position.id)) {
    showAlert('Error', 'All position assignments must have a position selected.', 'danger');
    return false;
  }
  if (form.value.roles.some((role) => !role)) {
    showAlert('Error', 'All role assignments must have a role selected.', 'danger');
    return false;
  }
  if (form.value.permissions.some((permission) => !permission)) {
    showAlert('Error', 'All permission assignments must have a permission selected.', 'danger');
    return false;
  }
  // Check for duplicate departments
  const deptIds = form.value.departments.map((dept) => dept.id).filter((id) => id);
  if (new Set(deptIds).size !== deptIds.length) {
    showAlert('Error', 'Duplicate department selections are not allowed.', 'danger');
    return false;
  }
  // Check for duplicate campuses
  const campusIds = form.value.campus.map((campus) => campus.id).filter((id) => id);
  if (new Set(campusIds).size !== campusIds.length) {
    showAlert('Error', 'Duplicate campus selections are not allowed.', 'danger');
    return false;
  }

  // Check for duplicate warehouses
  const warehouseIds = form.value.warehouses.map((warehouse) => warehouse.id).filter((id) => id);
  if (new Set(warehouseIds).size !== warehouseIds.length) {
    showAlert('Error', 'Duplicate warehouse selections are not allowed.', 'danger');
    return false;
  }

  // Check for duplicate positions
  const positionIds = form.value.positions.map((position) => position.id).filter((id) => id);
  if (new Set(positionIds).size !== positionIds.length) {
    showAlert('Error', 'Duplicate position selections are not allowed.', 'danger');
    return false;
  }
  // Check for multiple default departments
  const defaultDepts = form.value.departments.filter((dept) => dept.is_default);
  if (defaultDepts.length > 1) {
    showAlert('Error', 'Only one department can be set as default.', 'danger');
    return false;
  }
  // Check for multiple default campuses
  const defaultCampuses = form.value.campus.filter((campus) => campus.is_default);
  if (defaultCampuses.length > 1) {
    showAlert('Error', 'Only one campus can be set as default.', 'danger');
    return false;
  }

  // Check for multiple default warehouses
  const defaultWarehouses = form.value.warehouses.filter((warehouse) => warehouse.is_default);
  if (defaultWarehouses.length > 1) {
    showAlert('Error', 'Only one warehouse can be set as default.', 'danger');
    return false;
  }

  // Check for multiple default positions
  const defaultPositions = form.value.positions.filter((position) => position.is_default);
  if (defaultPositions.length > 1) {
    showAlert('Error', 'Only one position can be set as default.', 'danger');
    return false;
  }
  return true;
};

// Form submission
const submitForm = async () => {
  if (isSubmitting.value) return;
  if (!validateForm()) return;
  isSubmitting.value = true;
  try {
    const payload = {
      name: form.value.name.trim(),
      email: form.value.email.trim(),
      password: form.value.password ? form.value.password.trim() : undefined,
      card_number: form.value.card_number?.trim() || null,
      profile_url: form.value.profile_url?.trim() || null,
      signature_url: form.value.signature_url?.trim() || null,
      telegram_id: form.value.telegram_id?.trim() || null,
      phone: form.value.phone?.trim() || null,
      is_active: form.value.is_active,
      building_id: form.value.building_id ? Number(form.value.building_id) : null,
      departments: form.value.departments,
      campus: form.value.campus,
      warehouses: form.value.warehouses,
      positions: form.value.positions,
      roles: form.value.roles,
      permissions: form.value.permissions,
    };
    const url = isEditMode.value ? `/api/users/${userId.value}` : '/api/users';
    const method = isEditMode.value ? 'put' : 'post';
    await axios[method](url, payload);
    await showAlert('Success', isEditMode.value ? 'User updated successfully.' : 'User created successfully.', 'success');
    emit('submitted');
    goToIndex();
  } catch (err) {
    console.error('Submit error:', err.response?.data || err);
    await showAlert('Error', err.response?.data?.message || err.message || 'Failed to save user.', 'danger');
  } finally {
    isSubmitting.value = false;
  }
};

// Lifecycle hooks
onMounted(async () => {
  try {
    // Initialize form with initialData.user
    if (props.initialData?.user?.id) {
      form.value = {
        name: props.initialData.user.name || '',
        email: props.initialData.user.email || '',
        password: '',
        card_number: props.initialData.user.card_number || null,
        profile_url: props.initialData.user.profile_url || null,
        signature_url: props.initialData.user.signature_url || null,
        telegram_id: props.initialData.user.telegram_id || null,
        phone: props.initialData.user.phone || null,
        is_active: props.initialData.user.is_active !== undefined ? parseInt(props.initialData.user.is_active) : 1,
        building_id: props.initialData.user.building_id || null,
        departments: props.initialData.user.departments?.map((d) => ({
          id: d.id,
          is_default: d.is_default || d.pivot?.is_default || false,
        })) || [],
        campus: props.initialData.user.campus?.map((c) => ({
          id: c.id,
          is_default: c.is_default || c.pivot?.is_default || false,
        })) || [],
        warehouses: props.initialData.user.warehouses?.map((w) => ({
          id: w.id,
          is_default: w.is_default || w.pivot?.is_default || false,
        })) || [],
        positions: props.initialData.user.positions?.map((p) => ({
          id: p.id,
          is_default: p.is_default || p.pivot?.is_default || false,
        })) || [],
        email_verified_at: props.initialData.user.email_verified_at || null,
        roles: props.initialData.user.roles || [],
        permissions: props.initialData.user.permissions || [],
      };
      userId.value = props.initialData.user.id;
      isEditMode.value = true;
    }

    // Fetch data
    await Promise.all([
      fetchBuildings(),
      fetchDepartments(),
      fetchCampuses(),
      fetchWarehouses(),
      fetchPositions(),
      fetchRoles(),
      fetchPermissions(),
    ]);

    await nextTick();

    // Initialize Select2 for building
    if (buildingSelect.value) {
      initSelect2(buildingSelect.value, {
        placeholder: 'Select Building',
        width: '100%',
        allowClear: true,
      }, (v) => {
        form.value.building_id = v ? Number(v) : null;
      });
      $(buildingSelect.value).val(form.value.building_id || '').trigger('change.select2');
    } else {
      console.warn('Building select element not found');
    }

    // Initialize Select2 for departments
    form.value.departments.forEach((dept, index) => {
      const departmentSelect = document.querySelector(`.department-select[data-row="${index}"]`);
      if (departmentSelect) {
        initSelect2(departmentSelect, {
          placeholder: 'Select Department',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.departments[index].id = value ? Number(value) : '';
        });
        $(departmentSelect).val(dept.id || '').trigger('change.select2');
      } else {
        console.warn(`Department select element for row ${index} not found`);
      }
    });

    // Initialize Select2 for campuses
    form.value.campus.forEach((campus, index) => {
      const campusSelect = document.querySelector(`.campus-select[data-row="${index}"]`);
      if (campusSelect) {
        initSelect2(campusSelect, {
          placeholder: 'Select Campus',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.campus[index].id = value ? Number(value) : '';
        });
        $(campusSelect).val(campus.id || '').trigger('change.select2');
      } else {
        console.warn(`Campus select element for row ${index} not found`);
      }
    });

    // Initialize Select2 for warehouses
    form.value.warehouses.forEach((warehouse, index) => {
      const warehouseSelect = document.querySelector(`.warehouse-select[data-row="${index}"]`);
      if (warehouseSelect) {
        initSelect2(warehouseSelect, {
          placeholder: 'Select Warehouse',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.warehouses[index].id = value ? Number(value) : '';
        });
        $(warehouseSelect).val(warehouse.id || '').trigger('change.select2');
      } else {
        console.warn(`Warehouse select element for row ${index} not found`);
      }
    });

    // Initialize Select2 for positions
    form.value.positions.forEach((position, index) => {
      const positionSelect = document.querySelector(`.position-select[data-row="${index}"]`);
      if (positionSelect) {
        initSelect2(positionSelect, {
          placeholder: 'Select Position',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.positions[index].id = value ? Number(value) : '';
        });
        $(positionSelect).val(position.id || '').trigger('change.select2');
      } else {
        console.warn(`Position select element for row ${index} not found`);
      }
    });

    // Initialize Select2 for roles
    form.value.roles.forEach((role, index) => {
      const roleSelect = document.querySelector(`.role-select[data-row="${index}"]`);
      if (roleSelect) {
        initSelect2(roleSelect, {
          placeholder: 'Select Role',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.roles[index] = value || '';
        });
        $(roleSelect).val(role || '').trigger('change.select2');
      } else {
        console.warn(`Role select element for row ${index} not found`);
      }
    });

    // Initialize Select2 for permissions
    form.value.permissions.forEach((permission, index) => {
      const permissionSelect = document.querySelector(`.permission-select[data-row="${index}"]`);
      if (permissionSelect) {
        initSelect2(permissionSelect, {
          placeholder: 'Select Permission',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.permissions[index] = value || '';
        });
        $(permissionSelect).val(permission || '').trigger('change.select2');
      } else {
        console.warn(`Permission select element for row ${index} not found`);
      }
    });
  } catch (err) {
    console.error('Error in onMounted:', err);
    showAlert('Error', 'Failed to initialize form.', 'danger');
  }
});

onUnmounted(() => {
  try {
    if (buildingSelect.value) destroySelect2(buildingSelect.value);
    document.querySelectorAll('.department-select').forEach((el) => destroySelect2(el));
    document.querySelectorAll('.campus-select').forEach((el) => destroySelect2(el));
    document.querySelectorAll('.warehouse-select').forEach((el) => destroySelect2(el));
    document.querySelectorAll('.position-select').forEach((el) => destroySelect2(el));
    document.querySelectorAll('.role-select').forEach((el) => destroySelect2(el));
    document.querySelectorAll('.permission-select').forEach((el) => destroySelect2(el));
  } catch (err) {
    console.error('Error in onUnmounted:', err);
  }
});
</script>

<style scoped>
.card-header {
  border-bottom: 1px solid #e3e6f0;
}
.btn-icon {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.form-control.is-invalid {
  border-color: #dc3545;
}
.invalid-feedback {
  display: none;
  color: #dc3545;
}
.form-control.is-invalid ~ .invalid-feedback {
  display: block;
}
</style>