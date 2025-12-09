<template>
    <div id="app" class="flex flex-col h-screen">
        <Header
            @clearAllResults="clearAllResults"
            @runFailedTests="runFailedTests"
            @togglePlayStop="togglePlayStop"
            :is-any-test-running="isAnyTestRunning"
            :has-failed-tests="hasFailedTests"
            :is-any-stop-pending="isAnyStopPending"
            :results="results"
        ></Header>

        <!-- Main Container -->
        <div class="flex flex-grow overflow-hidden">
            <TestSidebar
                :is-test-running="isTestRunning"
                :is-test-stop-pending="isTestStopPending"
                @toggle-suite="toggleSuiteExpansion"
                @stopSingleTest="stopSingleTest"
                @runSuiteTests="runSuiteTests"
                @runSingleTest="runSingleTest"
            ></TestSidebar>

            <!-- Resizer -->
            <div id="resizer" class="w-1.5 cursor-col-resize bg-gray-700 hover:bg-blue-600 transition-colors duration-200"></div>

            <MainContent
                :results="results"
                :grouped-results="groupedResults"
                :individual-results="individualResults"
                :status-counts="statusCounts"
                :is-any-test-running="isAnyTestRunning"
                :format-nanoseconds="formatNanoseconds"
                @toggleTestDetails="toggleTestDetails"
                @toggleTestcaseGroup="toggleTestcaseGroupExpansion"
                @showFileCoverage="showFileCoverage"
            ></MainContent>
        </div>
    </div>
</template>

<script setup>
import { onMounted, computed, watch } from 'vue';
import { useStore } from './store.js';
import { ApiClient } from './api.js';
import { WebSocketManager } from './websocket.js';
import { updateFavicon } from './utils.js';

import Header from './components/Header.vue';
import TestSidebar from './components/TestSidebar.vue';
import MainContent from './components/MainContent.vue';

const store = useStore();
const api = new ApiClient('');
let wsManager = null;
const testIndex = {};

onMounted(async () => {
    try {
        // Fetch tests
        await fetchTests();

        // Connect WebSocket
        const wsHost = window.WS_HOST || '127.0.0.1';
        const wsPort = window.WS_PORT || '8080';
        wsManager = new WebSocketManager(`ws://${wsHost}:${wsPort}/ws/status`, store, {
            fetchCoverageReport: fetchCoverageReport,
        });
        await wsManager.connect();

        // Setup resizer
        setupResizer();

        // Update favicon
        updateFavicon('neutral');
    } catch (error) {
        console.error('Failed to initialize app:', error);
    }
});

// Watch for changes in filters and options, and save them to localStorage
watch(() => [store.state.options, store.state.selectedSuites, store.state.selectedGroups, store.state.coverage], (newState, oldState) => {
    store.saveState();
}, { deep: true });

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

async function _runTests(runOptions = {}) {
    store.setStarting(true);
    store.state.activeTab = 'results';

    // Filter out frontend-only options that PHPUnit doesn't understand
    const { displayMode, ...phpunitOptions } = store.state.options;

    const payload = {
        filters: runOptions.filters || [],
        groups: store.state.selectedGroups,
        suites: store.state.selectedSuites,
        options: { ...phpunitOptions, colors: true },
        coverage: !!store.state.coverage,
        contextId: runOptions.contextId || 'global',
    };

    try {
        await api.runTests(payload);
    } catch (error) {
        console.error('Failed to run tests:', error);
        updateFavicon('failure');
        store.setStarting(false);
    }
}

function runTests(runOptions = {}) {
    if (store.state.isStarting) {
        return;
    }
    _runTests(runOptions);
}

function runAllTests() {
    runTests({ contextId: 'global' });
}

function runFailedTests() {
    const failedTestIds = store.getFailedTestIds();
    if (failedTestIds.length === 0) {
        console.log('No failed tests to run.');
        return;
    }
    runTests({ filters: failedTestIds, contextId: 'failed' });
}

function runSingleTest(testId) {
    runTests({ filters: [testId], contextId: testId });
}

function runSuiteTests(suiteId) {
    runTests({ filters: [suiteId], contextId: suiteId });
}

async function stopAllTests() {
    try {
        store.markStopPending();
        await api.stopAllTests();
    } catch (error) {
        console.error('Failed to stop tests:', error);
        store.clearStopPending();
    }
}

async function stopSingleTest() {
    try {
        store.markStopPending();
        await api.stopSingleTest();
    } catch (error) {
        console.error(`Failed to stop test run:`, error);
        store.clearStopPending();
    }
}

function togglePlayStop() {
    if (store.state.isRunning) {
        stopAllTests();
    } else {
        runAllTests();
    }
}

