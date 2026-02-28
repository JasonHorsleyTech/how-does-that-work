<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    game: {
        id: number;
        code: string;
        status: string;
        current_round: number;
        max_rounds: number;
    };
    player: {
        id: number;
        name: string;
        is_host: boolean;
    };
    players: {
        id: number;
        name: string;
        score: number;
        is_host: boolean;
    }[];
    roundTurns: {
        id: number;
        player_name: string;
        topic_text: string | null;
        grade: string | null;
        score: number | null;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    {
        title: `Game — ${props.game.code}`,
        href: `/games/${props.game.code}/play`,
    },
];

const isFinalRound = props.game.current_round >= props.game.max_rounds;

const startNextRoundForm = useForm({});
const finalizeForm = useForm({});

function startNextRound() {
    startNextRoundForm.post(`/games/${props.game.code}/start-next-round`);
}

function viewFinalResults() {
    finalizeForm.post(`/games/${props.game.code}/finalize`);
}

function gradeClass(grade: string | null): string {
    switch (grade) {
        case 'A':
            return 'bg-green-100 text-green-800';
        case 'B':
            return 'bg-blue-100 text-blue-800';
        case 'C':
            return 'bg-yellow-100 text-yellow-800';
        case 'D':
            return 'bg-orange-100 text-orange-800';
        case 'F':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

let pollInterval: ReturnType<typeof setInterval> | null = null;

async function pollState() {
    try {
        const response = await fetch(`/games/${props.game.code}/play-state`);
        if (!response.ok) return;
        const data = await response.json();
        if (data.gameStatus === 'playing') {
            if (pollInterval !== null) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            window.location.href = `/games/${props.game.code}/play`;
        } else if (data.gameStatus === 'complete') {
            if (pollInterval !== null) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            window.location.href = `/games/${props.game.code}/complete`;
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
    <Head :title="`Round ${game.current_round} Complete`" />

    <!-- Host view: full app layout with sidebar -->
    <AppLayout v-if="player.is_host" :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center justify-center p-6">
            <div class="w-full max-w-2xl space-y-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">
                        Round {{ game.current_round }} of {{ game.max_rounds }}
                    </p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">
                        Round Complete!
                    </h1>
                </div>

                <!-- Round grade history -->
                <div class="rounded-xl border p-6">
                    <p
                        class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Round {{ game.current_round }} Results
                    </p>
                    <div class="space-y-3">
                        <div
                            v-for="turn in roundTurns"
                            :key="turn.id"
                            class="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3"
                        >
                            <div>
                                <p class="font-medium">
                                    {{ turn.player_name }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ turn.topic_text }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span
                                    v-if="turn.grade"
                                    class="rounded-md px-2 py-1 text-sm font-bold"
                                    :class="gradeClass(turn.grade)"
                                >
                                    {{ turn.grade }}
                                </span>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                                <span class="font-bold"
                                    >{{ turn.score ?? 0 }} pts</span
                                >
                            </div>
                        </div>
                        <p
                            v-if="roundTurns.length === 0"
                            class="text-center text-muted-foreground"
                        >
                            No completed turns this round.
                        </p>
                    </div>
                </div>

                <!-- Full scoreboard -->
                <div class="rounded-xl border p-6">
                    <p
                        class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Scoreboard
                    </p>
                    <ol class="space-y-2">
                        <li
                            v-for="(p, index) in players"
                            :key="p.id"
                            class="flex items-center justify-between rounded-lg px-3 py-2"
                            :class="
                                p.id === player.id
                                    ? 'bg-primary/10'
                                    : 'bg-muted/30'
                            "
                        >
                            <span class="font-medium">
                                <span class="mr-2 text-muted-foreground"
                                    >{{ index + 1 }}.</span
                                >
                                {{ p.name }}
                                <span
                                    v-if="p.is_host"
                                    class="ml-1 text-xs text-muted-foreground"
                                    >(host)</span
                                >
                            </span>
                            <span class="font-bold">{{ p.score }} pts</span>
                        </li>
                    </ol>
                </div>

                <!-- Host controls -->
                <div class="rounded-xl border p-4 text-center">
                    <button
                        v-if="!isFinalRound"
                        type="button"
                        :disabled="startNextRoundForm.processing"
                        class="rounded-xl bg-primary px-8 py-3 text-base font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                        @click="startNextRound"
                    >
                        Start Round {{ game.current_round + 1 }}
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="finalizeForm.processing"
                        class="rounded-xl bg-primary px-8 py-3 text-base font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                        @click="viewFinalResults"
                    >
                        View Final Results
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Guest / non-host view -->
    <div
        v-else
        class="flex min-h-screen flex-col items-center justify-center bg-background p-6"
    >
        <div class="w-full max-w-2xl space-y-6">
            <div class="text-center">
                <p class="text-sm font-medium text-muted-foreground">
                    Round {{ game.current_round }} of {{ game.max_rounds }}
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">
                    Round Complete!
                </h1>
            </div>

            <!-- Round grade history -->
            <div class="rounded-xl border p-6">
                <p
                    class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Round {{ game.current_round }} Results
                </p>
                <div class="space-y-3">
                    <div
                        v-for="turn in roundTurns"
                        :key="turn.id"
                        class="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3"
                    >
                        <div>
                            <p class="font-medium">{{ turn.player_name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ turn.topic_text }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                v-if="turn.grade"
                                class="rounded-md px-2 py-1 text-sm font-bold"
                                :class="gradeClass(turn.grade)"
                            >
                                {{ turn.grade }}
                            </span>
                            <span v-else class="text-sm text-muted-foreground"
                                >—</span
                            >
                            <span class="font-bold"
                                >{{ turn.score ?? 0 }} pts</span
                            >
                        </div>
                    </div>
                    <p
                        v-if="roundTurns.length === 0"
                        class="text-center text-muted-foreground"
                    >
                        No completed turns this round.
                    </p>
                </div>
            </div>

            <!-- Full scoreboard -->
            <div class="rounded-xl border p-6">
                <p
                    class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Scoreboard
                </p>
                <ol class="space-y-2">
                    <li
                        v-for="(p, index) in players"
                        :key="p.id"
                        class="flex items-center justify-between rounded-lg px-3 py-2"
                        :class="
                            p.id === player.id ? 'bg-primary/10' : 'bg-muted/30'
                        "
                    >
                        <span class="font-medium">
                            <span class="mr-2 text-muted-foreground"
                                >{{ index + 1 }}.</span
                            >
                            {{ p.name }}
                            <span
                                v-if="p.is_host"
                                class="ml-1 text-xs text-muted-foreground"
                                >(host)</span
                            >
                        </span>
                        <span class="font-bold">{{ p.score }} pts</span>
                    </li>
                </ol>
            </div>

            <!-- Non-host waiting message -->
            <div class="rounded-xl border p-4 text-center">
                <p class="text-muted-foreground">
                    Waiting for host to continue…
                </p>
            </div>
        </div>
    </div>
</template>
