// Mock the global fetch function
const mockFetch = jest.fn();
global.fetch = mockFetch;

import { ApiClient } from '../api.js';

describe('ApiClient', () => {
    let api;
    const baseUrl = 'http://localhost:8000';

    beforeEach(() => {
        api = new ApiClient(baseUrl);
        mockFetch.mockClear();
        jest.spyOn(console, 'error').mockImplementation(() => {}); // Suppress console.error during tests
    });

    afterEach(() => {
        jest.restoreAllMocks(); // Restore console.error
    });

    describe('fetchTests', () => {
        test('should fetch tests successfully', async () => {
            const mockResponseData = { suites: [], availableSuites: [], availableGroups: [] };
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchTests();

            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/tests`);
            expect(result).toEqual(mockResponseData);
        });

        test('should throw an error if fetch fails', async () => {
            const error = new Error('Network error');
            mockFetch.mockRejectedValueOnce(error);

            await expect(api.fetchTests()).rejects.toThrow('Network error');
            expect(console.error).toHaveBeenCalledWith('Failed to fetch tests:', error);
        });
    });

    describe('runTests', () => {
        const payload = { filters: ['test1'], groups: [], suites: [], options: {} };

        test('should run tests successfully', async () => {
            const mockResponseData = { status: 'running', runId: '123' };
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.runTests(payload);

            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/run`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            expect(result).toEqual(mockResponseData);
        });

        test('should throw an error if API returns non-ok response', async () => {
            const errorData = { error: 'Invalid payload' };
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve(errorData),
            });

            await expect(api.runTests(payload)).rejects.toThrow('Invalid payload');
            expect(console.error).toHaveBeenCalledWith('Failed to run tests:', expect.any(Error));
        });

        test('should throw a generic error if API returns non-ok response without specific error message', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({}),
            });

            await expect(api.runTests(payload)).rejects.toThrow('Failed to run tests');
            expect(console.error).toHaveBeenCalledWith('Failed to run tests:', expect.any(Error));
        });

        test('should throw an error if fetch fails', async () => {
            const error = new Error('Network error');
            mockFetch.mockRejectedValueOnce(error);

            await expect(api.runTests(payload)).rejects.toThrow('Network error');
            expect(console.error).toHaveBeenCalledWith('Failed to run tests:', error);
        });
    });

    describe('stopAllTests', () => {
        test('should stop all tests successfully', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
            });

            await expect(api.stopAllTests()).resolves.toBeUndefined();
            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/stop`, {
                method: 'POST',
            });
        });

        test('should throw an error if API returns non-ok response', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
            });

            await expect(api.stopAllTests()).rejects.toThrow('Failed to stop tests');
            expect(console.error).toHaveBeenCalledWith('Failed to stop tests:', expect.any(Error));
        });

        test('should throw an error if fetch fails', async () => {
            const error = new Error('Network error');
            mockFetch.mockRejectedValueOnce(error);

            await expect(api.stopAllTests()).rejects.toThrow('Network error');
            expect(console.error).toHaveBeenCalledWith('Failed to stop tests:', error);
        });
    });

    describe('stopSingleTest', () => {
        const runId = 'testRun123';

        test('should stop a single test successfully', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
            });

            await expect(api.stopSingleTest(runId)).resolves.toBeUndefined();
            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/stop-single-test/${runId}`, {
                method: 'POST',
            });
        });

        test('should throw an error if API returns non-ok response', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
            });

            await expect(api.stopSingleTest(runId)).rejects.toThrow(`Failed to stop test run ${runId}`);
            expect(console.error).toHaveBeenCalledWith(`Failed to stop test run ${runId}:`, expect.any(Error));
        });

        test('should throw an error if fetch fails', async () => {
            const error = new Error('Network error');
            mockFetch.mockRejectedValueOnce(error);

            await expect(api.stopSingleTest(runId)).rejects.toThrow('Network error');
            expect(console.error).toHaveBeenCalledWith(`Failed to stop test run ${runId}:`, error);
        });
    });
});
