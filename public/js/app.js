/**
 * Main Application Logic for PHPUnit Hub
 */

import {computed} from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.prod.js';
import {Store} from './store.js';
import {ApiClient} from './api.js';
import {WebSocketManager} from './websocket.js';
import {updateFavicon} from './utils.js';

export class App {
    constructor() {
        this.store = new Store();
        this.api = new ApiClient('');
        this.wsManager = null;
        this.testIndex = {};
    }

    /**
     * Initialize the app
     */
    async initialize() {
        try {
            // Fetch tests
            await this.fetchTests();
            
            // Connect WebSocket
            const wsHost = window.WS_HOST || '127.0.0.1';
            const wsPort = window.WS_PORT || '8080';
            this.wsManager = new WebSocketManager(`ws://${wsHost}:${wsPort}/ws/status`, this.store);
            await this.wsManager.connect();
            
            // Setup resizer
            this.setupResizer();
            
            // Update favicon
            updateFavicon('neutral');
        } catch (error) {
            console.error('Failed to initialize app:', error);
        }
    }

    /**
     * Fetch available tests
     */
    async fetchTests() {
        this.store.state.isLoading = true;
        try {
            const data = await this.api.fetchTests();
            this.store.state.testSuites = data.suites;
            this.store.state.availableSuites = data.availableSuites || [];
            this.store.state.availableGroups = data.availableGroups || [];
            
            // Build test index
            this.buildTestIndex();
        } catch (error) {
            console.error('Failed to fetch tests:', error);
            throw error; // Re-throw the error
        } finally {
            this.store.state.isLoading = false;
        }
    }

    /**
     * Build test index for quick lookup
     */
    buildTestIndex() {
        this.testIndex = {};
        this.store.state.testSuites.forEach(suite => {
            if (suite.methods) {
                suite.methods.forEach(method => {
                    this.testIndex[method.id] = {
                        suite,
                        method
                    };
                });
            }
        });
    }

    /**
     * Run tests with options
     */
    async runTests(runOptions = {}) {
        this.store.state.activeTab = 'results';

        // Filter out frontend-only options that PHPUnit doesn't understand
        const { resultUpdateMode, ...phpunitOptions } = this.store.state.options;

        const payload = {
            filters: runOptions.filters || [],
            groups: this.store.state.selectedGroups,
            suites: this.store.state.selectedSuites,
            options: { ...phpunitOptions, colors: true },
            contextId: runOptions.contextId || 'global',
        };

        try {
            await this.api.runTests(payload);
        } catch (error) {
            console.error('Failed to run tests:', error);
            updateFavicon('failure');
        }
    }

    /**
     * Run all tests
     */
    runAllTests() {
        this.runTests({ contextId: 'global' });
    }

    /**
     * Run failed tests
     */
    runFailedTests() {
        const failedTestIds = this.store.getFailedTestIds();
        if (failedTestIds.length === 0) {
            console.log('No failed tests to run.');
            return;
        }
        this.runTests({ filters: failedTestIds, contextId: 'failed' });
    }

    /**
     * Run single test
     */
    runSingleTest(testId) {
        this.runTests({ filters: [testId], contextId: testId });
    }

    /**
     * Run suite tests
     */
    runSuiteTests(suiteId) {
        this.runTests({ filters: [suiteId], contextId: suiteId });
    }

    /**
     * Stop all tests
     */
    async stopAllTests() {
        try {
            Object.keys(this.store.state.runningTestIds).forEach(runId => {
                this.store.markStopPending(runId);
            });
            await this.api.stopAllTests();
        } catch (error) {
            console.error('Failed to stop tests:', error);
            Object.keys(this.store.state.runningTestIds).forEach(runId => {
                this.store.clearStopPending(runId);
            });
        }
    }

    /**
     * Stop single test
     */
    async stopSingleTest(runId) {
        try {
            this.store.markStopPending(runId);
            await this.api.stopSingleTest(runId);
        } catch (error) {
            console.error(`Failed to stop test run ${runId}:`, error);
            this.store.clearStopPending(runId);
        }
    }

    /**
     * Toggle play/stop state
     */
    togglePlayStop() {
        if (Object.keys(this.store.state.runningTestIds).length > 0) {
            this.stopAllTests();
        } else {
            this.runAllTests();
        }
    }

