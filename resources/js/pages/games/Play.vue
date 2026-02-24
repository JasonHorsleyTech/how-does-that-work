<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
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
    currentTurn: {
        id: number;
        status: string;
        player_name: string;
        topic_choices: { id: number; text: string }[];
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

let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (!props.isActivePlayer) {
        pollInterval = setInterval(pollState, 3000);
    }
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

async function pollState() {
    try {
        const response = await fetch(`/games/${props.game.code}/play-state`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.ok) {
            const data = await response.json();
            if (data.turnStatus && data.turnStatus !== 'choosing') {
                clearInterval(pollInterval!);
                router.visit(`/games/${props.game.code}/play`);
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

                <!-- Active player is choosing -->
                <div v-else-if="currentTurn.status === 'choosing'" class="rounded-xl border p-8 text-center">
                    <p class="text-lg font-semibold">
                        {{ currentTurn.player_name }} is choosing their topic…
                    </p>
                    <p class="mt-2 text-muted-foreground">Waiting for them to pick.</p>
                </div>

                <!-- Active player has chosen, now recording -->
                <div v-else-if="currentTurn.status === 'recording'" class="rounded-xl border p-8 text-center">
                    <p class="text-lg font-semibold">
                        {{ currentTurn.player_name }} is explaining their topic…
                    </p>
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
            <div v-else-if="currentTurn.status === 'choosing' && isActivePlayer" class="space-y-4">
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

            <!-- Another player is choosing -->
            <div v-else-if="currentTurn.status === 'choosing' && !isActivePlayer" class="rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">
                    {{ currentTurn.player_name }} is choosing their topic…
                </p>
                <p class="mt-2 text-muted-foreground">Hang tight!</p>
            </div>

            <!-- Active player has chosen, now recording -->
            <div v-else-if="currentTurn.status === 'recording'" class="rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">
                    {{ currentTurn.player_name }} is explaining their topic…
                </p>
            </div>
        </div>
    </div>
</template>
