<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { MorphSVGPlugin } from 'gsap/MorphSVGPlugin';
import { Observer } from 'gsap/Observer';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    ArrowRight,
    Bot,
    ClipboardCheck,
    Headphones,
    Hospital,
    MessageCircle,
    RotateCcw,
    Send,
    ShieldCheck,
    Siren,
    Stethoscope,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, login } from '@/routes';
import {
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
};

const props = defineProps<{
    chatMessages: ChatMessage[];
    consultantRequestReference?: string | null;
}>();

const pageRoot = useTemplateRef<HTMLElement>('pageRoot');
const heroScene = useTemplateRef<HTMLElement>('heroScene');
const consultPanel = useTemplateRef<HTMLElement>('consultPanel');
const chatHistory = useTemplateRef<HTMLElement>('chatHistory');
const chatDraft = ref('');
let motionContext: gsap.Context | undefined;

const activeConsultTab = ref<'ai' | 'human'>(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('consult') === 'human'
        ? 'human'
        : 'ai',
);

const journey = [
    {
        title: 'Tell us what is happening',
        description: 'Patient details, history, and the current concern.',
        icon: ClipboardCheck,
    },
    {
        title: 'Understand the urgency',
        description: 'Safety rules and AI-supported triage guidance.',
        icon: Stethoscope,
    },
    {
        title: 'Choose the next care step',
        description: 'Capability-aware hospital matching and consent.',
        icon: Hospital,
    },
];

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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
    await nextTick();
    animateConsultPanel();
});

