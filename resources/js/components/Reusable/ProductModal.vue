<template>
  <div class="modal fade" id="productModal" tabindex="-1" role="dialog" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="productModalLabel">
            <i class="fal fa-search mr-2"></i>Select Product
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table id="productsTable" class="table table-bordered table-sm table-hover">
              <thead class="thead-light">
                <tr>
                  <th style="min-width: 100px;">Code</th>
                  <th style="min-width: 300px;">Product Name</th>
                  <th style="min-width: 150px;">Description</th>
                  <th style="min-width: 80px;">UoM</th>
                  <th style="min-width: 100px;">Unit Price</th>
                  <th style="min-width: 80px;">Select</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const emit = defineEmits(['productSelected'])
const products = ref([])
let table = ref(null)

const fetchProducts = async () => {
  try {
    const response = await axios.get('/api/digital-docs-approvals/get-products')
    products.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load products:', err)
  }
}

const selectProduct = (productId) => {
  const product = products.value.find(p => p.id === productId)
  if (product) {
    emit('productSelected', product)
    $('#productModal').modal('hide')
  }
}

onMounted(async () => {
  await fetchProducts()
  
  table.value = $('#productsTable').DataTable({
    data: products.value,
    responsive: true,
    pageLength: 25,
    columns: [
      { data: 'item_code', name: 'item_code' },
      { data: 'product_name', name: 'product_name' },
      { data: 'description', name: 'description' },
      { data: 'unit_name', name: 'unit_name' },
      { 
        data: 'unit_price', 
        name: 'unit_price',
        render: (data) => `$${parseFloat(data || 0).toFixed(2)}`
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        render: () => `
          <button type="button" class="btn btn-primary btn-sm select-product-btn">
            <i class="fal fa-check"></i> Select
          </button>
        `
      }
    ],
    drawCallback: function() {
      $('.select-product-btn').off('click').on('click', function() {
        const row = table.value.row($(this).closest('tr'))
        const data = row.data()
        selectProduct(data.id)
      })
    }
  })
})

onUnmounted(() => {
  if (table.value) {
    table.value.destroy()
  }
})
</script>