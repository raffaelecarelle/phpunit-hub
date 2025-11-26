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
    const parts = testId.split('::');
    return {
        suiteName: parts[0],
        testName: parts[1],
        fullId: testId
    };
}

/**
 * Format time in seconds to readable string
 */
export function formatTime(seconds) {
    if (!seconds) return '0.00s';
    return `${seconds.toFixed(4)}s`;
}

/**
 * Calculate passed tests from summary
 */
export function calculatePassedTests(summary) {
    if (!summary) return 0;
    const failed = summary.failures || 0;
    const error = summary.errors || 0;
    const warnings = summary.warnings || 0;
    const skipped = summary.skipped || 0;
    const incomplete = summary.incomplete || 0;
    const deprecations = summary.deprecations || 0;
    const problems = failed + error + warnings + skipped + incomplete + deprecations;
    return (summary.tests || 0) - problems;
}

