<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    stream: MediaStream;
    barCount?: number;
}>();

const bars = ref<number[]>(new Array(props.barCount ?? 7).fill(0));

let audioContext: AudioContext | null = null;
let analyser: AnalyserNode | null = null;
let animationId: number | null = null;
let lastFrameTime = 0;
const FRAME_INTERVAL = 1000 / 30; // ~30fps

function animate(timestamp: number) {
    if (timestamp - lastFrameTime < FRAME_INTERVAL) {
        animationId = requestAnimationFrame(animate);
        return;
    }
    lastFrameTime = timestamp;

    if (!analyser) return;

    const dataArray = new Uint8Array(analyser.frequencyBinCount);
    analyser.getByteFrequencyData(dataArray);

    const count = bars.value.length;
    const binSize = Math.floor(dataArray.length / count);
    const newBars: number[] = [];

    for (let i = 0; i < count; i++) {
        let sum = 0;
        for (let j = 0; j < binSize; j++) {
            sum += dataArray[i * binSize + j];
        }
        // Normalize 0-255 to 0-1
        newBars.push(sum / binSize / 255);
    }

    bars.value = newBars;
    animationId = requestAnimationFrame(animate);
}

onMounted(() => {
    audioContext = new AudioContext();
    analyser = audioContext.createAnalyser();
    analyser.fftSize = 64;
    analyser.smoothingTimeConstant = 0.7;

    const source = audioContext.createMediaStreamSource(props.stream);
    source.connect(analyser);

    animationId = requestAnimationFrame(animate);
});

onUnmounted(() => {
    if (animationId !== null) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }
    if (analyser) {
        analyser.disconnect();
        analyser = null;
    }
    if (audioContext) {
        audioContext.close();
        audioContext = null;
    }
});
</script>

<template>
    <div class="flex items-end justify-center gap-1.5" style="height: 48px">
        <div
            v-for="(level, i) in bars"
            :key="i"
            class="w-2 rounded-full bg-primary transition-[height] duration-75"
            :style="{ height: `${Math.max(6, level * 48)}px` }"
        />
    </div>
</template>
