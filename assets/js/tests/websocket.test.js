import {vi, expect, test, describe, beforeEach, afterEach} from 'vitest'; // Import vi from vitest

// Mock the global WebSocket object
const mockWebSocketInstance = {
    onopen: vi.fn(), // Make it a mock function
    onmessage: vi.fn(), // Make it a mock function
    onerror: vi.fn(), // Make it a mock function
    onclose: vi.fn(), // Make it a mock function
    close: vi.fn(),
    send: vi.fn(),
};
// Mock WebSocket as a constructor that returns mockWebSocketInstance
const MockWebSocket = vi.fn(function WebSocket() {
    // In a real scenario, you might want to store the URL or other constructor args
    // For now, just return the predefined mock instance
    return mockWebSocketInstance;
});
global.WebSocket = MockWebSocket;

// Mock dependencies
import * as Utils from '../utils.js'; // Import the module
vi.mock('../utils.js'); // Mock the module

class MockStore {
    constructor() {
        this.state = {
            testRun: null,
            isRunning: false,
            options: {
                resultUpdateMode: 'update', // Default to update mode
            },
        };
        this.initializeTestRun = vi.fn();
        this.handleTestEvent = vi.fn();
        this.stopTestRun = vi.fn();
        this.clearRunningTests = vi.fn();
        this.getTestRun = vi.fn(() => this.state.testRun);
        this.isTestRunning = vi.fn(() => this.state.isRunning);
        this.finishTestRun = vi.fn();
    }
}

import {WebSocketManager} from '../websocket.js';

