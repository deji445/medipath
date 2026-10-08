<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Siren } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
const stages = [
    'Patient',
    'Current concern',
    'Assessment',
    'Hospital',
    'Share',
];

const currentStage = computed(() => {
    const url = page.url;

    if (url.startsWith('/intake/submission')) return 4;
    if (url.startsWith('/intake/routing')) return 3;
    if (url.startsWith('/intake/assessment/safety')) return 2;
    if (url.startsWith('/intake/assessment')) return 2;
    if (url.startsWith('/intake/complete')) return 2;
    if (url.startsWith('/intake/visit') || url.startsWith('/intake/review'))
        return 1;

    return 0;
});

const showJourney = computed(() => !page.url.startsWith('/emergency'));
const journeyRail = ref<HTMLElement | null>(null);

const revealCurrentStage = async () => {
    await nextTick();

    const rail = journeyRail.value;
    const activeStage = rail?.querySelector<HTMLElement>(
        '[aria-current="step"]',
    );

    if (!rail || !activeStage) return;

    rail.scrollTo({
        left:
            activeStage.offsetLeft -
            (rail.clientWidth - activeStage.clientWidth) / 2,
        behavior: 'smooth',
    });
};

onMounted(revealCurrentStage);
watch(() => page.url, revealCurrentStage);
</script>

<template>
    <div class="patient-shell min-h-screen bg-slate-50 text-slate-950">
        <Toaster position="top-center" />
        <header class="border-b border-slate-200 bg-white/95 backdrop-blur-sm">
            <div
                class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-4 sm:px-6"
            >
                <Link
                    href="/"
                    class="flex items-center gap-3 rounded-md text-lg font-semibold outline-none focus-visible:ring-3 focus-visible:ring-teal-700/30"
                >
                    <AppLogoIcon class="size-9 text-[#197f6d]" />
                    <span>MediPath</span>
                </Link>
                <span
                    v-if="showJourney"
                    class="hidden border-l border-slate-200 pl-3 text-sm text-slate-500 sm:inline"
                >
                    Guided patient intake
                </span>
                <Link
                    href="/emergency"
                    class="ml-auto inline-flex h-9 items-center gap-2 rounded-md bg-[#c53f52] px-3 text-sm font-semibold text-white shadow-[0_8px_18px_-12px_rgba(197,63,82,0.9)] transition-colors outline-none hover:bg-[#ad3145] focus-visible:ring-3 focus-visible:ring-[#c53f52]/30"
                >
                    <Siren class="size-4" aria-hidden="true" />
                    <span class="hidden sm:inline">Emergency help</span>
                    <span class="sm:hidden">Emergency</span>
                </Link>
            </div>

            <div
                v-if="showJourney"
                class="border-t border-slate-100 bg-slate-50/80"
            >
                <ol
                    ref="journeyRail"
                    class="mx-auto flex max-w-5xl overflow-x-auto px-4 sm:px-6"
                    aria-label="Patient journey progress"
                >
                    <li
                        v-for="(stage, index) in stages"
                        :key="stage"
                        :aria-current="
                            index === currentStage ? 'step' : undefined
                        "
                        class="relative flex min-w-max flex-1 items-center gap-2 px-3 py-3 first:pl-0 last:pr-0"
                        :class="
                            index === currentStage
                                ? 'text-teal-800'
                                : index < currentStage
                                  ? 'text-slate-700'
                                  : 'text-slate-400'
                        "
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold tabular-nums"
                            :class="
                                index === currentStage
                                    ? 'border-teal-700 bg-teal-700 text-white'
                                    : index < currentStage
                                      ? 'border-teal-200 bg-teal-50 text-teal-800'
                                      : 'border-slate-200 bg-white text-slate-400'
                            "
                        >
                            {{ index + 1 }}
                        </span>
                        <span
                            class="text-xs font-medium whitespace-nowrap sm:text-sm"
                            >{{ stage }}</span
                        >
                        <span
                            v-if="index < stages.length - 1"
                            class="ml-auto hidden h-px min-w-3 flex-1 bg-slate-200 lg:block"
                            aria-hidden="true"
                        />
                    </li>
                </ol>
            </div>
        </header>

        <main
            class="patient-workspace mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-10"
        >
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-5xl flex-col gap-1 px-4 py-5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <span>MediPath prototype · Dummy patient data only</span>
                <span>Urgency guidance, not a diagnosis</span>
            </div>
        </footer>
    </div>
</template>
