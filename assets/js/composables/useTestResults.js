import { computed } from 'vue';
import { useStore } from '../store.js';

export function useTestResults(results) {
    const store = useStore();

    const getResults = computed(() => {
        const run = store.state.testRun;

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
    });

    const calculateRealtimeSummary = (run) => {
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
    };

    const getStatusCounts = computed(() => {
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
        const actualFailures = counts.failed + counts.error + counts.skipped + counts.incomplete + counts.risky;
        counts.passed = (s.tests || 0) - actualFailures;
        return counts;
    });

    const getIndividualResults = computed(() => {
        const resultsVal = results.value;
        if (!resultsVal) return [];

        let allTests = [];
        resultsVal.suites.forEach(suite => {
            allTests.push(...suite.testcases);
        });

        allTests = allTests.filter(t => {
            if (t.status === 'skipped' && !store.state.options.displaySkipped) return false;
            if (t.status === 'incomplete' && !store.state.options.displayIncomplete) return false;
            if (t.status === 'risky' && !store.state.options.displayRisky) return false;
            if (t.warnings?.length > 0 && !store.state.options.displayWarnings) {
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

            return durationB - durationA;
        });

        return allTests;
    });

    const getGroupedResults = computed(() => {
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

                if (tc.warnings?.length > 0) {
                    group.warning += tc.warnings.length;
                }
                if (tc.deprecations?.length > 0) {
                    group.deprecation += tc.deprecations.length;
                }
                if (tc.notices?.length > 0) {
                    group.notice += tc.notices.length;
                }

                if (tc.warnings?.length > 0 || tc.deprecations?.length > 0 || tc.notices?.length > 0 || status !== 'passed') {
                    group.hasIssues = true;
                }
            });
        });

        const statusOrder = { 'errored': 1, 'failed': 2, 'incomplete': 3, 'risky': 4, 'skipped': 5, 'warning': 6, 'deprecation': 7, 'notice': 8, 'passed': 9 };

        Object.values(groups).forEach(group => {
            let highestPriorityStatus = statusOrder['passed'];
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
    });

    return {
        getResults,
        getStatusCounts,
        getIndividualResults,
        getGroupedResults,
    };
}
