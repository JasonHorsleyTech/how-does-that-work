<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    game: { code: string };
    suggestedName: string | null;
    error: string | null;
}>();

const reconnecting = ref(false);
const micDenied = ref(false);

const form = useForm({
    name: props.suggestedName ?? '',
});

async function submit() {
    if (micDenied.value) {
        // User already saw the warning and is clicking "Join Anyway"
        form.post(`/join/${props.game.code}`);
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: false,
        });
        // Permission granted — release the stream immediately
        stream.getTracks().forEach((track) => track.stop());
    } catch {
        // Permission denied or device unavailable — show warning but allow joining
        micDenied.value = true;
        return;
    }

    form.post(`/join/${props.game.code}`);
}

onMounted(async () => {
    try {
        const stored = localStorage.getItem(
            `hdtw_player_${props.game.code}`,
        );
        if (!stored) return;

        const data = JSON.parse(stored) as {
            reconnect_token: string;
            game_code: string;
            player_id: number;
        };
        if (!data.reconnect_token) return;

        reconnecting.value = true;

        const response = await fetch(`/join/${props.game.code}/reconnect`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((c) => c.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
            body: JSON.stringify({
                reconnect_token: data.reconnect_token,
            }),
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success && result.redirect_url) {
                router.visit(result.redirect_url);
                return;
            }
        }

        // Reconnect failed — clear stored data and show normal form
        localStorage.removeItem(`hdtw_player_${props.game.code}`);
        reconnecting.value = false;
    } catch {
        reconnecting.value = false;
    }
});
</script>

<template>
    <Head :title="`Join Game — ${game.code}`" />

    <AuthBase
        :title="`Join Game — ${game.code}`"
        description="Enter your display name to join the game."
    >
        <div
            v-if="reconnecting"
            class="py-4 text-center text-sm text-muted-foreground"
        >
            Reconnecting…
        </div>

        <div
            v-else-if="error"
            class="rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
            {{ error }}
        </div>

        <form
            v-else
            class="flex flex-col gap-6"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="name">Your display name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    maxlength="50"
                    placeholder="E.g. Bold Llama"
                />
                <p v-if="form.errors.name" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </p>
                <p v-if="form.errors.game" class="text-sm text-destructive">
                    {{ form.errors.game }}
                </p>
            </div>

            <p
                v-if="micDenied"
                class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200"
            >
                Microphone access was denied. You can still play, but the
                host may need to upload audio on your behalf.
            </p>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{
                    form.processing
                        ? 'Joining…'
                        : micDenied
                          ? 'Join Anyway'
                          : 'Join Game'
                }}
            </Button>
        </form>
    </AuthBase>
</template>
