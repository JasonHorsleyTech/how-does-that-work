<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
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
    turn: {
        id: number;
        player_name: string;
        topic_text: string | null;
        status: string;
        grade: string | null;
        score: number | null;
        feedback: string | null;
        actual_explanation: string | null;
    };
    players: {
        id: number;
        name: string;
        score: number;
        is_host: boolean;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: `Game — ${props.game.code}`, href: `/games/${props.game.code}/play` },
];

const gradingFailed = computed(() => props.turn.status === 'grading_failed' || props.turn.grade === null);

const gradeBadgeClass = computed(() => {
    switch (props.turn.grade) {
        case 'A':
            return 'bg-green-100 text-green-800 border-green-300';
        case 'B':
            return 'bg-blue-100 text-blue-800 border-blue-300';
        case 'C':
            return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        case 'D':
            return 'bg-orange-100 text-orange-800 border-orange-300';
        case 'F':
            return 'bg-red-100 text-red-800 border-red-300';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
});

const advanceForm = useForm({});

function advanceToNext() {
    advanceForm.post(`/games/${props.game.code}/advance`);
}

let pollInterval: ReturnType<typeof setInterval> | null = null;

async function pollState() {
    try {
        const response = await fetch(`/games/${props.game.code}/play-state`);
        if (!response.ok) return;
        const data = await response.json();
        if (data.gameStatus === 'playing' || data.gameStatus === 'round_complete') {
            if (pollInterval !== null) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            window.location.href = `/games/${props.game.code}/play`;
        }
    } catch {
        // ignore transient errors
    }
}

onMounted(() => {
    if (!props.player.is_host) {
        pollInterval = setInterval(pollState, 3000);
    }
});

onUnmounted(() => {
    if (pollInterval !== null) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <Head :title="`Results — ${turn.player_name}`" />

    <!-- Host view: full app layout with sidebar -->
    <AppLayout v-if="player.is_host" :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center justify-center p-6">
            <div class="w-full max-w-2xl space-y-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">Round {{ game.current_round }}</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">Turn Results</h1>
                </div>

                <!-- Grading failed state -->
                <div v-if="gradingFailed" class="rounded-xl border p-8 text-center">
                    <p class="text-xl font-bold">{{ turn.player_name }}</p>
                    <p class="mt-1 text-muted-foreground">{{ turn.topic_text }}</p>
                    <p class="mt-4 text-amber-600">Grading failed — no score awarded</p>
                </div>

                <!-- Graded results -->
                <div v-else class="space-y-4">
                    <!-- Player & topic header -->
                    <div class="rounded-xl border p-6 text-center">
                        <p class="text-xl font-bold">{{ turn.player_name }}</p>
                        <p class="mt-1 text-muted-foreground">explained: <span class="font-medium text-foreground">{{ turn.topic_text }}</span></p>
                    </div>

                    <!-- Grade & score -->
                    <div class="flex items-center justify-center gap-6 rounded-xl border p-6">
                        <div class="text-center">
                            <p class="text-sm font-medium uppercase tracking-wide text-muted-foreground">Grade</p>
                            <span
                                class="mt-1 inline-block rounded-lg border-2 px-6 py-2 text-4xl font-bold"
                                :class="gradeBadgeClass"
                            >
                                {{ turn.grade }}
                            </span>
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-medium uppercase tracking-wide text-muted-foreground">Score</p>
                            <p class="mt-1 text-4xl font-bold">{{ turn.score }}<span class="text-xl text-muted-foreground">/100</span></p>
                        </div>
                    </div>

                    <!-- Feedback -->
                    <div class="rounded-xl border p-6">
                        <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Feedback</p>
                        <p class="mt-2 leading-relaxed">{{ turn.feedback }}</p>
                    </div>

                    <!-- Actual explanation -->
                    <div class="rounded-xl border p-6">
                        <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">The Real Answer</p>
                        <p class="mt-2 leading-relaxed">{{ turn.actual_explanation }}</p>
                    </div>
                </div>

                <!-- Scoreboard -->
                <div class="rounded-xl border p-6">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Scoreboard</p>
                    <ol class="space-y-2">
                        <li
                            v-for="(p, index) in players"
                            :key="p.id"
                            class="flex items-center justify-between rounded-lg px-3 py-2"
                            :class="p.id === player.id ? 'bg-primary/10' : 'bg-muted/30'"
                        >
                            <span class="font-medium">
                                <span class="mr-2 text-muted-foreground">{{ index + 1 }}.</span>
                                {{ p.name }}
                                <span v-if="p.is_host" class="ml-1 text-xs text-muted-foreground">(host)</span>
                            </span>
                            <span class="font-bold">{{ p.score }} pts</span>
                        </li>
                    </ol>
                </div>

                <!-- Host controls -->
                <div class="rounded-xl border p-4 text-center">
                    <button
                        type="button"
                        :disabled="advanceForm.processing"
                        class="rounded-xl bg-primary px-8 py-3 text-base font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                        @click="advanceToNext"
                    >
                        Next Player →
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Guest / non-host view -->
    <div v-else class="flex min-h-screen flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-2xl space-y-6">
            <div class="text-center">
                <p class="text-sm font-medium text-muted-foreground">Round {{ game.current_round }}</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">Turn Results</h1>
            </div>

            <!-- Grading failed state -->
            <div v-if="gradingFailed" class="rounded-xl border p-8 text-center">
                <p class="text-xl font-bold">{{ turn.player_name }}</p>
                <p class="mt-1 text-muted-foreground">{{ turn.topic_text }}</p>
                <p class="mt-4 text-amber-600">Grading failed — no score awarded</p>
            </div>

            <!-- Graded results -->
            <div v-else class="space-y-4">
                <!-- Player & topic header -->
                <div class="rounded-xl border p-6 text-center">
                    <p class="text-xl font-bold">{{ turn.player_name }}</p>
                    <p class="mt-1 text-muted-foreground">explained: <span class="font-medium text-foreground">{{ turn.topic_text }}</span></p>
                </div>

                <!-- Grade & score -->
                <div class="flex items-center justify-center gap-6 rounded-xl border p-6">
                    <div class="text-center">
                        <p class="text-sm font-medium uppercase tracking-wide text-muted-foreground">Grade</p>
                        <span
                            class="mt-1 inline-block rounded-lg border-2 px-6 py-2 text-4xl font-bold"
                            :class="gradeBadgeClass"
                        >
                            {{ turn.grade }}
                        </span>
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-medium uppercase tracking-wide text-muted-foreground">Score</p>
                        <p class="mt-1 text-4xl font-bold">{{ turn.score }}<span class="text-xl text-muted-foreground">/100</span></p>
                    </div>
                </div>

                <!-- Feedback -->
                <div class="rounded-xl border p-6">
                    <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Feedback</p>
                    <p class="mt-2 leading-relaxed">{{ turn.feedback }}</p>
                </div>

                <!-- Actual explanation -->
                <div class="rounded-xl border p-6">
                    <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">The Real Answer</p>
                    <p class="mt-2 leading-relaxed">{{ turn.actual_explanation }}</p>
                </div>
            </div>

            <!-- Scoreboard -->
            <div class="rounded-xl border p-6">
                <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Scoreboard</p>
                <ol class="space-y-2">
                    <li
                        v-for="(p, index) in players"
                        :key="p.id"
                        class="flex items-center justify-between rounded-lg px-3 py-2"
                        :class="p.id === player.id ? 'bg-primary/10' : 'bg-muted/30'"
                    >
                        <span class="font-medium">
                            <span class="mr-2 text-muted-foreground">{{ index + 1 }}.</span>
                            {{ p.name }}
                            <span v-if="p.is_host" class="ml-1 text-xs text-muted-foreground">(host)</span>
                        </span>
                        <span class="font-bold">{{ p.score }} pts</span>
                    </li>
                </ol>
            </div>

            <!-- Non-host waiting message -->
            <div class="rounded-xl border p-4 text-center">
                <p class="text-muted-foreground">Waiting for host to continue…</p>
            </div>
        </div>
    </div>
</template>
