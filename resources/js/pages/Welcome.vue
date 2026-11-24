<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { MorphSVGPlugin } from 'gsap/MorphSVGPlugin';
import { Observer } from 'gsap/Observer';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    ArrowRight,
    Bot,
    CheckCircle2,
    Headphones,
    MessageCircle,
    MapPin,
    Mic,
    MicOff,
    Moon,
    Sun,
    LockKeyhole,
    PhoneCall,
    RotateCcw,
    RotateCw,
    Send,
    Siren,
    Volume2,
    VolumeX,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useAppearance } from '@/composables/useAppearance';
import LanguageSwitch from '@/components/LanguageSwitch.vue';
import MediBuddy from '@/components/MediBuddy.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, login } from '@/routes';
import { toast } from 'vue-sonner';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    emergency?: boolean;
    red_flags?: string[];
    first_aid_steps?: string[];
};

type SpeechResultEvent = {
    resultIndex: number;
    results: {
        length: number;
        [index: number]: {
            isFinal: boolean;
            0: { transcript: string };
        };
    };
};

type SpeechRecognitionErrorEvent = {
    error?: string;
};

type SpeechRecognitionInstance = {
    continuous: boolean;
    interimResults: boolean;
    lang: string;
    start: () => void;
    stop: () => void;
    abort: () => void;
    onresult: ((event: SpeechResultEvent) => void) | null;
    onerror: ((event: SpeechRecognitionErrorEvent) => void) | null;
    onend: (() => void) | null;
};

type SpeechRecognitionConstructor = new () => SpeechRecognitionInstance;

const props = defineProps<{
    chatMessages: ChatMessage[];
    emergencyContact: {
        label: string;
        phone: string;
    };
    consultantRequestReference?: string | null;
    callbackVerification?: {
        phoneNumber: string;
        expiresAt?: string | null;
        demoCode?: string | null;
        status?: string | null;
    } | null;
}>();

const page = usePage();
const { resolvedAppearance, updateAppearance } = useAppearance();
const staffWorkspaceHref = computed(() => {
    const user = page.props.auth.user;

    if (!user) return login();

    return user.role === 'SUPER_ADMIN' ? '/platform' : dashboard();
});
const staffWorkspaceLabel = computed(() => {
    const user = page.props.auth.user;

    if (!user) return 'Staff login';

    return user.role === 'SUPER_ADMIN'
        ? 'Hospital network'
        : 'Admissions dashboard';
});
const pageRoot = useTemplateRef<HTMLElement>('pageRoot');
const heroScene = useTemplateRef<HTMLElement>('heroScene');
const consultPanel = useTemplateRef<HTMLElement>('consultPanel');
const chatHistory = useTemplateRef<HTMLElement>('chatHistory');
const chatDraft = ref('');
const visibleChatMessages = ref<ChatMessage[]>([...props.chatMessages]);
const chatError = ref('');
const chatProcessing = ref(false);
const receivingChatText = ref(false);
const browserSpeechInputSupported = ref(false);
const browserSpeechOutputSupported = ref(false);
const isListening = ref(false);
const speechInputStatus = ref('');
const speakingMessageIndex = ref<number | null>(null);
const emergencyPhone = ref(props.emergencyContact.phone);
const isTwiLocale = computed(() => page.props.localization.current === 'tw');
const speechInputSupported = computed(
    () => browserSpeechInputSupported.value && !isTwiLocale.value,
);
const speechOutputSupported = computed(
    () => browserSpeechOutputSupported.value && !isTwiLocale.value,
);
const speechInputUnavailableMessage = computed(() =>
    isTwiLocale.value
        ? 'Yɛda so resɔ Twi nne no ahwɛ. Kyerɛw wo nkra no seesei.'
        : 'Voice input is not available in this browser. You can keep typing.',
);
const readAloudUnavailableMessage = computed(() =>
    isTwiLocale.value
        ? 'Yɛda so resɔ Twi nne no ahwɛ. Fa akenkan di dwuma seesei.'
        : 'Read aloud is not available in this browser.',
);
const emergencyActive = computed(() =>
    visibleChatMessages.value.some((message) => message.emergency === true),
);
const canContinueFromChat = computed(
    () =>
        !emergencyActive.value &&
        visibleChatMessages.value.some((message) => message.role === 'user'),
);
const callbackConcernPrefill = computed(() =>
    visibleChatMessages.value
        .filter((message) => message.role === 'user')
        .map((message) => message.content.trim())
        .filter(Boolean)
        .join(' ')
        .slice(0, 1000),
);
const callbackCodeExpiry = computed(() => {
    if (!props.callbackVerification?.expiresAt) {
        return 'The code expires shortly.';
    }

    return `The code expires at ${new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(props.callbackVerification.expiresAt))}.`;
});
let motionContext: gsap.Context | undefined;
let recognition: SpeechRecognitionInstance | null = null;
let activeUtterance: SpeechSynthesisUtterance | null = null;
let receivedSpeech = false;
let patientLookupToastId: string | number | undefined;

const activeConsultTab = ref<'ai' | 'human'>(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('consult') === 'human'
        ? 'human'
        : 'ai',
);
const continuingFromChat =
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).get('start') === 'intake';

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function beginPatientLookup(): void {
    patientLookupToastId = toast.loading('Looking up patient profile', {
        description: 'Checking this number against saved patient records.',
    });
}

function finishPatientLookup(): void {
    if (patientLookupToastId !== undefined) {
        toast.dismiss(patientLookupToastId);
    }

    patientLookupToastId = undefined;
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function readJson(response: Response): Promise<Record<string, unknown>> {
    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) return {};

    return (await response.json()) as Record<string, unknown>;
}

function responseError(data: Record<string, unknown>): string {
    const errors = data.errors;

    if (errors && typeof errors === 'object' && 'message' in errors) {
        const messages = (errors as { message?: unknown }).message;

        if (Array.isArray(messages) && typeof messages[0] === 'string') {
            return messages[0];
        }
    }

    return typeof data.message === 'string'
        ? data.message
        : 'The AI consultant could not respond. Please try again.';
}

function stopSpeech(): void {
    if (!speechOutputSupported.value) return;

    window.speechSynthesis.cancel();
    activeUtterance = null;
    speakingMessageIndex.value = null;
}

function messageToSpeechText(message: ChatMessage): string {
    const firstAid = message.first_aid_steps ?? [];

    if (firstAid.length === 0) return message.content;

    return `${message.content} While help is on the way. ${firstAid.join(' ')}`;
}

