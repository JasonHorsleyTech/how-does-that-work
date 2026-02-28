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

const form = useForm({
    name: props.suggestedName ?? '',
});

function submit() {
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
            <p class="text-sm text-muted-foreground">
                This game needs your microphone because you'll be giving an
                impromptu speech. We'll ask for mic access when it's your turn.
            </p>

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

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Joining…' : 'Join Game' }}
            </Button>
        </form>
    </AuthBase>
</template>
