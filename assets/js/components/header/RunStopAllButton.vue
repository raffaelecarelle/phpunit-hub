<template>
    <button @click="togglePlayStop"
            :title="isAnyTestRunning ? 'Stop all test runs' : 'Run all tests'"
            :disabled="isAnyStopPending"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:opacity-60">
        <span v-if="isAnyStopPending">Stopping...</span>
        <template v-else>
            <span v-if="!isAnyTestRunning">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 inline-block">
                    <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                </svg>
                <span class="ml-2 align-middle">Run All</span>
            </span>
            <span v-else>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 inline-block">
                    <path d="M6 6h8v8H6z" />
                </svg>
                <span class="ml-2 align-middle">Stop All</span>
            </span>
        </template>
    </button>
</template>

<script setup>
import { useStore } from '../../store.js';

defineProps(['isAnyTestRunning', 'isAnyStopPending']);

const store = useStore();

function togglePlayStop() {
    if (store.state.isRunning) {
        store.stopAllTests();
    } else {
        store.runAllTests();
    }
}
</script>
