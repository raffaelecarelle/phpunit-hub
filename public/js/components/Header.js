
export const Header = {
    props: ['store', 'app', 'isAnyTestRunning', 'hasFailedTests', 'isAnyStopPending', 'results'],
    template: `
    <header class="bg-gray-800 shadow-md p-4 flex justify-between items-center z-10 relative">
        <h1 class="text-2xl font-bold text-white">PHPUnit Hub</h1>
        <div class="flex items-center space-x-4">
            <div class="relative">
                <button
                    @click.stop="toggleFilterPanel"
                    class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out">
                    Filters / Settings
                </button>
                <div v-show="store.state.showFilterPanel"
                     @click.stop
                     class="filter-panel-dropdown bg-gray-800 p-4 rounded-lg shadow-lg border border-gray-700">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-white">Filters & Settings</h3>
                        <button @click="app.store.clearFilters()" class="text-sm text-blue-400 hover:text-blue-300">Clear Filters</button>
                    </div>

                    <div class="form-group">
                        <label for="group-filter" class="block text-sm font-medium text-gray-300 mb-1">Test Groups</label>
                        <select id="group-filter"
                                v-model="store.state.selectedGroups"
                                multiple
                                class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white h-24">
                            <option v-for="(group, key) in store.state.availableGroups" :value="key">{{ group }}</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filter-suite" class="block text-sm font-medium text-gray-300 mb-1">Test Suites</label>
                        <select id="filter-suite"
                                v-model="store.state.selectedSuites"
                                multiple
                                class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white h-24">
                            <option v-for="(suite, key) in store.state.availableSuites" :value="key">{{ suite }}</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Display Mode</label>
                        <div class="space-y-2">
                            <label class="flex items-center text-sm">
                                <input type="radio" v-model="store.state.options.displayMode" value="default" class="mr-2">
                                Grouped (default)
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="radio" v-model="store.state.options.displayMode" value="individual" class="mr-2">
                                Individual tests
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Output Options</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.displayWarnings" class="mr-2">
                                Show Warnings
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.displayDeprecations" class="mr-2">
                                Show Deprecations
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.displayNotices" class="mr-2">
                                Show Notices
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.displaySkipped" class="mr-2">
                                Show Skipped
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.displayIncomplete" class="mr-2">
                                Show Incomplete
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.displayRisky" class="mr-2">
                                Show Risky
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Stop On Options</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.stopOnDefect" class="mr-2">
                                Defect
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.stopOnError" class="mr-2">
                                Error
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.stopOnFailure" class="mr-2">
                                Failure
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.stopOnWarning" class="mr-2">
                                Warning
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.options.stopOnRisky" class="mr-2">
                                Risky
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Coverage</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center text-sm">
                                <input type="checkbox" v-model="store.state.coverage" class="mr-2">
                                Run with Code Coverage
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <button @click="app.store.clearAllResults()"
                    :disabled="isAnyTestRunning || !results"
                    class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:bg-gray-500"
                    title="Clear all test results">
                Clear Results
            </button>

            <button @click="app.runFailedTests()"
                    :disabled="isAnyTestRunning || !hasFailedTests"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:bg-gray-500">
                Run Failed
            </button>

            <button @click="app.togglePlayStop()"
                    :title="isAnyTestRunning ? 'Stop all test runs' : 'Run all tests'"
                    :disabled="isAnyStopPending"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:opacity-60">
                <span v-if="isAnyStopPending">Stopping...</span>
                <template v-else>
                        <span v-if="!isAnyTestRunning">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 inline-block">
                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                            </svg>
                            <span class="ml-2 align-middle">Run All</span>
                        </span>
                    <span v-else>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 inline-block">
                                <path d="M6 6h8v8H6z" />
                            </svg>
                            <span class="ml-2 align-middle">Stop All</span>
                        </span>
                </template>
            </button>
        </div>
    </header>
    `,
    methods: {
        toggleFilterPanel() {
            this.store.toggleFilterPanel();
        }
    }
};
