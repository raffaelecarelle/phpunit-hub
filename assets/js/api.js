/**
 * API client for PHPUnit Hub
 */

export class ApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
    }

    /**
     * Utility function to retry a fetch request.
     * @param {Function} fn The fetch function to retry.
     * @param {number} retries The number of retries.
     * @param {number} delay The delay between retries in milliseconds.
     */
    async retry(fn, retries = 3, delay = 1000) {
        for (let i = 0; i < retries; i++) {
            try {
                return await fn();
            } catch (error) {
                // Only retry for network-related errors (TypeError: Failed to fetch or ERR_EMPTY_RESPONSE)
                if (error instanceof TypeError && error.message === 'Failed to fetch') {
                    console.warn(`Attempt ${i + 1} failed, retrying in ${delay}ms...`, error);
                    if (i < retries - 1) {
                        await new Promise(resolve => setTimeout(resolve, delay));
                    } else {
                        throw new Error('Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.');
                    }
                } else {
                    // For other types of errors, re-throw immediately
                    throw error;
                }
            }
        }
    }

    /**
     * Fetch available tests
     */
    async fetchTests() {
        return this.retry(async () => {
            const response = await fetch(`${this.baseUrl}/api/tests`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to fetch tests');
            }
            return await response.json();
        });
    }

    /**
     * Run tests with specified options
     */
    async runTests(payload) {
        return this.retry(async () => {
            const response = await fetch(`${this.baseUrl}/api/run`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to run tests');
            }

            return await response.json();
        });
    }

    /**
     * Stop all tests
     */
    async stopAllTests() {
        return this.retry(async () => {
            const response = await fetch(`${this.baseUrl}/api/stop`, {
                method: 'POST'
            });

            if (!response.ok) {
                throw new Error('Failed to stop tests');
            }
        });
    }

    /**
     * Stop a single test run
     */
    async stopSingleTest(runId) {
        return this.retry(async () => {
            const response = await fetch(`${this.baseUrl}/api/stop-single-test/${runId}`, {
                method: 'POST'
            });

            if (!response.ok) {
                throw new Error(`Failed to stop test run ${runId}`);
            }
        });
    }

    /**
     * Fetch coverage report
     */
    async fetchCoverage(runId) {
        return this.retry(async () => {
            const response = await fetch(`${this.baseUrl}/api/coverage/${runId}`);
            if (!response.ok) {
                let errorDetails = 'Unknown error';
                try {
                    const errorData = await response.json();
                    errorDetails = errorData.error || JSON.stringify(errorData);
                } catch (jsonError) {
                    errorDetails = response.statusText;
                }
                throw new Error(`Failed to fetch coverage report: ${errorDetails}`);
            }
            return await response.json();
        });
    }

    /**
     * Fetch file coverage
     */
    async fetchFileCoverage(runId, filePath) {
        return this.retry(async () => {
            const response = await fetch(`${this.baseUrl}/api/file-coverage?runId=${runId}&path=${encodeURIComponent(filePath)}`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to fetch file coverage');
            }
            return await response.json();
        });
    }

    /**
     * Fetch file content
     */
    async fetchFileContent(filePath) {
        return this.retry(async () => {
            const response = await fetch(`${this.baseUrl}/api/file-content?path=${encodeURIComponent(filePath)}`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to fetch file content');
            }
            return await response.text();
        });
    }
}
