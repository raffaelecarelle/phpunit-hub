<template>
    <main id="main-content" class="flex-1 p-4 flex flex-col">
        <tab-navigation></tab-navigation>

        <div class="flex-grow overflow-y-auto">
            <div v-if="store.state.activeTab === 'results'">
                <results-summary :results="results" :status-counts="statusCounts" :format-nanoseconds="formatNanoseconds"></results-summary>

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
                        <individual-test-results
                            :individual-results="individualResults"
                            :format-nanoseconds="formatNanoseconds"
                            @toggleTestDetails="handleToggleTestDetails"
                        ></individual-test-results>
                    </template>

                    <!-- Grouped Results -->
                    <template v-if="!isAnyTestRunning && store.state.options.displayMode === 'default'">
                        <grouped-test-results
                            :grouped-results="groupedResults"
                            :format-nanoseconds="formatNanoseconds"
                            @toggleTestcaseGroup="toggleTestcaseGroup"
                            @toggleTestDetails="handleToggleTestDetails"
                        ></grouped-test-results>
                    </template>
                </div>
            </div>
            <div v-if="store.state.activeTab === 'coverage'">
                <coverage-report @showFileCoverage="showFileCoverage"></coverage-report>
            </div>
        </div>
    </main>
</template>

<script setup>
import { useStore } from '../store.js';
import TabNavigation from './TabNavigation.vue';
import ResultsSummary from './ResultsSummary.vue';
import IndividualTestResults from './IndividualTestResults.vue';
import GroupedTestResults from './GroupedTestResults.vue';
import CoverageReport from './CoverageReport.vue';

const store = useStore();
defineProps([
    'results',
    'statusCounts',
    'isAnyTestRunning',
    'formatNanoseconds',
    'individualResults',
    'groupedResults'
]);
const emit = defineEmits(['toggleTestDetails', 'toggleTestcaseGroup', 'showFileCoverage']);

function toggleTestcaseGroup(className) {
    store.toggleTestcaseGroupExpansion(className);
    emit('toggleTestcaseGroup', className);
}

function handleToggleTestDetails(testcase) {
    if (store.state.expandedTestId === testcase.id) {
        store.setExpandedTest(null);
    } else {
        store.setExpandedTest(testcase.id);
    }
    emit('toggleTestDetails', testcase);
}

function showFileCoverage(filePath) {
    emit('showFileCoverage', filePath);
}
</script>
