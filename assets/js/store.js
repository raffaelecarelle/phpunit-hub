import { reactive } from 'vue';
import { parseTestId, updateFavicon } from './utils.js';

const STORAGE_KEY = 'phpunit-hub-settings';

const defaultOptions = {
    displayWarnings: true,
    displayDeprecations: true,
    displaySkipped: true,
    displayIncomplete: true,
    displayRisky: true,
    displayNotices: true,
    stopOnDefect: false,
    stopOnError: false,
    stopOnFailure: false,
    stopOnWarning: false,
    stopOnRisky: false,
    displayMode: 'default', // 'default' or 'individual'
};

const state = reactive({
    // Test suites
    testSuites: [],
    availableSuites: [],
    availableGroups: [],

    // UI state
    isLoading: false,
    isStarting: false,
    expandedSuites: new Set(),
    expandedTestcaseGroups: new Set(),
    expandedTestId: null,
    showFilterPanel: false,
    activeTab: 'results',
    sortBy: 'default', // 'default' or 'duration'
    sortDirection: 'desc', // 'asc' or 'desc'

    // Filter options
    selectedSuites: [],
    selectedGroups: [],
    options: { ...defaultOptions },
    coverage: false,

    // Test runs
    runningTestIds: {},
    stopPending: {},
    realtimeTestRuns: {},
    lastCompletedRunId: null,

    // Coverage
    coverageReport: null,
    isCoverageLoading: false,
    fileCoverage: null,
    coverageDriverMissing: false,
});

function loadState() {
    const savedState = localStorage.getItem(STORAGE_KEY);
    if (savedState) {
        try {
            const parsedState = JSON.parse(savedState);
            if (parsedState.options) {
                // Ensure resultUpdateMode is not loaded from old state
                const { resultUpdateMode, ...restOptions } = parsedState.options;
                state.options = { ...state.options, ...restOptions };
            }
            if (Array.isArray(parsedState.selectedSuites)) {
                state.selectedSuites = parsedState.selectedSuites;
            }
            if (Array.isArray(parsedState.selectedGroups)) {
                state.selectedGroups = parsedState.selectedGroups;
            }
            if(parsedState.coverage) {
                state.coverage = parsedState.coverage;
            }
        } catch (e) {
            console.error('Failed to load state from localStorage', e);
            localStorage.removeItem(STORAGE_KEY);
        }
    }
}

function saveState() {
    const stateToSave = {
        options: state.options,
        selectedSuites: state.selectedSuites,
        selectedGroups: state.selectedGroups,
        coverage: state.coverage,
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(stateToSave));
}

function setSortBy(sortBy) {
    if (state.sortBy === sortBy) {
        if (state.sortDirection === 'desc') {
            state.sortDirection = 'asc';
        } else {
            state.sortBy = 'default';
            state.sortDirection = 'desc';
        }
    } else {
        state.sortBy = sortBy;
        state.sortDirection = 'desc';
    }
}

function setDisplayMode(mode) {
    state.options.displayMode = mode;
}

function setStarting(isStarting) {
    state.isStarting = isStarting;
}

function initializeTestRun(runId, contextId) {
    state.isStarting = false;
    // Always reset for 'global' runs or for 'failed' runs (to show only re-run tests)
    const shouldReset = contextId === 'global' || contextId === 'failed';

    state.realtimeTestRuns[runId] = {
        status: 'running',
        contextId,
        suites: {},
        summary: null,
        failedTestIds: new Set(),
        executionEnded: false,
        sumOfDurations: 0,
    };
    state.runningTestIds[runId] = true;
    delete state.stopPending[runId];

    // Clear previous results in reset mode or for failed test runs
    if (shouldReset) {
        state.realtimeTestRuns = { [runId]: state.realtimeTestRuns[runId] };
        state.lastCompletedRunId = null;
        state.expandedTestId = null;
        state.expandedTestcaseGroups = new Set();
        resetSidebarTestStatuses();
    }

    // Assign runId to suite if this is a suite-level run
    state.testSuites.forEach(suite => {
        if (suite.id === contextId) {
            suite.runId = runId;
        }
    });

    state.activeTab = 'results';
}

