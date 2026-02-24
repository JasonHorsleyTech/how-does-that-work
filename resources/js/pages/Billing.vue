<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

defineProps<{
    credits: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Billing', href: '/billing' },
];

const form = useForm({});

function purchase() {
    form.post('/billing/checkout');
}
</script>

<template>
    <Head title="Billing" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col p-6">
            <div class="mx-auto w-full max-w-lg space-y-8">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Billing</h1>
                    <p class="mt-1 text-muted-foreground">Manage your credits to run games.</p>
                </div>

                <div class="rounded-xl border p-6 space-y-4">
                    <div>
                        <p class="text-sm text-muted-foreground">Available Credits</p>
                        <p class="text-4xl font-bold">{{ credits }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Each game uses 1 credit per player turn (1 for transcription + 1 for grading).
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border p-6 space-y-4">
                    <div>
                        <h2 class="text-xl font-semibold">Purchase Credits</h2>
                        <p class="mt-1 text-sm text-muted-foreground">100 credits for $2.00</p>
                    </div>

                    <p v-if="form.errors.checkout" class="text-sm text-destructive">
                        {{ form.errors.checkout }}
                    </p>

                    <Button class="w-full" :disabled="form.processing" @click="purchase">
                        {{ form.processing ? 'Redirecting to Stripe…' : 'Buy 100 Credits — $2.00' }}
                    </Button>

                    <p class="text-xs text-muted-foreground text-center">
                        Secure payment via Stripe. You'll be redirected to complete your purchase.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
