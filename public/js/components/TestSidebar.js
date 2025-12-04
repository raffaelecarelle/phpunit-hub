export const TestSidebar = {
    props: ['store', 'app', 'isTestRunning', 'isTestStopPending'],
    template: `
    <aside id="test-sidebar" class="bg-gray-800 p-4 overflow-y-auto border-r border-gray-700 w-80">
        <input type="text"
               v-model="store.state.searchQuery"
               placeholder="Search tests..."
               class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 mb-4 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">

        <div v-if="store.state.isLoading" class="flex justify-center h-full mt-4">
            <div class="spinner-big"></div>
        </div>

        <div v-if="!store.state.isLoading && (store.state.testSuites.length === 0)" class="text-gray-500">
            No tests found.
        </div>

        <div v-if="!store.state.isLoading" v-for="suite in store.state.testSuites" :key="suite.id" class="mb-4">
            <div class="suite-header text-md text-gray-200">
                <div @click="toggleSuite(suite.id)" class="flex items-center flex-grow cursor-pointer">
                    <svg class="suite-arrow w-4 h-4 text-gray-400"
                         :class="{ 'rotated': store.state.expandedSuites.has(suite.id) }"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24"
                         xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span class="font-bold">{{ suite.name }}</span>
                </div>
                <div class="flex items-center">
                    <div v-if="suite.runId && isTestRunning(suite.runId)" class="spinner !w-4 !h-4"></div>
                    <span v-if="suite.runId && isTestRunning(suite.runId)"
                          @click.stop="app.stopSingleTest(suite.runId)"
                          class="cursor-pointer text-red-500 hover:text-red-400 ml-2"
                          title="Stop this suite">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                <path d="M6 6h8v8H6z" />
                            </svg>
                        </span>
                    <span v-else
                          @click.stop="app.runSuiteTests(suite.id)"
                          :class="{'cursor-pointer text-green-500 hover:text-green-400': !isTestStopPending(suite.runId), 'text-gray-500': isTestStopPending(suite.runId)}"
                          :title="isTestStopPending(suite.runId) ? 'Stopping...' : 'Run all tests in this suite'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                            </svg>
                        </span>
                </div>
            </div>

            <ul v-show="store.state.expandedSuites.has(suite.id)" class="ml-2 mt-2 space-y-1">
                <li v-for="method in suite.methods" :key="method.id" class="test-item cursor-pointer" @click.stop="app.runSingleTest(method.id)">
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
            </ul>
        </div>
    </aside>
    `,
    methods: {
        toggleSuite(suiteId) {
            this.store.toggleSuiteExpansion(suiteId);
        }
    }
};
