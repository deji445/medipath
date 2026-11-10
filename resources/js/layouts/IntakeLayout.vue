<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, Siren } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitch from '@/components/LanguageSwitch.vue';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
const stages = [
    { name: 'Patient', detail: 'Secure profile' },
    { name: 'Current concern', detail: 'What is happening' },
    { name: 'Assessment', detail: 'Guided questions' },
    { name: 'Hospital', detail: 'Care options' },
    { name: 'Share', detail: 'Review and send' },
];

const currentStage = computed(() => {
    const url = page.url;
    if (url.startsWith('/intake/submission')) return 4;
    if (url.startsWith('/intake/routing')) return 3;
    if (
        url.startsWith('/intake/assessment') ||
        url.startsWith('/intake/complete')
    )
        return 2;
    if (url.startsWith('/intake/visit') || url.startsWith('/intake/review'))
        return 1;
    return 0;
});
const showJourney = computed(() => !page.url.startsWith('/emergency'));
const journeyRail = ref<HTMLElement | null>(null);
const revealCurrentStage = async () => {
    await nextTick();
    const rail = journeyRail.value;
    const active = rail?.querySelector<HTMLElement>('[aria-current="step"]');
    if (!rail || !active) return;
    rail.scrollTo({
        left: active.offsetLeft - (rail.clientWidth - active.clientWidth) / 2,
        behavior: 'smooth',
    });
};
onMounted(revealCurrentStage);
watch(() => page.url, revealCurrentStage);
</script>

