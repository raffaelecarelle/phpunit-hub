<template>
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="flex items-center p-3 bg-gray-700/50 text-xs text-gray-400 uppercase font-semibold">
            <div class="w-28 flex-shrink-0 pl-3">Status</div>
            <div class="flex-grow">Test Case</div>
            <div class="w-28 text-right flex-shrink-0 pr-3 cursor-pointer" @click="setSortBy('duration')">
                Duration
                <span v-if="store.state.sortBy === 'duration'">
                        <span v-if="store.state.sortDirection === 'asc'">▲</span>
                        <span v-else>▼</span>
                    </span>
            </div>
        </div>
        <div class="divide-y divide-gray-700">
            <div v-for="testcase in individualResults"
                 :key="'individual-test-' + testcase.id"
                 class="hover:bg-gray-700/50">
                <div class="flex items-center p-3 cursor-pointer"
                     @click="toggleTestDetails(testcase)">
                    <div class="w-28 flex-shrink-0 pl-3">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                  :class="{
                                      'bg-green-900 text-green-300': testcase.status === 'passed',
                                      'bg-red-900 text-red-300': testcase.status === 'failed' || testcase.status === 'errored',
                                      'bg-gray-700 text-gray-300': testcase.status === 'skipped',
                                      'bg-yellow-800 text-yellow-200': testcase.status === 'incomplete',
                                      'bg-orange-800 text-orange-200': testcase.status === 'risky'
                                  }">
                                {{ testcase.status }}
                            </span>
                    </div>
                    <div class="flex-grow">
                        <div class="text-sm text-white">{{ testcase.name }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ testcase.class }}</div>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        <span v-if="store.state.options.displayWarnings && testcase.warnings?.length > 0" class="text-amber-400">⚠ {{ testcase.warnings.length }} warning(s)</span>
                        <span v-if="store.state.options.displayDeprecations && testcase.deprecations?.length > 0" class="text-purple-400 ml-2">⚠ {{ testcase.deprecations.length }} deprecation(s)</span>
                        <span v-if="store.state.options.displayNotices && testcase.notices?.length > 0" class="text-sky-400 ml-2">⚠ {{ testcase.notices.length }} notice(s)</span>
                        </div>
                    <div class="w-28 text-right flex-shrink-0 pr-3">
                        <span class="text-sm" :class="{'text-yellow-400': (testcase.duration / 1000000000) > 0.5 }">{{ formatNanoseconds(testcase.duration) }}</span>
                    </div>
                </div>
                <TestDetails v-if="store.state.expandedTestId === testcase.id" :testcase="testcase"></TestDetails>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useStore } from '../store.js';
import TestDetails from './TestDetails.vue';

const store = useStore();
defineProps(['individualResults', 'formatNanoseconds']);
const emit = defineEmits(['toggleTestDetails']);

function setSortBy(sortBy) {
    store.setSortBy(sortBy);
}

function toggleTestDetails(testcase) {
    emit('toggleTestDetails', testcase);
}
</script>
