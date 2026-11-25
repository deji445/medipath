<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import LanguageSwitch from '@/components/LanguageSwitch.vue';
import { Button } from '@/components/ui/button';
import { ArrowRight, Send, Siren, UserRound, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    ref,
    onMounted,
    onBeforeUnmount,
    watch,
} from 'vue';

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
const context = ref<{
    resumeUrl: string;
    hasIntake: boolean;
    submission: { status: string; isDemo: boolean } | null;
    transport: { status: string; vehicle: string | null } | null;
    callbackStatus: string | null;
} | null>(null);
const minimized = ref(false);
const showHelp = ref(false);
const voiceAvailable = ref(false);
const listening = ref(false);
const voiceStatus = ref('');
const english = computed(() => page.props.localization.current === 'en');
const statusNames: Record<string, string> = {
    RECEIVED: 'Waiting for acknowledgement',
    ACKNOWLEDGED: 'Hospital team acknowledged your intake',
    IN_TRIAGE: 'Triage in progress',
    CLOSED: 'Intake closed',
    REQUESTED: 'Waiting for ambulance assignment',
    ASSIGNED: 'Ambulance assigned',
    EN_ROUTE: 'Travelling to you',
    AT_PATIENT: 'At patient',
    RETURNING: 'Returning to hospital',
    COMPLETED: 'Journey completed',
    CANCELLED: 'Request cancelled',
    NEW: 'Callback requested',
    CONTACTED: 'Callback marked contacted',
};
type Recognition = {
    lang: string;
    start(): void;
    stop(): void;
    abort(): void;
    onresult:
        | ((event: {
              results: {
                  [key: number]: { [key: number]: { transcript: string } };
              };
          }) => void)
        | null;
    onerror: (() => void) | null;
    onend: (() => void) | null;
};
let recognition: Recognition | null = null;
let refreshTimer: ReturnType<typeof setInterval> | null = null;
async function refreshContext(loadMessages = false): Promise<void> {
    try {
        const response = await fetch('/medi/context', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error();
        const data = await response.json();
        context.value = data;
        if (loadMessages && !processing.value)
            messages.value = data.messages ?? [];
    } catch {
        error.value =
            'Status could not be refreshed. Close and reopen Medi to retry.';
    }
}
function speak(text: string): void {
    if (!english.value || !('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-GH';
    window.speechSynthesis.speak(utterance);
}
function stopAudio(): void {
    window.speechSynthesis?.cancel();
}
function voice(): void {
    if (!recognition || !english.value) return;
    if (listening.value) {
        recognition.stop();
        return;
    }
    try {
        recognition.start();
        listening.value = true;
        voiceStatus.value = 'Listening. Review the text before sending.';
    } catch {
        voiceStatus.value = 'Microphone unavailable. You can keep typing.';
    }
}
onMounted(() => {
    minimized.value = sessionStorage.getItem('medi-minimized') === '1';
    const Constructor =
        (
            window as unknown as {
                SpeechRecognition?: new () => Recognition;
                webkitSpeechRecognition?: new () => Recognition;
            }
        ).SpeechRecognition ??
        (
            window as unknown as {
                webkitSpeechRecognition?: new () => Recognition;
            }
        ).webkitSpeechRecognition;
    if (Constructor) {
        recognition = new Constructor();
        recognition.lang = 'en-GH';
        voiceAvailable.value = true;
        recognition.onresult = (event) => {
            draft.value = (draft.value + ' ' + event.results[0][0].transcript)
                .trim()
                .slice(0, 1000);
        };
        recognition.onerror = () => {
            voiceStatus.value = 'Microphone unavailable. You can keep typing.';
            listening.value = false;
        };
        recognition.onend = () => {
            listening.value = false;
        };
    }
    refreshTimer = setInterval(() => {
        if (isOpen.value) void refreshContext();
    }, 10000);
});
watch(minimized, (value) =>
    sessionStorage.setItem('medi-minimized', value ? '1' : '0'),
);
watch(
    () => page.url,
    () => {
        if (isOpen.value) void refreshContext(true);
    },
);
onBeforeUnmount(() => {
    if (refreshTimer) clearInterval(refreshTimer);
    recognition?.abort();
    window.speechSynthesis?.cancel();
});

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
        minimized.value = false;
        void refreshContext(true);
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
            body: JSON.stringify({
                message,
                history: messages.value.slice(0, -1).slice(-12),
            }),
            signal: AbortSignal.timeout(65000),
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
            ? data.messages.slice(-12)
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
            class="mb-3 flex max-h-[min(36rem,calc(100dvh-7rem))] w-[calc(100vw-2rem)] max-w-[23rem] flex-col overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-[0_24px_60px_-26px_rgba(16,45,39,0.55)]"
            aria-label="Medi assistant"
            role="region"
        >
            <header
                class="flex shrink-0 items-center gap-3 border-b border-border bg-muted px-4 py-3"
            >
                <img
                    src="/images/medi-buddy.png"
                    alt=""
                    class="size-11 object-contain"
                />
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">Medi</h2>
                    <p class="text-xs text-muted-foreground">
                        Your MediPath guide
                    </p>
                </div>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-md text-foreground outline-none hover:bg-accent focus-visible:ring-3 focus-visible:ring-ring"
                    aria-label="Close Medi"
                    @click="isOpen = false"
                >
                    <X class="size-5" aria-hidden="true" />
                </button>
            </header>

            <div class="min-h-0 overflow-y-auto px-4 py-4">
                <p class="text-sm leading-6 text-muted-foreground">{{ tip }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="showHelp = !showHelp"
                        >Help with this step</Button
                    >
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="
                            isOpen = false;
                            minimized = true;
                        "
                        >Minimise</Button
                    >
                </div>
                <p v-if="showHelp" class="mt-3 text-sm leading-6">
                    Acknowledged means the hospital team has seen the saved
                    intake. Triage means they are reviewing its urgency. A demo
                    transport request waits for a dispatcher to assign a
                    vehicle. It does not call an emergency service.
                </p>
                <div
                    v-if="
                        context && (context.hasIntake || context.callbackStatus)
                    "
                    class="mt-4 grid gap-3 border-y border-border py-3 text-sm"
                    aria-live="polite"
                >
                    <Link
                        v-if="context.hasIntake"
                        :href="context.resumeUrl"
                        class="font-semibold underline underline-offset-4"
                        >{{
                            context.submission
                                ? 'View saved intake'
                                : 'Resume intake'
                        }}</Link
                    >
                    <p v-if="context.submission">
                        {{ context.submission.isDemo ? 'Demo: ' : ''
                        }}{{ statusNames[context.submission.status] }}
                    </p>
                    <p v-if="context.callbackStatus">
                        {{
                            context.callbackStatus === 'CLOSED'
                                ? 'Callback closed'
                                : statusNames[context.callbackStatus]
                        }}
                    </p>
                    <template v-if="context.transport"
                        ><p>{{ statusNames[context.transport.status] }}</p>
                        <Link
                            href="/intake/submission/ambulance-demo"
                            class="font-semibold underline underline-offset-4"
                            >Track ambulance</Link
                        ></template
                    >
                    <Form
                        v-else-if="context.submission"
                        action="/intake/submission/ambulance-request"
                        method="post"
                        v-slot="{ processing: requesting }"
                        ><Button size="sm" :disabled="requesting"
                            >Request demo ambulance</Button
                        ></Form
                    >
                </div>

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
                                ? 'ml-auto bg-primary text-primary-foreground'
                                : message.emergency
                                  ? 'border border-[#f2b8c0] bg-[#fff0f2] text-[#7d2331]'
                                  : 'bg-muted text-foreground'
                        "
                    >
                        {{ message.content }}
                        <button
                            v-if="message.role === 'assistant' && english"
                            type="button"
                            class="mt-2 block underline underline-offset-4"
                            @click="speak(message.content)"
                        >
                            Listen
                        </button>
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
                            class="min-h-11 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-ring/20"
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
                        class="mt-2 text-sm text-destructive"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                </form>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <Button
                        v-if="voiceAvailable && english"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        @click="voice"
                        >{{
                            listening ? 'Stop microphone' : 'Use microphone'
                        }}</Button
                    >
                    <Button
                        v-if="english"
                        variant="ghost"
                        size="sm"
                        @click="stopAudio"
                        >Stop audio</Button
                    >
                </div>
                <p v-if="voiceStatus" class="mt-2 text-xs" role="status">
                    {{ voiceStatus }}
                </p>
                <p v-if="!english" class="mt-2 text-xs">
                    Twi voice is still being tested. Please type your message.
                </p>
                <Form
                    v-if="
                        messages.some((message) => message.role === 'user') &&
                        !emergencyActive
                    "
                    action="/consultant/chat/intake"
                    method="post"
                    class="mt-3"
                    v-slot="{ processing: handingOff }"
                    ><Button
                        variant="outline"
                        size="sm"
                        :disabled="handingOff || processing"
                        >Continue with this concern</Button
                    ></Form
                >

                <div class="mt-4 border-t border-border pt-3">
                    <Link
                        href="/?consult=human#consult"
                        class="flex min-h-11 items-center justify-between rounded-md px-2 text-sm font-semibold text-foreground outline-none hover:bg-accent focus-visible:ring-3 focus-visible:ring-ring"
                    >
                        <span class="flex items-center gap-2">
                            <UserRound class="size-4" aria-hidden="true" />
                            Request a real consultant
                        </span>
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
                <div class="mt-3"><LanguageSwitch /></div>
            </div>
        </section>

        <button
            type="button"
            class="group flex min-h-14 items-center gap-2 rounded-full border border-border bg-card py-1.5 pr-4 pl-1.5 text-sm font-semibold text-foreground shadow-[0_14px_36px_-18px_rgba(16,45,39,0.7)] transition-transform outline-none hover:-translate-y-0.5 focus-visible:ring-3 focus-visible:ring-ring motion-reduce:transform-none"
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
            <span :class="minimized ? 'sr-only' : ''">{{
                isOpen ? 'Close Medi' : 'Ask Medi'
            }}</span>
        </button>
    </div>
</template>
