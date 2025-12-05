<template>
    <div @click.stop
         class="filter-panel-dropdown bg-gray-800 p-4 rounded-lg shadow-lg border border-gray-700">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-white">Filters & Settings</h3>
            <button @click="clearFilters" class="text-sm text-blue-400 hover:text-blue-300">Clear Filters</button>
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
</template>

<script setup>
import { useStore } from '../../../store.js';

const store = useStore();

function clearFilters() {
    store.clearFilters();
}
</script>
