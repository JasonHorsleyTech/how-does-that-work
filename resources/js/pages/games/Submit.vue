<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const props = defineProps<{
    game: {
        id: number;
        code: string;
        status: string;
    };
    player: {
        id: number;
        name: string;
        has_submitted: boolean;
        is_host: boolean;
    };
    submittedCount: number;
    totalCount: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: `Submit Topics — ${props.game.code}`, href: `/games/${props.game.code}/submit` },
];

const form = useForm({
    topics: ['', '', ''],
});

function submit() {
    form.post(`/games/${props.game.code}/topics`);
}

const submittedCount = ref(props.submittedCount);
const totalCount = ref(props.totalCount);
let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (props.player.has_submitted) {
        pollInterval = setInterval(pollStatus, 3000);
    }
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

async function pollStatus() {
    try {
        const response = await fetch(`/games/${props.game.code}/submission-status`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.ok) {
            const data = await response.json();
            submittedCount.value = data.submittedCount;
            totalCount.value = data.totalCount;

            if (data.gameStatus === 'playing') {
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
    <Head :title="`Submit Topics — ${game.code}`" />

    <!-- Host view: full app layout with sidebar -->
    <AppLayout v-if="player.is_host" :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center p-6">
            <div class="w-full max-w-lg space-y-8">
                <div class="text-center">
                    <h1 class="text-3xl font-bold tracking-tight">Submit Your Topics</h1>
                    <p class="mt-1 text-muted-foreground">
                        Enter three things for other players to explain.
                    </p>
                </div>

                <!-- Submitted state -->
                <div v-if="player.has_submitted" class="rounded-xl border p-8 text-center">
                    <p class="text-lg font-semibold">Topics submitted!</p>
                    <p class="mt-2 text-muted-foreground">Waiting for others to submit…</p>
                    <p class="mt-4 text-2xl font-bold">
                        {{ submittedCount }} / {{ totalCount }}
                    </p>
                    <p class="text-sm text-muted-foreground">players have submitted</p>
                </div>

                <!-- Submission form -->
                <form v-else class="space-y-6" @submit.prevent="submit">
                    <div v-for="n in 3" :key="n" class="grid gap-2">
                        <Label :for="`topic-${n}`">Topic {{ n }}</Label>
                        <Input
                            :id="`topic-${n}`"
                            v-model="form.topics[n - 1]"
                            type="text"
                            required
                            minlength="5"
                            maxlength="120"
                            placeholder="How does a microwave work?"
                        />
                        <p v-if="form.errors[`topics.${n - 1}`]" class="text-sm text-destructive">
                            {{ form.errors[`topics.${n - 1}`] }}
                        </p>
                    </div>

                    <p v-if="form.errors.game" class="text-sm text-destructive">
                        {{ form.errors.game }}
                    </p>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        {{ form.processing ? 'Submitting…' : 'Submit Topics' }}
                    </Button>
                </form>
            </div>
        </div>
    </AppLayout>

    <!-- Guest view: simple page layout -->
    <div v-else class="flex min-h-screen flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight">Submit Your Topics</h1>
                <p class="mt-1 text-muted-foreground">
                    Enter three things for other players to explain.
                </p>
            </div>

            <!-- Submitted state -->
            <div v-if="player.has_submitted" class="rounded-xl border p-8 text-center">
                <p class="text-lg font-semibold">Topics submitted!</p>
                <p class="mt-2 text-muted-foreground">Waiting for others to submit…</p>
                <p class="mt-4 text-2xl font-bold">
                    {{ submittedCount }} / {{ totalCount }}
                </p>
                <p class="text-sm text-muted-foreground">players have submitted</p>
            </div>

            <!-- Submission form -->
            <form v-else class="space-y-6" @submit.prevent="submit">
                <div v-for="n in 3" :key="n" class="grid gap-2">
                    <Label :for="`topic-${n}`">Topic {{ n }}</Label>
                    <Input
                        :id="`topic-${n}`"
                        v-model="form.topics[n - 1]"
                        type="text"
                        required
                        minlength="5"
                        maxlength="120"
                        placeholder="How does a microwave work?"
                    />
                    <p v-if="form.errors[`topics.${n - 1}`]" class="text-sm text-destructive">
                        {{ form.errors[`topics.${n - 1}`] }}
                    </p>
                </div>

                <p v-if="form.errors.game" class="text-sm text-destructive">
                    {{ form.errors.game }}
                </p>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Submitting…' : 'Submit Topics' }}
                </Button>
            </form>
        </div>
    </div>
</template>
