// Mock dependencies
class MockStore {
    constructor() {
        this.state = {
            testSuites: [],
            availableSuites: [],
            availableGroups: [],
            selectedGroups: [],
            selectedSuites: [],
            options: {},
            runningTestIds: {},
            stopPending: {},
            lastCompletedRunId: null,
            realtimeTestRuns: {},
            searchQuery: '',
        };
    }
    getFailedTestIds() { return []; }
    hasFailedTests() { return false; }
    markStopPending(runId) { this.state.stopPending[runId] = true; }
    clearStopPending(runId) { delete this.state.stopPending[runId]; }
}

class MockApiClient {
    constructor() {
        this.fetchTests = jest.fn(() => Promise.resolve({ suites: [], availableSuites: [], availableGroups: [] }));
        this.runTests = jest.fn(() => Promise.resolve());
        this.stopAllTests = jest.fn(() => Promise.resolve());
        this.stopSingleTest = jest.fn(() => Promise.resolve());
    }
}

// Mock WebSocketManager class
const MockWebSocketManager = jest.fn(() => ({
    connect: jest.fn(() => Promise.resolve()),
}));
jest.mock('../websocket.js', () => ({
    WebSocketManager: MockWebSocketManager,
}));


// Mock utils.js functions
jest.doMock('../utils.js', () => ({
    ...jest.requireActual('../utils.js'), // Keep original functions if not mocking them
    updateFavicon: jest.fn(), // Mock this specific function
}));

// Import the mocked function
import { updateFavicon } from '../utils.js';

import { App } from '../app.js';

