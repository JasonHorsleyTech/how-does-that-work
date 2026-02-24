<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

defineProps<{
    credits: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Billing', href: '/billing' },
    { title: 'Payment Successful', href: '/billing/success' },
];
</script>

<template>
    <Head title="Payment Successful" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col items-center justify-center p-6">
            <div class="w-full max-w-md space-y-6 text-center">
                <div class="text-6xl">🎉</div>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Payment Successful!</h1>
                    <p class="mt-2 text-muted-foreground">
                        100 credits have been added to your account.
                    </p>
                </div>

                <div class="rounded-xl border p-6">
                    <p class="text-sm text-muted-foreground">New Credit Balance</p>
                    <p class="text-4xl font-bold">{{ credits }}</p>
                </div>

                <div class="flex flex-col gap-3">
                    <Button as-child>
                        <Link href="/games/create">Host a Game</Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/dashboard">Back to Dashboard</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
