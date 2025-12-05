<template>
    <button @click="clearAllResults"
            :disabled="isAnyTestRunning || !results"
            class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:bg-gray-500"
            title="Clear all test results">
        Clear Results
    </button>
</template>

<script setup>
defineProps(['isAnyTestRunning', 'results']);
const emit = defineEmits(['clearAllResults']);

function clearAllResults() {
    emit('clearAllResults');
}
</script>
