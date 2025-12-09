<template>
    <div class="bg-gray-800 rounded-lg shadow-lg p-4">
        <div class="grid grid-cols-3 gap-4 text-center mb-4 border-b border-gray-700 pb-4">
            <div>
                <div class="text-sm text-gray-400">Total Tests</div>
                <div class="text-2xl font-bold">{{ results?.summary.tests ?? 0 }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-400">Total Assertions</div>
                <div class="text-2xl font-bold">{{ results?.summary.assertions ?? 0 }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-400">Duration</div>
                <div class="text-2xl font-bold">{{ formatNanoseconds(results?.summary.time) }}</div>
            </div>
        </div>
        <div class="flex justify-center flex-wrap gap-x-4 gap-y-2 text-sm">
            <span class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                {{ statusCounts.passed }} Passed
            </span>
            <span v-if="results?.summary.failures > 0" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                {{ results?.summary.failures }} Failed
            </span>
            <span v-if="results?.summary.errors > 0" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                {{ results?.summary.errors }} Errors
            </span>
            <span v-if="results?.summary.warnings > 0 && store.state.options.displayWarnings" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-amber-500 mr-2"></span>
                {{ results?.summary.warnings }} Warnings
            </span>
            <span v-if="results?.summary.skipped > 0 && store.state.options.displaySkipped" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-gray-500 mr-2"></span>
                {{ results?.summary.skipped }} Skipped
            </span>
            <span v-if="results?.summary.deprecations > 0 && store.state.options.displayDeprecations" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-purple-500 mr-2"></span>
                {{ results?.summary.deprecations }} Deprecations
            </span>
            <span v-if="results?.summary.notices > 0 && store.state.options.displayNotices" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-sky-500 mr-2"></span>
                {{ results?.summary.notices }} Notices
            </span>
            <span v-if="results?.summary.incomplete > 0 && store.state.options.displayIncomplete" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>
                {{ results?.summary.incomplete }} Incomplete
            </span>
            <span v-if="results?.summary.risky > 0 && store.state.options.displayRisky" class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-orange-500 mr-2"></span>
                {{ results?.summary.risky }} Risky
            </span>
        </div>
    </div>
</template>

<script setup>
import { useStore } from '../store.js';
import {formatNanoseconds} from '../utils.js';

const store = useStore();
defineProps(['results', 'statusCounts']);
</script>
