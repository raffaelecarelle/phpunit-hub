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
}));

import { Store } from '../store.js';

describe('Store', () => {
    let store;

    beforeEach(() => {
        store = new Store();
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
            searchQuery: '',
            expandedSuites: new Set(),
            expandedTestcaseGroups: new Set(),
            expandedTestId: null,
            showFilterPanel: false,
            activeTab: 'results',
            selectedSuites: [],
            selectedGroups: [],
            options: {
                displayWarnings: true,
                displayDeprecations: true,
                displaySkipped: true,
                displayIncomplete: true,
                stopOnDefect: false,
                stopOnError: false,
                stopOnFailure: false,
                stopOnWarning: false,
            },
            runningTestIds: {},
            stopPending: {},
            realtimeTestRuns: {},
            lastCompletedRunId: null,
        });
    });

    describe('initializeTestRun', () => {
        test('should set up a new test run correctly', () => {
            const runId = 'run123';
            const contextId = 'global';
            store.state.stopPending[runId] = true; // Simulate a pending stop

            store.initializeTestRun(runId, contextId);

            expect(store.state.realtimeTestRuns[runId]).toEqual({
                status: 'running',
                contextId: contextId,
                suites: {},
                summary: null,
                failedTestIds: new Set(),
            });
            expect(store.state.runningTestIds[runId]).toBe(true);
            expect(store.state.stopPending[runId]).toBeUndefined();
            expect(store.state.activeTab).toBe('results');
        });

        test('should reset sidebar and expanded state for global/failed context', () => {
            const runId = 'run123';
            store.state.expandedTestId = 'someTest';
            store.state.expandedTestcaseGroups.add('someGroup');
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'test1', status: 'passed' }] }];

            const resetSidebarSpy = jest.spyOn(store, 'resetSidebarTestStatuses');

            store.initializeTestRun(runId, 'global');
            expect(store.state.expandedTestId).toBeNull();
            expect(store.state.expandedTestcaseGroups.size).toBe(0);
            expect(resetSidebarSpy).toHaveBeenCalled();

            resetSidebarSpy.mockClear();
            store.initializeTestRun(runId, 'failed');
            expect(resetSidebarSpy).toHaveBeenCalled();
        });

        test('should not reset sidebar for other context IDs', () => {
            const runId = 'run123';
            store.state.expandedTestId = 'someTest';
            store.state.expandedTestcaseGroups.add('someGroup');
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'test1', status: 'passed' }] }];

            const resetSidebarSpy = jest.spyOn(store, 'resetSidebarTestStatuses');

            store.initializeTestRun(runId, 'specificTest');
            expect(store.state.expandedTestId).toBe('someTest');
            expect(store.state.expandedTestcaseGroups.size).toBe(1);
            expect(resetSidebarSpy).not.toHaveBeenCalled();
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
            expect(store.handleTestPrepared).toHaveBeenCalledWith(run, eventData);
        });

        test('should call handleTestWarningOrDeprecation for test.warning event', () => {
            const eventData = { event: 'test.warning', data: { test: 'SuiteA::testMethod' } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleTestWarningOrDeprecation).toHaveBeenCalledWith(run, eventData);
        });

        test('should call handleTestWarningOrDeprecation for test.deprecation event', () => {
            const eventData = { event: 'test.deprecation', data: { test: 'SuiteA::testMethod' } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleTestWarningOrDeprecation).toHaveBeenCalledWith(run, eventData);
        });

        test('should call handleTestCompleted for test.passed event', () => {
            const eventData = { event: 'test.passed', data: { test: 'SuiteA::testMethod' } };
            store.handleTestEvent(runId, eventData);
            expect(store.handleTestCompleted).toHaveBeenCalledWith(run, eventData, runId);
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
        beforeEach(() => {
            store.initializeTestRun('run123', 'global');
            run = store.state.realtimeTestRuns['run123'];
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'SuiteA::testMethod', status: null }] }];
        });

        test('should add a new test to an existing suite', () => {
            run.suites['SuiteA'] = { name: 'SuiteA', tests: {} };
            const eventData = { event: 'test.prepared', data: { test: 'SuiteA::testMethod' } };
            store.handleTestPrepared(run, eventData);

            expect(run.suites['SuiteA'].tests['SuiteA::testMethod']).toEqual({
                id: 'SuiteA::testMethod',
                name: 'testMethod',
                class: 'SuiteA',
                status: 'running',
                time: null, message: null, trace: null,
                warnings: [], deprecations: [],
            });
            expect(store.state.testSuites[0].methods[0].status).toBe('running');
        });

        test('should create suite if it does not exist and add test', () => {
            const eventData = { event: 'test.prepared', data: { test: 'SuiteB::testMethod' } };
            store.handleTestPrepared(run, eventData);

            expect(run.suites['SuiteB']).toBeDefined();
            expect(run.suites['SuiteB'].tests['SuiteB::testMethod']).toBeDefined();
        });
    });

    describe('handleTestWarningOrDeprecation', () => {
        let run;
        const testId = 'SuiteA::testMethod';
        beforeEach(() => {
            store.initializeTestRun('run123', 'global');
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

        test('should handle missing message for warning/deprecation', () => {
            const eventDataWarning = { event: 'test.warning', data: { test: testId } };
            store.handleTestWarningOrDeprecation(run, eventDataWarning);
            expect(run.suites['SuiteA'].tests[testId].warnings).toEqual(['Warning triggered']);

            const eventDataDeprecation = { event: 'test.deprecation', data: { test: testId } };
            store.handleTestWarningOrDeprecation(run, eventDataDeprecation);
            expect(run.suites['SuiteA'].tests[testId].deprecations).toEqual(['Deprecation triggered']);
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
                    [testId]: { id: testId, status: 'running', warnings: [], deprecations: [] }
                },
                passed: 0, failed: 0, errored: 0, skipped: 0, incomplete: 0, hasIssues: false,
            };
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: testId, status: 'running' }] }];
        });

        test('should update test status and suite counts for passed test', () => {
            const eventData = { event: 'test.passed', data: { test: testId, time: 0.5 } };
            store.handleTestCompleted(run, eventData, runId);

            const test = run.suites['SuiteA'].tests[testId];
            expect(test.status).toBe('passed');
            expect(test.time).toBe(0.5);
            expect(run.suites['SuiteA'].passed).toBe(1);
            expect(run.failedTestIds.has(testId)).toBe(false);
            expect(run.suites['SuiteA'].hasIssues).toBe(false);
            expect(store.state.testSuites[0].methods[0].status).toBe('passed');
            expect(store.state.testSuites[0].methods[0].time).toBe(0.5);
            expect(store.state.testSuites[0].methods[0].runId).toBe(runId);
        });

        test('should update test status, suite counts, and track failed test for failed test', () => {
            const eventData = { event: 'test.failed', data: { test: testId, message: 'Failed!', trace: 'stack' } };
            store.handleTestCompleted(run, eventData, runId);

            const test = run.suites['SuiteA'].tests[testId];
            expect(test.status).toBe('failed');
            expect(test.message).toBe('Failed!');
            expect(test.trace).toBe('stack');
            expect(run.suites['SuiteA'].failed).toBe(1);
            expect(run.failedTestIds.has(testId)).toBe(true);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
            expect(store.state.testSuites[0].methods[0].status).toBe('failed');
        });

        test('should update test status and suite counts for errored test', () => {
            const eventData = { event: 'test.errored', data: { test: testId } };
            store.handleTestCompleted(run, eventData, runId);
            expect(run.suites['SuiteA'].tests[testId].status).toBe('errored');
            expect(run.suites['SuiteA'].errored).toBe(1);
            expect(run.failedTestIds.has(testId)).toBe(true);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });

        test('should update test status and suite counts for skipped test', () => {
            const eventData = { event: 'test.skipped', data: { test: testId } };
            store.handleTestCompleted(run, eventData, runId);
            expect(run.suites['SuiteA'].tests[testId].status).toBe('skipped');
            expect(run.suites['SuiteA'].skipped).toBe(1);
            expect(run.failedTestIds.has(testId)).toBe(false);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });

        test('should remove test from failedTestIds if it passes after being failed', () => {
            run.failedTestIds.add(testId);
            const eventData = { event: 'test.passed', data: { test: testId } };
            store.handleTestCompleted(run, eventData, runId);
            expect(run.failedTestIds.has(testId)).toBe(false);
        });
    });

    describe('handleExecutionEnded', () => {
        let run;
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            run = store.state.realtimeTestRuns[runId];
        });

        test('should set the summary and lastCompletedRunId', () => {
            const summary = { numberOfTests: 10, numberOfFailures: 1 };
            const eventData = { event: 'execution.ended', data: { summary: summary } };
            store.handleExecutionEnded(run, eventData, runId);

            expect(run.summary).toEqual(summary);
            expect(store.state.lastCompletedRunId).toBe(runId);
        });
    });

    describe('updateSidebarTestStatus', () => {
        const testId = 'SuiteA::testMethod';
        const suiteId = 'SuiteA';
        const runId = 'run123';

        beforeEach(() => {
            store.state.testSuites = [
                { id: suiteId, methods: [{ id: testId, status: null, time: null, runId: null }] }
            ];
        });

        test('should update status, time, and runId for a matching test', () => {
            store.updateSidebarTestStatus(suiteId, testId, 'passed', 1.23, runId);
            const method = store.state.testSuites[0].methods[0];
            expect(method.status).toBe('passed');
            expect(method.time).toBe(1.23);
            expect(method.runId).toBe(runId);
        });

        test('should not update if suite or test not found', () => {
            store.updateSidebarTestStatus('NonExistentSuite', testId, 'passed');
            expect(store.state.testSuites[0].methods[0].status).toBeNull();

            store.updateSidebarTestStatus(suiteId, 'NonExistentTest', 'passed');
            expect(store.state.testSuites[0].methods[0].status).toBeNull();
        });
    });

    describe('resetSidebarTestStatuses', () => {
        beforeEach(() => {
            store.state.testSuites = [
                { id: 'SuiteA', methods: [{ id: 'test1', status: 'passed', time: 0.5, runId: 'run1' }] },
                { id: 'SuiteB', methods: [{ id: 'test2', status: 'failed', time: 1.0, runId: 'run1' }] },
            ];
        });

        test('should reset status, time, and runId for all tests in sidebar', () => {
            store.resetSidebarTestStatuses();
            expect(store.state.testSuites[0].methods[0]).toEqual({ id: 'test1', status: null, time: null, runId: null });
            expect(store.state.testSuites[1].methods[0]).toEqual({ id: 'test2', status: null, time: null, runId: null });
        });
    });

    describe('finishTestRun', () => {
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            store.state.runningTestIds[runId] = true;
            store.state.stopPending[runId] = true;
            jest.spyOn(store, 'updateSidebarAfterRun');
        });

        test('should mark run as finished and clear running/stop pending flags', () => {
            store.finishTestRun(runId);
            expect(store.state.realtimeTestRuns[runId].status).toBe('finished');
            expect(store.state.runningTestIds[runId]).toBeUndefined();
            expect(store.state.stopPending[runId]).toBeUndefined();
            expect(store.updateSidebarAfterRun).toHaveBeenCalledWith(runId);
        });
    });

    describe('stopTestRun', () => {
        const runId = 'run123';
        beforeEach(() => {
            store.initializeTestRun(runId, 'global');
            store.state.runningTestIds[runId] = true;
            store.state.stopPending[runId] = true;
            jest.spyOn(store, 'updateSidebarAfterRun');
        });

        test('should mark run as stopped and clear running/stop pending flags', () => {
            store.stopTestRun(runId);
            expect(store.state.realtimeTestRuns[runId].status).toBe('stopped');
            expect(store.state.runningTestIds[runId]).toBeUndefined();
            expect(store.state.stopPending[runId]).toBeUndefined();
            expect(store.updateSidebarAfterRun).toHaveBeenCalledWith(runId);
        });
    });

    describe('updateSidebarAfterRun', () => {
        const runId = 'run123';
        beforeEach(() => {
            store.state.testSuites = [
                { id: 'SuiteA', runId: runId, methods: [{ id: 'test1', runId: runId }] },
                { id: 'SuiteB', methods: [{ id: 'test2', runId: 'anotherRun' }] },
            ];
        });

        test('should clear runId from suites and methods matching the given runId', () => {
            store.updateSidebarAfterRun(runId);
            expect(store.state.testSuites[0].runId).toBeNull();
            expect(store.state.testSuites[0].methods[0].runId).toBeNull();
            expect(store.state.testSuites[1].methods[0].runId).toBe('anotherRun'); // Should remain unchanged
        });
    });

    describe('getTestRun', () => {
        test('should return the specified test run', () => {
            const runId = 'run123';
            store.initializeTestRun(runId, 'global');
            expect(store.getTestRun(runId)).toEqual(store.state.realtimeTestRuns[runId]);
        });

        test('should return undefined for a non-existent runId', () => {
            expect(store.getTestRun('nonExistent')).toBeUndefined();
        });
    });

    describe('getRunningTestCount', () => {
        test('should return the count of running tests', () => {
            store.state.runningTestIds = { 'run1': true, 'run2': true };
            expect(store.getRunningTestCount()).toBe(2);
        });

        test('should return 0 if no tests are running', () => {
            store.state.runningTestIds = {};
            expect(store.getRunningTestCount()).toBe(0);
        });
    });

    describe('clearRunningTests', () => {
        test('should clear all running tests and stop pending flags', () => {
            store.state.runningTestIds = { 'run1': true, 'run2': true };
            store.state.stopPending = { 'run1': true };
            store.clearRunningTests();
            expect(store.state.runningTestIds).toEqual({});
            expect(store.state.stopPending).toEqual({});
        });
    });

    describe('markStopPending', () => {
        test('should mark a run as stop pending', () => {
            store.markStopPending('run123');
            expect(store.state.stopPending['run123']).toBe(true);
        });
    });

    describe('clearStopPending', () => {
        test('should clear stop pending status for a run', () => {
            store.state.stopPending['run123'] = true;
            store.clearStopPending('run123');
            expect(store.state.stopPending['run123']).toBeUndefined();
        });
    });

    describe('toggleSuiteExpansion', () => {
        test('should add suiteId to expandedSuites if not present', () => {
            store.toggleSuiteExpansion('SuiteA');
            expect(store.state.expandedSuites.has('SuiteA')).toBe(true);
        });

        test('should remove suiteId from expandedSuites if present', () => {
            store.state.expandedSuites.add('SuiteA');
            store.toggleSuiteExpansion('SuiteA');
            expect(store.state.expandedSuites.has('SuiteA')).toBe(false);
        });
    });

    describe('toggleTestcaseGroupExpansion', () => {
        test('should add className to expandedTestcaseGroups if not present', () => {
            store.toggleTestcaseGroupExpansion('ClassA');
            expect(store.state.expandedTestcaseGroups.has('ClassA')).toBe(true);
        });

        test('should remove className from expandedTestcaseGroups if present', () => {
            store.state.expandedTestcaseGroups.add('ClassA');
            store.toggleTestcaseGroupExpansion('ClassA');
            expect(store.state.expandedTestcaseGroups.has('ClassA')).toBe(false);
        });
    });

    describe('setExpandedTest', () => {
        test('should set the expandedTestId', () => {
            store.setExpandedTest('test1234');
            expect(store.state.expandedTestId).toBe('test1234');
        });
    });

    describe('toggleFilterPanel', () => {
        test('should toggle showFilterPanel from false to true', () => {
            store.state.showFilterPanel = false;
            store.toggleFilterPanel();
            expect(store.state.showFilterPanel).toBe(true);
        });

        test('should toggle showFilterPanel from true to false', () => {
            store.state.showFilterPanel = true;
            store.toggleFilterPanel();
            expect(store.state.showFilterPanel).toBe(false);
        });
    });

    describe('getFailedTestIds', () => {
        test('should return an empty array if no last completed run', () => {
            store.state.lastCompletedRunId = null;
            expect(store.getFailedTestIds()).toEqual([]);
        });

        test('should return failed test IDs from the last completed run', () => {
            const runId = 'run123';
            store.initializeTestRun(runId, 'global');
            store.state.lastCompletedRunId = runId;
            store.state.realtimeTestRuns[runId].failedTestIds.add('test1');
            store.state.realtimeTestRuns[runId].failedTestIds.add('test2');

            expect(store.getFailedTestIds()).toEqual(['test1', 'test2']);
        });
    });

    describe('hasFailedTests', () => {
        test('should return false if no last completed run', () => {
            store.state.lastCompletedRunId = null;
            expect(store.hasFailedTests()).toBe(false);
        });

        test('should return true if the last completed run has failed tests', () => {
            const runId = 'run123';
            store.initializeTestRun(runId, 'global');
            store.state.lastCompletedRunId = runId;
            store.state.realtimeTestRuns[runId].failedTestIds.add('test1');

            expect(store.hasFailedTests()).toBe(true);
        });

        test('should return false if the last completed run has no failed tests', () => {
            const runId = 'run123';
            store.initializeTestRun(runId, 'global');
            store.state.lastCompletedRunId = runId;

            expect(store.hasFailedTests()).toBe(false);
        });
    });
});