watch(
    () => props.chatMessages.length,
    async (currentLength, previousLength) => {
        if (currentLength <= previousLength) return;

        await nextTick();
        const latestMessage = chatHistory.value?.querySelector(
            '[data-chat-message]:last-child',
        );

        if (!latestMessage) return;

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

onBeforeUnmount(() => {
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
                        :href="$page.props.auth.user ? dashboard() : login()"
                        class="hidden rounded-md text-sm font-semibold text-[#45635b] outline-none hover:text-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 sm:inline-flex"
                    >
                        {{
                            $page.props.auth.user ? 'Dashboard' : 'Staff login'
                        }}
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
                    class="mx-auto grid max-w-7xl items-stretch gap-10 px-5 py-12 sm:px-8 sm:py-16 lg:grid-cols-[1.08fr_0.92fr] lg:gap-16 lg:py-20"
                >
                    <section class="relative z-10 flex flex-col justify-center">
                        <h1
                            data-motion="hero-title"
                            class="max-w-3xl text-5xl leading-[0.98] font-semibold tracking-[-0.04em] text-balance text-[#173a33] sm:text-6xl"
                        >
                            One clear path from concern to care.
                        </h1>
                        <p
                            data-motion="hero-support"
                            class="mt-7 max-w-2xl text-lg leading-8 text-[#516c64] sm:text-xl"
                        >
                            Describe what is happening once. MediPath turns it
                            into structured urgency guidance and a
                            hospital-ready care summary.
                        </p>

                        <div
                            data-motion="hero-support"
                            class="mt-9 flex flex-col gap-3 sm:flex-row"
                        >
                            <Button
                                as-child
                                size="lg"
                                class="h-12 bg-[#197f6d] px-6 text-base text-white shadow-[0_12px_28px_-14px_rgba(25,127,109,0.8)] hover:bg-[#116655]"
                            >
                                <Link href="/intake">
                                    Start patient intake
                                    <ArrowRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                            <Button
                                as-child
                                size="lg"
                                variant="outline"
                                class="h-12 border-[#8eaea3] bg-white px-6 text-base text-[#244a41] hover:border-[#197f6d] hover:bg-[#f7faf8] hover:text-[#116655]"
                            >
                                <a href="#consult">
                                    Ask a consultant
                                    <MessageCircle
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                            </Button>
                            <Button
                                as-child
                                size="lg"
                                variant="outline"
                                class="h-12 border-[#c6d8d1] bg-white/85 px-6 text-base text-[#365a51] hover:bg-white"
                            >
                                <Link :href="login()"
                                    >Open staff workspace</Link
                                >
                            </Button>
                        </div>

                        <div
                            data-motion="hero-support"
                            class="mt-9 flex max-w-2xl items-start gap-3 border-t border-[#d2e2dc] pt-6 text-sm leading-6 text-[#516c64]"
                        >
                            <ShieldCheck
                                class="mt-0.5 size-5 shrink-0 text-[#197f6d]"
                                aria-hidden="true"
                            />
                            <p>
                                Your information is not shared with a hospital
                                until you explicitly choose one and consent.
                                MediPath provides urgency guidance, not a
                                diagnosis.
                            </p>
                        </div>
                    </section>

                    <section
                        class="relative z-10 overflow-hidden rounded-[2rem] border border-[#bfd8cf] bg-white/95 shadow-[0_28px_70px_-40px_rgba(22,53,47,0.62)]"
                        aria-labelledby="journey-title"
                    >
                        <div
                            class="border-b border-[#bfd8cf] bg-[#e7f1ed] p-7 sm:p-9"
                        >
                            <h2
                                id="journey-title"
                                class="max-w-md text-2xl leading-tight font-semibold tracking-tight text-[#173a33] sm:text-3xl"
                            >
                                Your information moves forward. You stay in
                                control.
                            </h2>
                            <p class="mt-3 max-w-lg leading-7 text-[#45635b]">
                                A single guided journey connects the patient’s
                                story to the team preparing to receive them.
                            </p>
                        </div>

                        <ol class="relative p-7 sm:p-9">
                            <span
                                data-motion="care-line"
                                class="absolute top-12 bottom-12 left-[3.2rem] w-px bg-[#b9cfE6] sm:left-[4.2rem]"
                                aria-hidden="true"
                            />
                            <li
                                v-for="(step, index) in journey"
                                :key="step.title"
                                data-motion="care-step"
                                class="relative grid grid-cols-[48px_1fr] gap-5 pb-8 last:pb-0"
                            >
                                <div
                                    class="z-10 flex size-12 items-center justify-center rounded-xl border border-[#bfd8cf] bg-white text-[#197f6d] shadow-sm"
                                >
                                    <component
                                        :is="step.icon"
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div class="pt-0.5">
                                    <div class="flex items-center gap-3">
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
                                    <p
                                        class="mt-2 text-sm leading-6 text-[#60766f]"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </li>
                        </ol>
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
                            <p class="mt-4 max-w-md leading-7 text-[#c9e2d9]">
                                Use AI for a low-risk health question or ask for
                                a real consultant to contact you. For urgent
                                symptoms, call 112 or use emergency help.
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
                                        v-if="chatMessages.length === 0"
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
                                        v-for="(message, index) in chatMessages"
                                        :key="`${index}-${message.role}`"
                                        data-chat-message
                                        class="flex"
                                        :class="
                                            message.role === 'user'
                                                ? 'justify-end'
                                                : 'justify-start'
                                        "
                                    >
                                        <p
                                            class="max-w-[88%] rounded-xl px-4 py-3 text-sm leading-6 break-words whitespace-pre-wrap"
                                            :class="
                                                message.role === 'user'
                                                    ? 'bg-[#197f6d] text-white'
                                                    : 'bg-white text-[#45635b]'
                                            "
                                        >
                                            {{ message.content }}
                                        </p>
                                    </div>
                                </div>

                                <Form
                                    action="/consultant/chat"
                                    method="post"
                                    class="mt-4"
                                    reset-on-success
                                    @success="chatDraft = ''"
                                    v-slot="{ errors, processing }"
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
                                        />
                                        <Button
                                            class="h-auto self-stretch bg-[#197f6d] px-4 text-white hover:bg-[#116655]"
                                            :disabled="processing"
                                            aria-label="Send message"
                                        >
                                            <Spinner v-if="processing" />
                                            <Send
                                                v-else
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="errors.message"
                                    />
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
                                    <Form
                                        v-if="chatMessages.length"
                                        action="/consultant/chat"
                                        method="delete"
                                    >
                                        <button
                                            class="inline-flex shrink-0 items-center gap-1 font-semibold text-[#45635b] underline underline-offset-4"
                                            type="submit"
                                        >
                                            <RotateCcw
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                            New chat
                                        </button>
                                    </Form>
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
                                        added to the queue. This prototype
                                        cannot guarantee a response time. Keep
                                        this reference:
                                        <strong class="break-all">{{
                                            consultantRequestReference
                                        }}</strong>
                                    </p>
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
                    class="mx-auto grid max-w-7xl divide-y divide-[#d2e2dc] px-5 sm:px-8 md:grid-cols-3 md:divide-x md:divide-y-0"
                >
                    <div class="py-6 md:pr-8">
                        <p class="font-semibold text-slate-950">
                            Built for Ghanaian care journeys
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Mobile-first intake with Ghanaian phone formatting.
                        </p>
                    </div>
                    <div class="py-6 md:px-8">
                        <p class="font-semibold text-slate-950">
                            Safety rules before AI
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Emergency warning signs interrupt the ordinary flow.
                        </p>
                    </div>
                    <div class="py-6 md:pl-8">
                        <p class="font-semibold text-slate-950">
                            Structured for the receiving team
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Hospitals receive a concise, consented patient
                            summary.
                        </p>
                    </div>
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
</style>
