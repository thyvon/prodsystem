<template>
  <div>
    <h2>Products</h2>
    <form @submit.prevent="addProduct">
      <input v-model="newProduct.name" placeholder="Product Name" required />
      <input v-model="newProduct.description" placeholder="Description" />
      <input v-model="newProduct.price" placeholder="Price" type="number" required />
      <select v-model="newProduct.supplier_id" required>
        <option disabled value="">Select Supplier</option>
        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
          {{ supplier.name }}
        </option>
      </select>
      <button type="submit">Add Product</button>
    </form>

    <ul>
      <li v-for="product in products" :key="product.id">
        {{ product.name }} - {{ product.supplier.name }}
        <button @click="deleteProduct(product.id)">Delete</button>
      </li>
    </ul>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      products: [],
      suppliers: [],
      newProduct: {
        name: '',
        description: '',
        price: '',
        supplier_id: ''
      }
    };
  },
  methods: {
    fetchProducts() {
      axios.get('/api/products').then(response => {
        this.products = response.data;
      });
    },
    fetchSuppliers() {
      axios.get('/api/suppliers').then(response => {
        this.suppliers = response.data;
      });
    },
    addProduct() {
      axios.post('/api/products', this.newProduct).then(() => {
        this.fetchProducts();
        this.newProduct = { name: '', description: '', price: '', supplier_id: '' }; // Reset form
      });
    },
    deleteProduct(id) {
      axios.delete(`/api/products/${id}`).then(() => {
        this.fetchProducts();
      });
    }
  },
  mounted() {
    this.fetchProducts();
    this.fetchSuppliers();
  }
};
</script>

<style scoped>
/* Add your styles here */
</style>
