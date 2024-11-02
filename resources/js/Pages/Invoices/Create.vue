<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">Create Invoice</h2>

        <form @submit.prevent="submit">
            <div v-for="(item, index) in items" :key="index" class="mb-4 flex items-center space-x-4">
                <select v-model="item.product_id" required class="border p-2 rounded">
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
        </form>
    </div>
</template>

<script>
import { ref } from 'vue';
import { Inertia } from '@inertiajs/inertia';

export default {
    props: {
        products: Array,
    },
    setup(props) {
        const items = ref([{ product_id: '', quantity: 1 }]);

        const addItem = () => {
            items.value.push({ product_id: '', quantity: 1 });
        };

        const removeItem = (index) => {
            items.value.splice(index, 1);
        };

        const submit = () => {
            Inertia.post('/invoices', { items: items.value });
        };

        return { items, addItem, removeItem, submit };
    },
};
</script>
