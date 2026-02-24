<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { calculateAudioLevel, isSpeechDetected } from '@/utils/audioLevel';
import { dashboard } from '@/routes';

const props = defineProps<{
    game: {
        id: number;
        code: string;
        status: string;
        current_round: number;
    };
    player: {
        id: number;
        name: string;
        is_host: boolean;
    };
    currentTurn: {
        id: number;
        status: string;
        player_name: string;
        topic_choices: { id: number; text: string }[];
        chosen_topic_text: string | null;
    } | null;
    isActivePlayer: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: `Game — ${props.game.code}`, href: `/games/${props.game.code}/play` },
];

const choiceForm = useForm({ topic_id: 0 });

function chooseTopic(topicId: number) {
    choiceForm.topic_id = topicId;
    choiceForm.post(`/games/${props.game.code}/turns/${props.currentTurn!.id}/choose-topic`);
}

// Local reactive state driven by polling (for non-active players) or page load
const localTurnStatus = ref(props.currentTurn?.status ?? null);
const revealPlayerName = ref(props.currentTurn?.player_name ?? '');
const revealTopicText = ref(props.currentTurn?.chosen_topic_text ?? '');

// Countdown state: shown when a topic has just been chosen
const showCountdown = ref(false);
const countdownSeconds = ref(3);

// Mic test state for the active player
const micState = ref<'idle' | 'testing' | 'confirmed' | 'error'>('idle');
const micTurnStarted = ref(false);

let countdownTimer: ReturnType<typeof setInterval> | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;
let micStream: MediaStream | null = null;
let micAudioContext: AudioContext | null = null;
let micAnalyser: AnalyserNode | null = null;
let micCheckInterval: ReturnType<typeof setInterval> | null = null;

function startGetReadyCountdown(playerName: string, topicText: string) {
    revealPlayerName.value = playerName;
    revealTopicText.value = topicText;
    showCountdown.value = true;
    countdownSeconds.value = 3;

    countdownTimer = setInterval(() => {
        countdownSeconds.value--;
        if (countdownSeconds.value <= 0) {
            clearInterval(countdownTimer!);
            countdownTimer = null;
            showCountdown.value = false;
        }
    }, 1000);
}

async function initMicTest() {
    if (micState.value !== 'idle') return;

    micState.value = 'testing';

    try {
        micStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        micAudioContext = new AudioContext();
        micAnalyser = micAudioContext.createAnalyser();
        micAnalyser.fftSize = 256;

        const source = micAudioContext.createMediaStreamSource(micStream);
        source.connect(micAnalyser);

        const dataArray = new Uint8Array(micAnalyser.frequencyBinCount);
        let consecutiveSpeechMs = 0;

        micCheckInterval = setInterval(() => {
            if (!micAnalyser) return;
            micAnalyser.getByteTimeDomainData(dataArray);
            const level = calculateAudioLevel(dataArray);

            if (isSpeechDetected(level)) {
                consecutiveSpeechMs += 100;
                if (consecutiveSpeechMs >= 1000) {
                    micState.value = 'confirmed';
                    clearInterval(micCheckInterval!);
                    micCheckInterval = null;
                }
            } else {
                consecutiveSpeechMs = 0;
            }
        }, 100);
    } catch {
        micState.value = 'error';
    }
}

function stopMicStream() {
    if (micCheckInterval) {
        clearInterval(micCheckInterval);
        micCheckInterval = null;
    }
    if (micAnalyser) {
        micAnalyser.disconnect();
        micAnalyser = null;
    }
    if (micAudioContext) {
        micAudioContext.close();
        micAudioContext = null;
    }
    if (micStream) {
        micStream.getTracks().forEach((track) => track.stop());
        micStream = null;
    }
}

function startMyTurn() {
    stopMicStream();
    micTurnStarted.value = true;
    // US-011 will implement the actual recording flow
}

