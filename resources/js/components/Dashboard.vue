<template>
    <div class="bg-white dark:bg-gray-800 p-6 rounded shadow text-gray-900 dark:text-gray-100">
        {{ message }}
    </div>
</template>

<script setup>
defineProps({
    message: {
        type: String,
        default: "You're logged in!"
    }
})
</script>
