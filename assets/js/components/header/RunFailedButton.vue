<template>
  <button
    :disabled="isAnyTestRunning || !hasFailedTests"
    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:bg-gray-500"
    @click="runFailedTests"
  >
    Run Failed
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { useStore } from '../../store.js';

const store = useStore();

const hasFailedTests = computed(() => store.hasFailedTests());

function runFailedTests() {
    store.runFailedTests();
}

const isAnyStopPending = computed(() => store.state.isStopping);

const isAnyTestRunning = computed(() => {
    return store.state.isStarting || store.state.isRunning || isAnyStopPending.value;
});
</script>