describe('WebSocketManager', () => {
    let wsManager;
    let store;
    const wsUrl = 'ws://localhost:8080/ws';

    beforeEach(() => {
        store = new MockStore();
        wsManager = new WebSocketManager(wsUrl, store);

        vi.clearAllMocks();
        Utils.updateFavicon.mockClear(); // Clear the mock here
        vi.spyOn(console, 'log').mockImplementation(() => {
        });
        vi.spyOn(console, 'error').mockImplementation(() => {
        });
        vi.spyOn(console, 'warn').mockImplementation(() => {
        });
        vi.useFakeTimers(); // Mock timers for setTimeout
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers(); // Restore real timers
    });

    describe('connect', () => {
        test('should create a new WebSocket instance', async () => {
            const connectPromise = wsManager.connect();
            // Simulate connection open by calling the mocked onopen handler
            mockWebSocketInstance.onopen();
            await connectPromise;

            expect(MockWebSocket).toHaveBeenCalledWith(wsUrl);
            expect(console.log).toHaveBeenCalledWith('WebSocket connected');
            expect(wsManager.reconnectAttempts).toBe(0);
        });

        test('should reject if WebSocket connection fails', async () => {
            const connectPromise = wsManager.connect();
            const error = new Error('Connection failed');
            // Simulate connection error by calling the mocked onerror handler
            mockWebSocketInstance.onerror(error);
            await expect(connectPromise).rejects.toThrow('Connection failed');
            expect(console.error).toHaveBeenCalledWith('WebSocket error:', error);
        });

        test('should reject if WebSocket constructor throws an error', async () => {
            MockWebSocket.mockImplementationOnce(() => {
                throw new Error('Invalid URL');
            });
            await expect(wsManager.connect()).rejects.toThrow('Invalid URL');
        });
    });

    describe('handleDisconnect', () => {
        test('should clear running tests and update favicon to neutral', () => {
            wsManager.handleDisconnect();
            expect(store.clearRunningTests).toHaveBeenCalledTimes(1);
            expect(Utils.updateFavicon).toHaveBeenCalledWith('neutral'); // Use Utils.updateFavicon
        });

        test('should attempt to reconnect if reconnect attempts are less than max', () => {
            wsManager.reconnectAttempts = 0;
            wsManager.maxReconnectAttempts = 1;
            const connectSpy = vi.spyOn(wsManager, 'connect').mockResolvedValueOnce();

            wsManager.handleDisconnect();
            expect(wsManager.reconnectAttempts).toBe(1);
            expect(console.log).toHaveBeenCalledWith('Attempting to reconnect in 2000ms...');

            vi.advanceTimersByTime(2000);
            expect(connectSpy).toHaveBeenCalledTimes(1);
        });

        test('should not attempt to reconnect if reconnect attempts exceed max', () => {
            wsManager.reconnectAttempts = wsManager.maxReconnectAttempts;
            const connectSpy = vi.spyOn(wsManager, 'connect');

            wsManager.handleDisconnect();
            expect(connectSpy).not.toHaveBeenCalled();
            expect(console.log).not.toHaveBeenCalledWith(expect.stringContaining('Attempting to reconnect'));
        });
    });

    describe('handleMessage', () => {
        test('should call handleTestStart for "start" message type', () => {
            const message = {type: 'start', contextId: 'global'};
            wsManager.handleMessage(message);
            expect(store.initializeTestRun).toHaveBeenCalledWith('global');
        });

        test('should call handleRealtimeEvent for "realtime" message type', () => {
            const message = {type: 'realtime', data: JSON.stringify({event: 'test.prepared'})};
            wsManager.handleMessage(message);
            expect(store.handleTestEvent).toHaveBeenCalledWith({event: 'test.prepared'});
        });

        test('should call handleTestExit for "exit" message type', () => {
            const message = {type: 'exit'};
            const fetchCoverageReportSpy = vi.fn();
            wsManager.callbacks.fetchCoverageReport = fetchCoverageReportSpy;
            store.state.coverage = true;
            wsManager.handleMessage(message);
            expect(fetchCoverageReportSpy).toHaveBeenCalled();
        });

        test('should call handleTestStopped for "stopped" message type', () => {
            const message = {type: 'stopped'};
            const updateFaviconSpy = vi.spyOn(wsManager, 'updateFaviconIfComplete');
            wsManager.handleMessage(message);
            expect(store.stopTestRun).toHaveBeenCalled();
            expect(updateFaviconSpy).toHaveBeenCalledTimes(1);
        });

        test('should warn for unknown message type', () => {
            const message = {type: 'unknown'};
            wsManager.handleMessage(message);
            expect(console.warn).toHaveBeenCalledWith('Unknown message type:', 'unknown');
        });
    });

    describe('handleRealtimeEvent', () => {
        test('should parse message data and call store.handleTestEvent', () => {
            const message = {data: JSON.stringify({event: 'test.passed', data: {test: 'Suite::test'}})};
            wsManager.handleRealtimeEvent(message);
            expect(store.handleTestEvent).toHaveBeenCalledWith({event: 'test.passed', data: {test: 'Suite::test'}});
        });

        test('should log an error if message data is invalid JSON', () => {
            const message = {data: 'invalid json'};
            wsManager.handleRealtimeEvent(message);
            expect(console.error).toHaveBeenCalledWith('Failed to parse realtime event:', expect.any(Error), 'invalid json');
        });
    });

    describe('updateFaviconIfComplete', () => {
        test('should set favicon to neutral if no tests are running', () => {
            store.isTestRunning.mockReturnValueOnce(false);
            wsManager.updateFaviconIfComplete();
            expect(Utils.updateFavicon).toHaveBeenCalledWith('neutral');
        });

        test('should not update favicon if tests are still running', () => {
            store.isTestRunning.mockReturnValueOnce(true);
            wsManager.updateFaviconIfComplete();
            expect(Utils.updateFavicon).not.toHaveBeenCalled();
        });
    });

    describe('disconnect', () => {
        test('should close the WebSocket connection if it exists', () => {
            wsManager.ws = mockWebSocketInstance;
            wsManager.disconnect();
            expect(mockWebSocketInstance.close).toHaveBeenCalledTimes(1);
            expect(wsManager.ws).toBeNull();
        });

        test('should do nothing if WebSocket connection does not exist', () => {
            wsManager.ws = null;
            wsManager.disconnect();
            expect(mockWebSocketInstance.close).not.toHaveBeenCalled();
        });
    });
});
