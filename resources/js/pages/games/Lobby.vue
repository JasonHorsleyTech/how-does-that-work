<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import QRCode from 'qrcode';

const props = defineProps<{
    game: {
        id: number;
        code: string;
        status: string;
        max_rounds: number;
        players: Array<{ id: number; name: string; is_host: boolean }>;
    };
    joinUrl: string;
    isHost: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: `Lobby — ${props.game.code}`, href: `/games/${props.game.code}/lobby` },
];

const qrDataUrl = ref<string>('');
const players = ref([...props.game.players]);
const nonHostCount = computed(() => players.value.filter((p) => !p.is_host).length);
const canStart = computed(() => nonHostCount.value >= 2);

let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(async () => {
    if (props.isHost) {
        qrDataUrl.value = await QRCode.toDataURL(props.joinUrl, {
            width: 200,
            margin: 1,
        });
    }

    pollInterval = setInterval(pollPlayers, 3000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

async function pollPlayers() {
    try {
        const response = await fetch(`/games/${props.game.code}/players`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.ok) {
            const data = await response.json();
            players.value = data.players;
        }
    } catch {
        // Ignore polling errors silently
    }
}
</script>

<template>
    <Head :title="`Lobby — ${game.code}`" />

    <!-- Host view: full app layout with sidebar -->
    <AppLayout v-if="isHost" :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center p-6">
            <div class="w-full max-w-lg space-y-8">
                <div class="text-center">
                    <h1 class="text-3xl font-bold tracking-tight">Game Lobby</h1>
                    <p class="mt-1 text-muted-foreground">
                        Share the code or link below so players can join.
                    </p>
                </div>

                <!-- Game code + QR -->
                <div class="flex flex-col items-center gap-6 rounded-xl border p-6">
                    <div class="text-center">
                        <p class="text-sm font-medium text-muted-foreground uppercase tracking-wider">
                            Game Code
                        </p>
                        <p class="mt-1 text-5xl font-bold tracking-widest">
                            {{ game.code }}
                        </p>
                    </div>

                    <div class="text-center">
                        <p class="mb-3 text-sm font-medium text-muted-foreground">Or scan to join:</p>
                        <img
                            v-if="qrDataUrl"
                            :src="qrDataUrl"
                            alt="QR code to join the game"
                            class="mx-auto rounded-lg"
                            width="200"
                            height="200"
                        />
                    </div>

                    <div class="w-full">
                        <p class="mb-1 text-sm font-medium text-muted-foreground">Shareable link:</p>
                        <code class="block break-all rounded bg-muted px-3 py-2 text-sm">
                            {{ joinUrl }}
                        </code>
                    </div>
                </div>

                <!-- Players list -->
                <div>
                    <h2 class="mb-3 text-lg font-semibold">Players ({{ players.length }})</h2>
                    <ul class="space-y-2">
                        <li
                            v-for="player in players"
                            :key="player.id"
                            class="flex items-center gap-3 rounded-lg border px-4 py-3"
                        >
                            <span class="h-2 w-2 rounded-full bg-green-500" aria-hidden="true" />
                            <span class="font-medium">{{ player.name }}</span>
                            <span v-if="player.is_host" class="ml-auto text-xs text-muted-foreground">
                                Host
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Start button -->
                <div class="pt-2">
                    <Button class="w-full" :disabled="!canStart">
                        Start Submission Phase
                    </Button>
                    <p v-if="!canStart" class="mt-2 text-center text-sm text-muted-foreground">
                        Waiting for at least 2 players to join…
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Guest view: simple page layout -->
    <div v-else class="flex min-h-screen flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight">Game Lobby</h1>
                <p class="mt-2 text-lg font-semibold text-muted-foreground">
                    Code: {{ game.code }}
                </p>
            </div>

            <!-- Players list -->
            <div class="rounded-xl border p-6">
                <h2 class="mb-3 text-lg font-semibold">Players ({{ players.length }})</h2>
                <ul class="space-y-2">
                    <li
                        v-for="player in players"
                        :key="player.id"
                        class="flex items-center gap-3 rounded-lg border px-4 py-3"
                    >
                        <span class="h-2 w-2 rounded-full bg-green-500" aria-hidden="true" />
                        <span class="font-medium">{{ player.name }}</span>
                        <span v-if="player.is_host" class="ml-auto text-xs text-muted-foreground">
                            Host
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Waiting message -->
            <div class="rounded-lg bg-muted px-6 py-4 text-center">
                <p class="font-medium">Waiting for host to start…</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    The host will begin the game once everyone has joined.
                </p>
            </div>
        </div>
    </div>
</template>