onMounted(() => {
    if (props.isActivePlayer) {
        if (localTurnStatus.value === 'recording') {
            initMicTest();
        }
        return;
    }

    // Non-active player: if page already shows recording state, start countdown
    if (localTurnStatus.value === 'recording' && revealTopicText.value) {
        startGetReadyCountdown(revealPlayerName.value, revealTopicText.value);
    }

    pollInterval = setInterval(pollState, 3000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    if (countdownTimer) clearInterval(countdownTimer);
    stopMicStream();
});

// Handle in-page transition from choosing → recording (Inertia SPA update)
watch(
    () => props.currentTurn?.status,
    (newStatus) => {
        if (newStatus === 'recording' && props.isActivePlayer && micState.value === 'idle') {
            localTurnStatus.value = 'recording';
            initMicTest();
        }
    },
);

async function pollState() {
    try {
        const response = await fetch(`/games/${props.game.code}/play-state`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.ok) {
            const data = await response.json();

            if (data.turnStatus === 'recording' && localTurnStatus.value === 'choosing') {
                // Topic was just chosen — show reveal + countdown in place
                localTurnStatus.value = 'recording';
                startGetReadyCountdown(
                    data.chosenTopicPlayerName ?? revealPlayerName.value,
                    data.chosenTopicText ?? '',
                );
            } else if (data.turnStatus !== localTurnStatus.value) {
                localTurnStatus.value = data.turnStatus;
            }
        }
    } catch {
        // Ignore polling errors silently
    }
}
</script>

<template>
    <Head :title="`Game — ${game.code}`" />

    <!-- Host view: full app layout with sidebar -->
    <AppLayout v-if="player.is_host" :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center justify-center p-6">
            <div class="w-full max-w-xl space-y-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">Round {{ game.current_round }}</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">Game in Progress</h1>
                </div>

                <!-- No active turn -->
                <div v-if="!currentTurn" class="rounded-xl border p-8 text-center">
                    <p class="text-lg font-semibold">Waiting for turns to begin…</p>
                </div>

                <!-- Topic reveal countdown (host observing) -->
                <div v-else-if="showCountdown" class="space-y-4 rounded-xl border p-8 text-center">
                    <p class="text-lg font-semibold">
                        {{ revealPlayerName }} has chosen to explain:
                    </p>
                    <p class="text-2xl font-bold text-primary">{{ revealTopicText }}</p>
                    <p class="text-lg text-muted-foreground">Get Ready… {{ countdownSeconds }}</p>
                </div>

                <!-- Active player is choosing -->
                <div v-else-if="localTurnStatus === 'choosing'" class="rounded-xl border p-8 text-center">
                    <p class="text-lg font-semibold">
                        {{ currentTurn.player_name }} is choosing their topic…
                    </p>
                    <p class="mt-2 text-muted-foreground">Waiting for them to pick.</p>
                </div>

                <!-- Active player is checking their microphone -->
                <div v-else-if="localTurnStatus === 'recording'" class="rounded-xl border p-8 text-center">
                    <p class="text-lg font-semibold">{{ revealPlayerName }} is checking their microphone…</p>
                    <p class="mt-2 text-muted-foreground">Almost time!</p>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Guest / non-host view: simple page layout -->
    <div v-else class="flex min-h-screen flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-xl space-y-6">
            <div class="text-center">
                <p class="text-sm font-medium text-muted-foreground">Round {{ game.current_round }}</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">How Does That Work?</h1>
            </div>

            <!-- No active turn -->
            <div v-if="!currentTurn" class="rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">Waiting for turns to begin…</p>
            </div>

            <!-- It's this player's turn to choose -->
            <div v-else-if="localTurnStatus === 'choosing' && isActivePlayer" class="space-y-4">
                <div class="rounded-xl border p-6 text-center">
                    <p class="text-lg font-semibold">It's your turn!</p>
                    <p class="mt-1 text-muted-foreground">Choose the topic you'd like to explain.</p>
                </div>

                <p v-if="choiceForm.errors.topic_id" class="text-sm text-destructive">
                    {{ choiceForm.errors.topic_id }}
                </p>
                <p v-if="choiceForm.errors.turn" class="text-sm text-destructive">
                    {{ choiceForm.errors.turn }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <button
                        v-for="topic in currentTurn.topic_choices"
                        :key="topic.id"
                        type="button"
                        :disabled="choiceForm.processing"
                        class="rounded-xl border-2 border-border p-6 text-left transition-colors hover:border-primary hover:bg-primary/5 disabled:opacity-50"
                        @click="chooseTopic(topic.id)"
                    >
                        <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Explain…</p>
                        <p class="mt-2 text-base font-medium">{{ topic.text }}</p>
                    </button>
                </div>
            </div>

            <!-- Active player mic test -->
            <div v-else-if="localTurnStatus === 'recording' && isActivePlayer" class="space-y-4 rounded-xl border p-8">
                <div class="text-center">
                    <p class="text-lg font-semibold">You chose:</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ currentTurn.chosen_topic_text }}</p>
                </div>

                <!-- Recording started (US-011 will implement actual recording) -->
                <div v-if="micTurnStarted" class="rounded-xl bg-muted p-6 text-center">
                    <p class="text-lg font-semibold">Recording starting…</p>
                    <p class="mt-1 text-muted-foreground">Get ready to explain!</p>
                </div>

                <!-- Mic confirmed -->
                <div v-else-if="micState === 'confirmed'" class="space-y-4 text-center">
                    <p class="font-semibold text-green-600">Mic confirmed! Start explaining when ready.</p>
                    <button
                        type="button"
                        class="w-full rounded-xl bg-primary px-6 py-4 text-lg font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                        @click="startMyTurn"
                    >
                        Start My Turn
                    </button>
                </div>

                <!-- Mic error — allow proceeding anyway -->
                <div v-else-if="micState === 'error'" class="space-y-4 text-center">
                    <p class="text-amber-600">Microphone not available. You can still continue.</p>
                    <button
                        type="button"
                        class="w-full rounded-xl bg-primary px-6 py-4 text-lg font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                        @click="startMyTurn"
                    >
                        Continue Without Mic Check
                    </button>
                </div>

                <!-- Mic testing in progress -->
                <div v-else class="space-y-3 text-center">
                    <p class="text-base font-medium">Say <em>"testing, testing, one, two, three"</em> to confirm your mic is working</p>
                    <div class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                        <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-red-500"></span>
                        Listening for your voice…
                    </div>
                </div>
            </div>

            <!-- Topic reveal countdown (non-active observing) -->
            <div v-else-if="showCountdown" class="space-y-4 rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">
                    {{ revealPlayerName }} has chosen to explain:
                </p>
                <p class="text-2xl font-bold text-primary">{{ revealTopicText }}</p>
                <p class="text-lg text-muted-foreground">Get Ready… {{ countdownSeconds }}</p>
            </div>

            <!-- Another player is choosing -->
            <div v-else-if="localTurnStatus === 'choosing' && !isActivePlayer" class="rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">
                    {{ currentTurn.player_name }} is choosing their topic…
                </p>
                <p class="mt-2 text-muted-foreground">Hang tight!</p>
            </div>

            <!-- Active player is checking their microphone (non-active observers) -->
            <div v-else-if="localTurnStatus === 'recording'" class="rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">{{ revealPlayerName }} is checking their microphone…</p>
                <p class="mt-2 text-muted-foreground">Almost time!</p>
            </div>
        </div>
    </div>
</template>
