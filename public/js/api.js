/**
 * API client for PHPUnit Hub
 */

export class ApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
    }

    /**
     * Fetch available tests
     */
    async fetchTests() {
        try {
            const response = await fetch(`${this.baseUrl}/api/tests`);
            return await response.json();
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
            const response = await fetch(`${this.baseUrl}/api/stop`, {
                method: 'POST'
            });

            if (!response.ok) {
                throw new Error('Failed to stop tests');
            }
        } catch (error) {
            console.error('Failed to stop tests:', error);
            throw error;
        }
    }

    /**
     * Stop a single test run
     */
    async stopSingleTest(runId) {
        try {
            const response = await fetch(`${this.baseUrl}/api/stop-single-test/${runId}`, {
                method: 'POST'
            });

            if (!response.ok) {
                throw new Error(`Failed to stop test run ${runId}`);
            }
        } catch (error) {
            console.error(`Failed to stop test run ${runId}:`, error);
            throw error;
        }
    }
}