function handleTestEvent(runId, eventData) {
    const run = state.realtimeTestRuns[runId];

    if (!run) {
        console.warn(`Received event for unknown runId: ${runId}`);
        return;
    }

    switch (eventData.event) {
        case 'suite.started':
            handleSuiteStarted(run, eventData);
            break;
        case 'test.prepared':
            handleTestPrepared(run, eventData, runId);
            break;
        case 'test.warning':
        case 'test.deprecation':
            handleTestWarningOrDeprecation(run, eventData);
            break;
        case 'test.notice':
            handleTestNotice(run, eventData);
            break;
        case 'test.passed':
        case 'test.failed':
        case 'test.errored':
        case 'test.skipped':
        case 'test.incomplete':
        case 'test.risky':
            handleTestCompleted(run, eventData, runId);
            break;
        case 'test.finished':
            handleTestFinished(run, eventData, runId);
            break;
        case 'execution.ended':
            handleExecutionEnded(run, eventData, runId);
            break;
    }
}

function handleSuiteStarted(run, eventData) {
    run.suites[eventData.data.name] = {
        name: eventData.data.name,
        count: eventData.data.count,
        tests: {},
        passed: 0,
        failed: 0,
        errored: 0,
        skipped: 0,
        incomplete: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        risky: 0,
        hasIssues: false,
    };
}

function handleTestPrepared(run, eventData, runId) {
    const { suiteName, testName } = parseTestId(eventData.data.testId);

    if (!run.suites[suiteName]) {
        run.suites[suiteName] = {
            name: suiteName,
            count: 0,
            tests: {},
            passed: 0,
            failed: 0,
            errored: 0,
            skipped: 0,
            incomplete: 0,
            warning: 0,
            deprecation: 0,
            notice: 0,
            risky: 0,
            hasIssues: false,
        };
    }

    run.suites[suiteName].tests[eventData.data.testId] = {
        id: eventData.data.testId,
        name: testName,
        class: suiteName,
        status: 'running',
        duration: null,
        assertions: 0,
        message: null,
        trace: null,
        warnings: [],
        deprecations: [],
        notices: [],
    };

    // Update sidebar
    updateSidebarTestStatus(suiteName, eventData.data.testId, 'running', null, runId);
}

function handleTestWarningOrDeprecation(run, eventData) {
    const { suiteName } = parseTestId(eventData.data.testId);
    const testId = eventData.data.testId;

    if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
        const test = run.suites[suiteName].tests[testId];
        const suite = run.suites[suiteName];

        if (eventData.event === 'test.warning') {
            test.warnings.push(eventData.data.message || 'Warning triggered');
            suite.warning++;
        } else if (eventData.event === 'test.deprecation') {
            test.deprecations.push(eventData.data.message || 'Deprecation triggered');
            suite.deprecation++;
        }

        suite.hasIssues = true;
    }
}

function handleTestNotice(run, eventData) {
    const { suiteName } = parseTestId(eventData.data.testId);
    const testId = eventData.data.testId;

    if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
        const test = run.suites[suiteName].tests[testId];
        const suite = run.suites[suiteName];

        test.notices.push(eventData.data.message || 'Notice triggered');
        suite.notice++;
        suite.hasIssues = true;
    }
}

function handleTestCompleted(run, eventData, runId) {
    const { suiteName, testName } = parseTestId(eventData.data.testId);
    const testId = eventData.data.testId;

    if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
        const test = run.suites[suiteName].tests[testId];
        const status = eventData.event.replace('test.', '');

        test.status = status;
        test.message = eventData.data.message || null;
        test.trace = eventData.data.trace || null;

        // Update suite counts
        const suite = run.suites[suiteName];
        if (suite[status] !== undefined) {
            suite[status]++;
        }

        // Track failed tests
        if (status === 'failed' || status === 'errored') {
            run.failedTestIds.add(testId);
            suite.hasIssues = true;
        } else if (status === 'passed') {
            // Remove from current run
            run.failedTestIds.delete(testId);
        } else if (status !== 'passed') {
            suite.hasIssues = true;
        }

        // Update sidebar
        updateSidebarTestStatus(suiteName, testId, status, test.duration, runId);
    }
}

function handleTestFinished(run, eventData, runId) {
    const { suiteName } = parseTestId(eventData.data.testId);
    const testId = eventData.data.testId;

    if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
        const test = run.suites[suiteName].tests[testId];
        test.duration = eventData.data.duration;
        test.assertions = eventData.data.assertions;
        run.sumOfDurations += test.duration;

        // Also update the sidebar with the final duration.
        // The status might have already been set by handleTestCompleted.
        if (test.status) {
            updateSidebarTestStatus(suiteName, testId, test.status, test.duration, runId);
        }
    }
}

function handleExecutionEnded(run, eventData, runId) {
    run.summary = eventData.data.summary;
    run.executionEnded = true;
    run.status = 'finished';
    state.lastCompletedRunId = runId;
    delete state.runningTestIds[runId];
    delete state.stopPending[runId];
    state.isStarting = false;
    updateSidebarAfterRun(runId);
    updateFavicon(run.summary.status === 'passed' ? 'success' : 'failure');
}

