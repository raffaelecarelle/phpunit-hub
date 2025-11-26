// Mock the global WebSocket object
const mockWebSocketInstance = {
    onopen: null,
    onmessage: null,
    onerror: null,
    onclose: null,
    close: jest.fn(),
    send: jest.fn(),
};
const MockWebSocket = jest.fn(() => mockWebSocketInstance);
global.WebSocket = MockWebSocket;

// Mock dependencies
// Define the mock function directly in the factory
const { updateFavicon } = jest.mock('../utils.js', () => ({
    updateFavicon: jest.fn(),
}));

class MockStore {
    constructor() {
        this.state = {
            realtimeTestRuns: {},
        };
        this.initializeTestRun = jest.fn();
        this.handleTestEvent = jest.fn();
        this.finishTestRun = jest.fn();
        this.stopTestRun = jest.fn();
        this.clearRunningTests = jest.fn();
        this.getTestRun = jest.fn((runId) => this.state.realtimeTestRuns[runId]);
        this.getRunningTestCount = jest.fn(() => Object.keys(this.state.runningTestRuns).length);
    }
}

import { WebSocketManager } from '../websocket.js';

describe('WebSocketManager', () => {
    let wsManager;
    let store;
    const wsUrl = 'ws://localhost:8080/ws';

    beforeEach(() => {
        store = new MockStore();
        wsManager = new WebSocketManager(wsUrl, store);

        jest.clearAllMocks();
        jest.spyOn(console, 'log').mockImplementation(() => {});
        jest.spyOn(console, 'error').mockImplementation(() => {});
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        jest.useFakeTimers(); // Mock timers for setTimeout
    });

    afterEach(() => {
        jest.restoreAllMocks();
        jest.useRealTimers(); // Restore real timers
    });

    describe('connect', () => {
        test('should create a new WebSocket instance', async () => {
            const connectPromise = wsManager.connect();
            mockWebSocketInstance.onopen(); // Simulate connection open
            await connectPromise;

            expect(MockWebSocket).toHaveBeenCalledWith(wsUrl);
            expect(console.log).toHaveBeenCalledWith('WebSocket connected');
            expect(wsManager.reconnectAttempts).toBe(0);
        });

        test('should reject if WebSocket connection fails', async () => {
            const connectPromise = wsManager.connect();
            const error = new Error('Connection failed');
            mockWebSocketInstance.onerror(error); // Simulate connection error
            await expect(connectPromise).rejects.toThrow('Connection failed');
            expect(console.error).toHaveBeenCalledWith('WebSocket error:', error);
        });

        test('should reject if WebSocket constructor throws an error', async () => {
            MockWebSocket.mockImplementationOnce(() => { throw new Error('Invalid URL'); });
            await expect(wsManager.connect()).rejects.toThrow('Invalid URL');
        });
    });

    describe('handleDisconnect', () => {
        test('should clear running tests and update favicon to neutral', () => {
            wsManager.handleDisconnect();
            expect(store.clearRunningTests).toHaveBeenCalledTimes(1);
            expect(updateFavicon).toHaveBeenCalledWith('neutral'); // Use the directly imported mock
        });

        test('should attempt to reconnect if reconnect attempts are less than max', () => {
            wsManager.reconnectAttempts = 0;
            wsManager.maxReconnectAttempts = 1;
            const connectSpy = jest.spyOn(wsManager, 'connect').mockResolvedValueOnce();

            wsManager.handleDisconnect();
            expect(wsManager.reconnectAttempts).toBe(1);
            expect(console.log).toHaveBeenCalledWith('Attempting to reconnect in 2000ms...');

            jest.advanceTimersByTime(2000);
            expect(connectSpy).toHaveBeenCalledTimes(1);
        });

        test('should not attempt to reconnect if reconnect attempts exceed max', () => {
            wsManager.reconnectAttempts = wsManager.maxReconnectAttempts;
            const connectSpy = jest.spyOn(wsManager, 'connect');

            wsManager.handleDisconnect();
            expect(connectSpy).not.toHaveBeenCalled();
            expect(console.log).not.toHaveBeenCalledWith(expect.stringContaining('Attempting to reconnect'));
        });
    });

    describe('handleMessage', () => {
        test('should call handleTestStart for "start" message type', () => {
            const message = { type: 'start', runId: '123', contextId: 'global' };
            wsManager.handleMessage(message);
            expect(store.initializeTestRun).toHaveBeenCalledWith('123', 'global');
        });

        test('should call handleRealtimeEvent for "realtime" message type', () => {
            const message = { type: 'realtime', runId: '123', data: JSON.stringify({ event: 'test.prepared' }) };
            wsManager.handleMessage(message);
            expect(store.handleTestEvent).toHaveBeenCalledWith('123', { event: 'test.prepared' });
        });

        test('should call handleTestExit for "exit" message type', () => {
            const message = { type: 'exit', runId: '123' };
            const updateFaviconSpy = jest.spyOn(wsManager, 'updateFaviconFromRun');
            wsManager.handleMessage(message);
            expect(store.finishTestRun).toHaveBeenCalledWith('123');
            expect(updateFaviconSpy).toHaveBeenCalledWith('123');
        });

        test('should call handleTestStopped for "stopped" message type', () => {
            const message = { type: 'stopped', runId: '123' };
            const updateFaviconSpy = jest.spyOn(wsManager, 'updateFaviconIfComplete');
            wsManager.handleMessage(message);
            expect(store.stopTestRun).toHaveBeenCalledWith('123');
            expect(updateFaviconSpy).toHaveBeenCalledTimes(1);
        });

        test('should warn for unknown message type', () => {
            const message = { type: 'unknown' };
            wsManager.handleMessage(message);
            expect(console.warn).toHaveBeenCalledWith('Unknown message type:', 'unknown');
        });
    });

    describe('handleRealtimeEvent', () => {
        test('should parse message data and call store.handleTestEvent', () => {
            const message = { runId: 'run456', data: JSON.stringify({ event: 'test.passed', data: { test: 'Suite::test' } }) };
            wsManager.handleRealtimeEvent(message);
            expect(store.handleTestEvent).toHaveBeenCalledWith('run456', { event: 'test.passed', data: { test: 'Suite::test' } });
        });

        test('should log an error if message data is invalid JSON', () => {
            const message = { runId: 'run456', data: 'invalid json' };
            wsManager.handleRealtimeEvent(message);
            expect(console.error).toHaveBeenCalledWith('Failed to parse realtime event:', expect.any(Error), 'invalid json');
        });
    });

    describe('updateFaviconFromRun', () => {
        test('should set favicon to failure if run has failures', () => {
            store.state.realtimeTestRuns['run1'] = { summary: { numberOfFailures: 1, numberOfErrors: 0 } };
            wsManager.updateFaviconFromRun('run1');
            expect(updateFavicon).toHaveBeenCalledWith('failure'); // Use the directly imported mock
        });

        test('should set favicon to failure if run has errors', () => {
            store.state.realtimeTestRuns['run1'] = { summary: { numberOfFailures: 0, numberOfErrors: 1 } };
            wsManager.updateFaviconFromRun('run1');
            expect(updateFavicon).toHaveBeenCalledWith('failure'); // Use the directly imported mock
        });

        test('should set favicon to success if run has no failures or errors', () => {
            store.state.realtimeTestRuns['run1'] = { summary: { numberOfFailures: 0, numberOfErrors: 0 } };
            wsManager.updateFaviconFromRun('run1');
            expect(updateFavicon).toHaveBeenCalledWith('success'); // Use the directly imported mock
        });

        test('should do nothing if run or summary is missing', () => {
            wsManager.updateFaviconFromRun('nonExistentRun');
            expect(updateFavicon).not.toHaveBeenCalled(); // Use the directly imported mock

            store.state.realtimeTestRuns['run2'] = { summary: null };
            wsManager.updateFaviconFromRun('run2');
            expect(updateFavicon).not.toHaveBeenCalled(); // Use the directly imported mock
        });
    });

    describe('updateFaviconIfComplete', () => {
        test('should set favicon to neutral if no tests are running', () => {
            store.getRunningTestCount.mockReturnValueOnce(0);
            wsManager.updateFaviconIfComplete();
            expect(updateFavicon).toHaveBeenCalledWith('neutral'); // Use the directly imported mock
        });

        test('should not update favicon if tests are still running', () => {
            store.getRunningTestCount.mockReturnValueOnce(1);
            wsManager.updateFaviconIfComplete();
            expect(updateFavicon).not.toHaveBeenCalled(); // Use the directly imported mock
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
