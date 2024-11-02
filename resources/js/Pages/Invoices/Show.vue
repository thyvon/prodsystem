<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10" id="printable-invoice">
        <h2 class="text-2xl font-bold mb-4">Invoice</h2>
        
        <!-- Header Section -->
        <div class="flex justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold">Invoice #{{ invoice?.id ?? 'N/A' }}</h3>
                <p>Created At: {{ invoice?.created_at ? new Date(invoice.created_at).toLocaleDateString() : 'N/A' }}</p>
            </div>
            <div class="text-right">
                <h3 class="text-lg font-bold">Total: ${{ formatCurrency(invoice?.total) }}</h3>
                <p>User ID: {{ invoice?.user_id ?? 'N/A' }}</p>
            </div>
        </div>

        <!-- Supplier Details -->
        <div class="flex mb-4">
            <div class="w-1/2 pr-2">
                <h3 class="text-lg font-bold">Supplier Details</h3>
                <p>Name: {{ invoice?.supplier?.name ?? 'N/A' }}</p>
            </div>
            <div class="w-1/2 pl-2">
                <p>ID: {{ invoice?.supplier_id ?? 'N/A' }}</p>
                <p>Email: {{ invoice?.supplier?.email ?? 'N/A' }}</p>
                <p>Phone: {{ invoice?.supplier?.phone ?? 'N/A' }}</p>
            </div>
        </div>

        <!-- Products Table -->
        <h3 class="text-xl font-bold mt-4 mb-2">Products</h3>
        <table class="min-w-full border-collapse border border-gray-300">
            <thead>
                <tr>
                    <th class="border border-gray-300 p-2">Product Name</th>
                    <th class="border border-gray-300 p-2">Quantity</th>
                    <th class="border border-gray-300 p-2">Price</th>
                    <th class="border border-gray-300 p-2">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="product in invoice?.products || []" :key="product.id">
                    <td class="border border-gray-300 p-2">{{ product.name }}</td>
                    <td class="border border-gray-300 p-2">{{ product.pivot.quantity }}</td>
                    <td class="border border-gray-300 p-2">${{ formatCurrency(product.price) }}</td>
                    <td class="border border-gray-300 p-2">${{ formatCurrency(product.pivot.quantity * product.price) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Generate PDF Button -->
        <button @click="generatePDF" class="generate-pdf-btn mt-4 inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
            Download as PDF
        </button>
        
        <!-- Display PDF -->
        <iframe v-if="pdfUrl" :src="pdfUrl" width="100%" height="500px" class="mt-6"></iframe>
    </div>
</template>

<script>
import { GlobalWorkerOptions } from 'pdfjs-dist/legacy/build/pdf';
import pdfWorker from 'pdfjs-dist/legacy/build/pdf.worker.min.js'; // Correct worker import
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

export default {
    props: {
        invoice: {
            type: Object,
            required: true
        },
    },
    data() {
        return {
            pdfUrl: null,
        };
    },
    methods: {
        formatCurrency(amount) {
            return amount ? Number(amount).toFixed(2) : '0.00';
        },
        async generatePDF() {
            const generateButton = document.querySelector('.generate-pdf-btn');
            generateButton.style.display = 'none';

            // Set the worker source
            GlobalWorkerOptions.workerSrc = pdfWorker;

            const invoiceElement = document.getElementById('printable-invoice');
            const canvas = await html2canvas(invoiceElement);
            const imgData = canvas.toDataURL('image/png');

            const pdf = new jsPDF('p', 'mm', 'a4');
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);

            generateButton.style.display = 'block';

            // Generate Blob URL for pdf.js to display
            const pdfBlob = pdf.output('blob');
            this.pdfUrl = URL.createObjectURL(pdfBlob);

            // Optionally download the PDF file
            pdf.save('invoice.pdf');
        },
    },
};
</script>

<style scoped>
/* Add any specific styles for your invoice here */
</style>