function updateSidebarTestStatus(suiteName, testId, status, time = null, runId = null) {
    state.testSuites.forEach(suite => {
        if (suite.id === suiteName) {
            suite.methods?.forEach(method => {
                if (method.id === testId) {
                    method.status = status;
                    if (time !== null) method.duration = time;
                    if (runId) method.runId = runId;
                    if (status !== 'running') {
                        method.runId = null;
                    }
                }
            });
        }
    });
}

export function resetSidebarTestStatuses() {
    state.testSuites.forEach(suite => {
        suite.methods?.forEach(method => {
            method.status = null;
            method.duration = null;
            method.runId = null;
        });
    });
}

function stopTestRun(runId) {
    const run = state.realtimeTestRuns[runId];
    if (run) {
        run.status = 'stopped';
    }
    delete state.runningTestIds[runId];
    delete state.stopPending[runId];
    state.isStarting = false;
    updateSidebarAfterRun(runId);
}

function updateSidebarAfterRun(runId) {
    state.testSuites.forEach(suite => {
        if (suite.runId === runId) suite.runId = null;
        suite.methods?.forEach(method => {
            if (method.runId === runId) method.runId = null;
        });
    });
}

function getTestRun(runId) {
    return state.realtimeTestRuns[runId];
}

function getRunningTestCount() {
    return Object.keys(state.runningTestIds).length;
}

function clearRunningTests() {
    state.runningTestIds = {};
    state.stopPending = {};
}

function markStopPending(runId) {
    state.stopPending[runId] = true;
}

function clearStopPending(runId) {
    delete state.stopPending[runId];
}

function toggleSuiteExpansion(suiteId) {
    if (state.expandedSuites.has(suiteId)) {
        state.expandedSuites.delete(suiteId);
    } else {
        state.expandedSuites.add(suiteId);
    }
}

function toggleTestcaseGroupExpansion(className) {
    if (state.expandedTestcaseGroups.has(className)) {
        state.expandedTestcaseGroups.delete(className);
    } else {
        state.expandedTestcaseGroups.add(className);
    }
}

function setExpandedTest(testId) {
    state.expandedTestId = testId;
}

function toggleFilterPanel() {
    state.showFilterPanel = !state.showFilterPanel;
}

function getFailedTestIds() {
    const run = state.realtimeTestRuns[state.lastCompletedRunId];
    return run ? Array.from(run.failedTestIds) : [];
}

function hasFailedTests() {
    const run = state.realtimeTestRuns[state.lastCompletedRunId];
    return run ? run.failedTestIds.size > 0 : false;
}

function clearAllResults() {
    state.realtimeTestRuns = {};
    state.lastCompletedRunId = null;
    state.expandedTestId = null;
    state.expandedTestcaseGroups = new Set();
    state.coverageReport = null;
    state.fileCoverage = null;
    resetSidebarTestStatuses();
    updateFavicon('neutral');
}

function clearFilters() {
    state.selectedSuites = [];
    state.selectedGroups = [];
    state.coverage = false;
    state.options = { ...defaultOptions };
}

function setActiveTab(tab) {
    state.activeTab = tab;
}

function setCoverageReport(report) {
    state.coverageReport = report;
}

function setCoverageLoading(isLoading) {
    state.isCoverageLoading = isLoading;
}

function setFileCoverage(coverage) {
    state.fileCoverage = coverage;
}

loadState();

export function useStore() {
    return {
        state,
        saveState,
        setSortBy,
        setDisplayMode,
        setStarting,
        initializeTestRun,
        handleTestEvent,
        handleSuiteStarted, // Exporting handleSuiteStarted
        handleTestPrepared, // Exporting handleTestPrepared
        handleTestWarningOrDeprecation, // Exporting handleTestWarningOrDeprecation
        handleTestNotice, // Exporting handleTestNotice
        handleTestCompleted, // Exporting handleTestCompleted
        handleTestFinished, // Exporting handleTestFinished
        handleExecutionEnded, // Exporting handleExecutionEnded
        stopTestRun,
        getTestRun,
        getRunningTestCount,
        clearRunningTests,
        markStopPending,
        clearStopPending,
        toggleSuiteExpansion,
        toggleTestcaseGroupExpansion,
        setExpandedTest,
        toggleFilterPanel,
        getFailedTestIds,
        hasFailedTests,
        clearAllResults,
        clearFilters,
        setActiveTab,
        setCoverageReport,
        setCoverageLoading,
        setFileCoverage,
        resetSidebarTestStatuses,
        updateSidebarAfterRun,
    };
}