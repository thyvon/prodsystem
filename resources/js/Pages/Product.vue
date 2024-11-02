<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">Products</h2>
        <form @submit.prevent="addProduct" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input v-model="newProduct.name" placeholder="Product Name" required class="border p-2 rounded" />
                <input v-model="newProduct.description" placeholder="Description" class="border p-2 rounded" />
                <input v-model="newProduct.price" placeholder="Price" type="number" required class="border p-2 rounded" />
                <select v-model="newProduct.supplier_id" required class="border p-2 rounded">
                    <option disabled value="">Select Supplier</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                        {{ supplier.name }}
                    </option>
                </select>
            </div>
            <button type="submit" class="mt-4 bg-green-500 text-white p-2 rounded hover:bg-green-600">Add Product</button>
        </form>
        
        <ul class="list-disc list-inside">
            <li v-for="product in products" :key="product.id" class="flex justify-between items-center py-2">
                <span class="text-lg">{{ product.name }} - {{ product.price }} - {{ product.supplier.name }}</span>
                <button @click="deleteProduct(product.id)" class="bg-red-500 text-white p-1 rounded hover:bg-red-600">Delete</button>
            </li>
        </ul>
    </div>
</template>

<script>
import { ref } from 'vue';
import { Inertia } from '@inertiajs/inertia';

export default {
    props: {
        products: Array,
        suppliers: Array,
    },
    setup(props) {
        const newProduct = ref({ name: '', description: '', price: '', supplier_id: '' });
        const products = ref(props.products);

        function addProduct() {
            Inertia.post('/products', newProduct.value);
            newProduct.value = { name: '', description: '', price: '', supplier_id: '' }; // Reset form
        }

        function deleteProduct(id) {
            if (confirm("Are you sure you want to delete this product?")) {
                Inertia.delete(`/products/${id}`);
            }
        }

        return { newProduct, products, addProduct, deleteProduct };
    }
};
</script>