function toggleMessageSpeech(message: ChatMessage, index: number): void {
    if (!speechOutputSupported.value) return;

    if (speakingMessageIndex.value === index) {
        stopSpeech();
        return;
    }

    stopSpeech();

    const utterance = new SpeechSynthesisUtterance(
        messageToSpeechText(message),
    );
    const voices = window.speechSynthesis.getVoices();

    utterance.lang = 'en-GH';
    utterance.rate = 0.95;
    utterance.voice =
        voices.find((voice) => voice.lang.toLowerCase() === 'en-gh') ??
        voices.find((voice) => voice.lang.toLowerCase() === 'en-gb') ??
        voices.find((voice) => voice.lang.toLowerCase().startsWith('en')) ??
        null;
    utterance.onend = () => {
        if (activeUtterance !== utterance) return;

        activeUtterance = null;
        speakingMessageIndex.value = null;
    };
    utterance.onerror = utterance.onend;
    activeUtterance = utterance;
    speakingMessageIndex.value = index;
    window.speechSynthesis.speak(utterance);
}

function toggleDictation(): void {
    if (!recognition || chatProcessing.value) return;

    if (isListening.value) {
        recognition.stop();
        return;
    }

    stopSpeech();
    receivedSpeech = false;
    isListening.value = true;
    speechInputStatus.value = 'Listening. Tap the microphone to stop.';

    try {
        recognition.start();
    } catch {
        isListening.value = false;
        speechInputStatus.value =
            'Voice input could not start. You can keep typing.';
    }
}

async function submitChat(): Promise<void> {
    const message = chatDraft.value.trim();

    if (!message || chatProcessing.value) return;

    recognition?.abort();
    isListening.value = false;
    stopSpeech();
    chatProcessing.value = true;
    receivingChatText.value = false;
    chatError.value = '';
    const previousMessages = [...visibleChatMessages.value];
    visibleChatMessages.value = [
        ...previousMessages,
        { role: 'user', content: message },
    ];
    chatDraft.value = '';

    try {
        const response = await fetch('/consultant/chat/stream', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                message,
                history: previousMessages.slice(-10),
            }),
        });

        if (!response.ok) {
            const data = await readJson(response);
            chatError.value = responseError(data);
            visibleChatMessages.value = previousMessages;
            chatDraft.value = message;
            return;
        }

        await consumeChatStream(response);
    } catch (error) {
        visibleChatMessages.value = previousMessages;
        chatDraft.value = message;
        chatError.value =
            error instanceof Error && error.message
                ? error.message
                : 'The AI consultant could not be reached. Check your connection and try again.';
    } finally {
        chatProcessing.value = false;
        receivingChatText.value = false;
    }
}

async function consumeChatStream(response: Response): Promise<void> {
    if (!response.body) {
        throw new Error(
            'Streaming is unavailable in this browser. Please try again.',
        );
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let completed = false;

    const consumeEvent = (block: string): void => {
        let eventName = 'message';
        const dataLines: string[] = [];

        for (const line of block.split(/\r?\n/)) {
            if (line.startsWith('event:')) {
                eventName = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice(5).trimStart());
            }
        }

        if (dataLines.length === 0) return;

        const data = JSON.parse(dataLines.join('\n')) as Record<
            string,
            unknown
        >;

        if (eventName === 'delta' && typeof data.text === 'string') {
            if (!receivingChatText.value) {
                receivingChatText.value = true;
                visibleChatMessages.value.push({
                    role: 'assistant',
                    content: '',
                });
            }

            const lastMessage = visibleChatMessages.value.at(-1);

            if (lastMessage?.role === 'assistant') {
                lastMessage.content += data.text;
            }
        }

        if (eventName === 'error') {
            throw new Error(
                typeof data.message === 'string'
                    ? data.message
                    : 'The AI consultant could not respond. Please try again.',
            );
        }

        if (eventName === 'complete') {
            if (Array.isArray(data.messages)) {
                visibleChatMessages.value = data.messages as ChatMessage[];
            }

            if (
                data.emergencyContact &&
                typeof data.emergencyContact === 'object' &&
                'phone' in data.emergencyContact &&
                typeof data.emergencyContact.phone === 'string'
            ) {
                emergencyPhone.value = data.emergencyContact.phone;
            }

            completed = true;
        }
    };

    while (true) {
        const { value, done } = await reader.read();
        buffer += decoder.decode(value, { stream: !done });
        const blocks = buffer.split(/\r?\n\r?\n/);
        buffer = blocks.pop() ?? '';

        for (const block of blocks) {
            consumeEvent(block);
        }

        if (done) break;
    }

    if (buffer.trim()) consumeEvent(buffer);

    if (!completed) {
        throw new Error(
            'The AI response ended unexpectedly. Please try again.',
        );
    }
}

function submitChatFromKeyboard(event: KeyboardEvent): void {
    if (event.isComposing) return;

    event.preventDefault();
    void submitChat();
}

