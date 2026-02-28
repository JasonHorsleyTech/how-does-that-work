<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    lastPollAt: number;
    error: boolean;
}>();

const pulsing = ref(false);

watch(
    () => props.lastPollAt,
    () => {
        if (props.error) return;
        pulsing.value = true;
        setTimeout(() => {
            pulsing.value = false;
        }, 600);
    },
);
</script>

<template>
    <div
        class="fixed right-3 bottom-3 z-50 h-2.5 w-2.5 rounded-full opacity-50 transition-all duration-300"
        :class="[
            error ? 'bg-orange-500' : 'bg-green-500',
            pulsing ? 'scale-150 opacity-80' : '',
        ]"
        aria-hidden="true"
    />
</template>
