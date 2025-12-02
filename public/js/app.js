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
            this.wsManager = new WebSocketManager(`ws://${wsHost}:${wsPort}/ws/status`, this.store, this);
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
     * Internal method to run tests.
     * Use runTests for public API.
     * @private
     */
    async _runTests(runOptions = {}) {
        this.store.setStarting(true);
        this.store.state.activeTab = 'results';

        // Filter out frontend-only options that PHPUnit doesn't understand
        const { displayMode, ...phpunitOptions } = this.store.state.options;

        const payload = {
            filters: runOptions.filters || [],
            groups: this.store.state.selectedGroups,
            suites: this.store.state.selectedSuites,
            options: { ...phpunitOptions, colors: true },
            coverage: this.store.state.coverage,
            contextId: runOptions.contextId || 'global',
        };

        try {
            await this.api.runTests(payload);
        } catch (error) {
            console.error('Failed to run tests:', error);
            updateFavicon('failure');
        } finally {
            this.store.setStarting(false);
        }
    }

    /**
     * Run tests with options. This is the main entry point for running tests.
     */
    runTests(runOptions = {}) {
        this._runTests(runOptions);
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
                if (this.store.state.isStarting) return true;
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
            individualResults: computed(() => this.getIndividualResults()),
            statusCounts: computed(() => this.getStatusCounts()),
            filteredTestSuites: computed(() => this.getFilteredTestSuites()),
        };
    }

    /**
     * Get results from current test run (always reset mode)
     */
    getResults() {
        const runs = this.store.state.realtimeTestRuns;
        const runIds = Object.keys(runs);

        if (runIds.length === 0) {
            return null;
        }

        // Always show only the last completed run
        let runId = this.store.state.lastCompletedRunId;
        if (!runId) {
            runId = runIds[runIds.length - 1];
        }
        return this.getSingleRunResults(runs[runId]);
    }

    /**
     * Calculate summary statistics from test data in real-time
     */
    calculateRealtimeSummary(run) {
        const summary = {
            tests: 0,
            assertions: 0,
            failures: 0,
            errors: 0,
            warnings: 0,
            skipped: 0,
            deprecations: 0,
            incomplete: 0,
        };

        if (!run || !run.suites) return summary;

        for (const suiteName in run.suites) {
            for (const testId in run.suites[suiteName].tests) {
                const testData = run.suites[suiteName].tests[testId];
                summary.tests++;
                summary.assertions += testData.assertions || 0;
                summary.warnings += testData.warnings?.length || 0;
                summary.deprecations += testData.deprecations?.length || 0;

                switch (testData.status) {
                    case 'failed': summary.failures++; break;
                    case 'errored': summary.errors++; break;
                    case 'skipped': summary.skipped++; break;
                    case 'incomplete': summary.incomplete++; break;
                }
            }
        }
        return summary;
    }

    /**
     * Get results from a single run
     */
    getSingleRunResults(run) {
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
        };
        const summary = { ...defaultSummary, ...(run.summary || {}) };

        // If the run is in progress, calculate totals in real-time
        // We check for `!run.summary` because the final summary from PHPUnit is the source of truth.
        // We only calculate in real-time if that final summary hasn't arrived yet.
        if (run && !run.summary) {
            const realtimeSummary = this.calculateRealtimeSummary(run);
            summary.numberOfTests = realtimeSummary.tests;
            summary.numberOfAssertions = realtimeSummary.assertions;
            summary.numberOfFailures = realtimeSummary.failures;
            summary.numberOfErrors = realtimeSummary.errors;
            summary.numberOfWarnings = realtimeSummary.warnings;
            summary.numberOfSkipped = realtimeSummary.skipped;
            summary.numberOfDeprecations = realtimeSummary.deprecations;
            summary.numberOfIncomplete = realtimeSummary.incomplete;
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
                summary.time += tc.duration || 0;
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

        const statusOrder = { 'errored': 1, 'failed': 2, 'incomplete': 3, 'skipped': 4, 'warning': 5, 'deprecation': 6, 'passed': 7 };

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
                if (this.store.state.sortBy === 'duration') {
                    if (durationA !== durationB) {
                        return this.store.state.sortDirection === 'asc' ? durationA - durationB : durationB - durationA;
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

    /**
     * Get individual results, sorted by time
     */
    getIndividualResults() {
        const results = this.getResults();
        if (!results) return [];

        let allTests = [];
        results.suites.forEach(suite => {
            allTests.push(...suite.testcases);
        });

        // Filter based on options
        allTests = allTests.filter(t => {
            if (t.status === 'skipped' && !this.store.state.options.displaySkipped) return false;
            if (t.status === 'incomplete' && !this.store.state.options.displayIncomplete) return false;
            if (t.warnings?.length > 0 && !this.store.state.options.displayWarnings) {
                // if it's just a warning and we hide them, don't show if it passed
                if (t.status === 'passed') return false;
            }
            if (t.deprecations?.length > 0 && !this.store.state.options.displayDeprecations) {
                if (t.status === 'passed') return false;
            }
            return true;
        });

        // Sort by duration, descending
        allTests.sort((a, b) => {
            const durationA = a.duration || 0;
            const durationB = b.duration || 0;

            if (this.store.state.sortBy === 'duration') {
                return this.store.state.sortDirection === 'asc' ? durationA - durationB : durationB - durationA;
            }

            const statusOrder = { 'errored': 1, 'failed': 2, 'incomplete': 3, 'skipped': 4, 'warning': 5, 'deprecation': 6, 'passed': 7 };
            const statusA = statusOrder[a.status || 'passed'] || 99;
            const statusB = statusOrder[b.status || 'passed'] || 99;
            if (statusA !== statusB) {
                return statusA - statusB;
            }

            return durationB - durationA; // Default to duration descending if statuses are equal
        });

        return allTests;
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

    async fetchCoverageReport(runId) {
        try {
            const report = await this.api.fetchCoverage(runId);
            this.store.setCoverageReport(report);
        } catch (error) {
            console.error('Failed to fetch coverage report:', error);
        } finally {
            this.store.setCoverageLoading(false);
        }
    }

    async showFileCoverage(filePath) {
        try {
            const runId = this.store.state.lastCompletedRunId;
            if (!runId) {
                console.error('No completed run ID found for file coverage.');
                return;
            }
            const coverage = await this.api.fetchFileCoverage(runId, filePath);
            this.store.setFileCoverage({ ...coverage, path: filePath });
        } catch (error) {
            console.error('Failed to fetch file coverage:', error);
        }
    }
}