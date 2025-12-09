<template>
    <div class="bg-gray-900 p-4 space-y-3">
        <div v-if="testcase.message">
            <h4 :class="{'text-red-400': testcase.status === 'failed' || testcase.status === 'errored'}" class="font-bold text-md mb-2">{{ testcase.message }}</h4>
            <pre v-if="testcase.trace" class="font-mono text-sm text-white whitespace-pre-wrap bg-black p-3 rounded overflow-x-auto">{{ testcase.trace }}</pre>
        </div>
        <div v-if="store.state.options.displayWarnings && testcase.warnings?.length > 0">
            <h5 class="font-bold text-sm text-amber-400 mb-2">Warnings ({{ testcase.warnings.length }})</h5>
            <div v-for="(warning, idx) in testcase.warnings" :key="'w-'+idx" class="bg-amber-900/20 border-l-4 border-amber-500 p-2 mb-2">
                <pre class="font-mono text-xs text-gray-300 whitespace-pre-wrap">{{ warning }}</pre>
            </div>
        </div>
        <div v-if="store.state.options.displayDeprecations && testcase.deprecations?.length > 0">
            <h5 class="font-bold text-sm text-purple-400 mb-2">Deprecations ({{ testcase.deprecations.length }})</h5>
            <div v-for="(deprecation, idx) in testcase.deprecations" :key="'d-'+idx" class="bg-purple-900/20 border-l-4 border-purple-500 p-2 mb-2">
                <pre class="font-mono text-xs text-gray-300 whitespace-pre-wrap">{{ deprecation }}</pre>
            </div>
        </div>
        <div v-if="store.state.options.displayNotices && testcase.notices?.length > 0">
            <h5 class="font-bold text-sm text-sky-400 mb-2">Notices ({{ testcase.notices.length }})</h5>
            <div v-for="(notice, idx) in testcase.notices" :key="'n-'+idx" class="bg-sky-900/20 border-l-4 border-sky-500 p-2 mb-2">
                <pre class="font-mono text-xs text-gray-300 whitespace-pre-wrap">{{ notice }}</pre>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useStore } from '../store.js';

const store = useStore();
defineProps(['testcase']);
</script>
