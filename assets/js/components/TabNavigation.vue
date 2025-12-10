<template>
  <div class="mb-4">
    <nav class="flex space-x-2">
      <a
        href="#"
        :class="['px-4 py-2 rounded-md text-sm font-medium', store.state.activeTab === 'results' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
        @click.prevent="setActiveTab('results')"
      >
        Results
      </a>
      <a
        href="#"
        :class="['px-4 py-2 rounded-md text-sm font-medium', store.state.activeTab === 'coverage' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']"
        @click.prevent="setActiveTab('coverage')"
      >
        Coverage
      </a>
    </nav>
  </div>
</template>

<script setup>
import { useStore } from '../store.js';

const store = useStore();

function setActiveTab(tab) {
    store.setActiveTab(tab);
}
</script>
