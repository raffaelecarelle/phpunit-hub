/**
 * Store/State management for PHPUnit Hub
 */

import { reactive } from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.prod.js';
import {parseTestId, updateFavicon} from './utils.js';

export class Store {
    constructor() {
        this.state = reactive({
            // Test suites
            testSuites: [],
            availableSuites: [],
            availableGroups: [],
            
            // UI state
            isLoading: false,
            searchQuery: '',
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
            options: {
                displayWarnings: true,
                displayDeprecations: true,
                displaySkipped: true,
                displayIncomplete: true,
                stopOnDefect: false,
                stopOnError: false,
                stopOnFailure: false,
                stopOnWarning: false,
                resultUpdateMode: 'update', // 'update' (append/merge) or 'reset' (clear all)
                displayMode: 'default', // 'default' or 'individual'
            },
            
            // Test runs
            runningTestIds: {},
            stopPending: {},
            realtimeTestRuns: {},
            lastCompletedRunId: null,
        });
    }

    setSortBy(sortBy) {
        if (this.state.sortBy === sortBy) {
            if (this.state.sortDirection === 'desc') {
                this.state.sortDirection = 'asc';
            } else {
                this.state.sortBy = 'default';
                this.state.sortDirection = 'desc';
            }
        } else {
            this.state.sortBy = sortBy;
            this.state.sortDirection = 'desc';
        }
    }

    setDisplayMode(mode) {
        this.state.options.displayMode = mode;
    }

    /**
     * Initialize a new test run
     */
    initializeTestRun(runId, contextId) {
        // Determine if we should reset results
        // Always reset for 'global' runs in reset mode, or for 'failed' runs (to show only re-run tests)
        const shouldReset = (this.state.options.resultUpdateMode === 'reset' && contextId === 'global') ||
                          contextId === 'failed';

        this.state.realtimeTestRuns[runId] = {
            status: 'running',
            contextId,
            suites: {},
            summary: null,
            failedTestIds: new Set(),
            executionEnded: false,
            sumOfDurations: 0,
        };
        this.state.runningTestIds[runId] = true;
        delete this.state.stopPending[runId];

        // Clear previous results in reset mode or for failed test runs
        if (shouldReset) {
            this.state.realtimeTestRuns = { [runId]: this.state.realtimeTestRuns[runId] };
            this.state.lastCompletedRunId = null;
            this.state.expandedTestId = null;
            this.state.expandedTestcaseGroups = new Set();
            this.resetSidebarTestStatuses();
        }

        // Assign runId to suite if this is a suite-level run
        this.state.testSuites.forEach(suite => {
            if (suite.id === contextId) {
                suite.runId = runId;
            }
        });

        this.state.activeTab = 'results';
    }

    /**
     * Handle incoming test event
     */
    handleTestEvent(runId, eventData) {
        const run = this.state.realtimeTestRuns[runId];
        if (!run) {
            console.warn(`Received event for unknown runId: ${runId}`);
            return;
        }

        switch (eventData.event) {
            case 'suite.started':
                this.handleSuiteStarted(run, eventData);
                break;
            case 'test.prepared':
                this.handleTestPrepared(run, eventData, runId);
                break;
            case 'test.warning':
            case 'test.deprecation':
                this.handleTestWarningOrDeprecation(run, eventData);
                break;
            case 'test.passed':
            case 'test.failed':
            case 'test.errored':
            case 'test.skipped':
            case 'test.incomplete':
                this.handleTestCompleted(run, eventData, runId);
                break;
            case 'test.finished':
                this.handleTestFinished(run, eventData, runId);
                break;
            case 'execution.ended':
                this.handleExecutionEnded(run, eventData, runId);
                break;
        }
    }

