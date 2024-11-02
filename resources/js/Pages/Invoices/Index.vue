<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">Invoices</h2>
        <table class="min-w-full border border-gray-300">
            <thead>
                <tr>
                    <th class="border-b px-4 py-2">Invoice ID</th>
                    <th class="border-b px-4 py-2">Total Amount</th>
                    <th class="border-b px-4 py-2">Created At</th>
                    <th class="border-b px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="invoice in invoices" :key="invoice.id">
                    <td class="border-b px-4 py-2">{{ invoice.id }}</td>
                    <td class="border-b px-4 py-2">${{ Number(invoice.total).toFixed(2) }}</td>
                    <td class="border-b px-4 py-2">{{ new Date(invoice.created_at).toLocaleDateString() }}</td>
                    <td class="border-b px-4 py-2">
                        <button @click="viewInvoice(invoice.id)" class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">View</button>
                        <button @click="deleteInvoice(invoice.id)" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">Delete</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import { ref } from 'vue';
import { Inertia } from '@inertiajs/inertia';

export default {
    props: {
        invoices: Array,
    },
    setup() {
        const viewInvoice = (id) => {
            // Handle viewing invoice logic
            Inertia.visit(`/invoices/${id}`);
        };

        const deleteInvoice = (id) => {
            if (confirm('Are you sure you want to delete this invoice?')) {
                Inertia.delete(`/invoices/${id}`);
            }
        };

        return { viewInvoice, deleteInvoice };
    },
};
</script>

<style scoped>
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    text-align: left;
}
</style>
