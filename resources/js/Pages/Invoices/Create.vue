<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">Create Invoice</h2>

        <form @submit.prevent="submit">
            <div class="mb-4">
                <label for="supplier" class="block mb-2">Supplier:</label>
                <select v-model="supplier_id" required class="border p-2 rounded w-full">
                    <option disabled value="">Select Supplier</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                        {{ supplier.name }}
                    </option>
                </select>
            </div>

            <div v-for="(item, index) in items" :key="index" class="mb-4 flex items-center space-x-4">
                <select v-model="item.product_id" required class="border p-2 rounded w-full">
                    <option disabled value="">Select Product</option>
                    <option v-for="product in products" :key="product.id" :value="product.id">
                        {{ product.name }} - ${{ product.price }}
                    </option>
                </select>
                <input v-model="item.quantity" type="number" placeholder="Quantity" required class="border p-2 rounded" min="1" />
                <button type="button" @click="removeItem(index)" class="bg-red-500 text-white p-1 rounded hover:bg-red-600">Remove</button>
            </div>
            <button type="button" @click="addItem" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Add More Products</button>
            <button type="submit" class="mt-4 bg-green-500 text-white p-2 rounded hover:bg-green-600">Create Invoice</button>

            <div v-if="successMessage" class="mt-4 text-green-500">{{ successMessage }}</div>
            <div v-if="errorMessage" class="mt-4 text-red-500">{{ errorMessage }}</div>
        </form>

        <!-- Debugging section to view suppliers -->
        <!-- <div class="mt-4">
            <h3 class="font-bold">Debug: Suppliers</h3>
            <pre>{{ suppliers }}</pre>
        </div> -->
    </div>
</template>

<script>
import { ref } from 'vue';
import { Inertia } from '@inertiajs/inertia';

export default {
    props: {
        products: Array,
        suppliers: Array, // Ensure suppliers are passed as a prop
    },
    setup(props) {
        const items = ref([{ product_id: '', quantity: 1 }]);
        const supplier_id = ref('');
        const successMessage = ref('');
        const errorMessage = ref('');

        const addItem = () => {
            items.value.push({ product_id: '', quantity: 1 });
        };

        const removeItem = (index) => {
            items.value.splice(index, 1);
        };

        const submit = () => {
            successMessage.value = '';
            errorMessage.value = '';
            Inertia.post('/invoices', { supplier_id: supplier_id.value, items: items.value })
                .then(() => {
                    successMessage.value = 'Invoice created successfully!';
                    items.value = [{ product_id: '', quantity: 1 }]; // Reset items
                    supplier_id.value = ''; // Reset supplier
                })
                .catch((error) => {
                    errorMessage.value = 'Failed to create invoice. Please check your input.';
                });
        };

        return { items, supplier_id, addItem, removeItem, submit, successMessage, errorMessage };
    },
};
</script>

<style scoped>
/* Add your styles here */
</style>
