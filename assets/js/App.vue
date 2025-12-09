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
import { onMounted, computed, watch } from 'vue';
import { useStore } from './store.js';
import { ApiClient } from './api.js';
import { WebSocketManager } from './websocket.js';
import { updateFavicon } from './utils.js';

import Header from './components/Header.vue';
import TestSidebar from './components/TestSidebar.vue';
import MainContent from './components/MainContent.vue';

const store = useStore();
const api = new ApiClient('');
let wsManager = null;

onMounted(async () => {
    try {
        // Connect WebSocket
        const wsHost = window.WS_HOST || '127.0.0.1';
        const wsPort = window.WS_PORT || '8080';
        wsManager = new WebSocketManager(`ws://${wsHost}:${wsPort}/ws/status`, store, {
            fetchCoverageReport: store.fetchCoverageReport,
        });
        await wsManager.connect();

        // Setup resizer
        setupResizer();

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

function setupResizer() {
    const resizer = document.getElementById('resizer');
    const sidebar = document.getElementById('test-sidebar');

    if (!resizer || !sidebar) return;

    let isResizing = false;

    resizer.addEventListener('mousedown', (e) => {
        e.preventDefault();
        isResizing = true;
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        const mouseMoveHandler = (e) => {
            if (!isResizing) return;
            const sidebarWidth = e.clientX;
            if (sidebarWidth > 200 && sidebarWidth < window.innerWidth - 200) {
                sidebar.style.width = `${sidebarWidth}px`;
            }
        };

        const mouseUpHandler = () => {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', mouseMoveHandler);
            document.removeEventListener('mouseup', mouseUpHandler);
        };

        document.addEventListener('mousemove', mouseMoveHandler);
        document.addEventListener('mouseup', mouseUpHandler);
    });
}
</script>
