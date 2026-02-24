<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    game: { code: string };
    suggestedName: string | null;
    error: string | null;
}>();

const form = useForm({
    name: props.suggestedName ?? '',
});

function submit() {
    form.post(`/join/${props.game.code}`);
}
</script>

<template>
    <Head :title="`Join Game — ${game.code}`" />

    <AuthBase
        :title="`Join Game — ${game.code}`"
        description="Enter your display name to join the game."
    >
        <div v-if="error" class="rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
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
