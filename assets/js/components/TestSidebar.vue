<template>
    <aside id="test-sidebar" class="bg-gray-800 p-4 overflow-y-auto border-r border-gray-700 w-80">
        <TestSearchBar @update:filtered-suites="suitesToDisplay = $event" />

        <div v-if="store.state.isLoading" class="flex justify-center h-full mt-4">
            <div class="spinner-big"></div>
        </div>

        <div v-if="!store.state.isLoading && (suitesToDisplay.length === 0)" class="text-gray-500">
            No tests found.
        </div>

        <TestSuite
            v-if="!store.state.isLoading"
            v-for="suite in suitesToDisplay"
            :key="suite.id"
            :suite="suite"
            :is-test-running="isTestRunning"
            :is-test-stop-pending="isTestStopPending"
            @toggle-suite="toggleSuite"
            @stopSingleTest="stopSingleTest"
            @runSuiteTests="runSuiteTests"
            @runSingleTest="runSingleTest"
        />
    </aside>
</template>

<script setup>
import { ref } from 'vue';
import { useStore } from '../store.js';
import TestSearchBar from './sidebar/TestSearchBar.vue';
import TestSuite from './sidebar/TestSuite.vue';

const store = useStore();
const suitesToDisplay = ref(store.state.testSuites || []); // Added || [] for safe initialization

defineProps(['isTestRunning', 'isTestStopPending']);
const emit = defineEmits(['toggle-suite', 'stopSingleTest', 'runSuiteTests', 'runSingleTest']);

function toggleSuite(suiteId) {
    emit('toggle-suite', suiteId);
}

function stopSingleTest() {
    emit('stopSingleTest');
}

function runSuiteTests(suiteId) {
    emit('runSuiteTests', suiteId);
}

function runSingleTest(testId) {
    emit('runSingleTest', testId);
}
</script>
