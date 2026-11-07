<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
    Ambulance,
    Building2,
    Check,
    Clock3,
    MapPin,
    Pause,
    Phone,
    Play,
    RotateCw,
    ShieldAlert,
} from "@lucide/vue";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Button } from "@/components/ui/button";

const props = defineProps<{
    submissionReference: string;
    hospital: {
        name: string;
        fullName: string;
        address?: string | null;
    };
    plan: {
        distanceKm: number;
        etaMinMinutes: number;
        etaMaxMinutes: number;
        usesEstimatedLocation: boolean;
        demoDurationSeconds: number;
        patientLocation: string;
    };
    emergencyContact: { label: string; phone: string };
}>();

const progress = ref(0);
const isRunning = ref(false);
const routePath = ref<SVGPathElement | null>(null);
const marker = ref({ x: 76, y: 205 });
const startedAt = ref<number | null>(null);
const elapsedBeforePause = ref(0);
const updatedAt = ref(new Date());
let timer: ReturnType<typeof setInterval> | null = null;

const stages = [
    { threshold: 0, title: "Preparing at the hospital" },
    { threshold: 10, title: "Demo ambulance dispatched" },
    { threshold: 22, title: "Travelling to the patient" },
    { threshold: 50, title: "Arrived at the patient" },
    { threshold: 62, title: "Travelling to the hospital" },
    { threshold: 100, title: "Demo journey complete" },
] as const;

const currentStageIndex = computed(() => {
    let index = 0;

    stages.forEach((stage, stageIndex) => {
        if (progress.value >= stage.threshold) index = stageIndex;
    });

    return index;
});
const currentStage = computed(() => stages[currentStageIndex.value]);
const outboundProgress = computed(() =>
    progress.value <= 50 ? progress.value * 2 : (100 - progress.value) * 2,
);
const remainingMinutes = computed(() => {
    const remainingFraction =
        progress.value <= 50 ? (50 - progress.value) / 50 : (100 - progress.value) / 50;

    return Math.max(
        progress.value >= 100 ? 0 : 1,
        Math.ceil(props.plan.etaMaxMinutes * remainingFraction),
    );
});
const etaLabel = computed(() => {
    if (progress.value >= 100) return "Journey complete";

    return progress.value < 50
        ? `${remainingMinutes.value} min to patient`
        : `${remainingMinutes.value} min to hospital`;
});
const lastUpdated = computed(() =>
    new Intl.DateTimeFormat("en-GH", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    }).format(updatedAt.value),
);

function updateMarker(): void {
    const path = routePath.value;

    if (!path) return;

    const point = path.getPointAtLength(path.getTotalLength() * (outboundProgress.value / 100));
    marker.value = { x: point.x, y: point.y };
}

function tick(): void {
    if (startedAt.value === null) return;

    const elapsed = elapsedBeforePause.value + (Date.now() - startedAt.value) / 1000;
    progress.value = Math.min(100, (elapsed / props.plan.demoDurationSeconds) * 100);
    updatedAt.value = new Date();

    if (progress.value >= 100) pauseDemo();
}

function startDemo(): void {
    if (progress.value >= 100) restartDemo();
    if (timer) return;

    startedAt.value = Date.now();
    isRunning.value = true;
    timer = setInterval(tick, 250);
}

function pauseDemo(): void {
    if (startedAt.value !== null) {
        elapsedBeforePause.value += (Date.now() - startedAt.value) / 1000;
    }

    startedAt.value = null;
    isRunning.value = false;

    if (timer) clearInterval(timer);
    timer = null;
}

function restartDemo(): void {
    pauseDemo();
    progress.value = 0;
    elapsedBeforePause.value = 0;
    startDemo();
}

watch(progress, updateMarker);

onMounted(async () => {
    await nextTick();
    updateMarker();

    if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        startDemo();
    }
});

onBeforeUnmount(pauseDemo);
</script>