describe('App', () => {
    let app;
    let store;
    let api;
    // let wsManager; // No longer needed as we mock the class directly

    beforeEach(() => {
        // Reset mocks before each test
        jest.clearAllMocks();

        // Create new instances of mocks and App
        store = new MockStore();
        api = new MockApiClient();
        // wsManager = new MockWebSocketManager(); // No longer needed

        app = new App();
        app.store = store;
        app.api = api;
        // The App constructor will now use our mocked WebSocketManager
        // app.wsManager = wsManager; // No longer needed

        // Mock setupResizer as it interacts with the DOM
        app.setupResizer = jest.fn();
    });

    describe('initialize', () => {
        test('should fetch tests, connect websocket, and setup resizer', async () => {
            // Ensure the mocked WebSocketManager is used
            const mockWsManagerInstance = new MockWebSocketManager();
            app.wsManager = mockWsManagerInstance; // Assign the mock instance to app.wsManager

            await app.initialize();

            expect(api.fetchTests).toHaveBeenCalledTimes(1);
            expect(mockWsManagerInstance.connect).toHaveBeenCalledTimes(1); // Check the mock instance's connect method
            expect(app.setupResizer).toHaveBeenCalledTimes(1);
            expect(updateFavicon).toHaveBeenCalledWith('neutral');
        });

        test('should log an error if initialization fails', async () => {
            const error = new Error('Failed to fetch tests');
            api.fetchTests.mockRejectedValueOnce(error);
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

            await app.initialize();

            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to initialize app:', error);
            consoleErrorSpy.mockRestore();
        });
    });

    describe('fetchTests', () => {
        test('should fetch tests and update store', async () => {
            const mockTestData = {
                suites: [{ name: 'Suite1', methods: [{ id: 'test1', name: 'testMethod1' }] }],
                availableSuites: ['Suite1'],
                availableGroups: ['Group1'],
            };
            api.fetchTests.mockResolvedValueOnce(mockTestData);

            await app.fetchTests();

            expect(api.fetchTests).toHaveBeenCalledTimes(1);
            expect(store.state.testSuites).toEqual(mockTestData.suites);
            expect(store.state.availableSuites).toEqual(mockTestData.availableSuites);
            expect(store.state.availableGroups).toEqual(mockTestData.availableGroups);
            expect(app.testIndex).toEqual({
                'test1': { suite: mockTestData.suites[0], method: mockTestData.suites[0].methods[0] }
            });
        });

        test('should log an error if fetching tests fails', async () => {
            const error = new Error('API error');
            api.fetchTests.mockRejectedValueOnce(error);
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

            await app.fetchTests();

            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch tests:', error);
            consoleErrorSpy.mockRestore();
        });
    });

    describe('buildTestIndex', () => {
        test('should build test index correctly', () => {
            store.state.testSuites = [
                {
                    name: 'SuiteA',
                    methods: [
                        { id: 'suiteA_test1', name: 'test1' },
                        { id: 'suiteA_test2', name: 'test2' },
                    ],
                },
                {
                    name: 'SuiteB',
                    methods: [
                        { id: 'suiteB_test1', name: 'test3' },
                    ],
                },
            ];

            app.buildTestIndex();

            expect(app.testIndex).toEqual({
                'suiteA_test1': { suite: store.state.testSuites[0], method: store.state.testSuites[0].methods[0] },
                'suiteA_test2': { suite: store.state.testSuites[0], method: store.state.testSuites[0].methods[1] },
                'suiteB_test1': { suite: store.state.testSuites[1], method: store.state.testSuites[1].methods[0] },
            });
        });

        test('should handle suites with no methods', () => {
            store.state.testSuites = [
                { name: 'SuiteC', methods: [] },
                { name: 'SuiteD' },
            ];

            app.buildTestIndex();

            expect(app.testIndex).toEqual({});
        });
    });

    describe('runTests', () => {
        test('should call api.runTests with correct payload', async () => {
            store.state.selectedGroups = ['GroupA'];
            store.state.selectedSuites = ['SuiteX'];
            store.state.options = { stopOnFailure: true };

            await app.runTests({ filters: ['testId1'], contextId: 'specificTest' });

            expect(store.state.activeTab).toBe('results');
            expect(api.runTests).toHaveBeenCalledWith({
                filters: ['testId1'],
                groups: ['GroupA'],
                suites: ['SuiteX'],
                options: { stopOnFailure: true, colors: true },
                contextId: 'specificTest',
            });
        });

        test('should log an error and update favicon if running tests fails', async () => {
            const error = new Error('Run error');
            api.runTests.mockRejectedValueOnce(error);
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

            await app.runTests();

            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to run tests:', error);
            expect(updateFavicon).toHaveBeenCalledWith('failure');
            consoleErrorSpy.mockRestore();
        });
    });

    describe('runAllTests', () => {
        test('should call runTests with global contextId', async () => {
            const runTestsSpy = jest.spyOn(app, 'runTests');
            await app.runAllTests();
            expect(runTestsSpy).toHaveBeenCalledWith({ contextId: 'global' });
            runTestsSpy.mockRestore();
        });
    });

    describe('runFailedTests', () => {
        test('should call runTests with failed test IDs', async () => {
            jest.spyOn(store, 'getFailedTestIds').mockReturnValueOnce(['failedTest1', 'failedTest2']);
            const runTestsSpy = jest.spyOn(app, 'runTests');

            await app.runFailedTests();

            expect(runTestsSpy).toHaveBeenCalledWith({ filters: ['failedTest1', 'failedTest2'], contextId: 'failed' });
            runTestsSpy.mockRestore();
        });

        test('should not run tests if no failed tests', async () => {
            jest.spyOn(store, 'getFailedTestIds').mockReturnValueOnce([]);
            const runTestsSpy = jest.spyOn(app, 'runTests');
            const consoleLogSpy = jest.spyOn(console, 'log').mockImplementation(() => {});

            await app.runFailedTests();

            expect(runTestsSpy).not.toHaveBeenCalled();
            expect(consoleLogSpy).toHaveBeenCalledWith('No failed tests to run.');
            consoleLogSpy.mockRestore();
            runTestsSpy.mockRestore();
        });
    });

    describe('runSingleTest', () => {
        test('should call runTests with the given testId', async () => {
            const runTestsSpy = jest.spyOn(app, 'runTests');
            await app.runSingleTest('test1234');
            expect(runTestsSpy).toHaveBeenCalledWith({ filters: ['test1234'], contextId: 'test1234' });
            runTestsSpy.mockRestore();
        });
    });

    describe('runSuiteTests', () => {
        test('should call runTests with the given suiteId', async () => {
            const runTestsSpy = jest.spyOn(app, 'runTests');
            await app.runSuiteTests('suiteABC');
            expect(runTestsSpy).toHaveBeenCalledWith({ filters: ['suiteABC'], contextId: 'suiteABC' });
            runTestsSpy.mockRestore();
        });
    });

    describe('stopAllTests', () => {
        test('should mark all running tests as stop pending and call api.stopAllTests', async () => {
            store.state.runningTestIds = { 'run1': true, 'run2': true };
            const markStopPendingSpy = jest.spyOn(store, 'markStopPending');

            await app.stopAllTests();

            expect(markStopPendingSpy).toHaveBeenCalledWith('run1');
            expect(markStopPendingSpy).toHaveBeenCalledWith('run2');
            expect(api.stopAllTests).toHaveBeenCalledTimes(1);
            markStopPendingSpy.mockRestore();
        });

        test('should clear stop pending if stopping all tests fails', async () => {
            const error = new Error('Stop error');
            api.stopAllTests.mockRejectedValueOnce(error);
            store.state.runningTestIds = { 'run1': true };
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
            const clearStopPendingSpy = jest.spyOn(store, 'clearStopPending');

            await app.stopAllTests();

            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to stop tests:', error);
            expect(clearStopPendingSpy).toHaveBeenCalledWith('run1');
            consoleErrorSpy.mockRestore();
            clearStopPendingSpy.mockRestore();
        });
    });

    describe('stopSingleTest', () => {
        test('should mark single test as stop pending and call api.stopSingleTest', async () => {
            const markStopPendingSpy = jest.spyOn(store, 'markStopPending');

            await app.stopSingleTest('runId123');

            expect(markStopPendingSpy).toHaveBeenCalledWith('runId123');
            expect(api.stopSingleTest).toHaveBeenCalledWith('runId123');
            markStopPendingSpy.mockRestore();
        });

        test('should clear stop pending if stopping single test fails', async () => {
            const error = new Error('Stop single error');
            api.stopSingleTest.mockRejectedValueOnce(error);
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
            const clearStopPendingSpy = jest.spyOn(store, 'clearStopPending');

            await app.stopSingleTest('runId123');

            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to stop test run runId123:', error);
            expect(clearStopPendingSpy).toHaveBeenCalledWith('runId123');
            consoleErrorSpy.mockRestore();
            clearStopPendingSpy.mockRestore();
        });
    });

    describe('togglePlayStop', () => {
        test('should call stopAllTests if any test is running', async () => {
            store.state.runningTestIds = { 'run1': true };
            const stopAllTestsSpy = jest.spyOn(app, 'stopAllTests');
            const runAllTestsSpy = jest.spyOn(app, 'runAllTests');

            await app.togglePlayStop();

            expect(stopAllTestsSpy).toHaveBeenCalledTimes(1);
            expect(runAllTestsSpy).not.toHaveBeenCalled();
            stopAllTestsSpy.mockRestore();
            runAllTestsSpy.mockRestore();
        });

        test('should call runAllTests if no tests are running', async () => {
            store.state.runningTestIds = {};
            const stopAllTestsSpy = jest.spyOn(app, 'stopAllTests');
            const runAllTestsSpy = jest.spyOn(app, 'runAllTests');

            await app.togglePlayStop();

            expect(stopAllTestsSpy).not.toHaveBeenCalled();
            expect(runAllTestsSpy).toHaveBeenCalledTimes(1);
            stopAllTestsSpy.mockRestore();
            runAllTestsSpy.mockRestore();
        });
    });

    describe('getResults', () => {
        test('should return null if no test runs', () => {
            store.state.realtimeTestRuns = {};
            expect(app.getResults()).toBeNull();
        });

        test('should return results for the last completed run', () => {
            store.state.lastCompletedRunId = 'run1';
            store.state.realtimeTestRuns = {
                'run1': {
                    summary: {
                        numberOfTests: 1, numberOfAssertions: 1, duration: 0.1,
                        numberOfFailures: 0, numberOfErrors: 0, numberOfWarnings: 0,
                        numberOfSkipped: 0, numberOfDeprecations: 0, numberOfIncomplete: 0,
                    },
                    suites: {
                        'SuiteA': {
                            name: 'SuiteA',
                            tests: {
                                'test1': { id: 'test1', name: 'testMethod', class: 'ClassA', status: 'passed' }
                            }
                        }
                    }
                }
            };

            const results = app.getResults();
            expect(results).toEqual({
                summary: {
                    tests: 1, assertions: 1, time: 0.1,
                    failures: 0, errors: 0, warnings: 0,
                    skipped: 0, deprecations: 0, incomplete: 0,
                },
                suites: [
                    {
                        name: 'SuiteA',
                        testcases: [
                            { id: 'test1', name: 'testMethod', class: 'ClassA', time: 0, status: 'passed', message: undefined, trace: undefined, warnings: [], deprecations: [] }
                        ]
                    }
                ]
            });
        });

        test('should return results for the most recent run if no completed run', () => {
            store.state.lastCompletedRunId = null;
            store.state.realtimeTestRuns = {
                'run1': { /* older run */ },
                'run2': {
                    summary: { numberOfTests: 2, numberOfFailures: 1 },
                    suites: {
                        'SuiteB': {
                            name: 'SuiteB',
                            tests: {
                                'test2': { id: 'test2', name: 'testMethod2', class: 'ClassB', status: 'failed' }
                            }
                        }
                    }
                }
            };

            const results = app.getResults();
            // The original test expected results.summary.numberOfTests to be 2,
            // but the mock data for run2 only specifies numberOfTests: 2 in summary,
            // and the getResults method transforms it to 'tests'.
            // Also, the suites transformation sets time to 0 if not present.
            expect(results.summary.tests).toBe(2);
            expect(results.suites[0].name).toBe('SuiteB');
        });
    });

    describe('getGroupedResults', () => {
        test('should return empty array if no results', () => {
            jest.spyOn(app, 'getResults').mockReturnValueOnce(null);
            expect(app.getGroupedResults()).toEqual([]);
        });

        test('should group test cases by class and sort them', () => {
            jest.spyOn(app, 'getResults').mockReturnValueOnce({
                suites: [
                    {
                        name: 'Suite1',
                        testcases: [
                            { id: 't1', name: 'testA', class: 'ClassB', status: 'passed' },
                            { id: 't2', name: 'testB', class: 'ClassA', status: 'failed' },
                            { id: 't3', name: 'testC', class: 'ClassB', status: 'warning', warnings: ['warn'] },
                        ]
                    }
                ]
            });

            const grouped = app.getGroupedResults();
            expect(grouped.length).toBe(2);
            expect(grouped[0].className).toBe('ClassA'); // ClassA has failed test, so it comes first
            expect(grouped[0].testcases[0].name).toBe('testB');
            expect(grouped[1].className).toBe('ClassB');
            expect(grouped[1].testcases[0].name).toBe('testC'); // Warning comes before passed
            expect(grouped[1].testcases[1].name).toBe('testA');
        });

        test('should correctly count statuses and issues', () => {
            jest.spyOn(app, 'getResults').mockReturnValueOnce({
                suites: [
                    {
                        name: 'Suite1',
                        testcases: [
                            { id: 't1', name: 'testA', class: 'ClassA', status: 'passed' },
                            { id: 't2', name: 'testB', class: 'ClassA', status: 'failed' },
                            { id: 't3', name: 'testC', class: 'ClassA', status: 'errored' },
                            { id: 't4', name: 'testD', class: 'ClassA', status: 'skipped' },
                            { id: 't5', name: 'testE', class: 'ClassA', status: 'warning', warnings: ['w1'] },
                            { id: 't6', name: 'testF', class: 'ClassA', status: 'deprecation', deprecations: ['d1'] },
                            { id: 't7', name: 'testG', class: 'ClassA', status: 'incomplete' },
                        ]
                    }
                ]
            });

            const grouped = app.getGroupedResults();
            const classA = grouped[0];
            expect(classA.passed).toBe(1);
            expect(classA.failed).toBe(1);
            expect(classA.errored).toBe(1);
            expect(classA.skipped).toBe(1);
            expect(classA.warning).toBe(1); // Corrected expectation based on the provided mock data
            expect(classA.deprecation).toBe(1);
            expect(classA.incomplete).toBe(1);
            expect(classA.hasIssues).toBe(true);
        });
    });

    describe('getStatusCounts', () => {
        test('should return zero counts if no results', () => {
            jest.spyOn(app, 'getResults').mockReturnValueOnce(null);
            expect(app.getStatusCounts()).toEqual({
                passed: 0, failed: 0, error: 0, warnings: 0, skipped: 0, deprecations: 0, incomplete: 0
            });
        });

        test('should correctly calculate status counts', () => {
            jest.spyOn(app, 'getResults').mockReturnValueOnce({
                summary: {
                    numberOfTests: 10,
                    numberOfFailures: 2,
                    numberOfErrors: 1,
                    numberOfWarnings: 3,
                    numberOfSkipped: 1,
                    numberOfDeprecations: 1,
                    numberOfIncomplete: 1,
                }
            });

            const counts = app.getStatusCounts();
            expect(counts).toEqual({
                passed: 1, // 10 total - (2f + 1e + 3w + 1s + 1d + 1i) = 1
                failed: 2,
                error: 1,
                warnings: 3,
                skipped: 1,
                deprecations: 1,
                incomplete: 1,
            });
        });
    });

    describe('getFilteredTestSuites', () => {
        beforeEach(() => {
            store.state.testSuites = [
                { name: 'SuiteA', methods: [{ id: 't1', name: 'testMethodOne' }] },
                { name: 'SuiteB', methods: [{ id: 't2', name: 'anotherTest' }] },
                { name: 'SuiteC', methods: [{ id: 't3', name: 'testMethodTwo' }] },
            ];
        });

        test('should return all suites if search query is empty', () => {
            store.state.searchQuery = '';
            expect(app.getFilteredTestSuites()).toEqual(store.state.testSuites);
        });

        test('should filter by suite name', () => {
            store.state.searchQuery = 'suitea';
            const filtered = app.getFilteredTestSuites();
            expect(filtered.length).toBe(1);
            expect(filtered[0].name).toBe('SuiteA');
        });

        test('should filter by method name', () => {
            store.state.searchQuery = 'anothertest';
            const filtered = app.getFilteredTestSuites();
            expect(filtered.length).toBe(1);
            expect(filtered[0].name).toBe('SuiteB');
            expect(filtered[0].methods.length).toBe(1);
            expect(filtered[0].methods[0].name).toBe('anotherTest');
        });

        test('should return suites where either name or method matches', () => {
            store.state.searchQuery = 'testmethod';
            const filtered = app.getFilteredTestSuites();
            expect(filtered.length).toBe(2);
            expect(filtered[0].name).toBe('SuiteA');
            expect(filtered[1].name).toBe('SuiteC');
        });

        test('should be case-insensitive', () => {
            store.state.searchQuery = 'suiteb';
            const filtered = app.getFilteredTestSuites();
            expect(filtered.length).toBe(1);
            expect(filtered[0].name).toBe('SuiteB');
        });

        test('should return empty array if no matches', () => {
            store.state.searchQuery = 'nomatch';
            expect(app.getFilteredTestSuites()).toEqual([]);
        });
    });
});