function setupResizer() {
    const resizer = document.getElementById('resizer');
    const sidebar = document.getElementById('test-sidebar');

    if (!resizer || !sidebar) return;

    let isResizing = false;

    resizer.addEventListener('mousedown', (e) => {
        e.preventDefault();
        isResizing = true;
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        const mouseMoveHandler = (e) => {
            if (!isResizing) return;
            const sidebarWidth = e.clientX;
            if (sidebarWidth > 200 && sidebarWidth < window.innerWidth - 200) {
                sidebar.style.width = `${sidebarWidth}px`;
            }
        };

        const mouseUpHandler = () => {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', mouseMoveHandler);
            document.removeEventListener('mouseup', mouseUpHandler);
        };

        document.addEventListener('mousemove', mouseMoveHandler);
        document.addEventListener('mouseup', mouseUpHandler);
    });
}

const isAnyTestRunning = computed(() => {
    return store.state.isStarting || store.state.isRunning || isAnyStopPending.value;
});

const isAnyStopPending = computed(() => store.state.isStopping);

const hasFailedTests = computed(() => store.hasFailedTests());

const results = computed(() => getResults());

const groupedResults = computed(() => getGroupedResults());

const individualResults = computed(() => getIndividualResults());

const statusCounts = computed(() => getStatusCounts());

