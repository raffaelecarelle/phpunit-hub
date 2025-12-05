export const GroupedTestResults = {
    props: [
        'groupedResults',
        'store',
        'app',
        'formatNanoseconds',
        'toggleTestDetails'
    ],
    template: `
        <div v-for="group in groupedResults" :key="group.className" class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="flex items-center justify-between bg-gray-700 px-4 py-3 cursor-pointer hover:bg-gray-600"
                 @click="toggleTestcaseGroup(group.className)">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-400 transition-transform"
                         :class="{ 'rotate-90': store.state.expandedTestcaseGroups.has(group.className) }"
                         viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-bold text-white">{{ group.className }}</span>
                </div>
                <div class="flex items-center space-x-4 text-sm">
                    <span v-if="group.failed > 0" class="px-2 py-0.5 rounded-full bg-red-900 text-red-300">{{ group.failed }} Failed</span>
                    <span v-if="group.errored > 0" class="px-2 py-0.5 rounded-full bg-red-900 text-red-300">{{ group.errored }} Error</span>
                    <span v-if="group.warning > 0 && store.state.options.displayWarnings" class="px-2 py-0.5 rounded-full bg-amber-800 text-amber-200">{{ group.warning }} Warnings</span>
                    <span v-if="group.deprecation > 0 && store.state.options.displayDeprecations" class="px-2 py-0.5 rounded-full bg-purple-800 text-purple-200">{{ group.deprecation }} Deprecations</span>
                    <span v-if="group.notice > 0 && store.state.options.displayNotices" class="px-2 py-0.5 rounded-full bg-sky-800 text-sky-200">{{ group.notice }} Notices</span>
                    <span v-if="group.skipped > 0 && store.state.options.displaySkipped" class="px-2 py-0.5 rounded-full bg-gray-700 text-gray-300">{{ group.skipped }} Skipped</span>
                    <span v-if="group.incomplete > 0 && store.state.options.displayIncomplete" class="px-2 py-0.5 rounded-full bg-yellow-800 text-yellow-200">{{ group.incomplete }} Incomplete</span>
                    <span v-if="group.risky > 0 && store.state.options.displayRisky" class="px-2 py-0.5 rounded-full bg-orange-800 text-orange-200">{{ group.risky }} Risky</span>
                    <span v-if="group.passed > 0" class="px-2 py-0.5 rounded-full bg-green-900 text-green-300">{{ group.passed }} Passed</span>
                </div>
            </div>
            <div v-show="store.state.expandedTestcaseGroups.has(group.className)" class="divide-y divide-gray-700">
                <!-- Failed/Errored tests -->
                <div v-for="testcase in group.testcases.filter(t => {
                             if (t.status === 'passed') return false;
                             if (t.status === 'skipped' && !store.state.options.displaySkipped) return false;
                             if (t.status === 'incomplete' && !store.state.options.displayIncomplete) return false;
                             if (t.status === 'risky' && !store.state.options.displayRisky) return false;
                             return true;
                         })"
                     :key="'test-' + testcase.id"
                     class="hover:bg-gray-700/50">
                    <div class="flex items-center p-3 cursor-pointer"
                         @click="toggleTestDetails(store, testcase)">
                        <div class="w-28 flex-shrink-0 pl-3">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                      :class="{
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
                            <div class="text-xs text-gray-400 mt-1">
                                <span v-if="store.state.options.displayWarnings && testcase.warnings?.length > 0" class="text-amber-400">⚠ {{ testcase.warnings.length }} warning(s)</span>
                                <span v-if="store.state.options.displayDeprecations && testcase.deprecations?.length > 0" class="text-purple-400 ml-2">⚠ {{ testcase.deprecations.length }} deprecation(s)</span>
                                <span v-if="store.state.options.displayNotices && testcase.notices?.length > 0" class="text-sky-400 ml-2">⚠ {{ testcase.notices.length }} notice(s)</span>
                            </div>
                        </div>
                        <div class="w-28 text-right flex-shrink-0 pr-3">
                            <span class="text-sm" :class="{'text-yellow-400': (testcase.duration / 1000000000) > 0.5 }">{{ formatNanoseconds(testcase.duration) }}</span>
                        </div>
                    </div>
                    <div v-if="store.state.expandedTestId === testcase.id"
                         class="bg-gray-900 p-4 space-y-3">
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
                </div>
                <!-- Passed tests with warnings/deprecations (if filters are enabled) -->
                <div v-for="testcase in group.testcases.filter(t => t.status === 'passed' && ((store.state.options.displayWarnings && t.warnings?.length > 0) || (store.state.options.displayDeprecations && t.deprecations?.length > 0) || (store.state.options.displayNotices && t.notices?.length > 0)))">
                    <div @click="toggleTestDetails(store, testcase)" class="flex items-center p-3 cursor-pointer hover:bg-gray-700/[.5]">
                        <div class="w-28 flex-shrink-0 pl-3">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">passed</span>
                        </div>
                        <div class="flex-grow">
                            <div class="text-sm text-white">{{ testcase.name }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                <span v-if="store.state.options.displayWarnings && testcase.warnings?.length > 0" class="text-amber-400">⚠ {{ testcase.warnings.length }} warning(s)</span>
                                <span v-if="store.state.options.displayDeprecations && testcase.deprecations?.length > 0" class="text-purple-400 ml-2">⚠ {{ testcase.deprecations.length }} deprecation(s)</span>
                                <span v-if="store.state.options.displayNotices && testcase.notices?.length > 0" class="text-sky-400 ml-2">⚠ {{ testcase.notices.length }} notice(s)</span>
                            </div>
                        </div>
                        <div class="w-28 text-right flex-shrink-0 pr-3">
                            <span class="text-sm" :class="{'text-yellow-400': (testcase.duration / 1000000000) > 0.5}">{{ formatNanoseconds(testcase.duration) }}</span>
                        </div>
                    </div>
                    <div v-if="store.state.expandedTestId === testcase.id" class="bg-gray-900 p-4 space-y-3">
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
                </div>
                <!-- Passed tests summary -->
                <div v-if="group.passed > 0" class="flex items-center p-3">
                    <div class="w-28 flex-shrink-0 pl-3">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">passed</span>
                    </div>
                    <div class="flex-grow">
                        <div class="text-sm text-gray-400">{{ group.passed }} test(s) passed</div>
                    </div>
                </div>
            </div>
        </div>
    `,
    methods: {
        toggleTestcaseGroup(className) {
            this.app.store.toggleTestcaseGroupExpansion(className);
        }
    }
};
