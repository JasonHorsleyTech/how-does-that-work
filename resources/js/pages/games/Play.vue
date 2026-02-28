<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import AudioVisualizer from '@/components/AudioVisualizer.vue';
import JoinLinkPanel from '@/components/JoinLinkPanel.vue';
import PollIndicator from '@/components/PollIndicator.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { calculateAudioLevel, isSpeechDetected } from '@/utils/audioLevel';
import {
    createCountdownTimer,
    type CountdownTimer,
} from '@/utils/countdownTimer';
import { gradingJokes } from '@/utils/gradingJokes';

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
    {
        title: `Game — ${props.game.code}`,
        href: `/games/${props.game.code}/play`,
    },
];

const choiceForm = useForm({ topic_id: 0 });

function chooseTopic(topicId: number) {
    choiceForm.topic_id = topicId;
    choiceForm.post(
        `/games/${props.game.code}/turns/${props.currentTurn!.id}/choose-topic`,
    );
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

// Recording state for the active player (after mic check)
const recordingPhase = ref<
    'idle' | 'recording' | 'uploading' | 'done' | 'error'
>('idle');
const timeLeft = ref(120);
const timeLeftDisplay = computed(() => {
    const m = Math.floor(timeLeft.value / 60);
    const s = String(timeLeft.value % 60).padStart(2, '0');
    return `${m}:${s}`;
});

// Non-active player: tracking when active player is explaining
const localRecordingStarted = ref(false);
const nonActiveTimeLeft = ref(120);
const nonActiveTimeDisplay = computed(() => {
    const m = Math.floor(nonActiveTimeLeft.value / 60);
    const s = String(nonActiveTimeLeft.value % 60).padStart(2, '0');
    return `${m}:${s}`;
});

let countdownTimer: ReturnType<typeof setInterval> | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;
const lastPollAt = ref(0);
const pollError = ref(false);
let micStream: MediaStream | null = null;
let micAudioContext: AudioContext | null = null;
let micAnalyser: AnalyserNode | null = null;
let micCheckInterval: ReturnType<typeof setInterval> | null = null;
let activeRecordingTimer: CountdownTimer | null = null;
let nonActiveRecordingTimer: CountdownTimer | null = null;
let mediaRecorder: MediaRecorder | null = null;
const recordingStream = ref<MediaStream | null>(null);
let audioChunks: Blob[] = [];

// Grading pipeline stage (from polling)
const gradingStage = ref<'transcribing' | 'grading' | 'failed' | null>(null);

const gradingStatusText = computed(() => {
    const isMe = props.isActivePlayer;
    switch (gradingStage.value) {
        case 'transcribing':
            return isMe
                ? 'Transcribing your speech…'
                : `Transcribing ${revealPlayerName.value}'s speech…`;
        case 'grading':
            return isMe
                ? 'Grading your explanation…'
                : `Grading ${revealPlayerName.value}'s explanation…`;
        case 'failed':
            return isMe
                ? 'Something went wrong while processing your speech.'
                : `Something went wrong while processing ${revealPlayerName.value}'s speech.`;
        default:
            return isMe
                ? 'Processing your speech…'
                : `Processing ${revealPlayerName.value}'s speech…`;
    }
});

// Grading jokes rotation
const currentJokeIndex = ref(0);
const jokeVisible = ref(true);
let jokeInterval: ReturnType<typeof setInterval> | null = null;

function startJokeRotation() {
    if (jokeInterval) return;
    currentJokeIndex.value = Math.floor(Math.random() * gradingJokes.length);
    jokeVisible.value = true;
    jokeInterval = setInterval(() => {
        jokeVisible.value = false;
        setTimeout(() => {
            currentJokeIndex.value =
                (currentJokeIndex.value + 1) % gradingJokes.length;
            jokeVisible.value = true;
        }, 400);
    }, 4500);
}

function stopJokeRotation() {
    if (jokeInterval) {
        clearInterval(jokeInterval);
        jokeInterval = null;
    }
}

function getCsrfToken(): string {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));
    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : '';
}

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
        micStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: false,
        });
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