function getResults() {
    const run = store.state.testRun;
    return getSingleRunResults(run);
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

function getSingleRunResults(run) {
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

    // If the run is in progress, calculate totals in real-time
    // We check for `!run.summary` because the final summary from PHPUnit is the source of truth.
    // We only calculate in real-time if that final summary hasn't arrived yet.
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

    // Transform suites data
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

function calculateSummaryFromTests(suites) {
    const summary = {
        tests: 0,
        assertions: 0,
        time: 0,
        failures: 0,
        errors: 0,
        warnings: 0,
        skipped: 0,
        deprecations: 0,
        incomplete: 0,
        risky: 0,
        notices: 0,
    };

    suites.forEach(suite => {
        suite.testcases.forEach(tc => {
            summary.tests++;
            summary.time += tc.duration || 0;
            summary.assertions += tc.assertions || 0;

            if (tc.status === 'failed') summary.failures++;
            else if (tc.status === 'errored') summary.errors++;
            else if (tc.status === 'skipped') summary.skipped++;
            else if (tc.status === 'incomplete') summary.incomplete++;
            else if (tc.status === 'risky') summary.risky++;

            summary.warnings += tc.warnings?.length || 0;
            summary.deprecations += tc.deprecations?.length || 0;
            summary.notices += tc.notices?.length || 0;
        });
    });

    return summary;
}

function getGroupedResults() {
    const resultsVal = results.value;
    if (!resultsVal) return [];

    const groups = {};
    resultsVal.suites.forEach(suite => {
        suite.testcases.forEach(tc => {
            if (!groups[tc.class]) {
                groups[tc.class] = {
                    className: tc.class,
                    testcases: [],
                    passed: 0,
                    failed: 0,
                    errored: 0,
                    skipped: 0,
                    warning: 0,
                    deprecation: 0,
                    incomplete: 0,
                    risky: 0,
                    notice: 0,
                    hasIssues: false
                };
            }

            const group = groups[tc.class];
            group.testcases.push(tc);
            const status = tc.status || 'passed';
            if (group[status] !== undefined) {
                group[status]++;
            }

            // Count warnings and deprecations
            if (tc.warnings?.length > 0) {
                group.warning += tc.warnings.length;
            }
            if (tc.deprecations?.length > 0) {
                group.deprecation += tc.deprecations.length;
            }
            if (tc.notices?.length > 0) {
                group.notice += tc.notices.length;
            }

            // Set hasIssues if any issues are present (warnings array, deprecations array, or non-passed status)
            if (tc.warnings?.length > 0 || tc.deprecations?.length > 0 || tc.notices?.length > 0 || status !== 'passed') {
                group.hasIssues = true;
            }
        });
    });

    const statusOrder = { 'errored': 1, 'failed': 2, 'incomplete': 3, 'risky': 4, 'skipped': 5, 'warning': 6, 'deprecation': 7, 'notice': 8, 'passed': 9 };

    // Determine suite status
    Object.values(groups).forEach(group => {
        let highestPriorityStatus = statusOrder['passed']; // Start with the lowest priority
        group.testcases.forEach(tc => {
            const tcStatus = tc.status || 'passed';
            const priority = statusOrder[tcStatus];
            if (priority && priority < highestPriorityStatus) {
                highestPriorityStatus = priority;
            }
        });
        group.suiteStatus = highestPriorityStatus;
    });

    const sortedGroups = Object.values(groups).sort((a, b) => {
        if (a.suiteStatus !== b.suiteStatus) {
            return a.suiteStatus - b.suiteStatus;
        }
        return a.className.localeCompare(b.className);
    });

    sortedGroups.forEach(group => {
        group.testcases.sort((a, b) => {
            const durationA = a.duration || 0;
            const durationB = b.duration || 0;
            if (store.state.sortBy === 'duration') {
                if (durationA !== durationB) {
                    return store.state.sortDirection === 'asc' ? durationA - durationB : durationB - durationA;
                }
            }

            const statusA = statusOrder[a.status || 'passed'] || 99;
            const statusB = statusOrder[b.status || 'passed'] || 99;
            if (statusA !== statusB) {
                return statusA - statusB;
            }
            return durationB - durationA;
        });
    });

    return sortedGroups;
}

function getIndividualResults() {
    const resultsVal = results.value;
    if (!resultsVal) return [];

    let allTests = [];
    resultsVal.suites.forEach(suite => {
        allTests.push(...suite.testcases);
    });

    // Filter based on options
    allTests = allTests.filter(t => {
        if (t.status === 'skipped' && !store.state.options.displaySkipped) return false;
        if (t.status === 'incomplete' && !store.state.options.displayIncomplete) return false;
        if (t.status === 'risky' && !store.state.options.displayRisky) return false;
        if (t.warnings?.length > 0 && !store.state.options.displayWarnings) {
            // if it's just a warning and we hide them, don't show if it passed
            if (t.status === 'passed') return false;
        }
        if (t.deprecations?.length > 0 && !store.state.options.displayDeprecations) {
            if (t.status === 'passed') return false;
        }
        if (t.notices?.length > 0 && !store.state.options.displayNotices) {
            if (t.status ==='passed') return false;
        }
        return true;
    });

    // Sort by duration, descending
    allTests.sort((a, b) => {
        const durationA = a.duration || 0;
        const durationB = b.duration || 0;

        if (store.state.sortBy === 'duration') {
            return store.state.sortDirection === 'asc' ? durationA - durationB : durationB - durationA;
        }

        const statusOrder = { 'errored': 1, 'failed': 2, 'incomplete': 3, 'risky': 4, 'skipped': 5, 'warning': 6, 'deprecation': 7, 'notice': 8, 'passed': 9 };
        const statusA = statusOrder[a.status || 'passed'] || 99;
        const statusB = statusOrder[b.status || 'passed'] || 99;
        if (statusA !== statusB) {
            return statusA - statusB;
        }

        return durationB - durationA; // Default to duration descending if statuses are equal
    });

    return allTests;
}

function getStatusCounts() {
    const resultsVal = results.value;

    if (!resultsVal) {
        return { passed: 0, failed: 0, error: 0, warnings: 0, skipped: 0, deprecations: 0, incomplete: 0, risky: 0, notices: 0 };
    }

    const s = resultsVal.summary;
    const counts = {
        passed: 0,
        failed: s.failures || 0,
        error: s.errors || 0,
        warnings: s.warnings || 0,
        skipped: s.skipped || 0,
        deprecations: s.deprecations || 0,
        incomplete: s.incomplete || 0,
        risky: s.risky || 0,
        notices: s.notices || 0,
    };

    // Only subtract actual failures (failed, error, skipped, incomplete) from total
    // Warnings and deprecations don't prevent a test from being "passed"
    const actualFailures = counts.failed + counts.error + counts.skipped + counts.incomplete + counts.risky;
    counts.passed = (s.tests || 0) - actualFailures;

    return counts;
}

async function fetchCoverageReport() {
    try {
        const report = await api.fetchCoverage();
        store.setCoverageReport(report);
    } catch (error) {
        console.error('Failed to fetch coverage report:', error);
    } finally {
        store.setCoverageLoading(false);
    }
}

async function showFileCoverage(filePath) {
    try {
        const coverage = await api.fetchFileCoverage(filePath);
        store.setFileCoverage({ ...coverage, path: filePath });
    } catch (error) {
        console.error('Failed to fetch file coverage:', error);
    }
}

function isTestRunning() {
    return store.state.isRunning;
}

function isTestStopPending() {
    return store.state.isStopping;
}

function formatNanoseconds(nanoseconds) {
    if (nanoseconds === undefined || nanoseconds === null) {
        return '0.00ms';
    }
    const seconds = nanoseconds / 1_000_000_000;
    if (seconds >= 1) {
        return `${seconds.toFixed(2)}s`;
    }
    return `${(nanoseconds / 1_000_000).toFixed(2)}ms`;
}

function toggleSuiteExpansion(suiteId) {
    store.toggleSuiteExpansion(suiteId);
}

function toggleTestcaseGroupExpansion(className) {
    store.toggleTestcaseGroupExpansion(className);
}

function toggleTestDetails(testcase) {
    if (store.state.expandedTestId === testcase.id) {
        store.setExpandedTest(null);
    } else {
        store.setExpandedTest(testcase.id);
    }
}

function clearAllResults() {
    store.clearAllResults();
}
</script>
