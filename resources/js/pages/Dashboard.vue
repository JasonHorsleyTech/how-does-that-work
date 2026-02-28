<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { create as gamesCreate } from '@/routes/games';
import { type BreadcrumbItem } from '@/types';

interface GameSummary {
    id: number;
    code: string;
    status: string;
    created_at: string;
    player_count: number;
    winner: { name: string; score: number } | null;
    rejoin_url: string | null;
}

defineProps<{
    games: GameSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        lobby: 'In Lobby',
        submitting: 'Submitting',
        playing: 'In Progress',
        grading: 'Grading',
        grading_complete: 'Grading Complete',
        round_complete: 'Round Complete',
        complete: 'Complete',
    };
    return labels[status] ?? status;
}

function statusClass(status: string): string {
    if (status === 'complete')
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
    if (
        ['playing', 'grading', 'grading_complete', 'round_complete'].includes(
            status,
        )
    )
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
    return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-tight">Your Games</h1>
                <Link
                    :href="gamesCreate().url"
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                    + Host a Game
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-if="games.length === 0"
                class="flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center"
            >
                <p class="text-lg font-medium text-muted-foreground">
                    No games yet
                </p>
                <p class="text-sm text-muted-foreground">
                    Host your first game to get started!
                </p>
                <Link
                    :href="gamesCreate().url"
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                    Host a Game
                </Link>
            </div>

            <!-- Games table -->
            <div
                v-else
                class="overflow-hidden rounded-xl border border-sidebar-border/70"
            >
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Date
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Code
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Players
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Winner
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/50">
                        <tr
                            v-for="game in games"
                            :key="game.id"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(game.created_at) }}
                            </td>
                            <td class="px-4 py-3 font-mono font-medium">
                                {{ game.code }}
                            </td>
                            <td class="px-4 py-3">{{ game.player_count }}</td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="
                                        game.winner &&
                                        game.status === 'complete'
                                    "
                                >
                                    {{ game.winner.name }}
                                    <span class="text-muted-foreground"
                                        >({{ game.winner.score }} pts)</span
                                    >
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusClass(game.status)"
                                >
                                    {{ statusLabel(game.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="game.rejoin_url"
                                    :href="game.rejoin_url"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-blue-700 transition-colors"
                                >
                                    Rejoin
                                </Link>
                                <Link
                                    v-else-if="game.status === 'complete'"
                                    :href="`/games/${game.code}/review`"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-muted px-3 py-1.5 text-xs font-medium text-foreground shadow-sm hover:bg-muted/80 transition-colors"
                                >
                                    Review
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
