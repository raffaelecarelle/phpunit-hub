<template>
    <div class="mb-4">
        <TestSuiteHeader
            :suite="suite"
            @toggle-suite="toggleSuiteExpansion"
            @runSuiteTests="runSuiteTests"
        />
        <TestList
            v-show="store.state.expandedSuites.has(suite.id)"
            :suite="suite"
            @runSingleTest="runSingleTest"
        />
    </div>
</template>

<script setup>
import { useStore } from '../../store.js';
import TestSuiteHeader from './TestSuiteHeader.vue';
import TestList from './TestList.vue';

const store = useStore();
defineProps(['suite']);

function stopSingleTest(runId) {
    emit('stopSingleTest', runId);
}

function runSuiteTests(suiteId) {
    store.runSuiteTests(suiteId);
}

function runSingleTest(testId) {
    store.runSingleTest(testId);
}

function toggleSuiteExpansion(suiteId) {
    store.toggleSuiteExpansion(suiteId);
}
</script>
