<template>
    <input type="text"
           v-model="searchQuery"
           placeholder="Search tests..."
           class="w-full bg-gray-800 border border-gray-600 rounded py-2 px-3 mb-4 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
</template>

<script setup>
import { ref, watch } from 'vue';
import { useStore } from '../../store.js';

const store = useStore();
const emit = defineEmits(['update:filtered-suites']);
const searchQuery = ref('');

const filterSuites = (query) => {
    if (!query) {
        emit('update:filtered-suites', store.state.testSuites);
        return;
    }

    const lowerCaseQuery = query.toLowerCase();
    const filtered = store.state.testSuites.map(suite => {
        const methods = suite.methods.filter(m => m.name.toLowerCase().includes(lowerCaseQuery));
        if (suite.name.toLowerCase().includes(lowerCaseQuery)) {
            return { ...suite, methods: suite.methods };
        }
        if (methods.length > 0) {
            return { ...suite, methods };
        }
        return null;
    }).filter(Boolean);

    emit('update:filtered-suites', filtered);
};

watch(searchQuery, (newQuery) => {
    filterSuites(newQuery);
});

watch(() => store.state.testSuites, (newSuites) => {
    searchQuery.value = '';
    // Immediately emit the full list when testSuites change and search is cleared
    emit('update:filtered-suites', newSuites);
});
</script>
