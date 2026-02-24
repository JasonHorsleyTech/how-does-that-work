<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
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
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: `Lobby — ${props.game.code}`, href: `/games/${props.game.code}/lobby` },
];

const qrDataUrl = ref<string>('');

onMounted(async () => {
    qrDataUrl.value = await QRCode.toDataURL(props.joinUrl, {
        width: 200,
        margin: 1,
    });
});
</script>

<template>
    <Head :title="`Lobby — ${game.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center p-6">
            <div class="w-full max-w-lg space-y-8">
                <div class="text-center">
                    <h1 class="text-3xl font-bold tracking-tight">
                        Game Lobby
                    </h1>
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
                        <p class="mb-3 text-sm font-medium text-muted-foreground">
                            Or scan to join:
                        </p>
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
                        <p class="mb-1 text-sm font-medium text-muted-foreground">
                            Shareable link:
                        </p>
                        <code class="block break-all rounded bg-muted px-3 py-2 text-sm">
                            {{ joinUrl }}
                        </code>
                    </div>
                </div>

                <!-- Players list -->
                <div>
                    <h2 class="mb-3 text-lg font-semibold">
                        Players ({{ game.players.length }})
                    </h2>
                    <ul class="space-y-2">
                        <li
                            v-for="player in game.players"
                            :key="player.id"
                            class="flex items-center gap-3 rounded-lg border px-4 py-3"
                        >
                            <span
                                class="h-2 w-2 rounded-full bg-green-500"
                                aria-hidden="true"
                            />
                            <span class="font-medium">{{ player.name }}</span>
                            <span
                                v-if="player.is_host"
                                class="ml-auto text-xs text-muted-foreground"
                            >
                                Host
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
