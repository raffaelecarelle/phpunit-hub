// Mock the global fetch function
import { vi, expect, describe, test, beforeEach, afterEach} from 'vitest';

const mockFetch = vi.fn();
global.fetch = mockFetch;

import { ApiClient } from '../api.js';

describe('ApiClient', () => {
    let api;
    const baseUrl = 'http://localhost:8000';
    let consoleErrorSpy;
    let consoleWarnSpy;

    beforeEach(() => {
        api = new ApiClient(baseUrl);
        mockFetch.mockClear();
        consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {}); // Suppress console.error during tests
        consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {}); // Suppress console.warn during tests
    });

    afterEach(() => {
        vi.restoreAllMocks(); // Restore console.error and console.warn
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
            expect(consoleErrorSpy).not.toHaveBeenCalled();
            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        test('should throw a specific network error after multiple retries if fetch fails persistently', async () => {
            const networkError = new TypeError('Failed to fetch');
            // Mock 4 rejections (initial attempt + 3 retries)
            mockFetch.mockRejectedValue(networkError);

            const expectedErrorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';

            await expect(api.fetchTests()).rejects.toThrow(expectedErrorMessage);
            expect(mockFetch).toHaveBeenCalledTimes(4); // 1 initial + 3 retries
            expect(consoleWarnSpy).toHaveBeenCalledTimes(3); // 3 warnings for 3 retries
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1); // 1 error for the final failure
            expect(consoleErrorSpy.mock.calls[0][1].message).toBe(expectedErrorMessage);
        });

        test('should succeed after retrying if fetch fails initially', async () => {
            const networkError = new TypeError('Failed to fetch');
            const mockResponseData = { suites: [], availableSuites: [], availableGroups: [] };

            // Mock 2 rejections, then a success
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchTests();

            expect(mockFetch).toHaveBeenCalledTimes(3); // 1 initial + 2 retries
            expect(result).toEqual(mockResponseData);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        test('should throw an error if API returns non-ok response (no retry for non-network errors)', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({ error: 'Server error' }),
            });

            await expect(api.fetchTests()).rejects.toThrow('Server error');
            expect(mockFetch).toHaveBeenCalledTimes(1); // No retry for non-network errors
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch tests:', expect.any(Error));
        });
    });

    describe('runTests', () => {
        const payload = { filters: ['test1'], groups: [], suites: [], options: {} };

        test('should run tests successfully', async () => {
            const mockResponseData = { status: 'running' };
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
            expect(consoleErrorSpy).not.toHaveBeenCalled();
            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        test('should throw a specific network error after multiple retries if fetch fails persistently', async () => {
            const networkError = new TypeError('Failed to fetch');
            mockFetch.mockRejectedValue(networkError);

            const expectedErrorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';

            await expect(api.runTests(payload)).rejects.toThrow(expectedErrorMessage);
            expect(mockFetch).toHaveBeenCalledTimes(4);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(3);
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to run tests:', expect.any(Error));
            expect(consoleErrorSpy.mock.calls[0][1].message).toBe(expectedErrorMessage);
        });

        test('should succeed after retrying if fetch fails initially', async () => {
            const networkError = new TypeError('Failed to fetch');
            const mockResponseData = { status: 'running' };

            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.runTests(payload);

            expect(mockFetch).toHaveBeenCalledTimes(3);
            expect(result).toEqual(mockResponseData);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        test('should throw an error if API returns non-ok response (no retry for non-network errors)', async () => {
            const errorData = { error: 'Invalid payload' };
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve(errorData),
            });

            await expect(api.runTests(payload)).rejects.toThrow('Invalid payload');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to run tests:', expect.any(Error));
        });

        test('should throw a generic error if API returns non-ok response without specific error message (no retry for non-network errors)', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({}),
            });

            await expect(api.runTests(payload)).rejects.toThrow('Failed to run tests');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to run tests:', expect.any(Error));
        });
    });

    describe('stopAllTests', () => {
        test('should stop all tests successfully', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({}),
            });

            await expect(api.stopAllTests()).resolves.toBeUndefined();
            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/stop`, {
                method: 'POST',
            });
            expect(consoleErrorSpy).not.toHaveBeenCalled();
            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        test('should throw a specific network error after multiple retries if fetch fails persistently', async () => {
            const networkError = new TypeError('Failed to fetch');
            mockFetch.mockRejectedValue(networkError);

            const expectedErrorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';

            await expect(api.stopAllTests()).rejects.toThrow(expectedErrorMessage);
            expect(mockFetch).toHaveBeenCalledTimes(4);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(3);
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to stop tests:', expect.any(Error));
            expect(consoleErrorSpy.mock.calls[0][1].message).toBe(expectedErrorMessage);
        });

        test('should succeed after retrying if fetch fails initially', async () => {
            const networkError = new TypeError('Failed to fetch');

            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({}),
            });

            await expect(api.stopAllTests()).resolves.toBeUndefined();
            expect(mockFetch).toHaveBeenCalledTimes(3);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        test('should throw an error if API returns non-ok response (no retry for non-network errors)', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({}),
            });

            await expect(api.stopAllTests()).rejects.toThrow('Failed to stop tests');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to stop tests:', expect.any(Error));
        });
    });

    describe('stopSingleTest', () => {
        test('should stop a single test successfully', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({}),
            });

            await expect(api.stopSingleTest()).resolves.toBeUndefined();
            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/stop-single-test`, {
                method: 'POST',
            });
            expect(consoleErrorSpy).not.toHaveBeenCalled();
            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        test('should throw a specific network error after multiple retries if fetch fails persistently', async () => {
            const networkError = new TypeError('Failed to fetch');
            mockFetch.mockRejectedValue(networkError);

            const expectedErrorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';

            await expect(api.stopSingleTest()).rejects.toThrow(expectedErrorMessage);
            expect(mockFetch).toHaveBeenCalledTimes(4);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(3);
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith(`Failed to stop test run:`, expect.any(Error));
            expect(consoleErrorSpy.mock.calls[0][1].message).toBe(expectedErrorMessage);
        });

        test('should succeed after retrying if fetch fails initially', async () => {
            const networkError = new TypeError('Failed to fetch');

            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({}),
            });

            await expect(api.stopSingleTest()).resolves.toBeUndefined();
            expect(mockFetch).toHaveBeenCalledTimes(3);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        test('should throw an error if API returns non-ok response (no retry for non-network errors)', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({}),
            });

            await expect(api.stopSingleTest()).rejects.toThrow(`Failed to stop test run`);
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith(`Failed to stop test run:`, expect.any(Error));
        });
    });

    describe('fetchCoverage', () => {
        test('should fetch coverage successfully', async () => {
            const mockResponseData = { coverage: 'data' };
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchCoverage();

            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/coverage`);
            expect(result).toEqual(mockResponseData);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        test('should throw a specific network error after multiple retries if fetch fails persistently', async () => {
            const networkError = new TypeError('Failed to fetch');
            mockFetch.mockRejectedValue(networkError);

            const expectedErrorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';

            await expect(api.fetchCoverage()).rejects.toThrow(expectedErrorMessage);
            expect(mockFetch).toHaveBeenCalledTimes(4);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(3);
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch coverage report:', expect.any(Error));
            expect(consoleErrorSpy.mock.calls[0][1].message).toBe(expectedErrorMessage);
        });

        test('should succeed after retrying if fetch fails initially', async () => {
            const networkError = new TypeError('Failed to fetch');
            const mockResponseData = { coverage: 'data' };

            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchCoverage();

            expect(mockFetch).toHaveBeenCalledTimes(3);
            expect(result).toEqual(mockResponseData);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        test('should throw an error if API returns non-ok response', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({ error: 'Coverage not found' }),
            });

            await expect(api.fetchCoverage()).rejects.toThrow('Failed to fetch coverage report: Coverage not found');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch coverage report:', expect.any(Error));
        });

        test('should throw an error if API returns non-ok response without specific error message', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({}),
            });

            await expect(api.fetchCoverage()).rejects.toThrow('Failed to fetch coverage report: {}');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch coverage report:', expect.any(Error));
        });

        test('should throw an error if API returns non-ok response with statusText but no json', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.reject(new Error('Invalid JSON')),
                statusText: 'Not Found',
            });

            await expect(api.fetchCoverage()).rejects.toThrow('Failed to fetch coverage report: Not Found');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch coverage report:', expect.any(Error));
        });
    });

    describe('fetchFileCoverage', () => {
        const filePath = '/path/to/file.php';

        test('should fetch file coverage successfully', async () => {
            const mockResponseData = { fileCoverage: 'data' };
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchFileCoverage(filePath);

            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/file-coverage?path=${encodeURIComponent(filePath)}`);
            expect(result).toEqual(mockResponseData);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        test('should throw a specific network error after multiple retries if fetch fails persistently', async () => {
            const networkError = new TypeError('Failed to fetch');
            mockFetch.mockRejectedValue(networkError);

            const expectedErrorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';

            await expect(api.fetchFileCoverage(filePath)).rejects.toThrow(expectedErrorMessage);
            expect(mockFetch).toHaveBeenCalledTimes(4);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(3);
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch file coverage:', expect.any(Error));
            expect(consoleErrorSpy.mock.calls[0][1].message).toBe(expectedErrorMessage);
        });

        test('should succeed after retrying if fetch fails initially', async () => {
            const networkError = new TypeError('Failed to fetch');
            const mockResponseData = { fileCoverage: 'data' };

            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchFileCoverage(filePath);

            expect(mockFetch).toHaveBeenCalledTimes(3);
            expect(result).toEqual(mockResponseData);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        test('should throw an error if API returns non-ok response', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({ error: 'File coverage not found' }),
            });

            await expect(api.fetchFileCoverage(filePath)).rejects.toThrow('File coverage not found');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch file coverage:', expect.any(Error));
        });

        test('should throw a generic error if API returns non-ok response without specific error message', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({}),
            });

            await expect(api.fetchFileCoverage(filePath)).rejects.toThrow('Failed to fetch file coverage');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch file coverage:', expect.any(Error));
        });
    });

    describe('fetchFileContent', () => {
        const filePath = '/path/to/file.php';

        test('should fetch file content successfully', async () => {
            const mockResponseData = 'file content';
            mockFetch.mockResolvedValueOnce({
                ok: true,
                text: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchFileContent(filePath);

            expect(mockFetch).toHaveBeenCalledWith(`${baseUrl}/api/file-content?path=${encodeURIComponent(filePath)}`);
            expect(result).toEqual(mockResponseData);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        test('should throw a specific network error after multiple retries if fetch fails persistently', async () => {
            const networkError = new TypeError('Failed to fetch');
            mockFetch.mockRejectedValue(networkError);

            const expectedErrorMessage = 'Network error: Could not connect to the server after multiple attempts. Please check your connection or try again.';

            await expect(api.fetchFileContent(filePath)).rejects.toThrow(expectedErrorMessage);
            expect(mockFetch).toHaveBeenCalledTimes(4);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(3);
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch file content:', expect.any(Error));
            expect(consoleErrorSpy.mock.calls[0][1].message).toBe(expectedErrorMessage);
        });

        test('should succeed after retrying if fetch fails initially', async () => {
            const networkError = new TypeError('Failed to fetch');
            const mockResponseData = 'file content';

            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockRejectedValueOnce(networkError);
            mockFetch.mockResolvedValueOnce({
                ok: true,
                text: () => Promise.resolve(mockResponseData),
            });

            const result = await api.fetchFileContent(filePath);

            expect(mockFetch).toHaveBeenCalledTimes(3);
            expect(result).toEqual(mockResponseData);
            expect(consoleWarnSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        test('should throw an error if API returns non-ok response', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                text: () => Promise.resolve('File not found'),
            });

            await expect(api.fetchFileContent(filePath)).rejects.toThrow('File not found');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch file content:', expect.any(Error));
        });

        test('should throw a generic error if API returns non-ok response without specific error message', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: false,
                text: () => Promise.resolve(''),
            });

            await expect(api.fetchFileContent(filePath)).rejects.toThrow('Failed to fetch file content');
            expect(mockFetch).toHaveBeenCalledTimes(1);
            expect(consoleWarnSpy).not.toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch file content:', expect.any(Error));
        });
    });
});
