<template>
    <div id="app" class="flex flex-col h-screen">
        <Header></Header>

        <!-- Main Container -->
        <div class="flex flex-grow overflow-hidden">
            <TestSidebar></TestSidebar>

            <!-- Resizer -->
            <div id="resizer" class="w-1.5 cursor-col-resize bg-gray-700 hover:bg-blue-600 transition-colors duration-200"></div>

            <MainContent></MainContent>
        </div>
    </div>
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { useStore } from './store.js';
import { WebSocketManager } from './websocket.js';
import { updateFavicon } from './utils.js';
import { useResizer } from './composables/useResizer.js';

import Header from './components/Header.vue';
import TestSidebar from './components/TestSidebar.vue';
import MainContent from './components/MainContent.vue';

const store = useStore();
let wsManager = null;

useResizer('resizer', 'test-sidebar');

onMounted(async () => {
    try {
        // Connect WebSocket
        const wsHost = window.WS_HOST || '127.0.0.1';
        const wsPort = window.WS_PORT || '8080';
        wsManager = new WebSocketManager(`ws://${wsHost}:${wsPort}/ws/status`, store, {
            fetchCoverageReport: store.fetchCoverageReport,
        });
        await wsManager.connect();

        // Update favicon
        updateFavicon('neutral');
    } catch (error) {
        console.error('Failed to initialize app:', error);
    }
});

watch(() => [
    store.state.options,
    store.state.selectedSuites,
    store.state.selectedGroups,
    store.state.coverage
], () => {
    store.saveState();
}, { deep: true });
</script>
