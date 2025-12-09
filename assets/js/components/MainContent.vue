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

const store = useStore();

const results = computed(() => getResults());

function getResults() {
    const run = store.state.testRun;

    if (!run) return null;

    const defaultSummary = {
        numberOfTests: 0,
        numberOfAssertions: 0,
        duration: 0,
        numberOfFailures: 0,
        numberOfErrors: 0,
        numberOfWarnings: 0,
        numberOfSkipped: 0,
        numberOfDeprecations: 0,
        numberOfIncomplete: 0,
        numberOfRisky: 0,
        numberOfNotices: 0,
    };
    const summary = { ...defaultSummary, ...(run.summary || {}) };

    if (run && !run.summary) {
        const realtimeSummary = calculateRealtimeSummary(run);
        summary.numberOfTests = realtimeSummary.tests;
        summary.numberOfAssertions = realtimeSummary.assertions;
        summary.numberOfFailures = realtimeSummary.failures;
        summary.numberOfErrors = realtimeSummary.errors;
        summary.numberOfWarnings = realtimeSummary.warnings;
        summary.numberOfSkipped = realtimeSummary.skipped;
        summary.numberOfDeprecations = realtimeSummary.deprecations;
        summary.numberOfIncomplete = realtimeSummary.incomplete;
        summary.numberOfRisky = realtimeSummary.risky;
        summary.notices = realtimeSummary.notices;
    }

    const transformedSuites = [];
    for (const suiteName in run.suites) {
        const suiteData = run.suites[suiteName];
        const testcases = [];
        for (const testId in suiteData.tests) {
            const testData = suiteData.tests[testId];
            testcases.push({
                name: testData.name,
                class: testData.class,
                id: testData.id,
                duration: testData.duration || 0,
                assertions: testData.assertions || 0,
                status: testData.status,
                message: testData.message,
                trace: testData.trace,
                warnings: testData.warnings || [],
                deprecations: testData.deprecations || [],
                notices: testData.notices || [],
            });
        }
        transformedSuites.push({
            name: suiteData.name,
            testcases,
        });
    }

    return {
        summary: {
            tests: summary.numberOfTests,
            assertions: summary.numberOfAssertions,
            time: run.sumOfDurations > 0 ? run.sumOfDurations : summary.duration,
            failures: summary.numberOfFailures,
            errors: summary.numberOfErrors,
            warnings: summary.numberOfWarnings,
            skipped: summary.numberOfSkipped,
            deprecations: summary.numberOfDeprecations,
            incomplete: summary.numberOfIncomplete,
            risky: summary.numberOfRisky,
            notices: summary.notices,
        },
        suites: transformedSuites,
    };
}

function calculateRealtimeSummary(run) {
    const summary = {
        tests: 0,
        assertions: 0,
        failures: 0,
        errors: 0,
        warnings: 0,
        skipped: 0,
        deprecations: 0,
        incomplete: 0,
        risky: 0,
        notices: 0,
    };

    if (!run || !run.suites) return summary;

    for (const suiteName in run.suites) {
        for (const testId in run.suites[suiteName].tests) {
            const testData = run.suites[suiteName].tests[testId];
            summary.tests++;
            summary.assertions += testData.assertions || 0;
            summary.warnings += testData.warnings?.length || 0;
            summary.deprecations += testData.deprecations?.length || 0;
            summary.notices += testData.notices?.length || 0;

            switch (testData.status) {
                case 'failed': summary.failures++; break;
                case 'errored': summary.errors++; break;
                case 'skipped': summary.skipped++; break;
                case 'incomplete': summary.incomplete++; break;
                case 'risky': summary.risky++; break;
            }
        }
    }
    return summary;
}

const isAnyTestRunning = computed(() => {
    return store.state.isStarting || store.state.isRunning || isAnyStopPending.value;
});

const isAnyStopPending = computed(() => store.state.isStopping);
</script>
