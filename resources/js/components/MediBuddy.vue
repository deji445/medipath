<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Send, Siren, UserRound, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    emergency?: boolean;
};

const page = usePage();
const isOpen = ref(false);
const draft = ref('');
const messages = ref<ChatMessage[]>([]);
const processing = ref(false);
const error = ref('');
const input = ref<HTMLInputElement | null>(null);

const tip = computed(() => {
    if (page.url.startsWith('/intake/submission')) {
        return 'I can explain what will be saved or help you speak with a consultant.';
    }
    if (page.url.startsWith('/intake/assessment')) {
        return 'Ask me about this step. Your intake answers stay unchanged.';
    }
    if (page.url.startsWith('/intake/routing')) {
        return 'I can explain why a hospital appears in your care options.';
    }
    if (page.url.startsWith('/emergency')) {
        return 'For immediate help in Ghana, call 112 or follow the emergency steps on this page.';
    }
    return 'Ask a health question, get help with MediPath, or request a real consultant.';
});

const emergencyActive = computed(() =>
    messages.value.some((message) => message.emergency === true),
);

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function toggle(): Promise<void> {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        await nextTick();
        input.value?.focus();
    }
}

async function sendMessage(): Promise<void> {
    const message = draft.value.trim();
    if (!message || processing.value || emergencyActive.value) return;

    processing.value = true;
    error.value = '';
    messages.value.push({ role: 'user', content: message });
    draft.value = '';

    try {
        const response = await fetch('/consultant/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message }),
        });
        const data = (await response.json()) as {
            messages?: ChatMessage[];
            message?: string;
        };

        if (!response.ok) {
            throw new Error(
                data.message ?? 'Medi could not respond. Please try again.',
            );
        }

        messages.value = Array.isArray(data.messages)
            ? data.messages.slice(-6)
            : messages.value;
    } catch (cause) {
        error.value =
            cause instanceof Error
                ? cause.message
                : 'Medi could not respond. Please try again.';
        draft.value = message;
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div
        class="fixed right-4 bottom-4 z-[1000] flex flex-col items-end sm:right-6 sm:bottom-6"
        @keydown.esc="isOpen = false"
    >
        <section
            v-if="isOpen"
            id="medi-buddy-panel"
            class="mb-3 max-h-[min(36rem,calc(100vh-7rem))] w-[calc(100vw-2rem)] max-w-[23rem] overflow-hidden rounded-2xl border border-[#b8d7ce] bg-white text-[#173a33] shadow-[0_24px_60px_-26px_rgba(16,45,39,0.55)]"
            aria-label="Medi assistant"
            role="region"
        >
            <header
                class="flex items-center gap-3 border-b border-[#d2e2dc] bg-[#eff8f5] px-4 py-3"
            >
                <img
                    src="/images/medi-buddy.png"
                    alt=""
                    class="size-11 object-contain"
                />
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">Medi</h2>
                    <p class="text-xs text-[#45635b]">Your MediPath guide</p>
                </div>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-md text-[#45635b] outline-none hover:bg-[#dff2ec] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                    aria-label="Close Medi"
                    @click="isOpen = false"
                >
                    <X class="size-5" aria-hidden="true" />
                </button>
            </header>

            <div class="max-h-[25rem] overflow-y-auto px-4 py-4">
                <p class="text-sm leading-6 text-[#45635b]">{{ tip }}</p>

                <div
                    v-if="messages.length"
                    class="mt-4 grid gap-3"
                    aria-live="polite"
                >
                    <div
                        v-for="(message, index) in messages"
                        :key="`${message.role}-${index}`"
                        class="max-w-[92%] rounded-xl px-3 py-2.5 text-sm leading-5"
                        :class="
                            message.role === 'user'
                                ? 'ml-auto bg-[#197f6d] text-white'
                                : message.emergency
                                  ? 'border border-[#f2b8c0] bg-[#fff0f2] text-[#7d2331]'
                                  : 'bg-[#edf4f1] text-[#173a33]'
                        "
                    >
                        {{ message.content }}
                    </div>
                </div>

                <Link
                    v-if="emergencyActive"
                    href="/emergency"
                    class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-[#c53f52] px-4 text-sm font-semibold text-white outline-none hover:bg-[#ad3145] focus-visible:ring-3 focus-visible:ring-[#c53f52]/30"
                >
                    <Siren class="size-4" aria-hidden="true" />
                    Open emergency help
                </Link>

                <form v-else class="mt-4" @submit.prevent="sendMessage">
                    <label for="medi-buddy-message" class="sr-only">
                        Message for the AI consultant
                    </label>
                    <div class="flex gap-2">
                        <input
                            id="medi-buddy-message"
                            ref="input"
                            v-model="draft"
                            maxlength="1000"
                            class="min-h-11 min-w-0 flex-1 rounded-md border border-[#b8cec7] bg-white px-3 text-sm text-[#173a33] outline-none placeholder:text-[#60766f] focus:border-[#197f6d] focus:ring-3 focus:ring-[#197f6d]/20"
                            placeholder="Type your concern"
                            :disabled="processing"
                        />
                        <button
                            type="submit"
                            class="grid size-11 shrink-0 place-items-center rounded-md bg-[#197f6d] text-white outline-none hover:bg-[#116655] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing || !draft.trim()"
                            aria-label="Send message"
                        >
                            <span
                                v-if="processing"
                                class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                            <Send v-else class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                    <p
                        v-if="error"
                        class="mt-2 text-sm text-[#9f2f40]"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                </form>

                <div class="mt-4 border-t border-[#d2e2dc] pt-3">
                    <Link
                        href="/?consult=human#consult"
                        class="flex min-h-11 items-center justify-between rounded-md px-2 text-sm font-semibold text-[#116655] outline-none hover:bg-[#eff8f5] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                    >
                        <span class="flex items-center gap-2">
                            <UserRound class="size-4" aria-hidden="true" />
                            Request a real consultant
                        </span>
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </section>

        <button
            type="button"
            class="group flex min-h-14 items-center gap-2 rounded-full border border-[#b8d7ce] bg-white py-1.5 pr-4 pl-1.5 text-sm font-semibold text-[#173a33] shadow-[0_14px_36px_-18px_rgba(16,45,39,0.7)] transition-transform outline-none hover:-translate-y-0.5 focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 motion-reduce:transform-none"
            :aria-expanded="isOpen"
            aria-controls="medi-buddy-panel"
            @click="toggle"
        >
            <span
                class="grid size-11 place-items-center rounded-full bg-[#dff2ec]"
            >
                <img
                    src="/images/medi-buddy.png"
                    alt=""
                    class="size-12 object-contain transition-transform group-hover:scale-105 motion-reduce:transform-none"
                />
            </span>
            <span>{{ isOpen ? 'Close Medi' : 'Ask Medi' }}</span>
        </button>
    </div>
</template>