async function startMyTurn() {
    stopMicStream();

    // Notify server that recording has started (sets started_at for time tracking)
    await fetch(
        `/games/${props.game.code}/turns/${props.currentTurn!.id}/start-recording`,
        {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    // Attempt to start MediaRecorder
    try {
        recordingStream.value = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: false,
        });
        audioChunks = [];
        const mimeType = MediaRecorder.isTypeSupported('audio/webm')
            ? 'audio/webm'
            : '';
        mediaRecorder = new MediaRecorder(
            recordingStream.value,
            mimeType ? { mimeType } : undefined,
        );
        mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };
        mediaRecorder.start();
    } catch {
        // Mic unavailable — proceed without recording, audio will be empty
        audioChunks = [];
    }

    recordingPhase.value = 'recording';
    timeLeft.value = 120;

    activeRecordingTimer = createCountdownTimer(
        120,
        (s) => {
            timeLeft.value = s;
        },
        () => {
            void stopAndUploadRecording();
        },
    );
    activeRecordingTimer.start();
}

async function stopAndUploadRecording() {
    if (activeRecordingTimer) {
        activeRecordingTimer.stop();
        activeRecordingTimer = null;
    }

    // Stop MediaRecorder and collect final data
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        await new Promise<void>((resolve) => {
            mediaRecorder!.onstop = () => resolve();
            mediaRecorder!.stop();
        });
    }

    if (recordingStream.value) {
        recordingStream.value.getTracks().forEach((t) => t.stop());
        recordingStream.value = null;
    }

    recordingPhase.value = 'uploading';

    await uploadAudio();
}

async function uploadAudio() {
    const blob = new Blob(audioChunks, { type: 'audio/webm' });
    const formData = new FormData();
    formData.append('audio', blob, `${props.currentTurn!.id}.webm`);

    try {
        const response = await fetch(
            `/api/games/${props.game.code}/turns/${props.currentTurn!.id}/audio`,
            {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            },
        );

        if (response.ok) {
            recordingPhase.value = 'done';
            // Start polling so the active player also navigates to results when grading completes
            if (!pollInterval) {
                pollInterval = setInterval(pollState, 3000);
            }
        } else {
            recordingPhase.value = 'error';
        }
    } catch {
        recordingPhase.value = 'error';
    }
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
    if (nonActiveRecordingTimer) nonActiveRecordingTimer.stop();
    stopMicStream();
    stopJokeRotation();
    if (activeRecordingTimer) activeRecordingTimer.stop();
    if (mediaRecorder && mediaRecorder.state !== 'inactive')
        mediaRecorder.stop();
    if (recordingStream.value) recordingStream.value.getTracks().forEach((t) => t.stop());
});

// Start joke rotation when grading begins
watch(recordingPhase, (phase) => {
    if (phase === 'done') {
        startJokeRotation();
    } else {
        stopJokeRotation();
    }
});

// Handle in-page transition from choosing → recording (Inertia SPA update)
watch(
    () => props.currentTurn?.status,
    (newStatus) => {
        if (
            newStatus === 'recording' &&
            props.isActivePlayer &&
            micState.value === 'idle'
        ) {
            localTurnStatus.value = 'recording';
            initMicTest();
        }
    },
);

async function pollState() {
    try {
        const response = await fetch(`/games/${props.game.code}/play-state`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            pollError.value = false;
            lastPollAt.value = Date.now();
            const data = await response.json();

            // Navigate to results when grading is complete
            if (
                data.gameStatus === 'grading_complete' &&
                data.completedTurnId
            ) {
                window.location.href = `/games/${props.game.code}/results/${data.completedTurnId}`;
                return;
            }

            // Update grading pipeline stage
            gradingStage.value = data.gradingStage ?? null;

            if (
                data.turnStatus === 'recording' &&
                localTurnStatus.value === 'choosing'
            ) {
                // Topic was just chosen — show reveal + countdown in place
                localTurnStatus.value = 'recording';
                startGetReadyCountdown(
                    data.chosenTopicPlayerName ?? revealPlayerName.value,
                    data.chosenTopicText ?? '',
                );
            } else if (data.turnStatus !== localTurnStatus.value) {
                localTurnStatus.value = data.turnStatus;
            }

            // Track player name for grading display
            if (data.chosenTopicPlayerName) {
                revealPlayerName.value = data.chosenTopicPlayerName;
            }

            // Start joke rotation when entering grading state
            if (
                (data.turnStatus === 'grading') &&
                !jokeInterval
            ) {
                startJokeRotation();
            }

            // Stop joke rotation on failure
            if (data.turnStatus === 'grading_failed') {
                stopJokeRotation();
            }

            // Detect when active player starts recording
            if (
                data.turnStatus === 'recording' &&
                data.recordingStarted &&
                !localRecordingStarted.value
            ) {
                localRecordingStarted.value = true;
                revealPlayerName.value =
                    data.chosenTopicPlayerName ?? revealPlayerName.value;

                // Start local countdown synced from server
                const serverTime =
                    typeof data.timeRemaining === 'number'
                        ? data.timeRemaining
                        : 120;
                nonActiveTimeLeft.value = serverTime;

                if (nonActiveRecordingTimer) nonActiveRecordingTimer.stop();
                nonActiveRecordingTimer = createCountdownTimer(
                    serverTime,
                    (s) => {
                        nonActiveTimeLeft.value = s;
                    },
                    () => {},
                );
                nonActiveRecordingTimer.start();
            } else if (
                data.turnStatus === 'recording' &&
                data.recordingStarted &&
                typeof data.timeRemaining === 'number'
            ) {
                // Resync from server on each poll
                nonActiveTimeLeft.value = data.timeRemaining;
            }
        } else {
            pollError.value = true;
            lastPollAt.value = Date.now();
        }
    } catch {
        pollError.value = true;
        lastPollAt.value = Date.now();
    }
}
</script>

