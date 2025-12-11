// Mock Vue's reactive function
import { vi, expect, describe, test, beforeEach, afterEach} from 'vitest';

vi.mock('vue', () => ({
    reactive: (obj) => obj,
}));

// Mock parseTestId from utils.js
vi.mock('../utils.js', async () => {
    const actualUtils = await vi.importActual('../utils.js');
    return {
        ...actualUtils,
        parseTestId: vi.fn((testId) => {
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
        updateFavicon: vi.fn(),
    };
});

import * as storeModule from '../store.js';

describe('Store', () => {
    let store;

    beforeEach(() => {
        vi.clearAllMocks();
        store = storeModule.useStore();
        vi.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    test('should initialize with correct default state', () => {
        expect(store.state).toEqual({
            testSuites: [],
            coverageDriverMissing: false,
            availableSuites: [],
            availableGroups: [],
            isLoading: false,
            isStarting: false,
            fileCoverage: null,
            expandedSuites: new Set(),
            expandedTestcaseGroups: new Set(),
            expandedTestId: null,
            showFilterPanel: false,
            activeTab: 'results',
            sortBy: 'default',
            sortDirection: 'desc',
            selectedSuites: [],
            selectedGroups: [],
            coverage: false,
            isCoverageLoading: false,
            coverageReport: null,
            testRun: null,
            isRunning: false,
            isStopping: false,
            parallel: false,
            paratest: false,
            options: {
                displayDeprecations: true,
                displayIncomplete: true,
                displayMode: "default",
                displayNotices: true,
                displayRisky: true,
                displaySkipped: true,
                displayWarnings: true,
                stopOnDefect: false,
                stopOnError: false,
                stopOnFailure: false,
                stopOnRisky: false,
                stopOnWarning: false,
            },
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
        test('should set up a new test run and reset isStarting for global context', () => {
            const contextId = 'global';
            store.state.isStarting = true;
            store.state.isStopping = true;

            store.initializeTestRun(contextId);

            expect(store.state.isStarting).toBe(false);
            expect(store.state.isRunning).toBe(true);
            expect(store.state.isStopping).toBe(false);
            expect(store.state.testRun).toEqual({
                status: 'running',
                contextId: contextId,
                executionEnded: false,
                suites: {},
                summary: null,
                sumOfDurations: 0,
                failedTestIds: new Set(),
            });
            expect(store.state.activeTab).toBe('results');
        });

        test('should reset sidebar and expanded state for failed context', () => {
            store.state.expandedTestId = 'someTest';
            store.state.expandedTestcaseGroups.add('someGroup');
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'test1', status: 'passed' }] }];

            store.initializeTestRun('failed');

            expect(store.state.expandedTestId).toBeNull();
            expect(store.state.expandedTestcaseGroups.size).toBe(0);
            // Verify the effect of resetSidebarTestStatuses
            expect(store.state.testSuites[0].methods[0].status).toBeNull();
        });
    });

    describe('handleTestEvent', () => {
        let run;

        beforeEach(() => {
            store.initializeTestRun('global');
            run = store.state.testRun;
        });

        test('should call handleSuiteStarted for suite.started event', () => {
            const eventData = { event: 'suite.started', data: { name: 'SuiteA', count: 1 } };
            store.handleTestEvent(eventData);

            // Verify the effect instead of spying
            expect(run.suites['SuiteA']).toBeDefined();
            expect(run.suites['SuiteA'].name).toBe('SuiteA');
        });

        test('should call handleTestPrepared for test.prepared event', () => {
            run.suites['SuiteA'] = { name: 'SuiteA', tests: {} };
            const eventData = { event: 'test.prepared', data: { testId: 'SuiteA::testMethod' } };
            store.handleTestEvent(eventData);

            // Verify the effect
            expect(run.suites['SuiteA'].tests['SuiteA::testMethod']).toBeDefined();
            expect(run.suites['SuiteA'].tests['SuiteA::testMethod'].status).toBe('running');
        });

        test('should call handleTestWarningOrDeprecation for test.warning event', () => {
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    'SuiteA::testMethod': { id: 'SuiteA::testMethod', warnings: [], deprecations: [] }
                },
                warning: 0,
                hasIssues: false,
            };
            const eventData = { event: 'test.warning', data: { testId: 'SuiteA::testMethod', message: 'Warning!' } };
            store.handleTestEvent(eventData);

            // Verify the effect
            expect(run.suites['SuiteA'].tests['SuiteA::testMethod'].warnings.length).toBe(1);
            expect(run.suites['SuiteA'].warning).toBe(1);
        });

        test('should call handleTestNotice for test.notice event', () => {
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    'SuiteA::testMethod': { id: 'SuiteA::testMethod', notices: [] }
                },
                notice: 0,
                hasIssues: false,
            };
            const eventData = { event: 'test.notice', data: { testId: 'SuiteA::testMethod', message: 'Notice!' } };
            store.handleTestEvent(eventData);

            // Verify the effect
            expect(run.suites['SuiteA'].tests['SuiteA::testMethod'].notices.length).toBe(1);
            expect(run.suites['SuiteA'].notice).toBe(1);
        });

        test('should call handleTestCompleted for test.passed event', () => {
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    'SuiteA::testMethod': { id: 'SuiteA::testMethod', status: 'running' }
                },
                passed: 0,
                hasIssues: false,
            };
            const eventData = { event: 'test.passed', data: { testId: 'SuiteA::testMethod' } };
            store.handleTestEvent(eventData);

            // Verify the effect
            expect(run.suites['SuiteA'].tests['SuiteA::testMethod'].status).toBe('passed');
            expect(run.suites['SuiteA'].passed).toBe(1);
        });

        test('should call handleTestFinished for test.finished event', () => {
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    'SuiteA::testMethod': { id: 'SuiteA::testMethod', duration: null, status: 'passed' }
                }
            };
            const eventData = { event: 'test.finished', data: { testId: 'SuiteA::testMethod', duration: 0.1, assertions: 5 } };
            store.handleTestEvent(eventData);

            // Verify the effect
            expect(run.suites['SuiteA'].tests['SuiteA::testMethod'].duration).toBe(0.1);
            expect(run.suites['SuiteA'].tests['SuiteA::testMethod'].assertions).toBe(5);
        });

        test('should warn for unknown run', () => {
            store.state.testRun = null; // Simulate no active run
            const eventData = { event: 'test.passed', data: { testId: 'SuiteA::testMethod' } };
            store.handleTestEvent(eventData);
            expect(console.warn).toHaveBeenCalledWith('Received event for unknown run');
        });
    });

    describe('handleSuiteStarted', () => {
        let run;
        beforeEach(() => {
            store.initializeTestRun('global');
            run = store.state.testRun;
        });

        test('should add a new suite to the run', () => {
            const eventData = { event: 'suite.started', data: { name: 'SuiteA', count: 5 } };
            store.handleSuiteStarted(run, eventData);

            expect(run.suites['SuiteA']).toEqual({
                name: 'SuiteA',
                count: 5,
                tests: {},
                passed: 0, failed: 0, errored: 0, skipped: 0, incomplete: 0, notice: 0,
                warning: 0, deprecation: 0, risky: 0, hasIssues: false,
            });
        });
    });

    describe('handleTestPrepared', () => {
        let run;
        beforeEach(() => {
            store.initializeTestRun('global');
            run = store.state.testRun;
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'SuiteA::testMethod', status: null }] }];
        });

        test('should add a new test to an existing suite', () => {
            run.suites['SuiteA'] = { name: 'SuiteA', tests: {} };
            const eventData = { event: 'test.prepared', data: { testId: 'SuiteA::testMethod' } };
            store.handleTestPrepared(run, eventData);

            expect(run.suites['SuiteA'].tests['SuiteA::testMethod']).toEqual({
                id: 'SuiteA::testMethod',
                name: 'testMethod',
                assertions: 0,
                class: 'SuiteA',
                status: 'running',
                duration: null, message: null, trace: null,
                warnings: [], deprecations: [], notices: []
            });
            expect(store.state.testSuites[0].methods[0].status).toBe('running');
        });

        test('should create suite if it does not exist and add test', () => {
            const eventData = { event: 'test.prepared', data: { testId: 'SuiteB::testMethod' } };
            store.handleTestPrepared(run, eventData);

            expect(run.suites['SuiteB']).toBeDefined();
            expect(run.suites['SuiteB'].tests['SuiteB::testMethod']).toBeDefined();
        });
    });

    describe('handleTestWarningOrDeprecation', () => {
        let run;
        const testId = 'SuiteA::testMethod';
        beforeEach(() => {
            store.initializeTestRun('global');
            run = store.state.testRun;
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
            const eventData = { event: 'test.warning', data: { testId: testId, message: 'Some warning' } };
            store.handleTestWarningOrDeprecation(run, eventData);

            expect(run.suites['SuiteA'].tests[testId].warnings).toEqual(['Some warning']);
            expect(run.suites['SuiteA'].warning).toBe(1);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });

        test('should add a deprecation to the test and update suite counts', () => {
            const eventData = { event: 'test.deprecation', data: { testId: testId, message: 'Some deprecation' } };
            store.handleTestWarningOrDeprecation(run, eventData);

            expect(run.suites['SuiteA'].tests[testId].deprecations).toEqual(['Some deprecation']);
            expect(run.suites['SuiteA'].deprecation).toBe(1);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });
    });

    describe('handleTestNotice', () => {
        let run;
        const testId = 'SuiteA::testMethod';
        beforeEach(() => {
            store.initializeTestRun('global');
            run = store.state.testRun;
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    [testId]: { id: testId, notices: [] }
                },
                notice: 0,
                hasIssues: false,
            };
        });

        test('should add a notice to the test and update suite counts', () => {
            const eventData = { event: 'test.notice', data: { testId: testId, message: 'Some notice' } };
            store.handleTestNotice(run, eventData);

            expect(run.suites['SuiteA'].tests[testId].notices).toEqual(['Some notice']);
            expect(run.suites['SuiteA'].notice).toBe(1);
            expect(run.suites['SuiteA'].hasIssues).toBe(true);
        });
    });

    describe('handleTestCompleted', () => {
        let run;
        const testId = 'SuiteA::testMethod';

        beforeEach(() => {
            store.initializeTestRun('global');
            run = store.state.testRun;
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    [testId]: { id: testId, status: 'running', warnings: [], deprecations: [], duration: null }
                },
                passed: 0, failed: 0, errored: 0, skipped: 0, incomplete: 0, hasIssues: false,
            };
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: testId, status: 'running' }] }];
        });

        test('should update test status and suite counts for passed test', () => {
            const eventData = { event: 'test.passed', data: { testId: testId } };
            store.handleTestCompleted(run, eventData);

            const test = run.suites['SuiteA'].tests[testId];
            expect(test.status).toBe('passed');
            expect(run.suites['SuiteA'].passed).toBe(1);
            expect(run.failedTestIds.has(testId)).toBe(false);
            expect(run.suites['SuiteA'].hasIssues).toBe(false);
            expect(store.state.testSuites[0].methods[0].status).toBe('passed');
        });

        test('should update test status for failed test and track it', () => {
            const eventData = { event: 'test.failed', data: { testId: testId, message: 'Failed!', trace: 'stack' } };
            store.handleTestCompleted(run, eventData);

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
            const eventData = { event: 'test.passed', data: { testId: testId } };
            store.handleTestCompleted(run, eventData);
            expect(run.failedTestIds.has(testId)).toBe(false);
        });
    });

    describe('handleTestFinished', () => {
        let run;
        const testId = 'SuiteA::testMethod';
        beforeEach(() => {
            store.initializeTestRun('global');
            run = store.state.testRun;
            run.suites['SuiteA'] = {
                name: 'SuiteA',
                tests: {
                    [testId]: { id: testId, duration: null, status: 'passed' }
                }
            };
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: testId, status: 'passed', duration: null }] }];
        });

        test('should update the duration and assertions of a test', () => {
            const eventData = { event: 'test.finished', data: { testId: testId, duration: 1.23, assertions: 5 } };
            store.handleTestFinished(run, eventData);
            const test = run.suites['SuiteA'].tests[testId];
            expect(test.duration).toBe(1.23);
            expect(test.assertions).toBe(5);
            expect(run.sumOfDurations).toBe(1.23);
            expect(store.state.testSuites[0].methods[0].duration).toBe(1.23);
        });
    });

    describe('stopTestRun', () => {
        beforeEach(() => {
            store.initializeTestRun('global');
            store.state.isRunning = true;
            store.state.isStopping = true;
            store.state.isStarting = true;
        });

        test('should mark run as stopped, clear flags, and reset isStarting', () => {
            store.state.testSuites = [
                { id: 'SuiteA', isRunning: true, methods: [{ id: 'SuiteA::test1', status: 'running' }] },
                { id: 'SuiteB', isRunning: false, methods: [{ id: 'SuiteB::test2', status: 'passed' }] },
            ];

            store.stopTestRun();
            expect(store.state.testRun.status).toBe('stopped');
            expect(store.state.isRunning).toBe(false);
            expect(store.state.isStopping).toBe(false);
            expect(store.state.isStarting).toBe(false);

            expect(store.state.testSuites[0].isRunning).toBe(false);
            expect(store.state.testSuites[0].methods[0].status).toBe('running');
            expect(store.state.testSuites[1].isRunning).toBe(false);
            expect(store.state.testSuites[1].methods[0].status).toBe('passed');
        });
    });

    describe('getFailedTestIds', () => {
        test('should return failed test IDs from the current run', () => {
            store.initializeTestRun('global');
            store.state.testRun.failedTestIds.add('test1');
            store.state.testRun.failedTestIds.add('test2');

            expect(store.getFailedTestIds()).toEqual(['test1', 'test2']);
        });
    });

    describe('hasFailedTests', () => {
        test('should return true if the current run has failed tests', () => {
            store.initializeTestRun('global');
            store.state.testRun.failedTestIds.add('test1');

            expect(store.hasFailedTests()).toBe(true);
        });
    });

    describe('clearAllResults', () => {
        test('should clear all test runs and reset state', () => {
            store.initializeTestRun('global');
            store.state.expandedTestId = 'someTest';
            store.state.testSuites = [{ id: 'SuiteA', methods: [{ id: 'test1', status: 'passed' }] }];

            store.clearAllResults();

            expect(store.state.testRun).toBeNull();
            expect(store.state.expandedTestId).toBeNull();
            // Verify the effect of resetSidebarTestStatuses
            expect(store.state.testSuites[0].methods[0].status).toBeNull();
        });
    });
});
