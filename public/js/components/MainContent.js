import { TabNavigation } from './TabNavigation.js';
import { ResultsSummary } from './ResultsSummary.js';
import { IndividualTestResults } from './IndividualTestResults.js';
import { GroupedTestResults } from './GroupedTestResults.js';
import { CoverageReport } from './CoverageReport.js'; // Import the new component

export const MainContent = {
    components: {
        TabNavigation,
        ResultsSummary,
        IndividualTestResults,
        GroupedTestResults,
        CoverageReport // Add the new component
    },
    props: [
        'store',
        'app',
        'results',
        'statusCounts',
        'isAnyTestRunning',
        'formatNanoseconds',
        'toggleTestDetails',
        'individualResults',
        'groupedResults'
    ],
    template: `
    <main id="main-content" class="flex-1 p-4 flex flex-col">
        <tab-navigation :store="store"></tab-navigation>

        <div class="flex-grow overflow-y-auto">
            <div v-show="store.state.activeTab === 'results'">
                <results-summary :results="results" :status-counts="statusCounts" :format-nanoseconds="formatNanoseconds" :store="store"></results-summary>

                <!-- Empty State -->
                <div v-if="!results" class="text-gray-500 text-center pt-10">
                    Run tests to see the results.
                </div>

                <!-- Results Content -->
                <div v-else class="space-y-2 mt-4">
                    <!-- Spinner during execution -->
                    <div v-if="isAnyTestRunning" class="flex justify-center items-center pt-10">
                        <div class="spinner-big"></div>
                    </div>

                    <!-- Individual Test Results -->
                    <template v-if="!isAnyTestRunning && store.state.options.displayMode === 'individual'">
                        <individual-test-results
                            :individual-results="individualResults"
                            :store="store"
                            :app="app"
                            :format-nanoseconds="formatNanoseconds"
                            :toggle-test-details="toggleTestDetails"
                        ></individual-test-results>
                    </template>

                    <!-- Grouped Results -->
                    <template v-if="!isAnyTestRunning && store.state.options.displayMode === 'default'">
                        <grouped-test-results
                            :grouped-results="groupedResults"
                            :store="store"
                            :app="app"
                            :format-nanoseconds="formatNanoseconds"
                            :toggle-test-details="toggleTestDetails"
                        ></grouped-test-results>
                    </template>
                </div>
            </div>
            <div v-show="store.state.activeTab === 'coverage'">
                <coverage-report :store="store" :app="app"></coverage-report>
            </div>
        </div>
    </main>
    `,
    methods: {
        toggleTestcaseGroup(className) {
            this.app.store.toggleTestcaseGroupExpansion(className);
        },
    }
};