<template>
    <Head :title="`Game — ${game.code}`" />

    <!-- Host view: full app layout with sidebar (only when observing, not when it's the host's turn) -->
    <AppLayout v-if="player.is_host && !isActivePlayer" :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center justify-center p-6">
            <div class="w-full max-w-xl space-y-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">
                        Round {{ game.current_round }}
                    </p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">
                        Game in Progress
                    </h1>
                </div>

                <!-- No active turn -->
                <div
                    v-if="!currentTurn"
                    class="rounded-xl border p-8 text-center"
                >
                    <p class="text-lg font-semibold">
                        Waiting for turns to begin…
                    </p>
                </div>

                <!-- Topic reveal countdown (host observing) -->
                <div
                    v-else-if="showCountdown"
                    class="space-y-4 rounded-xl border p-8 text-center"
                >
                    <p class="text-lg font-semibold">
                        {{ revealPlayerName }} has chosen to explain:
                    </p>
                    <p class="text-2xl font-bold text-primary">
                        {{ revealTopicText }}
                    </p>
                    <p class="text-lg text-muted-foreground">
                        Get Ready… {{ countdownSeconds }}
                    </p>
                </div>

                <!-- Active player is choosing -->
                <div
                    v-else-if="localTurnStatus === 'choosing'"
                    class="rounded-xl border p-8 text-center"
                >
                    <p class="text-lg font-semibold">
                        {{ currentTurn.player_name }} is choosing their topic…
                    </p>
                    <p class="mt-2 text-muted-foreground">
                        Waiting for them to pick.
                    </p>
                </div>

                <!-- Active player is explaining (host view) -->
                <div
                    v-else-if="
                        localTurnStatus === 'recording' && localRecordingStarted
                    "
                    class="rounded-xl border p-8 text-center"
                >
                    <p class="text-lg font-semibold">
                        {{ revealPlayerName }} is explaining…
                    </p>
                    <p
                        class="mt-2 text-3xl font-bold text-primary tabular-nums"
                    >
                        {{ nonActiveTimeDisplay }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">remaining</p>
                </div>

                <!-- Active player is checking their microphone -->
                <div
                    v-else-if="localTurnStatus === 'recording'"
                    class="rounded-xl border p-8 text-center"
                >
                    <p class="text-lg font-semibold">
                        {{ revealPlayerName }} is checking their microphone…
                    </p>
                    <p class="mt-2 text-muted-foreground">Almost time!</p>
                </div>

                <!-- Grading in progress (host observing) -->
                <div
                    v-else-if="localTurnStatus === 'grading'"
                    class="rounded-xl border p-8 text-center"
                >
                    <div class="flex justify-center mb-4">
                        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary/30 border-t-primary"></div>
                    </div>
                    <p class="text-lg font-semibold">
                        {{ gradingStatusText }}
                    </p>
                    <p
                        class="mt-4 text-sm text-muted-foreground transition-opacity duration-300"
                        :class="jokeVisible ? 'opacity-100' : 'opacity-0'"
                    >
                        {{ gradingJokes[currentJokeIndex] }}
                    </p>
                </div>

                <!-- Grading failed (host observing) -->
                <div
                    v-else-if="localTurnStatus === 'grading_failed'"
                    class="rounded-xl border border-destructive/50 p-8 text-center"
                >
                    <p class="text-lg font-semibold text-destructive">
                        Grading Failed
                    </p>
                    <p class="mt-2 text-muted-foreground">
                        {{ gradingStatusText }}
                    </p>
                </div>

                <JoinLinkPanel :game-code="game.code" />
            </div>
        </div>
    </AppLayout>

    <!-- Guest / non-host view: simple page layout -->
    <div
        v-else
        class="flex min-h-screen flex-col items-center justify-center bg-background p-6"
    >
        <div class="w-full max-w-xl space-y-6">
            <div class="text-center">
                <p class="text-sm font-medium text-muted-foreground">
                    Round {{ game.current_round }}
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">
                    How Does That Work?
                </h1>
            </div>

            <!-- No active turn -->
            <div v-if="!currentTurn" class="rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">Waiting for turns to begin…</p>
            </div>

            <!-- It's this player's turn to choose -->
            <div
                v-else-if="localTurnStatus === 'choosing' && isActivePlayer"
                class="space-y-4"
            >
                <div class="rounded-xl border p-6 text-center">
                    <p class="text-lg font-semibold">It's your turn!</p>
                    <p class="mt-1 text-muted-foreground">
                        Choose the topic you'd like to explain.
                    </p>
                </div>

                <p
                    v-if="choiceForm.errors.topic_id"
                    class="text-sm text-destructive"
                >
                    {{ choiceForm.errors.topic_id }}
                </p>
                <p
                    v-if="choiceForm.errors.turn"
                    class="text-sm text-destructive"
                >
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
                        <p
                            class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Explain…
                        </p>
                        <p class="mt-2 text-base font-medium">
                            {{ topic.text }}
                        </p>
                    </button>
                </div>
            </div>

            <!-- Active player: recording phase (after mic check) -->
            <div
                v-else-if="
                    localTurnStatus === 'recording' &&
                    isActivePlayer &&
                    recordingPhase === 'recording'
                "
                class="space-y-6 rounded-xl border p-8"
            >
                <!-- Topic reminder -->
                <div class="text-center">
                    <p
                        class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Explaining:
                    </p>
                    <p class="mt-1 text-xl font-bold text-primary">
                        {{ currentTurn.chosen_topic_text }}
                    </p>
                </div>

                <!-- Audio visualizer -->
                <AudioVisualizer
                    v-if="recordingStream"
                    :stream="recordingStream"
                />

                <!-- Timer -->
                <div class="text-center">
                    <p
                        class="text-6xl font-bold tabular-nums"
                        :class="
                            timeLeft <= 30
                                ? 'text-destructive'
                                : 'text-foreground'
                        "
                    >
                        {{ timeLeftDisplay }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">remaining</p>
                </div>

                <!-- Recording indicator -->
                <div
                    class="flex items-center justify-center gap-2 text-sm font-medium text-red-600"
                >
                    <span
                        class="inline-block h-3 w-3 animate-pulse rounded-full bg-red-500"
                    ></span>
                    Recording…
                </div>

                <!-- Done button -->
                <button
                    type="button"
                    class="w-full rounded-xl border-2 border-border px-6 py-3 font-semibold transition-colors hover:border-primary hover:bg-primary/5"
                    @click="stopAndUploadRecording"
                >
                    I'm Done
                </button>
            </div>

            <!-- Active player: uploading -->
            <div
                v-else-if="
                    localTurnStatus === 'recording' &&
                    isActivePlayer &&
                    recordingPhase === 'uploading'
                "
                class="rounded-xl border p-8 text-center"
            >
                <p class="text-lg font-semibold">Uploading your explanation…</p>
                <p class="mt-2 text-muted-foreground">Hang tight!</p>
            </div>

            <!-- Active player: done / grading (before first poll updates localTurnStatus) -->
            <div
                v-else-if="
                    localTurnStatus === 'recording' &&
                    isActivePlayer &&
                    recordingPhase === 'done'
                "
                class="rounded-xl border p-8 text-center"
            >
                <div class="flex justify-center mb-4">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary/30 border-t-primary"></div>
                </div>
                <p class="text-lg font-semibold">Processing your speech…</p>
                <p
                    class="mt-4 text-sm text-muted-foreground transition-opacity duration-300"
                    :class="jokeVisible ? 'opacity-100' : 'opacity-0'"
                >
                    {{ gradingJokes[currentJokeIndex] }}
                </p>
            </div>

            <!-- Active player: upload error -->
            <div
                v-else-if="
                    localTurnStatus === 'recording' &&
                    isActivePlayer &&
                    recordingPhase === 'error'
                "
                class="rounded-xl border p-8 text-center"
            >
                <p class="text-lg font-semibold text-destructive">
                    Upload failed
                </p>
                <p class="mt-2 text-muted-foreground">
                    Something went wrong. Please try again.
                </p>
                <button
                    type="button"
                    class="mt-4 rounded-xl bg-primary px-6 py-3 font-semibold text-primary-foreground hover:opacity-90"
                    @click="uploadAudio"
                >
                    Retry Upload
                </button>
            </div>

            <!-- Active player mic test -->
            <div
                v-else-if="localTurnStatus === 'recording' && isActivePlayer"
                class="space-y-4 rounded-xl border p-8"
            >
                <div class="text-center">
                    <p class="text-lg font-semibold">You chose:</p>
                    <p class="mt-1 text-xl font-bold text-primary">
                        {{ currentTurn.chosen_topic_text }}
                    </p>
                </div>

                <!-- Mic confirmed -->
                <div
                    v-if="micState === 'confirmed'"
                    class="space-y-4 text-center"
                >
                    <p class="font-semibold text-green-600">
                        Mic confirmed! Start explaining when ready.
                    </p>
                    <button
                        type="button"
                        class="w-full rounded-xl bg-primary px-6 py-4 text-lg font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                        @click="startMyTurn"
                    >
                        Start My Turn
                    </button>
                </div>

                <!-- Mic error — allow proceeding anyway -->
                <div
                    v-else-if="micState === 'error'"
                    class="space-y-4 text-center"
                >
                    <p class="text-amber-600">
                        Microphone not available. You can still continue.
                    </p>
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
                    <p class="text-base font-medium">
                        Say <em>"testing, testing, one, two, three"</em> to
                        confirm your mic is working
                    </p>
                    <div
                        class="flex items-center justify-center gap-2 text-sm text-muted-foreground"
                    >
                        <span
                            class="inline-block h-2 w-2 animate-pulse rounded-full bg-red-500"
                        ></span>
                        Listening for your voice…
                    </div>
                </div>
            </div>

            <!-- Topic reveal countdown (non-active observing) -->
            <div
                v-else-if="showCountdown"
                class="space-y-4 rounded-xl border p-8 text-center"
            >
                <p class="text-lg font-semibold">
                    {{ revealPlayerName }} has chosen to explain:
                </p>
                <p class="text-2xl font-bold text-primary">
                    {{ revealTopicText }}
                </p>
                <p class="text-lg text-muted-foreground">
                    Get Ready… {{ countdownSeconds }}
                </p>
            </div>

            <!-- Another player is explaining (non-active observers) -->
            <div
                v-else-if="
                    localTurnStatus === 'recording' && localRecordingStarted
                "
                class="rounded-xl border p-8 text-center"
            >
                <p class="text-lg font-semibold">
                    {{ revealPlayerName }} is explaining…
                </p>
                <p class="mt-2 text-3xl font-bold text-primary tabular-nums">
                    {{ nonActiveTimeDisplay }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">remaining</p>
            </div>

            <!-- Another player is choosing -->
            <div
                v-else-if="localTurnStatus === 'choosing' && !isActivePlayer"
                class="rounded-xl border p-8 text-center"
            >
                <p class="text-lg font-semibold">
                    {{ currentTurn.player_name }} is choosing their topic…
                </p>
                <p class="mt-2 text-muted-foreground">Hang tight!</p>
            </div>

            <!-- Active player is checking their microphone (non-active observers) -->
            <div
                v-else-if="localTurnStatus === 'recording'"
                class="rounded-xl border p-8 text-center"
            >
                <p class="text-lg font-semibold">
                    {{ revealPlayerName }} is checking their microphone…
                </p>
                <p class="mt-2 text-muted-foreground">Almost time!</p>
            </div>

            <!-- Grading in progress -->
            <div
                v-else-if="localTurnStatus === 'grading'"
                class="rounded-xl border p-8 text-center"
            >
                <div class="flex justify-center mb-4">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary/30 border-t-primary"></div>
                </div>
                <p class="text-lg font-semibold">
                    {{ gradingStatusText }}
                </p>
                <p
                    v-if="isActivePlayer"
                    class="mt-4 text-sm text-muted-foreground transition-opacity duration-300"
                    :class="jokeVisible ? 'opacity-100' : 'opacity-0'"
                >
                    {{ gradingJokes[currentJokeIndex] }}
                </p>
                <p v-else class="mt-2 text-sm text-muted-foreground">
                    Hang tight while we process the results…
                </p>
            </div>

            <!-- Grading failed -->
            <div
                v-else-if="localTurnStatus === 'grading_failed'"
                class="rounded-xl border border-destructive/50 p-8 text-center"
            >
                <p class="text-lg font-semibold text-destructive">
                    Grading Failed
                </p>
                <p class="mt-2 text-muted-foreground">
                    {{ gradingStatusText }}
                </p>
            </div>
        </div>
    </div>

    <PollIndicator :last-poll-at="lastPollAt" :error="pollError" />
</template>
