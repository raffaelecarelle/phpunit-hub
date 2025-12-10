<template>
  <button
    :disabled="isAnyTestRunning"
    class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:bg-gray-500"
    title="Clear all test results"
    @click="clearAllResults"
  >
    Clear Results
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { useStore } from '../../store.js';

const store = useStore();

function clearAllResults() {
    store.clearAllResults();
}

const isAnyTestRunning = computed(() => {
    return store.state.isStarting || store.state.isRunning || isAnyStopPending.value;
});

const isAnyStopPending = computed(() => store.state.isStopping);
</script>
