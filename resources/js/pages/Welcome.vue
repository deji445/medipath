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
    ClipboardCheck,
    Headphones,
    Hospital,
    MessageCircle,
    MapPin,
    Mic,
    MicOff,
    LockKeyhole,
    PhoneCall,
    RotateCcw,
    RotateCw,
    Send,
    ShieldCheck,
    Siren,
    Stethoscope,
    Volume2,
    VolumeX,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitch from '@/components/LanguageSwitch.vue';
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

const journey = [
    {
        title: 'Tell us what is happening',
        icon: ClipboardCheck,
    },
    {
        title: 'Understand the urgency',
        icon: Stethoscope,
    },
    {
        title: 'Choose a capable hospital',
        icon: Hospital,
    },
];

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
                )
                .from(
                    '[data-motion="care-line"]',
                    {
                        scaleY: 0,
                        transformOrigin: 'top center',
                        duration: 0.48,
                        clearProps: 'all',
                    },
                    '-=0.32',
                )
                .from(
                    '[data-motion="care-step"]',
                    {
                        x: 10,
                        autoAlpha: 0,
                        duration: 0.32,
                        stagger: 0.07,
                        clearProps: 'all',
                    },
                    '-=0.38',
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
    <div ref="pageRoot" class="min-h-screen bg-[#f4f8f6] text-[#16352f]">
        <header
            class="relative z-30 border-b border-[#d2e2dc] bg-[#f8fbf9]/95 backdrop-blur-sm"
        >
            <nav
                class="mx-auto flex max-w-7xl items-center gap-4 px-5 py-4 sm:px-8"
                aria-label="Primary navigation"
            >
                <Link
                    href="/"
                    class="flex items-center gap-3 rounded-md text-lg font-semibold text-[#173a33] outline-none focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                >
                    <AppLogoIcon class="size-10 text-[#197f6d]" />
                    <span>MediPath</span>
                </Link>

                <div class="ml-auto flex items-center gap-2 sm:gap-4">
                    <LanguageSwitch />
                    <Link
                        href="/emergency"
                        class="home-emergency-alarm inline-flex size-11 items-center justify-center rounded-full bg-[#c53f52] text-white shadow-[0_8px_20px_-10px_rgba(197,63,82,0.9)] transition-colors outline-none hover:bg-[#ad3145] focus-visible:ring-3 focus-visible:ring-[#c53f52]/35"
                        aria-label="Emergency help"
                        title="Emergency help"
                    >
                        <Siren class="size-5" aria-hidden="true" />
                        <span class="sr-only">Emergency help</span>
                    </Link>
                    <Link
                        :href="staffWorkspaceHref"
                        class="hidden rounded-md text-sm font-semibold text-[#45635b] outline-none hover:text-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 sm:inline-flex"
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
                        class="h-full w-full opacity-45 sm:opacity-70 lg:opacity-80"
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
                    class="mx-auto grid min-h-[calc(100svh-73px)] max-w-7xl items-center gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[1.18fr_0.82fr] lg:gap-20 lg:py-24"
                >
                    <section class="relative z-10 flex flex-col justify-center">
                        <h1
                            data-motion="hero-title"
                            class="max-w-3xl text-5xl leading-[0.98] font-semibold tracking-[-0.035em] text-balance text-[#173a33] sm:text-6xl lg:text-[4.5rem]"
                        >
                            From symptoms to the right next step.
                        </h1>
                        <p
                            data-motion="hero-support"
                            class="mt-7 max-w-xl text-lg leading-8 text-[#45635b] sm:text-xl"
                        >
                            Share what is happening. Get clear urgency guidance
                            and a hospital-ready summary.
                        </p>

                        <section
                            id="patient-start"
                            data-motion="hero-support"
                            class="mt-9 max-w-2xl scroll-mt-28 rounded-2xl border border-[#b9d1c8] bg-white/92 p-5 shadow-[0_18px_48px_-32px_rgba(23,58,51,0.5)] sm:p-6"
                            aria-labelledby="patient-start-title"
                        >
                            <h2
                                id="patient-start-title"
                                class="text-xl font-semibold tracking-[-0.02em] text-[#173a33] sm:text-2xl"
                            >
                                Find or create the patient profile
                            </h2>
                            <p
                                class="mt-2 max-w-xl text-sm leading-6 text-[#516c64]"
                            >
                                Enter a Ghanaian mobile number. We will use it
                                only to find the patient’s demo profile.
                            </p>
                            <p
                                v-if="continuingFromChat"
                                role="status"
                                class="mt-4 flex items-start gap-2 rounded-lg border border-[#b9d1c8] bg-[#edf6f2] px-3 py-2.5 text-sm font-medium text-[#244a41]"
                            >
                                <CheckCircle2
                                    class="mt-0.5 size-4 shrink-0 text-[#197f6d]"
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
                                <input
                                    type="hidden"
                                    name="streamlined"
                                    value="1"
                                />
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
                                            class="h-12 border-[#b9d1c8] bg-white text-base md:text-base"
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
                                    class="mt-3 text-xs leading-5 text-[#60766f]"
                                >
                                    Demo environment. Use dummy patient
                                    information only.
                                </p>
                            </Form>
                        </section>

                        <div
                            data-motion="hero-support"
                            class="mt-5 flex items-center"
                        >
                            <a
                                href="#consult"
                                class="inline-flex min-h-11 items-center gap-2 rounded-md px-1 text-base font-semibold text-[#244a41] underline decoration-[#9abbb0] underline-offset-8 transition-colors outline-none hover:text-[#116655] hover:decoration-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
                            >
                                Ask the AI consultant
                                <MessageCircle
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>

                        <div
                            data-motion="hero-support"
                            class="mt-10 flex max-w-xl items-center gap-3 text-sm leading-6 text-[#516c64]"
                        >
                            <ShieldCheck
                                class="size-5 shrink-0 text-[#197f6d]"
                                aria-hidden="true"
                            />
                            <p>
                                Nothing is sent to a hospital without your
                                consent.
                            </p>
                        </div>
                    </section>

                    <section
                        class="relative z-10 py-4 lg:pl-8"
                        aria-labelledby="journey-title"
                    >
                        <div class="border-b border-[#b9d1c8] pb-6">
                            <h2
                                id="journey-title"
                                class="max-w-sm text-2xl leading-tight font-semibold tracking-[-0.025em] text-[#173a33] sm:text-3xl"
                            >
                                One guided care journey.
                            </h2>
                        </div>

                        <ol class="relative py-7">
                            <span
                                data-motion="care-line"
                                class="absolute top-12 bottom-12 left-6 w-px bg-[#9fc3b7]"
                                aria-hidden="true"
                            />
                            <li
                                v-for="(step, index) in journey"
                                :key="step.title"
                                data-motion="care-step"
                                class="relative grid grid-cols-[48px_1fr] items-center gap-5 pb-8 last:pb-0"
                            >
                                <div
                                    class="z-10 flex size-12 items-center justify-center rounded-xl bg-[#f8fbf9] text-[#197f6d] ring-1 ring-[#b9d1c8]"
                                >
                                    <component
                                        :is="step.icon"
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div>
                                    <div class="flex items-baseline gap-3">
                                        <span
                                            class="text-xs font-semibold text-[#197f6d] tabular-nums"
                                            >0{{ index + 1 }}</span
                                        >
                                        <h3
                                            class="font-semibold text-[#173a33]"
                                        >
                                            {{ step.title }}
                                        </h3>
                                    </div>
                                </div>
                            </li>
                        </ol>
                        <p
                            class="border-t border-[#b9d1c8] pt-5 text-sm text-[#516c64]"
                        >
                            Guidance, not a diagnosis.
                        </p>
                    </section>
                </div>
            </div>

            <section
                id="consult"
                class="scroll-mt-6 border-y border-[#1b5b4e] bg-[#173a33] py-12 text-white sm:py-16"
                aria-labelledby="consult-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8">
                    <div
                        class="grid gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:gap-16"
                    >
                        <div>
                            <h2
                                id="consult-title"
                                class="max-w-md text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl"
                            >
                                Start with a conversation.
                            </h2>
                            <p class="mt-4 max-w-sm leading-7 text-[#c9e2d9]">
                                Ask a low-risk health question or request a
                                callback. For emergencies, call 112.
                            </p>
                            <Link
                                href="/emergency"
                                class="mt-6 inline-flex h-11 items-center gap-2 rounded-md bg-[#c53f52] px-4 font-semibold text-white shadow-[0_10px_24px_-15px_rgba(197,63,82,0.9)] transition-colors outline-none hover:bg-[#ad3145] focus-visible:ring-3 focus-visible:ring-white/45"
                            >
                                <Siren class="size-4" aria-hidden="true" />
                                Open emergency help
                            </Link>
                        </div>

                        <div
                            class="overflow-hidden rounded-2xl bg-white text-[#16352f] shadow-[0_24px_64px_-36px_rgba(0,0,0,0.8)]"
                        >
                            <div
                                class="grid grid-cols-2 border-b border-[#d2e2dc] bg-[#edf4f1] p-1.5"
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
                                            ? 'bg-white text-[#197f6d] shadow-sm'
                                            : 'text-[#60766f] hover:text-[#16352f]'
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
                                            ? 'bg-white text-[#197f6d] shadow-sm'
                                            : 'text-[#60766f] hover:text-[#16352f]'
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
                                class="p-5 sm:p-7"
                            >
                                <div
                                    ref="chatHistory"
                                    class="max-h-80 min-h-52 space-y-4 overflow-y-auto rounded-xl bg-[#f1f5fa] p-4"
                                    aria-live="polite"
                                >
                                    <div
                                        v-if="visibleChatMessages.length === 0"
                                        class="flex gap-3"
                                    >
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#dff2ec] text-[#116655]"
                                        >
                                            <Bot
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <p
                                            class="max-w-xl rounded-xl bg-white px-4 py-3 text-sm leading-6 text-slate-700"
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
                                            class="w-full rounded-xl bg-[#fff0f2] p-5 text-[#711d2b] ring-1 ring-[#e9a8b2] sm:p-6"
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
                                                        class="text-base font-semibold text-[#8a2637]"
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
                                                        class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-md px-2 text-xs font-semibold text-[#8a2637] outline-none hover:bg-white/70 focus-visible:ring-3 focus-visible:ring-[#c53f52]/30 disabled:cursor-not-allowed disabled:opacity-55"
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
                                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-[#8a2637] ring-1 ring-[#d98290] outline-none hover:bg-[#fff7f8] focus-visible:ring-3 focus-visible:ring-[#c53f52]/30"
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
                                                    class="text-sm font-semibold text-[#711d2b]"
                                                >
                                                    While help is on the way
                                                </h4>
                                                <ol
                                                    class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-[#711d2b] marker:font-semibold marker:text-[#c53f52]"
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
                                                class="mt-3 text-xs leading-5 text-[#8a2637]"
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
                                                    : 'bg-white text-[#45635b]'
                                            "
                                        >
                                            <p>{{ message.content }}</p>
                                            <button
                                                v-if="
                                                    message.role === 'assistant'
                                                "
                                                type="button"
                                                class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-md px-2 text-xs font-semibold text-[#197f6d] outline-none hover:bg-[#eef8f4] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 disabled:cursor-not-allowed disabled:opacity-55"
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
                                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#dff2ec] text-[#116655]"
                                            aria-hidden="true"
                                        >
                                            <Bot class="size-4" />
                                        </div>
                                        <div
                                            class="flex h-11 items-center gap-1.5 rounded-xl bg-white px-4 shadow-[0_8px_20px_-16px_rgba(17,102,85,0.7)]"
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
                                    <div class="flex gap-2">
                                        <textarea
                                            id="consultant_message"
                                            v-model="chatDraft"
                                            name="message"
                                            required
                                            maxlength="1000"
                                            rows="2"
                                            placeholder="Describe the concern…"
                                            class="min-h-12 min-w-0 flex-1 resize-none rounded-md border border-[#c6d8d1] bg-white px-3 py-2 text-base text-[#16352f] outline-none placeholder:text-[#758b84] focus-visible:border-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/25"
                                            @keydown.enter.exact="
                                                submitChatFromKeyboard
                                            "
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="h-auto self-stretch border-[#c6d8d1] px-3 text-[#197f6d] hover:bg-[#e9f5f0] hover:text-[#116655]"
                                            :class="
                                                isListening
                                                    ? 'bg-[#dff2ec] ring-3 ring-[#197f6d]/20'
                                                    : 'bg-white'
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
                                            class="h-auto self-stretch bg-[#197f6d] px-4 text-white hover:bg-[#116655]"
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
                                    <p class="mt-2 text-xs text-[#60766f]">
                                        Enter to send. Shift + Enter for a new
                                        line.
                                    </p>
                                    <p
                                        v-if="
                                            speechInputStatus ||
                                            !speechInputSupported
                                        "
                                        class="mt-1 text-xs font-medium text-[#45635b]"
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
                                    class="mt-5 rounded-xl bg-[#e7f1ed] p-4 sm:flex sm:items-center sm:justify-between sm:gap-5"
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
                                        <p class="font-semibold text-[#173a33]">
                                            Continue with this concern
                                        </p>
                                        <p
                                            class="mt-1 text-sm leading-6 text-[#45635b]"
                                        >
                                            Your messages will prefill the main
                                            concern. Phone verification is still
                                            required before intake.
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
                                    class="mt-4 flex items-start justify-between gap-4 text-xs leading-5 text-[#60766f]"
                                >
                                    <p>
                                        Messages are processed by Gemini and
                                        kept temporarily in this session. Do not
                                        include unnecessary identifying
                                        information.
                                    </p>
                                    <div v-if="visibleChatMessages.length">
                                        <button
                                            class="inline-flex shrink-0 items-center gap-1 font-semibold text-[#45635b] underline underline-offset-4"
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
                                class="p-5 sm:p-7"
                            >
                                <div
                                    v-if="consultantRequestReference"
                                    class="rounded-xl bg-[#e8f5f3] p-5 text-[#154f4c]"
                                    role="status"
                                >
                                    <h3 class="font-semibold">
                                        Request recorded
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-[#356f6b]"
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
                                            class="flex size-10 items-center justify-center rounded-xl bg-[#dff2ec] text-[#116655]"
                                        >
                                            <LockKeyhole
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <h3
                                            class="mt-4 text-xl font-semibold text-[#173a33]"
                                        >
                                            Verify your callback number
                                        </h3>
                                        <p
                                            class="mt-2 text-sm leading-6 text-[#45635b]"
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
                                        class="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"
                                    >
                                        <CheckCircle2
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {{ callbackVerification.status }}
                                    </p>

                                    <div
                                        v-if="callbackVerification.demoCode"
                                        class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-200"
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
                                                class="mt-2 h-14 w-full rounded-xl border border-[#c6d8d1] bg-white px-4 text-center text-2xl font-semibold tracking-[0.32em] text-[#173a33] caret-[#197f6d] outline-none focus-visible:border-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/25"
                                                placeholder="000000"
                                            />
                                            <p
                                                id="callback-code-hint"
                                                class="mt-2 text-sm text-[#60766f]"
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
                                        class="flex flex-wrap items-center justify-between gap-3 border-t border-[#d2e2dc] pt-4"
                                    >
                                        <Form
                                            action="/consultant/request/resend"
                                            method="post"
                                            v-slot="{ errors, processing }"
                                        >
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                class="text-[#45635b] hover:bg-[#e7f1ed] hover:text-[#173a33]"
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
                                                class="text-[#60766f] hover:bg-slate-100 hover:text-[#173a33]"
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
                                            class="mt-2 text-sm leading-6 text-slate-600"
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
                                            class="w-full resize-y rounded-md border border-[#c6d8d1] bg-white px-3 py-2 text-base text-[#16352f] outline-none focus-visible:border-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/25"
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

            <section class="border-y border-[#d2e2dc] bg-[#f8fbf9]">
                <div
                    class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-5 text-sm font-semibold text-[#365a51] sm:px-8 md:flex-row md:items-center md:justify-between md:gap-8"
                >
                    <p>Built for Ghanaian care journeys</p>
                    <p>Emergency checks before AI</p>
                    <p>Consent before hospital sharing</p>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
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
            0 8px 20px -10px rgba(197, 63, 82, 0.85);
    }
    50% {
        box-shadow:
            0 0 0 7px rgba(197, 63, 82, 0.17),
            0 10px 28px -8px rgba(197, 63, 82, 0.78);
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
