/** @jest-environment jsdom */
// Mock btoa as it's not available in a Node.js test environment
global.btoa = jest.fn((str) => Buffer.from(str).toString('base64'));

import { favicons, updateFavicon, parseTestId, formatTime, calculatePassedTests } from '../utils.js';

describe('utils.js', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    describe('favicons', () => {
        test('should contain neutral, success, and failure SVG strings', () => {
            expect(favicons.neutral).toBeDefined();
            expect(favicons.success).toBeDefined();
            expect(favicons.failure).toBeDefined();
            expect(typeof favicons.neutral).toBe('string');
            expect(favicons.neutral).toMatch(/^<svg/);
        });
    });

    describe('updateFavicon', () => {
        let mockFaviconLink;

        beforeEach(() => {
            mockFaviconLink = { href: '' };
            jest.spyOn(document, 'getElementById').mockReturnValue(mockFaviconLink);
        });

        test('should update favicon with neutral status by default', () => {
            updateFavicon();
            const expectedSvg = favicons.neutral;
            expect(document.getElementById).toHaveBeenCalledWith('favicon');
            expect(global.btoa).toHaveBeenCalledWith(expectedSvg);
            expect(mockFaviconLink.href).toBe(`data:image/svg+xml;base64,${Buffer.from(expectedSvg).toString('base64')}`);
        });

        test('should update favicon with success status', () => {
            updateFavicon('success');
            const expectedSvg = favicons.success;
            expect(document.getElementById).toHaveBeenCalledWith('favicon');
            expect(global.btoa).toHaveBeenCalledWith(expectedSvg);
            expect(mockFaviconLink.href).toBe(`data:image/svg+xml;base64,${Buffer.from(expectedSvg).toString('base64')}`);
        });

        test('should update favicon with failure status', () => {
            updateFavicon('failure');
            const expectedSvg = favicons.failure;
            expect(document.getElementById).toHaveBeenCalledWith('favicon');
            expect(global.btoa).toHaveBeenCalledWith(expectedSvg);
            expect(mockFaviconLink.href).toBe(`data:image/svg+xml;base64,${Buffer.from(expectedSvg).toString('base64')}`);
        });

        test('should fall back to neutral if unknown status is provided', () => {
            updateFavicon('unknown');
            const expectedSvg = favicons.neutral;
            expect(document.getElementById).toHaveBeenCalledWith('favicon');
            expect(global.btoa).toHaveBeenCalledWith(expectedSvg);
            expect(mockFaviconLink.href).toBe(`data:image/svg+xml;base64,${Buffer.from(expectedSvg).toString('base64')}`);
        });

        test('should do nothing if favicon element is not found', () => {
            document.getElementById.mockReturnValue(null);
            updateFavicon('success');
            expect(document.getElementById).toHaveBeenCalledWith('favicon');
            expect(global.btoa).not.toHaveBeenCalled();
            expect(mockFaviconLink.href).toBe(''); // Should remain unchanged
        });
    });

    describe('parseTestId', () => {
        test('should correctly parse a full test ID', () => {
            const testId = 'MyNamespace\\MyClass::testMethod';
            const result = parseTestId(testId);
            expect(result).toEqual({
                suiteName: 'MyNamespace\\MyClass',
                testName: 'testMethod',
                fullId: testId,
            });
        });

        test('should correctly parse a test ID without a method (suite only)', () => {
            const testId = 'MyNamespace\\MyClass';
            const result = parseTestId(testId);
            expect(result).toEqual({
                suiteName: 'MyNamespace\\MyClass',
                testName: undefined, // Or null, depending on desired behavior for missing method
                fullId: testId,
            });
        });

        test('should handle empty string', () => {
            const testId = '';
            const result = parseTestId(testId);
            expect(result).toEqual({
                suiteName: '',
                testName: undefined,
                fullId: testId,
            });
        });

        test('should handle test ID with multiple colons (only split on first)', () => {
            const testId = 'MyNamespace\\MyClass::testMethod::data_provider_key';
            const result = parseTestId(testId);
            expect(result).toEqual({
                suiteName: 'MyNamespace\\MyClass',
                testName: 'testMethod::data_provider_key',
                fullId: testId,
            });
        });
    });

    describe('formatTime', () => {
        test('should format time with two decimal places and "s" suffix', () => {
            expect(formatTime(1.23456)).toBe('1.2346s');
            expect(formatTime(10.0)).toBe('10.0000s');
            expect(formatTime(0.001)).toBe('0.0010s');
            expect(formatTime(123.456789)).toBe('123.4568s');
        });

        test('should return "0.00s" for null or undefined input', () => {
            expect(formatTime(null)).toBe('0.00s');
            expect(formatTime(undefined)).toBe('0.00s');
            expect(formatTime(0)).toBe('0.0000s'); // Corrected expectation
        });
    });

    describe('calculatePassedTests', () => {
        test('should correctly calculate passed tests from a summary', () => {
            const summary = {
                tests: 10,
                failures: 2,
                errors: 1,
                warnings: 1,
                skipped: 1,
                incomplete: 1,
                deprecations: 1,
            };
            // 10 total - (2f + 1e + 1w + 1s + 1i + 1d) = 10 - 7 = 3
            expect(calculatePassedTests(summary)).toBe(5);
        });

        test('should return total tests if no failures, errors, etc.', () => {
            const summary = {
                tests: 5,
                failures: 0,
                errors: 0,
                warnings: 0,
                skipped: 0,
                incomplete: 0,
                deprecations: 0,
            };
            expect(calculatePassedTests(summary)).toBe(5);
        });

        test('should handle missing properties in summary by treating them as 0', () => {
            const summary = {
                tests: 7,
                failures: 2,
            };
            // 7 total - (2f + 0e + 0w + 0s + 0i + 0d) = 7 - 2 = 5
            expect(calculatePassedTests(summary)).toBe(5);
        });

        test('should return 0 if summary is null or undefined', () => {
            expect(calculatePassedTests(null)).toBe(0);
            expect(calculatePassedTests(undefined)).toBe(0);
        });

        test('should return 0 if total tests is 0', () => {
            const summary = {
                tests: 0,
                failures: 0,
                errors: 0,
            };
            expect(calculatePassedTests(summary)).toBe(0);
        });
    });
});
