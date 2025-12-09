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
        for (let i = 0; i <= retries; i++) {
            try {
                return await fn();
            } catch (error) {
                const isNetworkError = error instanceof TypeError && error.message === 'Failed to fetch';

                if (isNetworkError && i < retries) {
                    console.warn(`Attempt ${i + 1} failed due to network error. Retrying in ${delay}ms...`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                } else if (isNetworkError) {
                    const errorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';
                    throw new Error(errorMessage);
                } else {
                    throw error; // Re-throw non-network errors immediately
                }
            }
        }
    }

    /**
     * Fetch available tests
     */
    async fetchTests() {
        try {
            return await this.retry(async () => {
                const response = await fetch(`${this.baseUrl}/api/tests`);
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to fetch tests');
                }
                return await response.json();
            });
        } catch (error) {
            console.error('Failed to fetch tests:', error);
            throw error;
        }
    }

    /**
     * Run tests with specified options
     */
    async runTests(payload) {
        try {
            return await this.retry(async () => {
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
        } catch (error) {
            console.error('Failed to run tests:', error);
            throw error;
        }
    }

    /**
     * Stop all tests
     */
    async stopAllTests() {
        try {
            await this.retry(async () => {
                const response = await fetch(`${this.baseUrl}/api/stop`, {
                    method: 'POST'
                });

                if (!response.ok) {
                    throw new Error('Failed to stop tests');
                }
            });
        } catch (error) {
            console.error('Failed to stop tests:', error);
            throw error;
        }
    }

    /**
     * Stop a single test run
     */
    async stopSingleTest() {
        try {
            await this.retry(async () => {
                const response = await fetch(`${this.baseUrl}/api/stop-single-test`, {
                    method: 'POST'
                });

                if (!response.ok) {
                    throw new Error(`Failed to stop test run`);
                }
            });
        } catch (error) {
            console.error(`Failed to stop test run:`, error);
            throw error;
        }
    }

    /**
     * Fetch coverage report
     */
    async fetchCoverage() {
        try {
            return await this.retry(async () => {
                const response = await fetch(`${this.baseUrl}/api/coverage`);
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
        } catch (error) {
            console.error('Failed to fetch coverage report:', error);
            throw error;
        }
    }

    /**
     * Fetch file coverage
     */
    async fetchFileCoverage(filePath) {
        try {
            return await this.retry(async () => {
                const response = await fetch(`${this.baseUrl}/api/file-coverage?path=${encodeURIComponent(filePath)}`);
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to fetch file coverage');
                }
                return await response.json();
            });
        } catch (error) {
            console.error('Failed to fetch file coverage:', error);
            throw error;
        }
    }

    /**
     * Fetch file content
     */
    async fetchFileContent(filePath) {
        try {
            return await this.retry(async () => {
                const response = await fetch(`${this.baseUrl}/api/file-content?path=${encodeURIComponent(filePath)}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    try {
                        const errorData = JSON.parse(errorText);
                        throw new Error(errorData.error || 'Failed to fetch file content');
                    } catch (e) {
                        throw new Error(errorText || 'Failed to fetch file content');
                    }
                }
                return await response.text();
            });
        } catch (error) {
            console.error('Failed to fetch file content:', error);
            throw error;
        }
    }
}
