<template>
    <main id="main-content" class="flex-1 p-4 flex flex-col">
        <tab-navigation></tab-navigation>

        <div class="flex-grow overflow-y-auto">
            <div v-if="store.state.activeTab === 'results'">
                <results-summary :results="results"></results-summary>

                <!-- Empty State -->
                <div v-if="!results" class="text-gray-500 text-center pt-10">
                    Run tests to see the results.
                </div>

                <!-- Results Content -->
                <div v-if="results" class="space-y-2 mt-4">
                    <!-- Spinner during execution -->
                    <div v-if="isAnyTestRunning" class="flex justify-center items-center pt-10">
                        <div class="spinner-big"></div>
                    </div>

                    <!-- Individual Test Results -->
                    <template v-if="!isAnyTestRunning && store.state.options.displayMode === 'individual'">
                        <individual-test-results :results="results"></individual-test-results>
                    </template>

                    <!-- Grouped Results -->
                    <template v-if="!isAnyTestRunning && store.state.options.displayMode === 'default'">
                        <grouped-test-results :results="results"></grouped-test-results>
                    </template>
                </div>
            </div>
            <div v-if="store.state.activeTab === 'coverage'">
                <coverage-report></coverage-report>
            </div>
        </div>
    </main>
</template>

<script setup>
import { computed } from 'vue';
import { useStore } from '../store.js';
import TabNavigation from './TabNavigation.vue';
import ResultsSummary from './ResultsSummary.vue';
import IndividualTestResults from './IndividualTestResults.vue';
import GroupedTestResults from './GroupedTestResults.vue';
import CoverageReport from './CoverageReport.vue';
import { useTestResults } from '../composables/useTestResults.js';

const store = useStore();
const { getResults } = useTestResults();

const results = getResults;

const isAnyTestRunning = computed(() => {
    return store.state.isStarting || store.state.isRunning || isAnyStopPending.value;
});

const isAnyStopPending = computed(() => store.state.isStopping);
</script>