<template>
    <div class="patient-shell min-h-screen bg-[#edf4f1] text-slate-950">
        <Toaster position="top-center" />
        <header
            class="relative z-30 border-b border-[#c8dbd4] bg-[#f8fbfa]/95 backdrop-blur-sm"
        >
            <div
                class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8"
            >
                <Link
                    href="/"
                    class="flex min-h-11 items-center gap-3 rounded-md text-lg font-semibold outline-none focus-visible:ring-3 focus-visible:ring-teal-700/30"
                >
                    <AppLogoIcon class="size-9 text-[#197f6d]" />
                    <span>MediPath</span>
                </Link>
                <div class="ml-auto flex items-center gap-2">
                    <LanguageSwitch />
                    <Link
                        href="/emergency"
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-md bg-[#db5364] text-sm font-semibold text-white shadow-[0_8px_18px_-12px_rgba(197,63,82,0.9)] transition-colors outline-none hover:bg-[#c84355] focus-visible:ring-3 focus-visible:ring-[#db5364]/30 sm:w-auto sm:px-3"
                        aria-label="Emergency help"
                    >
                        <Siren class="size-4" aria-hidden="true" />
                        <span class="hidden sm:inline">Emergency help</span>
                    </Link>
                </div>
            </div>

            <ol
                v-if="showJourney"
                ref="journeyRail"
                class="mx-auto flex max-w-7xl overflow-x-auto border-t border-[#dbe8e3] px-4 xl:hidden"
                aria-label="Patient journey progress"
            >
                <li
                    v-for="(stage, index) in stages"
                    :key="stage.name"
                    :aria-current="index === currentStage ? 'step' : undefined"
                    class="relative flex min-w-max items-center gap-2 px-4 py-3 first:pl-0"
                    :class="
                        index === currentStage
                            ? 'text-teal-950'
                            : 'text-slate-600'
                    "
                >
                    <span
                        class="grid size-6 place-items-center rounded-full text-xs font-semibold tabular-nums"
                        :class="
                            index === currentStage
                                ? 'bg-teal-700 text-white'
                                : index < currentStage
                                  ? 'bg-teal-100 text-teal-800'
                                  : 'bg-white text-teal-950'
                        "
                    >
                        <Check
                            v-if="index < currentStage"
                            class="size-3.5"
                            aria-hidden="true"
                        /><template v-else>{{ index + 1 }}</template>
                    </span>
                    <span
                        class="text-xs font-semibold whitespace-nowrap sm:text-sm"
                        >{{ stage.name }}</span
                    >
                    <span
                        v-if="index < stages.length - 1"
                        class="ml-2 h-px w-6 bg-slate-300"
                        aria-hidden="true"
                    />
                </li>
            </ol>
        </header>

        <main
            class="mx-auto grid max-w-7xl items-start xl:grid-cols-[17.5rem_minmax(0,1fr)]"
        >
            <aside
                v-if="showJourney"
                class="journey-panel sticky top-0 hidden min-h-[calc(100vh-4.25rem)] overflow-hidden bg-[#102d27] px-7 py-10 text-white xl:block"
            >
                <svg
                    class="pointer-events-none absolute inset-x-0 bottom-0 w-full opacity-35"
                    viewBox="0 0 280 520"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M-24 472C55 433 33 358 124 331C210 305 160 218 248 177C306 150 275 72 320 35"
                        stroke="#6dc2aa"
                        stroke-width="2"
                        stroke-dasharray="7 10"
                    />
                    <path
                        d="M-45 500C80 468 65 385 150 359C239 332 195 249 289 205"
                        stroke="#bde9dc"
                        stroke-width="18"
                        stroke-linecap="round"
                        opacity=".12"
                    />
                </svg>
                <div class="relative">
                    <p
                        class="max-w-[14rem] text-2xl leading-tight font-semibold tracking-[-0.025em]"
                    >
                        One clear route through your care journey.
                    </p>
                    <p class="mt-3 text-sm leading-6 text-[#b9d5cc]">
                        Your answers are saved as you move. You can review them
                        before anything is shared.
                    </p>
                    <ol class="mt-10" aria-label="Patient journey progress">
                        <li
                            v-for="(stage, index) in stages"
                            :key="stage.name"
                            :aria-current="
                                index === currentStage ? 'step' : undefined
                            "
                            class="relative flex gap-4 pb-8 last:pb-0"
                        >
                            <span
                                v-if="index < stages.length - 1"
                                class="absolute top-7 left-[13px] h-[calc(100%_-_0.35rem)] w-px"
                                :class="
                                    index < currentStage
                                        ? 'bg-[#6dc2aa]'
                                        : 'bg-white/20'
                                "
                                aria-hidden="true"
                            />
                            <span
                                class="relative z-10 grid size-7 shrink-0 place-items-center rounded-full border text-xs font-semibold tabular-nums"
                                :class="
                                    index === currentStage
                                        ? 'border-[#aee0d2] bg-[#aee0d2] text-[#102d27] shadow-[0_0_0_7px_rgba(174,224,210,0.12)]'
                                        : index < currentStage
                                          ? 'border-[#6dc2aa] bg-[#197f6d] text-white'
                                          : 'border-white/25 bg-[#102d27] text-[#b9d5cc]'
                                "
                            >
                                <Check
                                    v-if="index < currentStage"
                                    class="size-3.5"
                                    aria-hidden="true"
                                /><template v-else>{{ index + 1 }}</template>
                            </span>
                            <div class="pt-0.5">
                                <p
                                    class="text-sm font-semibold"
                                    :class="
                                        index === currentStage
                                            ? 'text-white'
                                            : index < currentStage
                                              ? 'text-[#dff2ec]'
                                              : 'text-[#9dbab1]'
                                    "
                                >
                                    {{ stage.name }}
                                </p>
                                <p
                                    v-if="index === currentStage"
                                    class="mt-1 text-xs text-[#aee0d2]"
                                >
                                    {{ stage.detail }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </div>
            </aside>

            <div
                class="patient-workspace min-w-0 px-4 py-7 sm:px-6 sm:py-10 lg:px-10 xl:px-12"
            >
                <div class="mx-auto max-w-5xl"><slot /></div>
            </div>
        </main>

        <footer class="border-t border-[#c8dbd4] bg-[#f8fbfa] xl:pl-[17.5rem]">
            <div
                class="mx-auto flex max-w-5xl flex-col gap-1 px-4 py-5 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <span>MediPath prototype · Dummy patient data only</span
                ><span>Urgency guidance, not a diagnosis</span>
            </div>
        </footer>
    </div>
</template>
