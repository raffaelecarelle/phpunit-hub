<template>
    <div class="relative">
        <filter-panel-button @toggle="toggleFilterPanel"></filter-panel-button>
        <filter-panel-content v-show="store.state.showFilterPanel"></filter-panel-content>
    </div>
</template>

<script setup>
import { useStore } from '../../store.js';
import FilterPanelButton from './filter/FilterPanelButton.vue';
import FilterPanelContent from './filter/FilterPanelContent.vue';

const store = useStore();

function toggleFilterPanel() {
    store.toggleFilterPanel();
}
</script>
