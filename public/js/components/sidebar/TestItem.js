export const TestItem = {
    props: ['method', 'app', 'isTestRunning'],
    template: `
        <li class="test-item cursor-pointer" @click.stop="app.runSingleTest(method.id)">
            <div class="test-item-left w-full">
                <div class="flex items-center">
                    <span v-if="method.runId && isTestRunning(method.runId)" class="status-icon spinner"></span>
                    <span v-else class="status-icon" :class="method.status ? 'status-'+method.status : 'status-neutral'"></span>
                </div>
                <div class="flex items-center space-x-2 justify-between w-full">
                    <span class="test-name">{{ method.name }}</span>
                </div>
            </div>
        </li>
    `
};
