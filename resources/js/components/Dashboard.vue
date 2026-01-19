<template>
    <div class="bg-white dark:bg-gray-800 p-6 rounded shadow text-gray-900 dark:text-gray-100">
        <h2 class="text-xl font-semibold">
            Welcome {{ authName }}!
        </h2>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            This system is currently under development. Some features may not be fully available yet.
        </p>
    </div>
</template>

<script setup>
defineProps({
    authName: {
        type: String,
    }
})
</script>