    /**
     * Handle suite.started event
     */
    handleSuiteStarted(run, eventData) {
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
            risky: 0,
            hasIssues: false,
        };
    }

    /**
     * Handle test.prepared event
     */
    handleTestPrepared(run, eventData, runId) {
        const { suiteName, testName } = parseTestId(eventData.data.test);
        
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
                risky: 0,
                hasIssues: false,
            };
        }

        run.suites[suiteName].tests[eventData.data.test] = {
            id: eventData.data.test,
            name: testName,
            class: suiteName,
            status: 'running',
            duration: null,
            assertions: 0,
            message: null,
            trace: null,
            warnings: [],
            deprecations: [],
        };

        // Update sidebar
        this.updateSidebarTestStatus(suiteName, eventData.data.test, 'running', null, runId);
    }

    /**
     * Handle test warning/deprecation event
     */
    handleTestWarningOrDeprecation(run, eventData) {
        const { suiteName } = parseTestId(eventData.data.test);
        const testId = eventData.data.test;

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

    /**
     * Handle test completion event
     */
    handleTestCompleted(run, eventData, runId) {
        const { suiteName, testName } = parseTestId(eventData.data.test);
        const testId = eventData.data.test;

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

                // In update mode, also remove from all other runs (test now passes)
                if (this.state.options.resultUpdateMode === 'update') {
                    for (const otherRunId in this.state.realtimeTestRuns) {
                        if (otherRunId !== runId) {
                            this.state.realtimeTestRuns[otherRunId].failedTestIds?.delete(testId);
                        }
                    }
                }
            } else if (status !== 'passed') {
                suite.hasIssues = true;
            }

            // Update sidebar
            this.updateSidebarTestStatus(suiteName, testId, status, test.duration, runId);
        }
    }

    handleTestFinished(run, eventData, runId) {
        const { suiteName } = parseTestId(eventData.data.test);
        const testId = eventData.data.test;

        if (run.suites[suiteName] && run.suites[suiteName].tests[testId]) {
            const test = run.suites[suiteName].tests[testId];
            test.duration = eventData.data.duration;
            test.assertions = eventData.data.assertions;
            run.sumOfDurations += test.duration;

            // Also update the sidebar with the final duration.
            // The status might have already been set by handleTestCompleted.
            if (test.status) {
                this.updateSidebarTestStatus(suiteName, testId, test.status, test.duration, runId);
            }
        }
    }

    /**
     * Handle execution.ended event
     */
    handleExecutionEnded(run, eventData, runId) {
        run.summary = eventData.data.summary;
        run.executionEnded = true;
        run.status = 'finished';
        this.state.lastCompletedRunId = runId;
        delete this.state.runningTestIds[runId];
        delete this.state.stopPending[runId];
        this.updateSidebarAfterRun(runId);
        updateFavicon(run.summary.status === 'passed' ? 'success' : 'failure');
    }

    /**
     * Update sidebar test status
     */
    updateSidebarTestStatus(suiteName, testId, status, time = null, runId = null) {
        this.state.testSuites.forEach(suite => {
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

    /**
     * Reset all sidebar test statuses
     */
    resetSidebarTestStatuses() {
        this.state.testSuites.forEach(suite => {
            suite.methods?.forEach(method => {
                method.status = null;
                method.duration = null;
                method.runId = null;
            });
        });
    }

    /**
     * Stop a test run
     */
    stopTestRun(runId) {
        const run = this.state.realtimeTestRuns[runId];
        if (run) {
            run.status = 'stopped';
        }
        delete this.state.runningTestIds[runId];
        delete this.state.stopPending[runId];
        this.updateSidebarAfterRun(runId);
    }

    /**
     * Update sidebar after run completion
     */
    updateSidebarAfterRun(runId) {
        this.state.testSuites.forEach(suite => {
            if (suite.runId === runId) suite.runId = null;
            suite.methods?.forEach(method => {
                if (method.runId === runId) method.runId = null;
            });
        });
    }

    /**
     * Get a specific test run
     */
    getTestRun(runId) {
        return this.state.realtimeTestRuns[runId];
    }

    /**
     * Get count of running tests
     */
    getRunningTestCount() {
        return Object.keys(this.state.runningTestIds).length;
    }

    /**
     * Clear all running tests
     */
    clearRunningTests() {
        this.state.runningTestIds = {};
        this.state.stopPending = {};
    }

    /**
     * Mark a test run as pending stop
     */
    markStopPending(runId) {
        this.state.stopPending[runId] = true;
    }

    /**
     * Clear stop pending status
     */
    clearStopPending(runId) {
        delete this.state.stopPending[runId];
    }

    /**
     * Toggle suite expansion
     */
    toggleSuiteExpansion(suiteId) {
        if (this.state.expandedSuites.has(suiteId)) {
            this.state.expandedSuites.delete(suiteId);
        } else {
            this.state.expandedSuites.add(suiteId);
        }
    }

    /**
     * Toggle testcase group expansion
     */
    toggleTestcaseGroupExpansion(className) {
        if (this.state.expandedTestcaseGroups.has(className)) {
            this.state.expandedTestcaseGroups.delete(className);
        } else {
            this.state.expandedTestcaseGroups.add(className);
        }
    }

    /**
     * Set expanded test details
     */
    setExpandedTest(testId) {
        this.state.expandedTestId = testId;
    }

    /**
     * Toggle filter panel visibility
     */
    toggleFilterPanel() {
        this.state.showFilterPanel = !this.state.showFilterPanel;
    }

    /**
     * Get failed test IDs from all runs (in update mode) or last run (in reset mode)
     */
    getFailedTestIds() {
        if (this.state.options.resultUpdateMode === 'update') {
            // In update mode, collect failed tests from all runs
            const allFailedTests = new Set();
            for (const runId in this.state.realtimeTestRuns) {
                const run = this.state.realtimeTestRuns[runId];
                if (run.failedTestIds) {
                    run.failedTestIds.forEach(testId => allFailedTests.add(testId));
                }
            }
            return Array.from(allFailedTests);
        } else {
            // In reset mode, only show failed tests from last completed run
            const run = this.state.realtimeTestRuns[this.state.lastCompletedRunId];
            return run ? Array.from(run.failedTestIds) : [];
        }
    }

    /**
     * Check if there are failed tests
     */
    hasFailedTests() {
        if (this.state.options.resultUpdateMode === 'update') {
            // In update mode, check all runs for failed tests
            for (const runId in this.state.realtimeTestRuns) {
                const run = this.state.realtimeTestRuns[runId];
                if (run.failedTestIds && run.failedTestIds.size > 0) {
                    return true;
                }
            }
            return false;
        } else {
            // In reset mode, only check last completed run
            const run = this.state.realtimeTestRuns[this.state.lastCompletedRunId];
            return run ? run.failedTestIds.size > 0 : false;
        }
    }

    /**
     * Clear all test results manually
     */
    clearAllResults() {
        this.state.realtimeTestRuns = {};
        this.state.lastCompletedRunId = null;
        this.state.expandedTestId = null;
        this.state.expandedTestcaseGroups = new Set();
        this.resetSidebarTestStatuses();
        updateFavicon('neutral');
    }
}