async function resetChat(): Promise<void> {
    if (chatProcessing.value) return;

    chatError.value = '';
    recognition?.abort();
    isListening.value = false;
    speechInputStatus.value = '';
    stopSpeech();

    try {
        const response = await fetch('/consultant/chat', {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            chatError.value = 'The conversation could not be cleared.';
            return;
        }

        visibleChatMessages.value = [];
        chatDraft.value = '';
    } catch {
        chatError.value = 'The conversation could not be cleared.';
    }
}

function animateConsultPanel(): void {
    const panel = consultPanel.value;

    if (!panel) return;

    gsap.killTweensOf(panel);

    if (prefersReducedMotion()) {
        gsap.fromTo(
            panel,
            { opacity: 0.72 },
            { opacity: 1, duration: 0.12, ease: 'none', clearProps: 'opacity' },
        );

        return;
    }

    gsap.fromTo(
        panel,
        { autoAlpha: 0, x: 10, clipPath: 'inset(0 0 0 4%)' },
        {
            autoAlpha: 1,
            x: 0,
            clipPath: 'inset(0 0 0 0%)',
            duration: 0.24,
            ease: 'power3.out',
            clearProps: 'all',
        },
    );
}

onMounted(() => {
    if (!pageRoot.value) return;

    const speechWindow = window as typeof window & {
        SpeechRecognition?: SpeechRecognitionConstructor;
        webkitSpeechRecognition?: SpeechRecognitionConstructor;
    };
    const Recognition =
        speechWindow.SpeechRecognition ?? speechWindow.webkitSpeechRecognition;

    browserSpeechOutputSupported.value =
        'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;

    if (Recognition && !isTwiLocale.value) {
        browserSpeechInputSupported.value = true;
        recognition = new Recognition();
        recognition.continuous = true;
        recognition.interimResults = false;
        recognition.lang = navigator.language.toLowerCase().startsWith('en')
            ? navigator.language
            : 'en-GH';
        recognition.onresult = (event) => {
            const spokenParts: string[] = [];

            for (
                let index = event.resultIndex;
                index < event.results.length;
                index++
            ) {
                if (event.results[index].isFinal) {
                    spokenParts.push(event.results[index][0].transcript.trim());
                }
            }

            if (spokenParts.length === 0) return;

            receivedSpeech = true;
            chatDraft.value = [chatDraft.value.trim(), spokenParts.join(' ')]
                .filter(Boolean)
                .join(' ')
                .slice(0, 1000);
            speechInputStatus.value =
                'Voice added. Review the message before sending.';
        };
        recognition.onerror = (event) => {
            isListening.value = false;
            speechInputStatus.value =
                event.error === 'not-allowed'
                    ? 'Microphone access was not allowed. You can keep typing.'
                    : 'Voice input stopped. You can keep typing.';
        };
        recognition.onend = () => {
            isListening.value = false;

            if (!receivedSpeech) {
                speechInputStatus.value =
                    'Voice input stopped. Tap the microphone to try again.';
            }
        };
    }

    gsap.registerPlugin(DrawSVGPlugin, MorphSVGPlugin, Observer, ScrollTrigger);

    motionContext = gsap.context(() => {
        const media = gsap.matchMedia();

        media.add('(prefers-reduced-motion: no-preference)', () => {
            const timeline = gsap.timeline({
                defaults: { ease: 'power3.out' },
            });

            const ribbonPaths = gsap.utils.toArray<SVGPathElement>(
                '[data-motion~="ribbon-draw"]',
            );
            const morphPaths = gsap.utils.toArray<SVGPathElement>(
                '[data-motion~="ribbon-morph"]',
            );

            timeline
                .from('[data-motion="hero-title"]', {
                    y: 22,
                    autoAlpha: 0,
                    filter: 'blur(5px)',
                    duration: 0.58,
                    clearProps: 'all',
                })
                .from(
                    '[data-motion="hero-support"]',
                    {
                        y: 12,
                        autoAlpha: 0,
                        duration: 0.38,
                        stagger: 0.06,
                        clearProps: 'all',
                    },
                    '-=0.3',
                )
                .fromTo(
                    ribbonPaths,
                    { drawSVG: '0% 0%' },
                    {
                        drawSVG: '0% 100%',
                        duration: 0.9,
                        stagger: 0.07,
                        ease: 'power2.inOut',
                    },
                    0.08,
                );

            const ambientRibbon = gsap.timeline({
                paused: true,
                repeat: -1,
                yoyo: true,
                defaults: { ease: 'sine.inOut' },
            });

            morphPaths.forEach((path) => {
                const destination = path.dataset.morphTo;

                if (!destination) return;

                ambientRibbon.to(
                    path,
                    {
                        morphSVG: {
                            shape: destination,
                            type: 'rotational',
                            map: 'complexity',
                        },
                        duration: 5.8,
                    },
                    0,
                );
            });

            const ribbonSweep = gsap.to('[data-motion~="ribbon-highlight"]', {
                strokeDashoffset: -180,
                duration: 4.8,
                repeat: -1,
                ease: 'none',
                paused: true,
            });

            let heroIsVisible = true;
            const syncAmbientRibbon = () => {
                const shouldPlay =
                    heroIsVisible && document.visibilityState === 'visible';

                if (shouldPlay) {
                    ambientRibbon.play();
                    ribbonSweep.play();
                } else {
                    ambientRibbon.pause();
                    ribbonSweep.pause();
                }
            };

            const visibilityTrigger = ScrollTrigger.create({
                trigger: heroScene.value,
                start: 'top bottom',
                end: 'bottom top',
                onEnter: () => {
                    heroIsVisible = true;
                    syncAmbientRibbon();
                },
                onEnterBack: () => {
                    heroIsVisible = true;
                    syncAmbientRibbon();
                },
                onLeave: () => {
                    heroIsVisible = false;
                    syncAmbientRibbon();
                },
                onLeaveBack: () => {
                    heroIsVisible = false;
                    syncAmbientRibbon();
                },
            });

            document.addEventListener('visibilitychange', syncAmbientRibbon);
            syncAmbientRibbon();

            gsap.to('[data-motion="ribbon-scroll"]', {
                yPercent: 14,
                xPercent: 3,
                rotation: 1.2,
                transformOrigin: '50% 50%',
                ease: 'none',
                scrollTrigger: {
                    trigger: heroScene.value,
                    start: 'top top',
                    end: 'bottom top',
                    scrub: 0.65,
                },
            });

            const ribbonDrift = pageRoot.value?.querySelector<SVGGElement>(
                '[data-motion="ribbon-drift"]',
            );
            const driftX = ribbonDrift
                ? gsap.quickTo(ribbonDrift, 'x', {
                      duration: 0.55,
                      ease: 'power3.out',
                  })
                : undefined;
            const driftY = ribbonDrift
                ? gsap.quickTo(ribbonDrift, 'y', {
                      duration: 0.55,
                      ease: 'power3.out',
                  })
                : undefined;

            const observer = Observer.create({
                target: heroScene.value,
                type: 'pointer,touch',
                tolerance: 10,
                onMove: (self) => {
                    driftX?.(gsap.utils.clamp(-18, 18, self.deltaX * 0.08));
                    driftY?.(gsap.utils.clamp(-12, 12, self.deltaY * 0.06));
                },
                onStop: () => {
                    driftX?.(0);
                    driftY?.(0);
                },
            });

            return () => {
                observer.kill();
                visibilityTrigger.kill();
                ambientRibbon.kill();
                ribbonSweep.kill();
                document.removeEventListener(
                    'visibilitychange',
                    syncAmbientRibbon,
                );
            };
        });

        return () => media.revert();
    }, pageRoot.value);
});

watch(activeConsultTab, async () => {
    recognition?.abort();
    isListening.value = false;
    speechInputStatus.value = '';
    stopSpeech();
    await nextTick();
    animateConsultPanel();
});

watch(
    () => visibleChatMessages.value.length,
    async (currentLength, previousLength) => {
        if (currentLength <= previousLength) return;

        await nextTick();
        const latestMessage = chatHistory.value?.querySelector(
            '[data-chat-message]:last-child',
        );

        if (!latestMessage) return;

        chatHistory.value?.scrollTo({
            top: chatHistory.value.scrollHeight,
            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        });

        gsap.killTweensOf(latestMessage);
        gsap.fromTo(
            latestMessage,
            prefersReducedMotion()
                ? { opacity: 0.72 }
                : { opacity: 0, y: 8, scale: 0.985 },
            {
                opacity: 1,
                y: 0,
                scale: 1,
                duration: prefersReducedMotion() ? 0.12 : 0.22,
                ease: 'power2.out',
                clearProps: 'all',
            },
        );
    },
);

watch(chatProcessing, async (isProcessing) => {
    if (!isProcessing) return;

    await nextTick();
    chatHistory.value?.scrollTo({
        top: chatHistory.value.scrollHeight,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
});

onBeforeUnmount(() => {
    recognition?.abort();
    stopSpeech();
    motionContext?.revert();
});
</script>

<template>
    <Head title="Welcome" />
    <div
        ref="pageRoot"
        class="home-page min-h-screen bg-(--home-canvas) text-(--home-ink)"
    >
        <header
            class="relative z-30 border-b border-(--home-line) bg-(--home-header) backdrop-blur-sm"
        >
            <nav
                class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 sm:gap-4 sm:px-8"
                aria-label="Primary navigation"
            >
                <Link
                    href="/"
                    class="flex items-center gap-3 rounded-md text-lg font-semibold text-(--home-ink) outline-none focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                >
                    <AppLogoIcon class="size-10 text-(--home-accent)" />
                    <span class="max-[374px]:sr-only">MediPath</span>
                </Link>

                <div class="ml-auto flex items-center gap-2 sm:gap-4">
                    <LanguageSwitch />
                    <button
                        type="button"
                        :aria-label="isTwiLocale ? 'Sum mu kɔla' : 'Dark mode'"
                        :title="isTwiLocale ? 'Sum mu kɔla' : 'Dark mode'"
                        :aria-pressed="resolvedAppearance === 'dark'"
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-md border border-(--home-line) bg-(--home-surface) text-(--home-ink) hover:bg-(--home-soft) focus-visible:outline-2 focus-visible:outline-offset-2"
                        @click="
                            updateAppearance(
                                resolvedAppearance === 'dark'
                                    ? 'light'
                                    : 'dark',
                            )
                        "
                    >
                        <Sun
                            v-if="resolvedAppearance === 'dark'"
                            class="size-5"
                            aria-hidden="true"
                        />
                        <Moon v-else class="size-5" aria-hidden="true" />
                    </button>
                    <Link
                        href="/emergency"
                        class="home-emergency-alarm inline-flex size-11 items-center justify-center rounded-full border border-(--home-line) bg-(--home-surface) text-(--home-alert-text) transition-colors outline-none hover:bg-(--home-alert-surface) focus-visible:ring-3 focus-visible:ring-[#c53f52]/35"
                        aria-label="Emergency help"
                        title="Emergency help"
                    >
                        <Siren class="size-5" aria-hidden="true" />
                        <span class="sr-only">Emergency help</span>
                    </Link>
                    <Link
                        :href="staffWorkspaceHref"
                        class="hidden rounded-md text-sm font-semibold text-(--home-muted) outline-none hover:text-(--home-accent) focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 sm:inline-flex"
                    >
                        {{ staffWorkspaceLabel }}
                    </Link>
                </div>
            </nav>
        </header>

        <main>
            <div ref="heroScene" class="relative isolate overflow-hidden">
                <div
                    class="pointer-events-none absolute inset-0 -z-10"
                    aria-hidden="true"
                >
                    <svg
                        class="h-full w-full opacity-45 sm:opacity-70 lg:opacity-80 dark:opacity-20"
                        viewBox="0 0 1440 720"
                        preserveAspectRatio="xMidYMid slice"
                        fill="none"
                    >
                        <defs>
                            <linearGradient
                                id="care-ribbon"
                                x1="40"
                                y1="620"
                                x2="1450"
                                y2="160"
                                gradientUnits="userSpaceOnUse"
                            >
                                <stop offset="0" stop-color="#197f6d" />
                                <stop offset="0.48" stop-color="#2d8f79" />
                                <stop offset="0.78" stop-color="#78aa8f" />
                                <stop offset="1" stop-color="#197f6d" />
                            </linearGradient>
                        </defs>
                        <g data-motion="ribbon-scroll">
                            <g data-motion="ribbon-drift">
                                <path
                                    data-motion="ribbon-draw ribbon-morph"
                                    data-morph-to="M-120 604C138 548 350 664 582 448C790 254 862 178 1082 254C1270 320 1330 454 1560 270"
                                    d="M-120 650C150 474 338 720 566 492C756 302 822 112 1062 206C1254 282 1294 522 1560 326"
                                    stroke="#dff2ec"
                                    stroke-width="104"
                                    stroke-linecap="round"
                                    opacity="0.58"
                                />
                                <path
                                    data-motion="ribbon-draw ribbon-morph"
                                    data-morph-to="M-120 604C138 548 350 664 582 448C790 254 862 178 1082 254C1270 320 1330 454 1560 270"
                                    d="M-120 650C150 474 338 720 566 492C756 302 822 112 1062 206C1254 282 1294 522 1560 326"
                                    stroke="url(#care-ribbon)"
                                    stroke-width="64"
                                    stroke-linecap="round"
                                    opacity="0.22"
                                />
                                <path
                                    data-motion="ribbon-draw ribbon-morph"
                                    data-morph-to="M-120 592C142 570 356 640 592 438C804 256 872 204 1084 270C1262 326 1344 426 1560 254"
                                    d="M-120 638C154 496 344 696 576 482C770 304 832 138 1064 222C1246 288 1308 494 1560 310"
                                    stroke="url(#care-ribbon)"
                                    stroke-width="18"
                                    stroke-linecap="round"
                                    opacity="0.34"
                                />
                                <path
                                    data-motion="ribbon-draw ribbon-morph ribbon-highlight"
                                    data-morph-to="M-120 580C146 592 362 616 602 428C818 258 882 230 1086 286C1254 332 1358 398 1560 238"
                                    d="M-120 626C160 518 350 672 586 472C784 306 842 164 1066 238C1238 294 1322 466 1560 294"
                                    stroke="#ffffff"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-dasharray="72 42"
                                    opacity="0.72"
                                />
                            </g>
                        </g>
                    </svg>
                </div>

                <div
                    class="mx-auto grid max-w-6xl items-center gap-6 px-4 py-8 sm:px-8 sm:py-12 lg:grid-cols-[1fr_1fr] lg:gap-14 lg:py-20"
                >
                    <section class="relative z-10 flex flex-col justify-center">
                        <h1
                            data-motion="hero-title"
                            class="max-w-md text-4xl leading-[1.04] font-semibold tracking-[-0.035em] text-balance text-(--home-ink) sm:text-5xl lg:text-6xl"
                        >
                            From symptoms to the right next step.
                        </h1>
                        <p
                            data-motion="hero-support"
                            class="mt-4 max-w-md text-base leading-7 text-(--home-muted) sm:text-lg"
                        >
                            Share what is happening. Get clear urgency guidance
                            and a hospital-ready summary.
                        </p>
                    </section>
                    <section
                        id="patient-start"
                        data-motion="hero-support"
                        class="min-w-0 scroll-mt-6 rounded-2xl border border-(--home-line) bg-(--home-surface) p-5 sm:p-7"
                        aria-labelledby="patient-start-title"
                    >
                        <h2
                            id="patient-start-title"
                            class="text-xl font-semibold tracking-[-0.02em] text-(--home-ink) sm:text-2xl"
                        >
                            Start your intake
                        </h2>
                        <p
                            class="mt-2 max-w-xl text-sm leading-6 text-(--home-muted)"
                        >
                            Use your Ghanaian mobile number to find or create a
                            profile.
                        </p>
                        <p
                            v-if="continuingFromChat"
                            role="status"
                            class="mt-4 flex items-start gap-2 rounded-lg border border-(--home-line) bg-(--home-soft) px-3 py-2.5 text-sm font-medium text-(--home-ink)"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-(--home-accent)"
                                aria-hidden="true"
                            />
                            Concern saved. Verify the patient to continue.
                        </p>
                        <Form
                            action="/intake/lookup"
                            method="post"
                            class="mt-5"
                            v-slot="{ errors, processing }"
                            @start="beginPatientLookup"
                            @finish="finishPatientLookup"
                        >
                            <input type="hidden" name="streamlined" value="1" />
                            <div
                                class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                            >
                                <div class="grid gap-2">
                                    <Label for="home_phone_number"
                                        >Mobile number</Label
                                    >
                                    <Input
                                        id="home_phone_number"
                                        name="phone_number"
                                        type="tel"
                                        required
                                        autocomplete="tel"
                                        inputmode="tel"
                                        placeholder="024 123 4567"
                                        class="h-12 border-(--home-line) bg-(--home-surface) text-base md:text-base"
                                        :aria-invalid="
                                            Boolean(errors.phone_number)
                                        "
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    class="h-12 w-full bg-[#197f6d] px-6 text-base text-white hover:bg-[#116655] sm:w-auto"
                                    :disabled="processing"
                                >
                                    <Spinner v-if="processing" />
                                    Continue
                                    <ArrowRight
                                        v-if="!processing"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                            <InputError
                                class="mt-2"
                                :message="errors.phone_number"
                            />
                            <p
                                class="mt-3 text-xs leading-5 text-(--home-muted)"
                            >
                                Demo environment. Use dummy patient information
                                only.
                            </p>
                        </Form>

                        <div
                            class="mt-4 flex flex-wrap items-center justify-between gap-x-4 border-t border-(--home-line) pt-3"
                        >
                            <a
                                href="#consult"
                                class="inline-flex min-h-11 items-center gap-2 rounded-md px-1 text-base font-semibold text-(--home-ink) underline decoration-[#9abbb0] underline-offset-8 transition-colors outline-none hover:text-(--home-accent) hover:decoration-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                            >
                                Ask the AI consultant
                                <MessageCircle
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                            <Link
                                href="/intake/submission/track"
                                class="inline-flex min-h-11 items-center text-sm font-semibold text-(--home-accent) underline-offset-4 hover:underline"
                                >Track an intake</Link
                            >
                        </div>
                    </section>
                </div>
            </div>

            <section
                id="consult"
                class="scroll-mt-4 border-t border-(--home-line) bg-(--home-surface) py-8 sm:py-12"
                aria-labelledby="consult-title"
            >
                <div class="mx-auto max-w-3xl px-4 sm:px-8">
                    <div class="grid gap-5">
                        <div>
                            <h2
                                id="consult-title"
                                class="max-w-md text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl"
                            >
                                Start with a conversation.
                            </h2>
                            <p
                                class="mt-2 text-sm leading-6 text-(--home-muted)"
                            >
                                Ask a low-risk health question or request a
                                callback. For emergencies, call 112.
                            </p>
                            <Link
                                href="/emergency"
                                class="mt-3 inline-flex min-h-11 items-center gap-2.5 rounded-lg border border-(--home-line) bg-(--home-surface) px-3.5 text-sm font-semibold text-(--home-ink) transition-colors hover:border-(--home-alert-text) hover:bg-(--home-alert-surface)"
                                ><Siren
                                    class="size-4 text-(--home-alert-text)"
                                    aria-hidden="true" />Emergency
                                help<ArrowRight
                                    class="size-4"
                                    aria-hidden="true"
                            /></Link>
                        </div>

                        <div
                            class="overflow-hidden rounded-2xl border border-(--home-line) bg-(--home-surface) text-(--home-ink)"
                        >
                            <div
                                class="grid grid-cols-2 border-b border-(--home-line) bg-(--home-soft) p-1.5"
                                role="tablist"
                                aria-label="Consultation options"
                            >
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeConsultTab === 'ai'"
                                    class="flex min-h-12 items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold transition-colors outline-none focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                                    :class="
                                        activeConsultTab === 'ai'
                                            ? 'bg-(--home-surface) text-(--home-accent) shadow-sm'
                                            : 'text-(--home-muted) hover:text-(--home-ink)'
                                    "
                                    @click="activeConsultTab = 'ai'"
                                >
                                    <Bot class="size-4" aria-hidden="true" />
                                    AI consultant
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="
                                        activeConsultTab === 'human'
                                    "
                                    class="flex min-h-12 items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold transition-colors outline-none focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                                    :class="
                                        activeConsultTab === 'human'
                                            ? 'bg-(--home-surface) text-(--home-accent) shadow-sm'
                                            : 'text-(--home-muted) hover:text-(--home-ink)'
                                    "
                                    @click="activeConsultTab = 'human'"
                                >
                                    <Headphones
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Real consultant
                                </button>
                            </div>

                            <div
                                v-if="activeConsultTab === 'ai'"
                                ref="consultPanel"
                                role="tabpanel"
                                class="p-3 min-[375px]:p-4 sm:p-7"
                            >
                                <div
                                    ref="chatHistory"
                                    class="max-h-80 space-y-4 overflow-y-auto rounded-xl bg-(--home-canvas) p-4"
                                    aria-live="polite"
                                >
                                    <div
                                        v-if="visibleChatMessages.length === 0"
                                        class="flex gap-3"
                                    >
                                        <div
                                            class="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-(--home-soft) text-(--home-accent) min-[400px]:flex"
                                        >
                                            <Bot
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <p
                                            class="max-w-xl rounded-xl bg-(--home-surface) px-4 py-3 text-sm leading-6 text-(--home-muted)"
                                        >
                                            Hello. Tell me what is bothering
                                            you, and I’ll ask one useful
                                            question at a time. I can guide, but
                                            I cannot diagnose.
                                        </p>
                                    </div>
                                    <div
                                        v-for="(
                                            message, index
                                        ) in visibleChatMessages"
                                        :key="`${index}-${message.role}`"
                                        data-chat-message
                                        class="flex"
                                        :class="
                                            message.role === 'user'
                                                ? 'justify-end'
                                                : 'justify-start'
                                        "
                                    >
                                        <div
                                            v-if="
                                                message.role === 'assistant' &&
                                                message.emergency
                                            "
                                            class="w-full rounded-xl bg-(--home-alert-surface) p-5 text-(--home-alert-text) ring-1 ring-[#e9a8b2] sm:p-6"
                                            role="alert"
                                        >
                                            <div class="flex items-start gap-3">
                                                <span
                                                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#c53f52] text-white"
                                                >
                                                    <Siren
                                                        class="size-5"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div class="min-w-0">
                                                    <h3
                                                        class="text-base font-semibold text-(--home-alert-text)"
                                                    >
                                                        Get emergency help now
                                                    </h3>
                                                    <p
                                                        class="mt-1 text-sm leading-6 break-words"
                                                    >
                                                        {{ message.content }}
                                                    </p>
                                                    <button
                                                        type="button"
                                                        class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-md px-2 text-xs font-semibold text-(--home-alert-text) outline-none hover:bg-(--home-alert-surface) focus-visible:ring-3 focus-visible:ring-[#c53f52]/30 disabled:cursor-not-allowed disabled:opacity-55"
                                                        :disabled="
                                                            !speechOutputSupported
                                                        "
                                                        :title="
                                                            speechOutputSupported
                                                                ? undefined
                                                                : readAloudUnavailableMessage
                                                        "
                                                        :aria-label="
                                                            !speechOutputSupported
                                                                ? readAloudUnavailableMessage
                                                                : speakingMessageIndex ===
                                                                    index
                                                                  ? 'Stop reading this emergency message'
                                                                  : 'Read this emergency message aloud'
                                                        "
                                                        @click="
                                                            toggleMessageSpeech(
                                                                message,
                                                                index,
                                                            )
                                                        "
                                                    >
                                                        <VolumeX
                                                            v-if="
                                                                speakingMessageIndex ===
                                                                index
                                                            "
                                                            class="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        <Volume2
                                                            v-else
                                                            class="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        {{
                                                            speakingMessageIndex ===
                                                            index
                                                                ? 'Stop'
                                                                : 'Listen'
                                                        }}
                                                    </button>
                                                </div>
                                            </div>
                                            <div
                                                class="mt-5 grid gap-2 sm:grid-cols-2"
                                            >
                                                <a
                                                    :href="`tel:${emergencyPhone}`"
                                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-[#c53f52] px-4 text-sm font-semibold text-white outline-none hover:bg-[#ad3145] focus-visible:ring-3 focus-visible:ring-[#c53f52]/30"
                                                >
                                                    <PhoneCall
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Call
                                                    {{ emergencyPhone }} now
                                                </a>
                                                <Link
                                                    href="/emergency"
                                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-(--home-surface) px-4 text-sm font-semibold text-(--home-alert-text) ring-1 ring-[#d98290] outline-none hover:bg-(--home-alert-surface) focus-visible:ring-3 focus-visible:ring-[#c53f52]/30"
                                                >
                                                    <MapPin
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Find emergency care
                                                </Link>
                                            </div>
                                            <div
                                                v-if="
                                                    message.first_aid_steps
                                                        ?.length
                                                "
                                                class="mt-5 border-t border-[#e9a8b2] pt-4"
                                            >
                                                <h4
                                                    class="text-sm font-semibold text-(--home-alert-text)"
                                                >
                                                    While help is on the way
                                                </h4>
                                                <ol
                                                    class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-(--home-alert-text) marker:font-semibold marker:text-[#c53f52]"
                                                >
                                                    <li
                                                        v-for="step in message.first_aid_steps"
                                                        :key="step"
                                                        class="pl-1"
                                                    >
                                                        {{ step }}
                                                    </li>
                                                </ol>
                                            </div>
                                            <p
                                                class="mt-3 text-xs leading-5 text-(--home-alert-text)"
                                            >
                                                The AI conversation has stopped
                                                so it does not delay urgent
                                                care.
                                            </p>
                                        </div>
                                        <div
                                            v-else
                                            class="max-w-[88%] rounded-xl px-4 py-3 text-sm leading-6 break-words whitespace-pre-wrap"
                                            :class="
                                                message.role === 'user'
                                                    ? 'bg-[#197f6d] text-white'
                                                    : 'bg-(--home-surface) text-(--home-muted)'
                                            "
                                        >
                                            <p>{{ message.content }}</p>
                                            <button
                                                v-if="
                                                    message.role === 'assistant'
                                                "
                                                type="button"
                                                class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-md px-2 text-xs font-semibold text-(--home-accent) outline-none hover:bg-(--home-soft) focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 disabled:cursor-not-allowed disabled:opacity-55"
                                                :disabled="
                                                    !speechOutputSupported
                                                "
                                                :title="
                                                    speechOutputSupported
                                                        ? undefined
                                                        : readAloudUnavailableMessage
                                                "
                                                :aria-label="
                                                    !speechOutputSupported
                                                        ? readAloudUnavailableMessage
                                                        : speakingMessageIndex ===
                                                            index
                                                          ? 'Stop reading this response'
                                                          : 'Read this response aloud'
                                                "
                                                @click="
                                                    toggleMessageSpeech(
                                                        message,
                                                        index,
                                                    )
                                                "
                                            >
                                                <VolumeX
                                                    v-if="
                                                        speakingMessageIndex ===
                                                        index
                                                    "
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />
                                                <Volume2
                                                    v-else
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />
                                                {{
                                                    speakingMessageIndex ===
                                                    index
                                                        ? 'Stop'
                                                        : 'Listen'
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            chatProcessing && !receivingChatText
                                        "
                                        class="flex items-end gap-3"
                                        role="status"
                                        aria-label="AI consultant is preparing a response"
                                    >
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-(--home-soft) text-(--home-accent)"
                                            aria-hidden="true"
                                        >
                                            <Bot class="size-4" />
                                        </div>
                                        <div
                                            class="flex h-11 items-center gap-1.5 rounded-xl bg-(--home-surface) px-4 shadow-[0_8px_20px_-16px_rgba(17,102,85,0.7)]"
                                            aria-hidden="true"
                                        >
                                            <span
                                                class="consultant-typing-dot"
                                            />
                                            <span
                                                class="consultant-typing-dot"
                                            />
                                            <span
                                                class="consultant-typing-dot"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <form
                                    v-if="!emergencyActive"
                                    class="mt-4"
                                    :aria-busy="chatProcessing"
                                    @submit.prevent="submitChat"
                                >
                                    <Label
                                        for="consultant_message"
                                        class="sr-only"
                                        >Message for the AI consultant</Label
                                    >
                                    <div
                                        class="grid grid-cols-[1fr_auto_auto] gap-2 sm:flex"
                                    >
                                        <textarea
                                            id="consultant_message"
                                            v-model="chatDraft"
                                            name="message"
                                            required
                                            maxlength="1000"
                                            rows="2"
                                            placeholder="Describe the concern…"
                                            class="col-span-full min-h-12 min-w-0 flex-1 resize-none rounded-md border border-(--home-line) bg-(--home-surface) px-3 py-2 text-base text-(--home-ink) outline-none placeholder:text-(--home-muted) focus-visible:border-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/25"
                                            @keydown.enter.exact="
                                                submitChatFromKeyboard
                                            "
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="col-start-2 min-h-11 min-w-11 self-stretch border-(--home-line) px-3 text-(--home-accent) hover:bg-(--home-soft) hover:text-(--home-accent) sm:h-auto"
                                            :class="
                                                isListening
                                                    ? 'bg-(--home-soft) ring-3 ring-[#197f6d]/20'
                                                    : 'bg-(--home-surface)'
                                            "
                                            :disabled="
                                                chatProcessing ||
                                                !speechInputSupported
                                            "
                                            :aria-pressed="isListening"
                                            :aria-label="
                                                !speechInputSupported
                                                    ? speechInputUnavailableMessage
                                                    : isListening
                                                      ? 'Stop voice input'
                                                      : 'Start voice input'
                                            "
                                            :title="
                                                speechInputSupported
                                                    ? undefined
                                                    : speechInputUnavailableMessage
                                            "
                                            @click="toggleDictation"
                                        >
                                            <MicOff
                                                v-if="isListening"
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            <Mic
                                                v-else
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                        <Button
                                            type="submit"
                                            class="min-h-11 min-w-11 self-stretch bg-[#197f6d] px-4 text-white hover:bg-[#116655] sm:h-auto"
                                            :disabled="chatProcessing"
                                            aria-label="Send message"
                                        >
                                            <Spinner v-if="chatProcessing" />
                                            <Send
                                                v-else
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="chatError"
                                    />
                                    <p
                                        class="mt-2 hidden text-xs text-(--home-muted) sm:block"
                                    >
                                        Enter to send. Shift + Enter for a new
                                        line.
                                    </p>
                                    <p
                                        v-if="
                                            speechInputStatus ||
                                            !speechInputSupported
                                        "
                                        class="mt-1 text-xs font-medium text-(--home-muted)"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        {{
                                            speechInputStatus ||
                                            speechInputUnavailableMessage
                                        }}
                                    </p>
                                </form>

                                <Form
                                    v-if="canContinueFromChat"
                                    action="/consultant/chat/intake"
                                    method="post"
                                    class="mt-5 rounded-xl bg-(--home-soft) p-4 sm:flex sm:items-center sm:justify-between sm:gap-5"
                                    v-slot="{ errors, processing }"
                                >
                                    <template
                                        v-for="(
                                            message, index
                                        ) in visibleChatMessages"
                                        :key="`handoff-${index}`"
                                    >
                                        <input
                                            type="hidden"
                                            :name="`history[${index}][role]`"
                                            :value="message.role"
                                        />
                                        <input
                                            type="hidden"
                                            :name="`history[${index}][content]`"
                                            :value="message.content"
                                        />
                                        <input
                                            type="hidden"
                                            :name="`history[${index}][emergency]`"
                                            :value="
                                                message.emergency ? '1' : '0'
                                            "
                                        />
                                    </template>
                                    <div class="min-w-0">
                                        <p
                                            class="font-semibold text-(--home-ink)"
                                        >
                                            Continue with this concern
                                        </p>
                                        <p
                                            class="mt-1 text-sm leading-6 text-(--home-muted)"
                                        >
                                            Your conversation will become an
                                            editable intake summary. MediPath
                                            will reuse what you already
                                            answered.
                                        </p>
                                        <InputError
                                            class="mt-2"
                                            :message="errors.chat_handoff"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        class="mt-4 min-h-11 w-full shrink-0 bg-[#197f6d] px-5 text-white hover:bg-[#116655] sm:mt-0 sm:w-auto"
                                        :disabled="processing"
                                    >
                                        <Spinner v-if="processing" />
                                        Continue
                                        <ArrowRight
                                            v-if="!processing"
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </Form>

                                <div
                                    class="mt-4 flex items-start justify-between gap-4 text-xs leading-5 text-(--home-muted)"
                                >
                                    <p>
                                        Messages are processed by Gemini and
                                        kept temporarily in this session. Do not
                                        include unnecessary identifying
                                        information.
                                    </p>
                                    <div v-if="visibleChatMessages.length">
                                        <button
                                            class="inline-flex shrink-0 items-center gap-1 font-semibold text-(--home-muted) underline underline-offset-4"
                                            type="button"
                                            @click="resetChat"
                                        >
                                            <RotateCcw
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                            New chat
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                ref="consultPanel"
                                role="tabpanel"
                                class="p-3 min-[375px]:p-4 sm:p-7"
                            >
                                <div
                                    v-if="consultantRequestReference"
                                    class="rounded-xl bg-(--home-soft) p-5 text-(--home-ink)"
                                    role="status"
                                >
                                    <h3 class="font-semibold">
                                        Request recorded
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-(--home-muted)"
                                    >
                                        A consultant callback request has been
                                        added to the queue with a verified phone
                                        number. This prototype cannot guarantee
                                        a response time. Keep this reference:
                                        <strong class="break-all">{{
                                            consultantRequestReference
                                        }}</strong>
                                    </p>
                                </div>

                                <div
                                    v-else-if="callbackVerification"
                                    class="space-y-5"
                                >
                                    <div>
                                        <div
                                            class="flex size-10 items-center justify-center rounded-xl bg-(--home-soft) text-(--home-accent)"
                                        >
                                            <LockKeyhole
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <h3
                                            class="mt-4 text-xl font-semibold text-(--home-ink)"
                                        >
                                            Verify your callback number
                                        </h3>
                                        <p
                                            class="mt-2 text-sm leading-6 text-(--home-muted)"
                                        >
                                            Enter the 6-digit code sent to
                                            {{
                                                callbackVerification.phoneNumber
                                            }}. The request will not enter the
                                            consultant queue until it is
                                            verified.
                                        </p>
                                    </div>

                                    <p
                                        v-if="callbackVerification.status"
                                        role="status"
                                        class="flex items-center gap-2 rounded-xl bg-(--home-soft) px-4 py-3 text-sm font-medium text-(--home-ink)"
                                    >
                                        <CheckCircle2
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {{ callbackVerification.status }}
                                    </p>

                                    <div
                                        v-if="callbackVerification.demoCode"
                                        class="rounded-xl bg-(--home-warning) px-4 py-3 text-sm text-(--home-warning-text) ring-1 ring-amber-200"
                                    >
                                        <p class="font-semibold">
                                            Demo verification code
                                        </p>
                                        <p class="mt-1">
                                            Use
                                            <strong
                                                class="font-mono text-base tracking-widest"
                                                >{{
                                                    callbackVerification.demoCode
                                                }}</strong
                                            >. Demo mode does not verify
                                            ownership of this phone number.
                                        </p>
                                    </div>

                                    <Form
                                        action="/consultant/request/verify"
                                        method="post"
                                        class="space-y-4"
                                        v-slot="{ errors, processing }"
                                    >
                                        <div>
                                            <Label for="callback_code"
                                                >Verification code</Label
                                            >
                                            <input
                                                id="callback_code"
                                                name="code"
                                                type="text"
                                                inputmode="numeric"
                                                autocomplete="one-time-code"
                                                pattern="[0-9]{6}"
                                                maxlength="6"
                                                required
                                                autofocus
                                                aria-describedby="callback-code-hint"
                                                class="mt-2 h-14 w-full rounded-xl border border-(--home-line) bg-(--home-surface) px-4 text-center text-2xl font-semibold tracking-[0.32em] text-(--home-ink) caret-[#197f6d] outline-none focus-visible:border-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/25"
                                                placeholder="000000"
                                            />
                                            <p
                                                id="callback-code-hint"
                                                class="mt-2 text-sm text-(--home-muted)"
                                            >
                                                {{ callbackCodeExpiry }} Never
                                                share this code with anyone.
                                            </p>
                                            <InputError
                                                class="mt-2"
                                                :message="errors.code"
                                            />
                                        </div>
                                        <Button
                                            class="h-11 w-full bg-[#197f6d] text-white hover:bg-[#116655]"
                                            :disabled="processing"
                                        >
                                            <Spinner v-if="processing" />
                                            Verify and request callback
                                        </Button>
                                    </Form>

                                    <div
                                        class="flex flex-wrap items-center justify-between gap-3 border-t border-(--home-line) pt-4"
                                    >
                                        <Form
                                            action="/consultant/request/resend"
                                            method="post"
                                            v-slot="{ errors, processing }"
                                        >
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                class="text-(--home-muted) hover:bg-(--home-soft) hover:text-(--home-ink)"
                                                :disabled="processing"
                                            >
                                                <RotateCw
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Resend code
                                            </Button>
                                            <InputError
                                                class="mt-2"
                                                :message="errors.resend"
                                            />
                                        </Form>
                                        <Form
                                            action="/consultant/request"
                                            method="delete"
                                        >
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                class="text-(--home-muted) hover:bg-(--home-soft) hover:text-(--home-ink)"
                                            >
                                                Use another number
                                            </Button>
                                        </Form>
                                    </div>
                                </div>

                                <Form
                                    v-else
                                    action="/consultant/request"
                                    method="post"
                                    class="space-y-5"
                                    v-slot="{ errors, processing }"
                                >
                                    <div>
                                        <h3 class="text-xl font-semibold">
                                            Request a consultant callback
                                        </h3>
                                        <p
                                            class="mt-2 text-sm leading-6 text-(--home-muted)"
                                        >
                                            Leave a number and a short note.
                                            This is not an emergency service,
                                            and availability is not guaranteed.
                                        </p>
                                    </div>
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="consultant_name"
                                                >Name</Label
                                            >
                                            <Input
                                                id="consultant_name"
                                                name="name"
                                                required
                                                maxlength="160"
                                            />
                                            <InputError
                                                :message="errors.name"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="consultant_phone"
                                                >Phone number</Label
                                            >
                                            <Input
                                                id="consultant_phone"
                                                name="phone_number"
                                                type="tel"
                                                required
                                                placeholder="024 123 4567"
                                            />
                                            <InputError
                                                :message="errors.phone_number"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="consultant_concern"
                                            >What would you like to discuss?
                                            (optional)</Label
                                        >
                                        <textarea
                                            id="consultant_concern"
                                            name="concern"
                                            :value="callbackConcernPrefill"
                                            maxlength="1000"
                                            rows="3"
                                            class="w-full resize-y rounded-md border border-(--home-line) bg-(--home-surface) px-3 py-2 text-base text-(--home-ink) outline-none focus-visible:border-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/25"
                                        />
                                        <InputError :message="errors.concern" />
                                    </div>
                                    <Button
                                        class="h-11 bg-[#197f6d] px-5 text-white hover:bg-[#116655]"
                                        :disabled="processing"
                                    >
                                        <Spinner v-if="processing" />
                                        Request callback
                                    </Button>
                                </Form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        <MediBuddy />
        <footer class="border-t border-(--home-line) bg-(--home-header)">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 text-sm text-(--home-muted) sm:px-8"
            >
                <p>Guidance, not a diagnosis.</p>
                <Link
                    :href="staffWorkspaceHref"
                    class="inline-flex min-h-11 items-center font-semibold underline underline-offset-4"
                    >{{ staffWorkspaceLabel }}</Link
                >
            </div>
        </footer>
    </div>
</template>

<style scoped>
.home-page {
    color-scheme: light;
    --home-canvas: light-dark(#eef4f1, #101816);
    --home-surface: light-dark(#fafcfb, #1b2622);
    --home-header: light-dark(#f1f5f2, #14241e);
    --home-soft: light-dark(#e6efe9, #223a30);
    --home-line: light-dark(#b9cec4, #496759);
    --home-ink: light-dark(#173a33, #f6faf8);
    --home-muted: light-dark(#486459, #d1ddd7);
    --home-accent: light-dark(#116655, #90dec0);
    --home-alert-surface: light-dark(#fff0f2, #42252e);
    --home-alert-text: light-dark(#82293d, #ffc0cc);
    --home-warning: light-dark(#fffbeb, #3a3021);
    --home-warning-text: light-dark(#451a03, #fce2a3);
}

.dark .home-page {
    color-scheme: dark;
}

.home-page :deep(input[data-slot='input']) {
    color: var(--home-ink);
    background-color: var(--home-surface);
    border-color: var(--home-line);
}

.home-page :deep(input[data-slot='input']::placeholder) {
    color: var(--home-muted);
}

.home-page :deep(:focus-visible) {
    outline: 2px solid var(--home-accent);
    outline-offset: 2px;
}

@media (prefers-reduced-motion: no-preference) {
    .home-emergency-alarm {
        animation: emergency-alarm-glow 1.8s ease-in-out infinite;
    }

    .consultant-typing-dot {
        animation: consultant-typing 1.05s ease-in-out infinite;
    }

    .consultant-typing-dot:nth-child(2) {
        animation-delay: 0.14s;
    }

    .consultant-typing-dot:nth-child(3) {
        animation-delay: 0.28s;
    }
}

.consultant-typing-dot {
    width: 0.375rem;
    height: 0.375rem;
    border-radius: 50%;
    background: #197f6d;
    opacity: 0.58;
}

@keyframes emergency-alarm-glow {
    0%,
    100% {
        box-shadow:
            0 0 0 0 rgba(197, 63, 82, 0.12),
            0 1px 6px rgba(197, 63, 82, 0.1);
    }
    50% {
        box-shadow:
            0 0 0 3px rgba(197, 63, 82, 0.12),
            0 1px 8px rgba(197, 63, 82, 0.14);
    }
}

@keyframes consultant-typing {
    0%,
    60%,
    100% {
        opacity: 0.42;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-0.18rem);
    }
}
</style>
