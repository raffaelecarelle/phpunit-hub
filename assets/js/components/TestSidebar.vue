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
        />
    </aside>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useStore } from '../store.js';
import TestSearchBar from './sidebar/TestSearchBar.vue';
import TestSuite from './sidebar/TestSuite.vue';
import { ApiClient } from '../api.js';

const api = new ApiClient('');
const store = useStore();
const suitesToDisplay = ref(store.state.testSuites || []);
const testIndex = {};

defineProps(['isTestRunning', 'isTestStopPending']);
const emit = defineEmits(['toggle-suite', 'stopSingleTest']);

function toggleSuite(suiteId) {
    emit('toggle-suite', suiteId);
}

function stopSingleTest() {
    emit('stopSingleTest');
}

onMounted(async () => {
    try {
        // Fetch tests
        await fetchTests();
    } catch (error) {
        console.error('Failed to initialize sidebar:', error);
    }
});

async function fetchTests() {
    store.state.isLoading = true;
    try {
        const data = await api.fetchTests();
        store.state.testSuites = data.suites;
        store.state.availableSuites = data.availableSuites || [];
        store.state.availableGroups = data.availableGroups || [];
        store.state.coverageDriverMissing = !data.coverageDriver;

        // Build test index
        buildTestIndex();
    } catch (error) {
        console.error('Failed to fetch tests:', error);
        throw error; // Re-throw the error
    } finally {
        store.state.isLoading = false;
    }
}

function buildTestIndex() {
    store.state.testSuites.forEach(suite => {
        if (suite.methods) {
            suite.methods.forEach(method => {
                testIndex[method.id] = {
                    suite,
                    method
                };
            });
        }
    });
}
</script>
