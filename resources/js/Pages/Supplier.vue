<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">Suppliers</h2>
        <form @submit.prevent="addSupplier" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input v-model="newSupplier.name" placeholder="Supplier Name" required class="border p-2 rounded" />
                <input v-model="newSupplier.contact_person" placeholder="Contact Person" class="border p-2 rounded" />
                <input v-model="newSupplier.email" placeholder="Email" class="border p-2 rounded" />
                <input v-model="newSupplier.phone" placeholder="Phone" class="border p-2 rounded" />
            </div>
            <button type="submit" class="mt-4 bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Add Supplier</button>
        </form>
        
        <ul class="list-disc list-inside">
            <li v-for="supplier in suppliers" :key="supplier.id" class="flex justify-between items-center py-2">
                <span class="text-lg">{{ supplier.name }}</span>
                <button @click="deleteSupplier(supplier.id)" class="bg-red-500 text-white p-1 rounded hover:bg-red-600">Delete</button>
            </li>
        </ul>
    </div>
</template>

<script>
import { ref } from 'vue';
import { Inertia } from '@inertiajs/inertia';

export default {
    props: {
        suppliers: Array,
    },
    setup(props) {
        const newSupplier = ref({ name: '', contact_person: '', email: '', phone: '' });
        const suppliers = ref(props.suppliers);

        function addSupplier() {
            Inertia.post('/suppliers', newSupplier.value);
            newSupplier.value = { name: '', contact_person: '', email: '', phone: '' }; // Reset form
        }

        function deleteSupplier(id) {
            if (confirm("Are you sure you want to delete this supplier?")) {
                Inertia.delete(`/suppliers/${id}`);
            }
        }

        return { newSupplier, suppliers, addSupplier, deleteSupplier };
    }
};
</script>
