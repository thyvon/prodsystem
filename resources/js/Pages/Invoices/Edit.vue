<template>
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">Edit Invoice</h2>
        <form @submit.prevent="updateInvoice">
            <div class="mb-4">
                <label for="supplier" class="mr-2">Supplier:</label>
                <select v-model="invoice.supplier_id" id="supplier" class="border rounded p-2" required>
                    <option value="" disabled>Select a supplier</option>
                    <option v-for="supplier in suppliers" :value="supplier.id" :key="supplier.id">{{ supplier.name }}</option>
                </select>
            </div>
            <div v-for="(product, index) in invoice.products" :key="index" class="mb-4 flex items-center">
                <label :for="`product-${index}`" class="mr-2">Product:</label>
                <select v-model="product.id" :id="`product-${index}`" class="border rounded p-2" required>
                    <option value="" disabled>Select a product</option>
                    <option v-for="prod in products" :value="prod.id" :key="prod.id">{{ prod.name }}</option>
                </select>
                <label :for="`quantity-${index}`" class="ml-4">Quantity:</label>
                <input 
                    type="number" 
                    v-model="product.quantity" 
                    :id="`quantity-${index}`" 
                    class="border rounded p-2 ml-2" 
                    min="1" 
                    required 
                />
                <button @click.prevent="removeProduct(index)" class="bg-red-500 text-white px-2 py-1 rounded ml-2">Remove</button>
            </div>
            <button @click.prevent="addProduct" class="bg-green-500 text-white px-4 py-2 rounded">Add Product</button>
            <div class="mt-4">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Update Invoice</button>
            </div>
            <div v-if="successMessage" class="mt-4 text-green-500">{{ successMessage }}</div>
        </form>
    </div>
</template>

<script>
import { ref } from 'vue';
import { Inertia } from '@inertiajs/inertia';

export default {
    props: {
        invoice: Object,
        products: Array,
        suppliers: Array,
    },
    setup(props) {
        // Debugging output to confirm props
        console.log('Invoice props:', props.invoice);  

        const invoice = ref({
            id: props.invoice.id,  // Ensure the ID is included
            supplier_id: props.invoice.supplier_id,
            products: props.invoice.products.map(product => ({
                id: product.id,
                quantity: product.pivot.quantity,
            })),
        });
        
        const successMessage = ref('');

        const addProduct = () => {
            invoice.value.products.push({ id: null, quantity: 1 });
        };

        const removeProduct = (index) => {
            invoice.value.products.splice(index, 1);
        };

        const updateInvoice = () => {
            const hasInvalidProducts = invoice.value.products.length === 0 || 
                invoice.value.products.some(p => !p.id || p.quantity <= 0);
            
            if (hasInvalidProducts) {
                alert('Please ensure all products are selected and have a valid quantity.');
                return;
            }

            if (!invoice.value.id) {
                console.error('Invoice ID is undefined');
                return;
            }

            // Calculate total
            const total = invoice.value.products.reduce((sum, product) => {
                const productDetail = props.products.find(p => p.id === product.id);
                return sum + (productDetail ? productDetail.price * product.quantity : 0);
            }, 0);

            console.log('Total calculated:', total);

            Inertia.patch(`/invoices/${invoice.value.id}`, { 
                supplier_id: invoice.value.supplier_id,
                products: invoice.value.products,
                total: total,  // Include total in the request
            })
            .then(() => {
                successMessage.value = 'Invoice updated successfully!';
            })
            .catch((error) => {
                console.error('Error updating invoice:', error);
                successMessage.value = 'Failed to update the invoice.';
            });
        };

        return { invoice, addProduct, removeProduct, updateInvoice, successMessage };
    },
};
</script>

<style scoped>
/* Add your styles here */
</style>
