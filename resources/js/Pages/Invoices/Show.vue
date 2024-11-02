<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">Invoice #{{ invoice.id }}</h2>
        <p>Total: ${{ invoice.total.toFixed(2) }}</p>
        <p>Created At: {{ new Date(invoice.created_at).toLocaleDateString() }}</p>
        <p>User ID: {{ invoice.user_id }}</p>
        <a href="/invoices" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Back to Invoices</a>
    </div>
</template>

<script>
export default {
    props: {
        invoice: Object,
    },
};
</script>
