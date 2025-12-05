
import { TestSearchBar } from './sidebar/TestSearchBar.js';
import { TestSuite } from './sidebar/TestSuite.js';

export const TestSidebar = {
    props: ['store', 'app', 'isTestRunning', 'isTestStopPending'],
    components: {
        TestSearchBar,
        TestSuite
    },
    data() {
        return {
            suitesToDisplay: this.store.state.testSuites
        };
    },
    template: `
    <aside id="test-sidebar" class="bg-gray-800 p-4 overflow-y-auto border-r border-gray-700 w-80">
        <TestSearchBar :store="store" @update:filtered-suites="suitesToDisplay = $event" />

        <div v-if="store.state.isLoading" class="flex justify-center h-full mt-4">
            <div class="spinner-big"></div>
        </div>

        <div v-if="!store.state.isLoading && (suitesToDisplay.length === 0)" class="text-gray-500">
            No tests found.
        </div>

        <TestSuite
            v-if="!store.state.isLoading"
            v-for="suite in suitesToDisplay"
            :key="suite.id"
            :suite="suite"
            :store="store"
            :app="app"
            :is-test-running="isTestRunning"
            :is-test-stop-pending="isTestStopPending"
            @toggle-suite="toggleSuite"
        />
    </aside>
    `,
    methods: {
        toggleSuite(suiteId) {
            this.store.toggleSuiteExpansion(suiteId);
        }
    }
};
