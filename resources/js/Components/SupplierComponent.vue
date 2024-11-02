<template>
  <div>
    <h2>Suppliers</h2>
    <form @submit.prevent="addSupplier">
      <input v-model="newSupplier.name" placeholder="Supplier Name" required />
      <input v-model="newSupplier.contact_person" placeholder="Contact Person" />
      <input v-model="newSupplier.email" placeholder="Email" />
      <input v-model="newSupplier.phone" placeholder="Phone" />
      <button type="submit">Add Supplier</button>
    </form>
    
    <ul>
      <li v-for="supplier in suppliers" :key="supplier.id">
        {{ supplier.name }} 
        <button @click="deleteSupplier(supplier.id)">Delete</button>
      </li>
    </ul>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      suppliers: [],
      newSupplier: {
        name: '',
        contact_person: '',
        email: '',
        phone: ''
      }
    };
  },
  methods: {
    fetchSuppliers() {
      axios.get('/api/suppliers').then(response => {
        this.suppliers = response.data;
      });
    },
    addSupplier() {
      axios.post('/api/suppliers', this.newSupplier).then(() => {
        this.fetchSuppliers();
        this.newSupplier = { name: '', contact_person: '', email: '', phone: '' }; // Reset the form
      });
    },
    deleteSupplier(id) {
      axios.delete(`/api/suppliers/${id}`).then(() => {
        this.fetchSuppliers();
      });
    }
  },
  mounted() {
    this.fetchSuppliers();
  }
};
</script>

<style scoped>
/* Add your styles here */
</style>
