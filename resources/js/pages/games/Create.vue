<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Create Game', href: '/games/create' },
];

const form = useForm({
    max_rounds: '1',
});

function submit() {
    form.post('/games');
}
</script>

<template>
    <Head title="Create Game" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center justify-center p-6">
            <div class="w-full max-w-md space-y-6">
                <div>
                    <h1 class="text-2xl font-bold">Create a New Game</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Set up your game room and invite players.
                    </p>
                </div>

                <form class="space-y-6" @submit.prevent="submit">
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-medium">
                            How many rounds?
                        </legend>
                        <div class="flex gap-4">
                            <label
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    v-model="form.max_rounds"
                                    type="radio"
                                    value="1"
                                    class="accent-primary"
                                />
                                <span>1 round</span>
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    v-model="form.max_rounds"
                                    type="radio"
                                    value="2"
                                    class="accent-primary"
                                />
                                <span>2 rounds</span>
                            </label>
                        </div>
                        <p v-if="form.errors.max_rounds" class="text-sm text-destructive">
                            {{ form.errors.max_rounds }}
                        </p>
                    </fieldset>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        {{ form.processing ? 'Creating…' : 'Create Game' }}
                    </Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
