// Mock Vue's reactive function
jest.mock('https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.prod.js', () => ({
    reactive: (obj) => obj, // Simply return the object for testing purposes
}));

// Mock parseTestId from utils.js
const { parseTestId } = jest.requireActual('../utils.js'); // Use requireActual to get the real module, then mock specific functions
jest.mock('../utils.js', () => ({
    ...jest.requireActual('../utils.js'), // Import and retain default behavior
    parseTestId: jest.fn((testId) => { // Mock only parseTestId
        const separatorIndex = testId.indexOf('::');
        if (separatorIndex === -1) {
            return {
                suiteName: testId,
                testName: undefined,
                fullId: testId
            };
        }
        return {
            suiteName: testId.substring(0, separatorIndex),
            testName: testId.substring(separatorIndex + 2),
            fullId: testId
        };
    }),
    updateFavicon: jest.fn(),
}));

import { Store } from '../store.js';

describe('Store', () => {
    let store;

    beforeEach(() => {
        store = new Store();
        localStorage.clear();
        jest.clearAllMocks();
        jest.spyOn(console, 'warn').mockImplementation(() => {}); // Suppress console.warn
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    test('should initialize with correct default state', () => {
        expect(store.state).toEqual({
            testSuites: [],
            availableSuites: [],
            availableGroups: [],
            isLoading: false,
            isStarting: false,
            fileCoverage: null,
            searchQuery: '',
            coverage: false,
            isCoverageLoading: false,
            coverageReport: null,
            expandedSuites: new Set(),
            expandedTestcaseGroups: new Set(),
            expandedTestId: null,
            showFilterPanel: false,
            activeTab: 'results',
            sortBy: 'default',
            sortDirection: 'desc',
            selectedSuites: [],
            selectedGroups: [],
            options: Store.defaultOptions,
            runningTestIds: {},
            stopPending: {},
            realtimeTestRuns: {},
            lastCompletedRunId: null,
        });
    });

    describe('setStarting', () => {
        test('should set isStarting state', () => {
            expect(store.state.isStarting).toBe(false);
            store.setStarting(true);
            expect(store.state.isStarting).toBe(true);
            store.setStarting(false);
            expect(store.state.isStarting).toBe(false);
        });
    });

    describe('initializeTestRun', () => {
        test('should set up a new test run and reset isStarting', () => {
            const runId = 'run123';
            const contextId = 'global';
            store.state.isStarting = true;
            store.state.stopPending[runId] = true; // Simulate a pending stop

            store.initializeTestRun(runId, contextId);

            expect(store.state.isStarting).toBe(false);
            expect(store.state.realtimeTestRuns[runId]).toEqual({
                status: 'running',
                contextId: contextId,
                executionEnded: false,
                suites: {},
                summary: null,
                sumOfDurations: 0,
                failedTestIds: new Set(),
            });
            expect(store.state.runningTestIds[runId]).toBe(true);
            expect(store.state.stopPending[runId]).toBeUndefined();
            expect(store.state.activeTab).toBe('results');
        });

        test('should reset sidebar and expanded state for failed context', () => {
            const runId = 'run123';
            store.state.expandedTestId = 'someTest';
            store.state.expandedTestcaseGroups.add('someGroup');
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'test1', status: 'passed' }] }];

            const resetSidebarSpy = jest.spyOn(store, 'resetSidebarTestStatuses');

            store.initializeTestRun(runId, 'failed');
            expect(store.state.expandedTestId).toBeNull();
            expect(store.state.expandedTestcaseGroups.size).toBe(0);
            expect(resetSidebarSpy).toHaveBeenCalled();
        });

        test('should reset results for global context in reset mode', () => {
            const runId = 'run123';
            store.state.options.resultUpdateMode = 'reset';
            store.state.realtimeTestRuns['oldRun'] = { status: 'finished' };
            store.state.lastCompletedRunId = 'oldRun';

            store.initializeTestRun(runId, 'global');
            expect(store.state.realtimeTestRuns['oldRun']).toBeUndefined();
            expect(store.state.lastCompletedRunId).toBeNull();
        });

        test('should not reset results for global context in update mode', () => {
            const runId = 'run123';
            store.state.options.resultUpdateMode = 'update';
            store.state.realtimeTestRuns['oldRun'] = { status: 'finished' };
            store.state.lastCompletedRunId = 'oldRun';

            store.initializeTestRun(runId, 'global');
            expect(store.state.realtimeTestRuns['oldRun']).toBeDefined();
            expect(store.state.lastCompletedRunId).toBe('oldRun');
        });
    });

    describe('handleTestEvent', () => {
        let run;
        const runId = 'run123';

        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            run = store.state.realtimeTestRuns[runId];
            jest.spyOn(store, 'handleSuiteStarted');
            jest.spyOn(store, 'handleTestPrepared');
            jest.spyOn(store, 'handleTestWarningOrDeprecation');
            jest.spyOn(store, 'handleTestCompleted');
            jest.spyOn(store, 'handleTestFinished');
            jest.spyOn(store, 'handleExecutionEnded');
        });

        test('should call handleSuiteStarted for suite.started event', () => {
            const eventData = { event: 'suite.started', data: { name: 'SuiteA', count: 1 } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleSuiteStarted).toHaveBeenCalledWith(run, eventData);
        });

        test('should call handleTestPrepared for test.prepared event', () => {
            const eventData = { event: 'test.prepared', data: { test: 'SuiteA::testMethod' } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleTestPrepared).toHaveBeenCalledWith(run, eventData, runId);
        });

        test('should call handleTestWarningOrDeprecation for test.warning event', () => {
            const eventData = { event: 'test.warning', data: { test: 'SuiteA::testMethod' } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleTestWarningOrDeprecation).toHaveBeenCalledWith(run, eventData);
        });

        test('should call handleTestCompleted for test.passed event', () => {
            const eventData = { event: 'test.passed', data: { test: 'SuiteA::testMethod' } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleTestCompleted).toHaveBeenCalledWith(run, eventData, runId);
        });

        test('should call handleTestFinished for test.finished event', () => {
            const eventData = { event: 'test.finished', data: { test: 'SuiteA::testMethod', duration: 0.1 } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleTestFinished).toHaveBeenCalledWith(run, eventData, 'run123');
        });

        test('should call handleExecutionEnded for execution.ended event', () => {
            const eventData = { event: 'execution.ended', data: { summary: {} } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleExecutionEnded).toHaveBeenCalledWith(run, eventData, runId);
        });

        test('should warn for unknown runId', () => {
            const eventData = { event: 'test.passed', data: { test: 'SuiteA::testMethod' } };
            store.handleTestEvent('unknownRun', eventData);
            expect(console.warn).toHaveBeenCalledWith('Received event for unknown runId: unknownRun');
        });
    });

    describe('handleSuiteStarted', () => {
        let run;
        beforeEach(() => {
            store.initializeTestRun('run123', 'global');
            run = store.state.realtimeTestRuns['run123'];
        });

        test('should add a new suite to the run', () => {
            const eventData = { event: 'suite.started', data: { name: 'SuiteA', count: 5 } };
            store.handleSuiteStarted(run, eventData);

            expect(run.suites['SuiteA']).toEqual({
                name: 'SuiteA',
                count: 5,
                tests: {},
                passed: 0, failed: 0, errored: 0, skipped: 0, incomplete: 0,
                warning: 0, deprecation: 0, risky: 0, hasIssues: false,
            });
        });
    });

    describe('handleTestPrepared', () => {
        let run;
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            run = store.state.realtimeTestRuns[runId];
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'SuiteA::testMethod', status: null }] }];
        });

        test('should add a new test to an existing suite', () => {
            run.suites['SuiteA'] = { name: 'SuiteA', tests: {} };
            const eventData = { event: 'test.prepared', data: { test: 'SuiteA::testMethod' } };
            store.handleTestPrepared(run, eventData, runId);

            expect(run.suites['SuiteA'].tests['SuiteA::testMethod']).toEqual({
                id: 'SuiteA::testMethod',
                name: 'testMethod',
                assertions: 0,
                class: 'SuiteA',
                status: 'running',
                duration: null, message: null, trace: null,
                warnings: [], deprecations: [],
            });
            expect(store.state.testSuites[0].methods[0].status).toBe('running');
            expect(store.state.testSuites[0].methods[0].runId).toBe(runId);
        });

        test('should create suite if it does not exist and add test', () => {
            const eventData = { event: 'test.prepared', data: { test: 'SuiteB::testMethod' } };
            store.handleTestPrepared(run, eventData, runId);

            expect(run.suites['SuiteB']).toBeDefined();
            expect(run.suites['SuiteB'].tests['SuiteB::testMethod']).toBeDefined();
        });
    });

    describe('handleTestWarningOrDeprecation', () => {
        let run;
        const testId = 'SuiteA::testMethod';
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            run = store.state.realtimeTestRuns[runId];
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    [testId]: { id: testId, warnings: [], deprecations: [] }
                },
                warning: 0,
                deprecation: 0,
                hasIssues: false,
            };
        });

        test('should add a warning to the test and update suite counts', () => {
            const eventData = { event: 'test.warning', data: { test: testId, message: 'Some warning' } };
            store.handleTestWarningOrDeprecation(run, eventData);

            expect(run.suites['SuiteA'].tests[testId].warnings).toEqual(['Some warning']);
            expect(run.suites['SuiteA'].warning).toBe(1);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });

        test('should add a deprecation to the test and update suite counts', () => {
            const eventData = { event: 'test.deprecation', data: { test: testId, message: 'Some deprecation' } };
            store.handleTestWarningOrDeprecation(run, eventData);

            expect(run.suites['SuiteA'].tests[testId].deprecations).toEqual(['Some deprecation']);
            expect(run.suites['SuiteA'].deprecation).toBe(1);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });
    });

    describe('handleTestCompleted', () => {
        let run;
        const testId = 'SuiteA::testMethod';
        const runId = 'run123';

        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            run = store.state.realtimeTestRuns[runId];
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    [testId]: { id: testId, status: 'running', warnings: [], deprecations: [], duration: null }
                },
                passed: 0, failed: 0, errored: 0, skipped: 0, incomplete: 0, hasIssues: false,
            };
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: testId, status: 'running', runId: runId }] }];
        });

        test('should update test status and suite counts for passed test', () => {
            const eventData = { event: 'test.passed', data: { test: testId } };
            store.handleTestCompleted(run, eventData, runId);

            const test = run.suites['SuiteA'].tests[testId];
            expect(test.status).toBe('passed');
            expect(run.suites['SuiteA'].passed).toBe(1);
            expect(run.failedTestIds.has(testId)).toBe(false);
            expect(run.suites['SuiteA'].hasIssues).toBe(false);
            expect(store.state.testSuites[0].methods[0].status).toBe('passed');
            expect(store.state.testSuites[0].methods[0].runId).toBeNull();
        });

        test('should update test status for failed test and track it', () => {
            const eventData = { event: 'test.failed', data: { test: testId, message: 'Failed!', trace: 'stack' } };
            store.handleTestCompleted(run, eventData, runId);

            const test = run.suites['SuiteA'].tests[testId];
            expect(test.status).toBe('failed');
            expect(test.message).toBe('Failed!');
            expect(test.trace).toBe('stack');
            expect(run.suites['SuiteA'].failed).toBe(1);
            expect(run.failedTestIds.has(testId)).toBe(true);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });

        test('should remove test from failedTestIds if it passes after being failed', () => {
            run.failedTestIds.add(testId);
            const eventData = { event: 'test.passed', data: { test: testId } };
            store.handleTestCompleted(run, eventData, runId);
            expect(run.failedTestIds.has(testId)).toBe(false);
        });

        test('should remove test from all runs failedTestIds in update mode when test passes', () => {
            store.state.options.resultUpdateMode = 'update';
            const runId2 = 'run456';
            store.initializeTestRun(runId2, 'global');
            const run2 = store.state.realtimeTestRuns[runId2];
            run2.failedTestIds.add(testId);
            run.failedTestIds.add(testId);

            const eventData = { event: 'test.passed', data: { test: testId } };
            store.handleTestCompleted(run, eventData, runId);

            expect(run.failedTestIds.has(testId)).toBe(false);
            expect(run2.failedTestIds.has(testId)).toBe(false);
        });
    });

    describe('handleTestFinished', () => {
        let run;
        const testId = 'SuiteA::testMethod';
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            run = store.state.realtimeTestRuns[runId];
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    [testId]: { id: testId, duration: null, status: 'passed' }
                }
            };
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: testId, status: 'passed', duration: null }] }];
        });

        test('should update the duration and assertions of a test', () => {
            const eventData = { event: 'test.finished', data: { test: testId, duration: 1.23, assertions: 5 } };
            store.handleTestFinished(run, eventData, runId);
            const test = run.suites['SuiteA'].tests[testId];
            expect(test.duration).toBe(1.23);
            expect(test.assertions).toBe(5);
            expect(run.sumOfDurations).toBe(1.23);
            expect(store.state.testSuites[0].methods[0].duration).toBe(1.23);
        });
    });

    describe('handleExecutionEnded', () => {
        let run;
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            run = store.state.realtimeTestRuns[runId];
            store.state.runningTestIds[runId] = true;
            store.state.stopPending[runId] = true;
            store.state.isStarting = true;
            jest.spyOn(store, 'updateSidebarAfterRun');
        });

        test('should set summary, status, clear flags, and reset isStarting', () => {
            const summary = { numberOfTests: 10, numberOfFailures: 1, status: 'failure' };
            const eventData = { event: 'execution.ended', data: { summary: summary } };
            store.handleExecutionEnded(run, eventData, runId);

            expect(run.summary).toEqual(summary);
            expect(run.status).toBe('finished');
            expect(store.state.lastCompletedRunId).toBe(runId);
            expect(store.state.runningTestIds[runId]).toBeUndefined();
            expect(store.state.stopPending[runId]).toBeUndefined();
            expect(store.state.isStarting).toBe(false);
            expect(store.updateSidebarAfterRun).toHaveBeenCalledWith(runId);
        });
    });

    describe('stopTestRun', () => {
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            store.state.runningTestIds[runId] = true;
            store.state.stopPending[runId] = true;
            store.state.isStarting = true;
            jest.spyOn(store, 'updateSidebarAfterRun');
        });

        test('should mark run as stopped, clear flags, and reset isStarting', () => {
            store.stopTestRun(runId);
            expect(store.state.realtimeTestRuns[runId].status).toBe('stopped');
            expect(store.state.runningTestIds[runId]).toBeUndefined();
            expect(store.state.stopPending[runId]).toBeUndefined();
            expect(store.state.isStarting).toBe(false);
            expect(store.updateSidebarAfterRun).toHaveBeenCalledWith(runId);
        });
    });

    describe('getFailedTestIds', () => {
        test('should return failed test IDs from the last completed run in reset mode', () => {
            store.state.options.resultUpdateMode = 'reset';
            const runId = 'run123';
            store.initializeTestRun(runId, 'global');
            store.state.lastCompletedRunId = runId;
            store.state.realtimeTestRuns[runId].failedTestIds.add('test1');
            store.state.realtimeTestRuns[runId].failedTestIds.add('test2');

            expect(store.getFailedTestIds()).toEqual(['test1', 'test2']);
        });

        test('should return failed test IDs from all runs in update mode', () => {
            store.state.options.resultUpdateMode = 'update';
            store.initializeTestRun('run1', 'global');
            store.state.realtimeTestRuns['run1'].failedTestIds.add('test1');
            store.initializeTestRun('run2', 'global');
            store.state.realtimeTestRuns['run2'].failedTestIds.add('test2');

            const failedIds = store.getFailedTestIds();
            expect(failedIds).toContain('test1');
            expect(failedIds).toContain('test2');
        });
    });

    describe('hasFailedTests', () => {
        test('should return true if the last completed run has failed tests in reset mode', () => {
            store.state.options.resultUpdateMode = 'reset';
            const runId = 'run123';
            store.initializeTestRun(runId, 'global');
            store.state.lastCompletedRunId = runId;
            store.state.realtimeTestRuns[runId].failedTestIds.add('test1');

            expect(store.hasFailedTests()).toBe(true);
        });

        test('should return true if any run has failed tests in update mode', () => {
            store.state.options.resultUpdateMode = 'update';
            store.initializeTestRun('run1', 'global');
            store.state.realtimeTestRuns['run1'].failedTestIds.add('test1');

            expect(store.hasFailedTests()).toBe(true);
        });
    });

    describe('clearAllResults', () => {
        test('should clear all test runs and reset state', () => {
            store.initializeTestRun('run1', 'global');
            store.state.lastCompletedRunId = 'run1';
            store.state.expandedTestId = 'someTest';
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'test1', status: 'passed' }] }];
            const resetSidebarSpy = jest.spyOn(store, 'resetSidebarTestStatuses');

            store.clearAllResults();

            expect(store.state.realtimeTestRuns).toEqual({});
            expect(store.state.lastCompletedRunId).toBeNull();
            expect(store.state.expandedTestId).toBeNull();
            expect(resetSidebarSpy).toHaveBeenCalled();
        });
    });
});
