/**
 * Utility functions for PHPUnit Hub Frontend
 */

export const favicons = {
    neutral: `<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="14" fill="#6b7280"/></svg>`,
    success: `<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="14" fill="#10B981"/><polyline points="9 16, 14 21, 23 12" stroke="white" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    failure: `<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="16" fill="#EF4444"/><line x1="11" y1="11" x2="21" y2="21" stroke="white" stroke-width="3" stroke-linecap="round"/><line x1="21" y1="11" x2="11" y2="21" stroke="white" stroke-width="3" stroke-linecap="round"/></svg>`
};

/**
 * Update the favicon based on test status
 */
export function updateFavicon(status = 'neutral') {
    const favicon = document.getElementById('favicon');
    if (favicon) {
        const svg = favicons[status] || favicons.neutral;
        favicon.href = `data:image/svg+xml;base64,${btoa(svg)}`;
    }
}

/**
 * Parse test ID to extract suite name and test name
 */
export function parseTestId(testId) {
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
}

/**
 * Format time in seconds to readable string
 */
export function formatTime(seconds) {
    if (seconds === null || seconds === undefined) return '0.00s';
    return `${seconds.toFixed(4)}s`;
}

/**
 * Calculate passed tests from summary
 */
export function calculatePassedTests(summary) {
    if (!summary) return 0;
    const failed = summary.failures || 0;
    const error = summary.errors || 0;
    const skipped = summary.skipped || 0;
    const incomplete = summary.incomplete || 0;
    const risky = summary.risky || 0;
    const problems = failed + error + skipped + incomplete + risky;
    return (summary.tests || 0) - problems;
}

/**
 * Toggle test details view
 * @param {object} store - The application store.
 * @param {object} testcase - The testcase object.
 */
export function toggleTestDetails(store, testcase) {
    const { state } = store;
    const isExpandable = testcase.status !== 'passed' ||
        (testcase.warnings?.length > 0 && state.options.displayWarnings) ||
        (testcase.deprecations?.length > 0 && state.options.displayDeprecations) ||
        (testcase.notices?.length > 0 && state.options.displayNotices);

    if (!isExpandable) return;

    const id = testcase.id;
    store.setExpandedTest(state.expandedTestId === id ? null : id);
}

export function formatNanoseconds(nanoseconds) {
    if (nanoseconds === undefined || nanoseconds === null) {
        return '0.00ms';
    }
    const seconds = nanoseconds / 1_000_000_000;
    if (seconds >= 1) {
        return `${seconds.toFixed(2)}s`;
    }
    return `${(nanoseconds / 1_000_000).toFixed(2)}ms`;
}
