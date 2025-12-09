<template>
    <div class="suite-header text-md text-gray-200">
        <div @click="$emit('toggle-suite', suite.id)" class="flex items-center flex-grow cursor-pointer">
            <svg class="suite-arrow w-4 h-4 text-gray-400"
                 :class="{ 'rotated': store.state.expandedSuites.has(suite.id) }"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24"
                 xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
            <span class="font-bold">{{ suite.name }}</span>
        </div>
        <div class="flex items-center">
            <div v-if="suite.isRunning" class="spinner !w-4 !h-4"></div>
            <span v-if="suite.isRunning"
                  @click.stop="stopSingleTest"
                  class="cursor-pointer text-red-500 hover:text-red-400 ml-2"
                  title="Stop this suite">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path d="M6 6h8v8H6z" />
                    </svg>
                </span>
            <span v-else
                  @click.stop="runSuiteTests(suite.id)"
                  :class="{'cursor-pointer text-green-500 hover:text-green-400': !isTestStopPending(), 'text-gray-500': isTestStopPending()}"
                  :title="isTestStopPending() ? 'Stopping...' : 'Run all tests in this suite'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                    </svg>
                </span>
        </div>
    </div>
</template>

<script setup>
import { useStore } from '../../store.js';
import { ApiClient } from '../../api.js';

const api = new ApiClient('');
const store = useStore();
const props = defineProps(['suite']);
const emit = defineEmits(['toggle-suite', 'runSuiteTests']);

async function stopSingleTest() {
    try {
        store.markStopPending();
        await api.stopSingleTest();
    } catch (error) {
        console.error(`Failed to stop test run:`, error);
        store.clearStopPending();
    }
}

function runSuiteTests(suiteId) {
    if (isTestRunning()) {
        return;
    }
    emit('runSuiteTests', suiteId);
}

function isTestRunning() {
    return store.state.isRunning;
}

function isTestStopPending() {
    return store.state.isStopping;
}
</script>
