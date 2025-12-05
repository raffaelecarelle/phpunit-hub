<template>
    <div class="mb-4">
        <TestSuiteHeader
            :suite="suite"
            :is-test-running="isTestRunning"
            :is-test-stop-pending="isTestStopPending"
            @toggle-suite="$emit('toggle-suite', suite.id)"
            @stopSingleTest="stopSingleTest"
            @runSuiteTests="runSuiteTests"
        />
        <TestList
            v-show="store.state.expandedSuites.has(suite.id)"
            :suite="suite"
            :is-test-running="isTestRunning"
            @runSingleTest="runSingleTest"
        />
    </div>
</template>

<script setup>
import { useStore } from '../../store.js';
import TestSuiteHeader from './TestSuiteHeader.vue';
import TestList from './TestList.vue';

const store = useStore();
defineProps(['suite', 'isTestRunning', 'isTestStopPending']);
const emit = defineEmits(['toggle-suite', 'stopSingleTest', 'runSuiteTests', 'runSingleTest']);

function stopSingleTest(runId) {
    emit('stopSingleTest', runId);
}

function runSuiteTests(suiteId) {
    emit('runSuiteTests', suiteId);
}

function runSingleTest(testId) {
    emit('runSingleTest', testId);
}
</script>
