<template>
    <header class="bg-gray-800 shadow-md p-4 flex justify-between items-center z-10 relative">
        <header-title></header-title>
        <div class="flex items-center space-x-4">
            <filter-panel></filter-panel>
            <clear-results-button
                :is-any-test-running="isAnyTestRunning"
                :results="results"
            ></clear-results-button>
            <run-failed-button
                :is-any-test-running="isAnyTestRunning"
                :has-failed-tests="hasFailedTests"
            ></run-failed-button>
            <run-stop-all-button
                :is-any-test-running="isAnyTestRunning"
                :is-any-stop-pending="isAnyStopPending"
            ></run-stop-all-button>
        </div>
    </header>
</template>

<script setup>
import HeaderTitle from './header/HeaderTitle.vue';
import FilterPanel from './header/FilterPanel.vue';
import ClearResultsButton from './header/ClearResultsButton.vue';
import RunFailedButton from './header/RunFailedButton.vue';
import RunStopAllButton from './header/RunStopAllButton.vue';

defineProps(['isAnyTestRunning', 'hasFailedTests', 'isAnyStopPending', 'results']);
</script>
