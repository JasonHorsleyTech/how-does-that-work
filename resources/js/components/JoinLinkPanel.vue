<script setup lang="ts">
import QRCode from 'qrcode';
import { onMounted, ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

const props = defineProps<{
    gameCode: string;
}>();

const isOpen = ref(false);
const qrDataUrl = ref<string>('');
const joinUrl = `${window.location.origin}/join/${props.gameCode}`;

onMounted(async () => {
    qrDataUrl.value = await QRCode.toDataURL(joinUrl, {
        width: 200,
        margin: 1,
    });
});
</script>

<template>
    <Collapsible v-model:open="isOpen">
        <CollapsibleTrigger as-child>
            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"
                    />
                    <polyline points="16 6 12 2 8 6" />
                    <line x1="12" y1="2" x2="12" y2="15" />
                </svg>
                {{ isOpen ? 'Hide join link' : 'Share join link' }}
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 transition-transform"
                    :class="{ 'rotate-180': isOpen }"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="6 9 12 15 18 9" />
                </svg>
            </button>
        </CollapsibleTrigger>

        <CollapsibleContent>
            <div
                class="mt-3 flex flex-col items-center gap-4 rounded-xl border p-4"
            >
                <div class="text-center">
                    <p
                        class="text-sm font-medium tracking-wider text-muted-foreground uppercase"
                    >
                        Game Code
                    </p>
                    <p class="mt-1 text-3xl font-bold tracking-widest">
                        {{ gameCode }}
                    </p>
                </div>

                <img
                    v-if="qrDataUrl"
                    :src="qrDataUrl"
                    alt="QR code to join the game"
                    class="rounded-lg"
                    width="160"
                    height="160"
                />

                <div class="w-full">
                    <p
                        class="mb-1 text-sm font-medium text-muted-foreground"
                    >
                        Shareable link:
                    </p>
                    <code
                        class="block rounded bg-muted px-3 py-2 text-sm break-all"
                    >
                        {{ joinUrl }}
                    </code>
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
