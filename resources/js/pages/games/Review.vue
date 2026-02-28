<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { formatScore } from '@/utils/formatScore';

interface TurnData {
    id: number;
    player_id: number;
    player_name: string;
    topic_text: string | null;
    transcript: string | null;
    grade: string | null;
    score: number | null;
    feedback: string | null;
    actual_explanation: string | null;
    round_number: number;
}

const props = defineProps<{
    game: {
        id: number;
        code: string;
        max_rounds: number;
        created_at: string;
    };
    players: {
        id: number;
        name: string;
        score: number;
        is_host: boolean;
    }[];
    allTurns: TurnData[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    {
        title: `Game ${props.game.code} — Review`,
        href: `/games/${props.game.code}/review`,
    },
];

const winner = props.players[0] ?? null;

const rounds = computed(() => {
    const roundNumbers = [
        ...new Set(props.allTurns.map((t) => t.round_number)),
    ].sort((a, b) => a - b);

    return roundNumbers.map((num) => ({
        number: num,
        turns: props.allTurns.filter((t) => t.round_number === num),
    }));
});

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function gradeClass(grade: string | null): string {
    switch (grade) {
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
}
</script>

<template>
    <Head :title="`Game ${game.code} — Review`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center p-4 md:p-6">
            <div class="w-full max-w-3xl space-y-6">
                <!-- Game header -->
                <div class="text-center">
                    <p class="text-sm text-muted-foreground">
                        {{ formatDate(game.created_at) }}
                    </p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">
                        Game {{ game.code }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ players.length }} players &middot;
                        {{ game.max_rounds }}
                        {{ game.max_rounds === 1 ? 'round' : 'rounds' }}
                    </p>
                </div>

                <!-- Winner banner -->
                <div
                    v-if="winner"
                    class="rounded-xl border-2 border-yellow-400 bg-yellow-50 p-5 text-center dark:bg-yellow-950/20"
                >
                    <h2 class="text-xl font-bold">
                        {{ winner.name }} won with
                        {{ formatScore(winner.score) }} points
                    </h2>
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
                            :class="
                                index === 0
                                    ? 'bg-yellow-50 ring-1 ring-yellow-300 dark:bg-yellow-950/20'
                                    : 'bg-muted/30'
                            "
                        >
                            <span class="font-medium">
                                <span class="mr-2 text-muted-foreground">{{
                                    index + 1
                                }}.</span>
                                {{ p.name }}
                                <span
                                    v-if="p.is_host"
                                    class="ml-1 text-xs text-muted-foreground"
                                    >(host)</span
                                >
                            </span>
                            <span class="font-bold"
                                >{{ formatScore(p.score) }} pts</span
                            >
                        </li>
                    </ol>
                </div>

                <!-- Turns by round -->
                <div v-for="round in rounds" :key="round.number">
                    <h2
                        class="mb-3 text-lg font-semibold tracking-tight"
                    >
                        Round {{ round.number }}
                    </h2>
                    <div class="space-y-4">
                        <div
                            v-for="turn in round.turns"
                            :key="turn.id"
                            class="rounded-xl border p-5"
                        >
                            <!-- Turn header -->
                            <div
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <p class="font-semibold">
                                        {{ turn.player_name }}
                                    </p>
                                    <p
                                        class="text-sm text-muted-foreground"
                                    >
                                        Topic:
                                        <span
                                            class="font-medium text-foreground"
                                            >{{
                                                turn.topic_text ?? '—'
                                            }}</span
                                        >
                                    </p>
                                </div>
                                <div
                                    class="flex shrink-0 items-center gap-3"
                                >
                                    <span
                                        v-if="turn.grade"
                                        class="rounded-lg border-2 px-3 py-1 text-lg font-bold"
                                        :class="gradeClass(turn.grade)"
                                    >
                                        {{ turn.grade }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >—</span
                                    >
                                    <span class="text-lg font-bold">
                                        {{ formatScore(turn.score) }}
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >/100</span
                                        >
                                    </span>
                                </div>
                            </div>

                            <!-- Transcript -->
                            <div
                                v-if="turn.transcript"
                                class="mt-4 rounded-lg bg-muted/30 p-4"
                            >
                                <p
                                    class="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    What they said
                                </p>
                                <p
                                    class="text-sm leading-relaxed italic"
                                >
                                    "{{ turn.transcript }}"
                                </p>
                            </div>

                            <!-- Feedback -->
                            <div v-if="turn.feedback" class="mt-3">
                                <p
                                    class="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Feedback
                                </p>
                                <p class="text-sm leading-relaxed">
                                    {{ turn.feedback }}
                                </p>
                            </div>

                            <!-- Actual explanation -->
                            <div
                                v-if="turn.actual_explanation"
                                class="mt-3"
                            >
                                <p
                                    class="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    The Real Answer
                                </p>
                                <p class="text-sm leading-relaxed">
                                    {{ turn.actual_explanation }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state if no turns -->
                <div
                    v-if="allTurns.length === 0"
                    class="rounded-xl border p-8 text-center"
                >
                    <p class="text-muted-foreground">
                        No completed turns in this game.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