    /**
     * Setup sidebar resizer
     */
    setupResizer() {
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

    /**
     * Get computed values for Vue
     */
    getComputedValues() {
        return {
            isAnyTestRunning: computed(() => {
                // Check if there are any tests running
                const runningCount = Object.keys(this.store.state.runningTestIds).length;
                if (runningCount === 0) return false;

                // Check if any running test hasn't received execution.ended yet
                for (const runId in this.store.state.runningTestIds) {
                    const run = this.store.state.realtimeTestRuns[runId];
                    if (!run || !run.executionEnded) {
                        return true;
                    }
                }

                return false;
            }),
            isAnyStopPending: computed(() => Object.keys(this.store.state.stopPending).length > 0),
            hasFailedTests: computed(() => this.store.hasFailedTests()),
            results: computed(() => this.getResults()),
            groupedResults: computed(() => this.getGroupedResults()),
            statusCounts: computed(() => this.getStatusCounts()),
            filteredTestSuites: computed(() => this.getFilteredTestSuites()),
        };
    }

    /**
     * Get results from current test run (or merged results in update mode)
     */
    getResults() {
        const runs = this.store.state.realtimeTestRuns;
        const runIds = Object.keys(runs);

        if (runIds.length === 0) {
            return null;
        }

        // In update mode, merge all completed/finished runs
        const isUpdateMode = this.store.state.options.resultUpdateMode === 'update';

        if (isUpdateMode) {
            return this.getMergedResults(runs);
        } else {
            // In reset mode, show only the last completed run
            let runId = this.store.state.lastCompletedRunId;
            if (!runId) {
                runId = runIds[runIds.length - 1];
            }
            return this.getSingleRunResults(runs[runId]);
        }
    }

    /**
     * Get results from a single run
     */
    getSingleRunResults(run) {
        if (!run) return null;

        const summary = run.summary || {
            numberOfTests: 0,
            numberOfAssertions: 0,
            duration: 0,
            numberOfFailures: 0,
            numberOfErrors: 0,
            numberOfWarnings: 0,
            numberOfSkipped: 0,
            numberOfDeprecations: 0,
            numberOfIncomplete: 0,
        };

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
                    time: testData.time || 0,
                    status: testData.status,
                    message: testData.message,
                    trace: testData.trace,
                    warnings: testData.warnings || [],
                    deprecations: testData.deprecations || [],
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
                time: summary.duration,
                failures: summary.numberOfFailures,
                errors: summary.numberOfErrors,
                warnings: summary.numberOfWarnings,
                skipped: summary.numberOfSkipped,
                deprecations: summary.numberOfDeprecations,
                incomplete: summary.numberOfIncomplete,
            },
            suites: transformedSuites,
        };
    }

    /**
     * Get merged results from all test runs
     */
    getMergedResults(runs) {
        const mergedSuites = {};
        const mergedSummary = {
            numberOfTests: 0,
            numberOfAssertions: 0,
            duration: 0,
            numberOfFailures: 0,
            numberOfErrors: 0,
            numberOfWarnings: 0,
            numberOfSkipped: 0,
            numberOfDeprecations: 0,
            numberOfIncomplete: 0,
        };

        // Track which tests have been seen in summaries to avoid double-counting assertions
        const testsSeen = new Set();

        // Merge all runs
        for (const runId in runs) {
            const run = runs[runId];

            // Only merge runs that have completed (or are running with data)
            // Skip runs that are just initialized but have no test data yet
            if (Object.keys(run.suites).length === 0) {
                continue;
            }

            // Merge suites and tests
            for (const suiteName in run.suites) {
                if (!mergedSuites[suiteName]) {
                    mergedSuites[suiteName] = {
                        name: suiteName,
                        tests: {},
                        runIds: []
                    };
                }

                const suiteData = run.suites[suiteName];
                for (const testId in suiteData.tests) {
                    // Track if this is a new test or an override
                    const isNewTest = !mergedSuites[suiteName].tests[testId];

                    // Later runs override earlier runs for the same test
                    mergedSuites[suiteName].tests[testId] = {
                        ...suiteData.tests[testId],
                        runId: runId
                    };

                    // Mark this test as seen in this run
                    if (!testsSeen.has(testId)) {
                        testsSeen.add(testId);
                    }
                }

                // Track which runs contributed to this suite
                if (!mergedSuites[suiteName].runIds.includes(runId)) {
                    mergedSuites[suiteName].runIds.push(runId);
                }
            }

            // Accumulate summaries from all completed runs
            // For assertions and duration, we need to accumulate from each unique run
            // For other stats, we'll recalculate from merged tests
            if (run.summary && run.status === 'finished') {
                mergedSummary.numberOfAssertions += run.summary.numberOfAssertions || 0;
                mergedSummary.duration += run.summary.duration || 0;
            }
        }

        // Transform merged suites
        const transformedSuites = [];
        for (const suiteName in mergedSuites) {
            const suiteData = mergedSuites[suiteName];
            const testcases = [];
            for (const testId in suiteData.tests) {
                const testData = suiteData.tests[testId];
                testcases.push({
                    name: testData.name,
                    class: testData.class,
                    id: testData.id,
                    time: testData.time || 0,
                    status: testData.status,
                    message: testData.message,
                    trace: testData.trace,
                    warnings: testData.warnings || [],
                    deprecations: testData.deprecations || [],
                });
            }
            if (testcases.length > 0) {
                transformedSuites.push({
                    name: suiteData.name,
                    testcases,
                });
            }
        }

        // If no tests found, return null
        if (transformedSuites.length === 0) {
            return null;
        }

        // Calculate counts from merged tests (but keep accumulated assertions and duration)
        const calculatedSummary = this.calculateSummaryFromTests(transformedSuites);

        return {
            summary: {
                tests: calculatedSummary.tests,
                assertions: mergedSummary.numberOfAssertions,
                time: mergedSummary.duration,
                failures: calculatedSummary.failures,
                errors: calculatedSummary.errors,
                warnings: calculatedSummary.warnings,
                skipped: calculatedSummary.skipped,
                deprecations: calculatedSummary.deprecations,
                incomplete: calculatedSummary.incomplete,
            },
            suites: transformedSuites,
        };
    }

    /**
     * Calculate summary statistics from test data
     */
    calculateSummaryFromTests(suites) {
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
        };

        suites.forEach(suite => {
            suite.testcases.forEach(tc => {
                summary.tests++;
                summary.time += tc.time || 0;
                summary.assertions += tc.assertions || 0;

                if (tc.status === 'failed') summary.failures++;
                else if (tc.status === 'errored') summary.errors++;
                else if (tc.status === 'skipped') summary.skipped++;
                else if (tc.status === 'incomplete') summary.incomplete++;

                summary.warnings += tc.warnings?.length || 0;
                summary.deprecations += tc.deprecations?.length || 0;
            });
        });

        return summary;
    }

    /**
     * Get grouped results
     */
    getGroupedResults() {
        const results = this.getResults();
        if (!results) return [];

        const groups = {};
        results.suites.forEach(suite => {
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

                // Set hasIssues if any issues are present (warnings array, deprecations array, or non-passed status)
                if (tc.warnings?.length > 0 || tc.deprecations?.length > 0 || status !== 'passed') {
                    group.hasIssues = true;
                }
            });
        });

        const sortedGroups = Object.values(groups).sort((a, b) => {
            if (a.hasIssues && !b.hasIssues) return -1;
            if (!a.hasIssues && b.hasIssues) return 1;
            return a.className.localeCompare(b.className);
        });

        sortedGroups.forEach(group => {
            group.testcases.sort((a, b) => {
                const statusOrder = { 'failed': 1, 'errored': 2, 'warning': 3, 'deprecation': 4, 'skipped': 5, 'incomplete': 6, 'passed': 7 };
                const statusA = statusOrder[a.status || 'passed'] || 99;
                const statusB = statusOrder[b.status || 'passed'] || 99;
                if (statusA !== statusB) {
                    return statusA - statusB;
                }
                return a.name.localeCompare(b.name);
            });
        });

        return sortedGroups;
    }

    /**
     * Get status counts
     */
    getStatusCounts() {
        const results = this.getResults();

        if (!results) {
            return { passed: 0, failed: 0, error: 0, warnings: 0, skipped: 0, deprecations: 0, incomplete: 0 };
        }

        const s = results.summary;
        const counts = {
            passed: 0,
            failed: s.failures || 0,
            error: s.errors || 0,
            warnings: s.warnings || 0,
            skipped: s.skipped || 0,
            deprecations: s.deprecations || 0,
            incomplete: s.incomplete || 0
        };

        // Only subtract actual failures (failed, error, skipped, incomplete) from total
        // Warnings and deprecations don't prevent a test from being "passed"
        const actualFailures = counts.failed + counts.error + counts.skipped + counts.incomplete;
        counts.passed = (s.tests || 0) - actualFailures;

        return counts;
    }

    /**
     * Get filtered test suites based on search query
     */
    getFilteredTestSuites() {
        const query = this.store.state.searchQuery;
        if (!query) return this.store.state.testSuites;

        const lower = query.toLowerCase();
        return this.store.state.testSuites.map(suite => {
            const methods = suite.methods.filter(m => m.name.toLowerCase().includes(lower));
            if (suite.name.toLowerCase().includes(lower)) {
                return { ...suite, methods: suite.methods };
            }
            if (methods.length > 0) {
                return { ...suite, methods };
            }
            return null;
        }).filter(Boolean);
    }
}
