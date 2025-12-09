/**
 * WebSocket handler for real-time test events
 */

import { updateFavicon } from './utils.js';

export class WebSocketManager {
    constructor(url = 'ws://127.0.0.1:8080/ws/status', store, callbacks = {}) {
        this.url = url;
        this.store = store;
        this.callbacks = callbacks; // { fetchCoverageReport: Function }
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 2000;
    }

    /**
     * Connect to WebSocket server
     */
    connect() {
        return new Promise((resolve, reject) => {
            try {
                this.ws = new WebSocket(this.url);

                this.ws.onopen = () => {
                    console.log('WebSocket connected');
                    this.reconnectAttempts = 0;
                    resolve();
                };

                this.ws.onmessage = (event) => {
                    this.handleMessage(JSON.parse(event.data));
                };

                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    reject(error);
                };

                this.ws.onclose = () => {
                    console.log('WebSocket disconnected');
                    this.handleDisconnect();
                };
            } catch (error) {
                reject(error);
            }
        });
    }

    /**
     * Handle WebSocket disconnect
     */
    handleDisconnect() {
        this.store.clearRunningTests();
        updateFavicon('neutral');

        // Attempt to reconnect
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = this.reconnectDelay * this.reconnectAttempts;
            console.log(`Attempting to reconnect in ${delay}ms...`);
            setTimeout(() => this.connect().catch(() => {}), delay);
        }
    }

    /**
     * Handle incoming WebSocket message
     */
    handleMessage(message) {
        switch (message.type) {
            case 'start':
                this.handleTestStart(message);
                break;
            case 'realtime':
                this.handleRealtimeEvent(message);
                break;
            case 'exit':
                this.handleTestExit();
                break;
            case 'stopped':
                this.handleTestStopped();
                break;
            default:
                console.warn('Unknown message type:', message.type);
        }
    }

    /**
     * Handle test start event
     */
    handleTestStart(message) {
        this.store.initializeTestRun(message.contextId);
    }

    /**
     * Handle realtime test event
     */
    handleRealtimeEvent(message) {
        try {
            const event = JSON.parse(message.data);
            this.store.handleTestEvent(event);

            if (event.event === 'execution.ended' && this.store.state.coverage) {
                this.store.setCoverageLoading(true);
            }
        } catch (error) {
            console.error('Failed to parse realtime event:', error, message.data);
        }
    }

    /**
     * Handle test exit event
     */
    handleTestExit() {
        if (this.store.state.coverage && this.callbacks.fetchCoverageReport) {
            this.callbacks.fetchCoverageReport();
        }
    }

    /**
     * Handle test stopped event
     */
    handleTestStopped() {
        this.store.stopTestRun();
        this.updateFaviconIfComplete();
    }

    /**
     * Update favicon if all tests are complete
     */
    updateFaviconIfComplete() {
        if (!this.store.isTestRunning()) {
            updateFavicon('neutral');
        }
    }

    /**
     * Disconnect from WebSocket
     */
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
    }
}
