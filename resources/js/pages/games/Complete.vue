<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { formatScore } from '@/utils/formatScore';

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
    allTurns: {
        id: number;
        player_id: number;
        player_name: string;
        topic_text: string | null;
        grade: string | null;
        score: number | null;
        round_number: number;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    {
        title: `Game — ${props.game.code}`,
        href: `/games/${props.game.code}/complete`,
    },
];

const winner = props.players[0] ?? null;

const playAgainForm = useForm({});

function playAgain() {
    playAgainForm.post(`/games/${props.game.code}/play-again`);
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

function turnsForPlayer(playerId: number) {
    return props.allTurns.filter((t) => t.player_id === playerId);
}
</script>

<template>
    <Head title="Game Over" />

    <!-- Host view: full app layout with sidebar -->
    <AppLayout v-if="player.is_host" :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center justify-center p-6">
            <div class="w-full max-w-2xl space-y-6">
                <!-- Winner banner -->
                <div
                    v-if="winner"
                    class="rounded-xl border-2 border-yellow-400 bg-yellow-50 p-6 text-center dark:bg-yellow-950/20"
                >
                    <p class="text-4xl">🏆</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight">
                        {{ winner.name }} wins with {{ formatScore(winner.score) }} points!
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">Game Over</p>
                </div>
                <div v-else class="rounded-xl border p-6 text-center">
                    <h1 class="text-3xl font-bold tracking-tight">
                        Game Over!
                    </h1>
                </div>

                <!-- Final scores -->
                <div class="rounded-xl border p-6">
                    <p
                        class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Final Scores
                    </p>
                    <ol class="space-y-2">
                        <li
                            v-for="(p, index) in players"
                            :key="p.id"
                            class="flex items-center justify-between rounded-lg px-3 py-2"
                            :class="[
                                index === 0
                                    ? 'bg-yellow-50 ring-1 ring-yellow-300 dark:bg-yellow-950/20'
                                    : 'bg-muted/30',
                                p.id === player.id ? 'font-semibold' : '',
                            ]"
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
                            <span class="font-bold">{{ formatScore(p.score) }} pts</span>
                        </li>
                    </ol>
                </div>

                <!-- Per-player turn history -->
                <div class="rounded-xl border p-6">
                    <p
                        class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Turn History
                    </p>
                    <div class="space-y-5">
                        <div v-for="p in players" :key="p.id">
                            <p class="mb-2 font-semibold">{{ p.name }}</p>
                            <div class="space-y-2">
                                <div
                                    v-for="turn in turnsForPlayer(p.id)"
                                    :key="turn.id"
                                    class="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3"
                                >
                                    <div class="min-w-0 flex-1 pr-3">
                                        <p class="truncate text-sm">
                                            {{ turn.topic_text }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-3"
                                    >
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
                                            >{{ formatScore(turn.score) }} pts</span
                                        >
                                    </div>
                                </div>
                                <p
                                    v-if="turnsForPlayer(p.id).length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    No completed turns.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Play Again -->
                <div class="rounded-xl border p-4 text-center">
                    <button
                        type="button"
                        :disabled="playAgainForm.processing"
                        class="rounded-xl bg-primary px-8 py-3 text-base font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                        @click="playAgain"
                    >
                        Play Again
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
            <!-- Winner banner -->
            <div
                v-if="winner"
                class="rounded-xl border-2 border-yellow-400 bg-yellow-50 p-6 text-center dark:bg-yellow-950/20"
            >
                <p class="text-4xl">🏆</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">
                    {{ winner.name }} wins with {{ formatScore(winner.score) }} points!
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">Game Over</p>
            </div>
            <div v-else class="rounded-xl border p-6 text-center">
                <h1 class="text-3xl font-bold tracking-tight">Game Over!</h1>
            </div>

            <!-- Final scores -->
            <div class="rounded-xl border p-6">
                <p
                    class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Final Scores
                </p>
                <ol class="space-y-2">
                    <li
                        v-for="(p, index) in players"
                        :key="p.id"
                        class="flex items-center justify-between rounded-lg px-3 py-2"
                        :class="[
                            index === 0
                                ? 'bg-yellow-50 ring-1 ring-yellow-300 dark:bg-yellow-950/20'
                                : 'bg-muted/30',
                            p.id === player.id ? 'font-semibold' : '',
                        ]"
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
                        <span class="font-bold">{{ formatScore(p.score) }} pts</span>
                    </li>
                </ol>
            </div>

            <!-- Per-player turn history -->
            <div class="rounded-xl border p-6">
                <p
                    class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Turn History
                </p>
                <div class="space-y-5">
                    <div v-for="p in players" :key="p.id">
                        <p class="mb-2 font-semibold">{{ p.name }}</p>
                        <div class="space-y-2">
                            <div
                                v-for="turn in turnsForPlayer(p.id)"
                                :key="turn.id"
                                class="flex items-center justify-between rounded-lg bg-muted/30 px-4 py-3"
                            >
                                <div class="min-w-0 flex-1 pr-3">
                                    <p class="truncate text-sm">
                                        {{ turn.topic_text }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
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
                                v-if="turnsForPlayer(p.id).length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No completed turns.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border p-4 text-center">
                <p class="text-muted-foreground">Thanks for playing!</p>
            </div>
        </div>
    </div>
</template>
