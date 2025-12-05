import { TestSuiteHeader } from './TestSuiteHeader.js';
import { TestList } from './TestList.js';

export const TestSuite = {
    props: ['suite', 'store', 'app', 'isTestRunning', 'isTestStopPending'],
    components: {
        TestSuiteHeader,
        TestList
    },
    template: `
        <div class="mb-4">
            <TestSuiteHeader
                :suite="suite"
                :store="store"
                :app="app"
                :is-test-running="isTestRunning"
                :is-test-stop-pending="isTestStopPending"
                @toggle-suite="$emit('toggle-suite', suite.id)"
            />
            <TestList
                v-show="store.state.expandedSuites.has(suite.id)"
                :suite="suite"
                :app="app"
                :is-test-running="isTestRunning"
            />
        </div>
    `
};