<template>
    <Head title="Ambulance tracking demo" />

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-6 py-6 sm:px-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800"
                    >
                        <Ambulance class="size-6" aria-hidden="true" />
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold text-slate-950 sm:text-3xl">
                                Ambulance tracking demo
                            </h1>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"
                                >Simulation only</span
                            >
                        </div>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            This demonstration follows a simulated vehicle from
                            {{ hospital.name }} to the patient and back. It does not contact a
                            hospital or dispatch an ambulance.
                        </p>
                    </div>
                </div>

                <a
                    :href="`tel:${emergencyContact.phone}`"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-md bg-[#c53f52] px-4 text-sm font-semibold text-white outline-none transition-colors hover:bg-[#ad3145] focus-visible:ring-3 focus-visible:ring-[#c53f52]/30"
                >
                    <Phone class="size-4" aria-hidden="true" />
                    Call {{ emergencyContact.phone }}
                </a>
            </div>
        </header>

        <div class="grid lg:grid-cols-[1.35fr_0.65fr]">
            <div class="border-b border-slate-200 p-6 lg:border-r lg:border-b-0 lg:p-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5"
                >
                    <div>
                        <p class="text-sm font-semibold text-slate-950" aria-live="polite">
                            {{ currentStage.title }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">Last updated {{ lastUpdated }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold text-teal-800">
                            {{ etaLabel }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Initial estimate
                            {{ plan.etaMinMinutes }}–{{ plan.etaMaxMinutes }} min
                        </p>
                    </div>
                </div>

                <div class="relative mt-6 overflow-hidden rounded-xl bg-[#eef5f2] p-4 sm:p-6">
                    <svg
                        viewBox="0 0 800 300"
                        class="w-full"
                        role="img"
                        aria-label="Abstract demonstration route between the selected hospital and patient location"
                    >
                        <path
                            d="M 76 205 C 170 45, 315 65, 405 165 S 610 275, 724 104"
                            fill="none"
                            stroke="#b9d1c8"
                            stroke-width="18"
                            stroke-linecap="round"
                        />
                        <path
                            ref="routePath"
                            d="M 76 205 C 170 45, 315 65, 405 165 S 610 275, 724 104"
                            fill="none"
                            stroke="#197f6d"
                            stroke-width="4"
                            stroke-linecap="round"
                            stroke-dasharray="10 12"
                        />
                        <circle cx="76" cy="205" r="18" fill="#ffffff" />
                        <circle cx="76" cy="205" r="9" fill="#197f6d" />
                        <circle cx="724" cy="104" r="18" fill="#ffffff" />
                        <circle cx="724" cy="104" r="9" fill="#2d8f79" />
                        <circle
                            :cx="marker.x"
                            :cy="marker.y"
                            r="13"
                            fill="#ffffff"
                            stroke="#173a33"
                            stroke-width="4"
                        />
                    </svg>
                    <div class="mt-2 flex items-start justify-between gap-5 text-xs text-slate-600">
                        <div class="max-w-[45%]">
                            <Building2 class="mb-1 size-4 text-teal-800" aria-hidden="true" />
                            <span class="font-medium text-slate-800">{{ hospital.name }}</span>
                        </div>
                        <div class="max-w-[45%] text-right">
                            <MapPin class="mb-1 ml-auto size-4 text-teal-800" aria-hidden="true" />
                            <span class="font-medium text-slate-800">{{
                                plan.patientLocation
                            }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <div
                        class="h-2 overflow-hidden rounded-full bg-slate-100"
                        role="progressbar"
                        aria-label="Ambulance demonstration progress"
                        :aria-valuenow="Math.round(progress)"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full bg-teal-700 transition-[width] duration-200 motion-reduce:transition-none"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <Button
                            v-if="!isRunning && progress < 100"
                            type="button"
                            @click="startDemo"
                        >
                            <Play class="size-4" aria-hidden="true" />
                            {{ progress > 0 ? "Resume demo" : "Start demo" }}
                        </Button>
                        <Button v-if="isRunning" type="button" variant="outline" @click="pauseDemo">
                            <Pause class="size-4" aria-hidden="true" />
                            Pause demo
                        </Button>
                        <Button type="button" variant="ghost" @click="restartDemo">
                            <RotateCw class="size-4" aria-hidden="true" />
                            Restart
                        </Button>
                    </div>
                </div>

                <div
                    class="mt-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950"
                >
                    <ShieldAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <p class="text-sm leading-6">
                        This prototype does not track a real vehicle. For an actual emergency in
                        Ghana, call
                        {{ emergencyContact.phone }} and follow the dispatcher’s instructions.
                    </p>
                </div>
            </div>

            <aside class="p-6 sm:p-8">
                <h2 class="font-semibold text-slate-950">Journey timeline</h2>
                <ol class="mt-5 space-y-0">
                    <li
                        v-for="(stage, index) in stages"
                        :key="stage.title"
                        class="relative flex gap-3 pb-6 last:pb-0"
                    >
                        <span
                            v-if="index < stages.length - 1"
                            class="absolute top-6 left-[11px] h-[calc(100%_-_1rem)] w-px bg-slate-200"
                            aria-hidden="true"
                        />
                        <span
                            class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border text-xs"
                            :class="
                                index < currentStageIndex
                                    ? 'border-teal-700 bg-teal-700 text-white'
                                    : index === currentStageIndex
                                      ? 'border-teal-700 bg-white text-teal-800'
                                      : 'border-slate-300 bg-white text-slate-400'
                            "
                        >
                            <Check
                                v-if="index < currentStageIndex"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            <Clock3 v-else class="size-3.5" aria-hidden="true" />
                        </span>
                        <span
                            class="pt-0.5 text-sm"
                            :class="
                                index <= currentStageIndex
                                    ? 'font-medium text-slate-900'
                                    : 'text-slate-500'
                            "
                        >
                            {{ stage.title }}
                        </span>
                    </li>
                </ol>

                <dl class="mt-7 border-t border-slate-200 pt-6 text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-slate-500">Route distance</dt>
                        <dd class="font-medium text-slate-900">{{ plan.distanceKm }} km</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-slate-500">Initial ETA</dt>
                        <dd class="font-medium text-slate-900">
                            {{ plan.etaMinMinutes }}–{{ plan.etaMaxMinutes }} min
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-slate-500">Reference</dt>
                        <dd
                            class="max-w-32 truncate font-mono text-xs font-medium text-slate-900"
                            :title="submissionReference"
                        >
                            {{ submissionReference }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-5 text-xs leading-5 text-slate-500">
                    Demo estimate assumes urban travel at approximately 33 km/h plus a three-minute
                    mobilisation period.
                    <template v-if="plan.usesEstimatedLocation">
                        Exact coordinates were unavailable, so a 6.4 km sample route is being used.
                    </template>
                </p>

                <Button as-child variant="outline" class="mt-6 w-full">
                    <Link href="/intake/submission/complete"> Back to submission </Link>
                </Button>
            </aside>
        </div>
    </section>
</template>
