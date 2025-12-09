import { reactive } from 'vue';
import { parseTestId, updateFavicon } from './utils.js';
import { ApiClient } from './api.js';

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

    // Test run
    testRun: null,
    isRunning: false,
    isStopping: false,

    // Coverage
    coverageReport: null,
    isCoverageLoading: false,
    fileCoverage: null,
    coverageDriverMissing: false,
});

// --- Private Store Functions ---

async function _runTests(runOptions = {}) {
    if (state.isStarting) {
        return;
    }

    state.isStarting = true;
    state.activeTab = 'results';

    const api = new ApiClient('');
    const { displayMode, ...phpunitOptions } = state.options;

    const payload = {
        filters: runOptions.filters || [],
        groups: state.selectedGroups,
        suites: state.selectedSuites,
        options: { ...phpunitOptions, colors: true },
        coverage: !!state.coverage,
        contextId: runOptions.contextId || 'global',
    };

    try {
        await api.runTests(payload);
    } catch (error) {
        console.error('Failed to run tests:', error);
        updateFavicon('failure');
        state.isStarting = false;
    }
}

// --- Public Store Functions / Actions ---

function loadState() {
    const savedState = localStorage.getItem(STORAGE_KEY);
    if (savedState) {
        try {
            const parsedState = JSON.parse(savedState);
            if (parsedState.options) {
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

function runTests(runOptions = {}) {
    _runTests(runOptions);
}

function runAllTests() {
    runTests({ contextId: 'global' });
}

function runFailedTests() {
    const failedTestIds = getFailedTestIds();
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
    const api = new ApiClient('');
    try {
        markStopPending();
        await api.stopAllTests();
    } catch (error) {
        console.error('Failed to stop tests:', error);
        clearStopPending();
    }
}

async function stopSingleTest() {
    const api = new ApiClient('');
    try {
        markStopPending();
        await api.stopSingleTest();
    } catch (error) {
        console.error(`Failed to stop test run:`, error);
        clearStopPending();
    }
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

function initializeTestRun(contextId) {
    state.isStarting = false;
    state.isRunning = true;
    state.isStopping = false;

    state.testRun = {
        status: 'running',
        contextId: contextId,
        suites: {},
        summary: null,
        failedTestIds: new Set(),
        executionEnded: false,
        sumOfDurations: 0,
    };
    state.expandedTestId = null;
    state.expandedTestcaseGroups = new Set();
    resetSidebarTestStatuses();

    state.testSuites.forEach(suite => {
        if (suite.id === contextId) {
            suite.isRunning = true;
        }
    });

    state.activeTab = 'results';
}

function handleTestEvent(eventData) {
    const run = state.testRun;

    if (!run) {
        console.warn(`Received event for unknown run`);
        return;
    }

    switch (eventData.event) {
        case 'suite.started':
            handleSuiteStarted(run, eventData);
            break;
        case 'test.prepared':
            handleTestPrepared(run, eventData);
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
            handleTestCompleted(run, eventData);
            break;
        case 'test.finished':
            handleTestFinished(run, eventData);
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

function handleTestPrepared(run, eventData) {
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

    updateSidebarTestStatus(suiteName, eventData.data.testId, 'running');
}

function handleTestWarningOrDeprecation(run, eventData) {
    const { suiteName } = parseTestId(eventData.data.testId);
    const testId = eventData.data.testId;

    if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
        const test = run.suites[suiteName].tests[testId];
        const suite = run.suites[suiteName];

        if (eventData.event === 'test.warning') {
            test.warnings.push(eventData.data.message || 'Some warning triggered');
            suite.warning++;
        } else if (eventData.event === 'test.deprecation') {
            test.deprecations.push(eventData.data.message || 'Some deprecation triggered');
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

        test.notices.push(eventData.data.message || 'Some notice triggered');
        suite.notice++;
        suite.hasIssues = true;
    }
}

function handleTestCompleted(run, eventData) {
    const { suiteName, testName } = parseTestId(eventData.data.testId);
    const testId = eventData.data.testId;

    if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
        const test = run.suites[suiteName].tests[testId];
        const status = eventData.event.replace('test.', '');

        test.status = status;
        test.message = eventData.data.message || null;
        test.trace = eventData.data.trace || null;

        const suite = run.suites[suiteName];
        if (suite[status] !== undefined) {
            suite[status]++;
        }

        if (status === 'failed' || status === 'errored') {
            run.failedTestIds.add(testId);
            suite.hasIssues = true;
        } else if (status === 'passed') {
            run.failedTestIds.delete(testId);
        } else if (status !== 'passed') {
            suite.hasIssues = true;
        }

        updateSidebarTestStatus(suiteName, testId, status, test.duration);
    }
}

function handleTestFinished(run, eventData) {
    const { suiteName } = parseTestId(eventData.data.testId);
    const testId = eventData.data.testId;

    if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
        const test = run.suites[suiteName].tests[testId];
        test.duration = eventData.data.duration;
        test.assertions = eventData.data.assertions;
        run.sumOfDurations += test.duration;

        if (test.status) {
            updateSidebarTestStatus(suiteName, testId, test.status, test.duration);
        }
    }
}

function handleExecutionEnded(eventData) {
    console.log('handleExecutionEnded', eventData);
    const run = state.testRun;
    if (run) {
        run.summary = eventData.data.summary;
        run.executionEnded = true;
        updateFavicon(run.summary.status === 'passed' ? 'success' : 'failure');
    }
}

function finishTestRun() {
    const run = state.testRun;
    if (run) {
        run.status = 'finished';
    }
    state.isRunning = false;
    state.isStopping = false;
    state.isStarting = false;
    updateSidebarAfterRun();
}

function updateSidebarTestStatus(suiteName, testId, status, time = null) {
    state.testSuites.forEach(suite => {
        if (suite.id === suiteName) {
            suite.methods?.forEach(method => {
                if (method.id === testId) {
                    method.status = status;
                    if (time !== null) method.duration = time;
                }
            });
        }
    });
}

function resetSidebarTestStatuses() {
    state.testSuites.forEach(suite => {
        suite.isRunning = false;
        suite.methods?.forEach(method => {
            method.status = null;
            method.duration = null;
        });
    });
}

function stopTestRun() {
    const run = state.testRun;
    if (run) {
        run.status = 'stopped';
    }
    state.isRunning = false;
    state.isStopping = false;
    state.isStarting = false;
    updateSidebarAfterRun();
}

function updateSidebarAfterRun() {
    state.testSuites.forEach(suite => {
        suite.isRunning = false;
    });
}

function getTestRun() {
    return state.testRun;
}

function isTestRunning() {
    return state.isRunning;
}

function clearRunningTests() {
    state.isRunning = false;
    state.isStopping = false;
}

function markStopPending() {
    state.isStopping = true;
}

function clearStopPending() {
    state.isStopping = false;
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
    const run = state.testRun;
    return run ? Array.from(run.failedTestIds) : [];
}

function hasFailedTests() {
    const run = state.testRun;
    return run ? run.failedTestIds.size > 0 : false;
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

async function fetchCoverageReport() {
    const api = new ApiClient('');
    try {
        const report = await api.fetchCoverage();
        setCoverageReport(report);
    } catch (error) {
        console.error('Failed to fetch coverage report:', error);
    } finally {
        setCoverageLoading(false);
    }
}

loadState();

export function useStore() {
    return {
        state,
        saveState,
        runTests,
        runAllTests,
        runFailedTests,
        runSingleTest,
        runSuiteTests,
        stopAllTests,
        stopSingleTest,
        setSortBy,
        setDisplayMode,
        setStarting,
        initializeTestRun,
        handleTestEvent,
        handleSuiteStarted,
        handleTestPrepared,
        handleTestWarningOrDeprecation,
        handleTestNotice,
        handleTestCompleted,
        handleTestFinished,
        handleExecutionEnded,
        finishTestRun,
        stopTestRun,
        getTestRun,
        isTestRunning,
        clearRunningTests,
        markStopPending,
        clearStopPending,
        toggleSuiteExpansion,
        toggleTestcaseGroupExpansion,
        setExpandedTest,
        toggleFilterPanel,
        getFailedTestIds,
        hasFailedTests,
        clearAllResults() {
            this.state.testRun = null;
            this.state.expandedTestId = null;
            this.state.expandedTestcaseGroups = new Set();
            this.state.coverageReport = null;
            this.state.fileCoverage = null;
            this.resetSidebarTestStatuses();
            updateFavicon('neutral');
        },
        clearFilters,
        setActiveTab,
        setCoverageReport,
        setCoverageLoading,
        setFileCoverage,
        fetchCoverageReport,
        resetSidebarTestStatuses,
        updateSidebarAfterRun,
    };
}
