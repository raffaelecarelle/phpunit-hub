import { TestItem } from './TestItem.js';

export const TestList = {
    props: ['suite', 'app', 'isTestRunning'],
    components: {
        TestItem
    },
    template: `
        <ul class="ml-2 mt-2 space-y-1">
            <TestItem
                v-for="method in suite.methods"
                :key="method.id"
                :method="method"
                :app="app"
                :is-test-running="isTestRunning"
            />
        </ul>
    `
};
