<template>
    <ul class="ml-2 mt-2 space-y-1">
        <TestItem
            v-for="method in suite.methods"
            :key="method.id"
            :method="method"
            @runSingleTest="runSingleTest"
        />
    </ul>
</template>

<script setup>
import TestItem from './TestItem.vue';

defineProps(['suite']);
const emit = defineEmits(['runSingleTest']);

function runSingleTest(testId) {
    emit('runSingleTest', testId);
}
</script>
