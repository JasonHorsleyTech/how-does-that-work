<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const joinCode = ref('');

function handleJoin() {
    const code = joinCode.value.trim().toUpperCase();
    if (code) {
        router.visit(`/join/${code}`);
    }
}
</script>

<template>
    <Head title="How Does That Work?" />
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- Nav -->
        <header class="flex items-center justify-end p-4 md:p-6">
            <nav class="flex items-center gap-2">
                <template v-if="$page.props.auth.user">
                    <Button as-child>
                        <Link :href="dashboard()">Dashboard</Link>
                    </Button>
                </template>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button v-if="canRegister" variant="outline" as-child>
                        <Link :href="register()">Register</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="flex flex-1 flex-col items-center justify-center px-6 py-12 md:py-20">
            <!-- Hero -->
            <div class="w-full max-w-2xl text-center">
                <h1 class="text-4xl font-bold tracking-tight md:text-6xl">
                    How Does That Work?
                </h1>
                <p class="mt-4 text-lg text-muted-foreground md:text-xl">
                    Reveal how little you actually understand about everyday things — and just how magical the world really is.
                </p>

                <!-- CTAs -->
                <div
                    class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center"
                >
                    <template v-if="$page.props.auth.user">
                        <Button size="lg" as-child>
                            <Link :href="dashboard()">Host a Game</Link>
                        </Button>
                    </template>
                    <template v-else-if="canRegister">
                        <Button size="lg" as-child>
                            <Link :href="register()">Host a Game</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button size="lg" as-child>
                            <Link :href="login()">Host a Game</Link>
                        </Button>
                    </template>

                    <!-- Join a Game -->
                    <div class="flex w-full gap-2 sm:w-auto">
                        <Input
                            v-model="joinCode"
                            placeholder="Game code"
                            maxlength="6"
                            class="w-full uppercase sm:w-32"
                            @keyup.enter="handleJoin"
                        />
                        <Button variant="outline" size="lg" @click="handleJoin">
                            Join
                        </Button>
                    </div>
                </div>
            </div>

            <!-- How to Play -->
            <div class="mt-20 w-full max-w-2xl">
                <h2 class="mb-8 text-center text-2xl font-semibold">
                    How to Play
                </h2>
                <ol class="grid gap-4 sm:grid-cols-2">
                    <li class="flex gap-4 rounded-lg border p-4">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                        >
                            1
                        </span>
                        <div>
                            <h3 class="font-semibold">Submit Your Topics</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Each player submits three things for others to
                                explain — like "How does glue work?" or "How
                                does a pipe organ make sound?"
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-4 rounded-lg border p-4">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                        >
                            2
                        </span>
                        <div>
                            <h3 class="font-semibold">Pick Your Topic</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                On your turn, choose from two topics submitted
                                by other players, then explain it out loud as
                                best you can.
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-4 rounded-lg border p-4">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                        >
                            3
                        </span>
                        <div>
                            <h3 class="font-semibold">
                                Record Your Explanation
                            </h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Speak your explanation into your microphone. You
                                have 2 minutes — use them wisely (or
                                hilariously).
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-4 rounded-lg border p-4">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                        >
                            4
                        </span>
                        <div>
                            <h3 class="font-semibold">Get Graded by AI</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                An AI judge scores your explanation, reveals
                                what you got right, what you missed, and how it
                                actually works.
                            </p>
                        </div>
                    </li>
                </ol>
            </div>
        </main>
    </div>
</template>
