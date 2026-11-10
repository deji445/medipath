<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Ambulance,
    Building2,
    Check,
    Clock3,
    LocateFixed,
    MapPin,
    Pause,
    Phone,
    Play,
    RotateCw,
    ShieldAlert,
} from '@lucide/vue';
import type { Map as LeafletMap, Marker as LeafletMarker } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

type Coordinates = { latitude: number; longitude: number };
const props = defineProps<{
    submissionReference: string;
    hospital: { name: string; fullName: string; address?: string | null };
    plan: {
        distanceKm: number;
        etaMinMinutes: number;
        etaMaxMinutes: number;
        usesEstimatedLocation: boolean;
        demoDurationSeconds: number;
        patientLocation: string;
        hospitalCoordinates: Coordinates;
        patientCoordinates: Coordinates;
    };
    emergencyContact: { label: string; phone: string };
}>();

const progress = ref(0);
const isRunning = ref(false);
const isMapReady = ref(false);
const mapCanvas = ref<HTMLElement | null>(null);
const startedAt = ref<number | null>(null);
const elapsedBeforePause = ref(0);
const updatedAt = ref(new Date());
let timer: ReturnType<typeof setInterval> | null = null;
let map: LeafletMap | null = null;
let ambulanceMarker: LeafletMarker | null = null;

const stages = [
    { threshold: 0, title: 'Preparing at the hospital' },
    { threshold: 10, title: 'Demo ambulance dispatched' },
    { threshold: 22, title: 'Travelling to the patient' },
    { threshold: 50, title: 'Arrived at the patient' },
    { threshold: 62, title: 'Travelling to the hospital' },
    { threshold: 100, title: 'Demo journey complete' },
] as const;

const currentStageIndex = computed(() => {
    let index = 0;
    stages.forEach((stage, stageIndex) => {
        if (progress.value >= stage.threshold) index = stageIndex;
    });
    return index;
});
const currentStage = computed(() => stages[currentStageIndex.value]);
const remainingMinutes = computed(() => {
    const fraction =
        progress.value <= 50
            ? (50 - progress.value) / 50
            : (100 - progress.value) / 50;
    return Math.max(
        progress.value >= 100 ? 0 : 1,
        Math.ceil(props.plan.etaMaxMinutes * fraction),
    );
});
const etaLabel = computed(() => {
    if (progress.value >= 100) return 'Journey complete';
    return progress.value < 50
        ? `${remainingMinutes.value} min to patient`
        : `${remainingMinutes.value} min to hospital`;
});
const lastUpdated = computed(() =>
    new Intl.DateTimeFormat('en-GH', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(updatedAt.value),
);

function currentPosition(): [number, number] {
    const hospital: [number, number] = [
        props.plan.hospitalCoordinates.latitude,
        props.plan.hospitalCoordinates.longitude,
    ];
    const patient: [number, number] = [
        props.plan.patientCoordinates.latitude,
        props.plan.patientCoordinates.longitude,
    ];
    const journeyProgress =
        progress.value <= 50
            ? progress.value / 50
            : (100 - progress.value) / 50;
    return [
        hospital[0] + (patient[0] - hospital[0]) * journeyProgress,
        hospital[1] + (patient[1] - hospital[1]) * journeyProgress,
    ];
}

function updateMapMarker(): void {
    ambulanceMarker?.setLatLng(currentPosition());
}
function tick(): void {
    if (startedAt.value === null) return;
    const elapsed =
        elapsedBeforePause.value + (Date.now() - startedAt.value) / 1000;
    progress.value = Math.min(
        100,
        (elapsed / props.plan.demoDurationSeconds) * 100,
    );
    updatedAt.value = new Date();
    if (progress.value >= 100) pauseDemo();
}
function startDemo(): void {
    if (progress.value >= 100) {
        progress.value = 0;
        elapsedBeforePause.value = 0;
    }
    if (timer) return;
    startedAt.value = Date.now();
    isRunning.value = true;
    timer = setInterval(tick, 250);
}
function pauseDemo(): void {
    if (startedAt.value !== null)
        elapsedBeforePause.value += (Date.now() - startedAt.value) / 1000;
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
function showWholeRoute(): void {
    if (!map) return;
    map.fitBounds(
        [
            [
                props.plan.hospitalCoordinates.latitude,
                props.plan.hospitalCoordinates.longitude,
            ],
            [
                props.plan.patientCoordinates.latitude,
                props.plan.patientCoordinates.longitude,
            ],
        ],
        { padding: [48, 48] },
    );
}

watch(progress, updateMapMarker);
onMounted(async () => {
    if (!mapCanvas.value) return;
    const L = await import('leaflet');
    const hospital: [number, number] = [
        props.plan.hospitalCoordinates.latitude,
        props.plan.hospitalCoordinates.longitude,
    ];
    const patient: [number, number] = [
        props.plan.patientCoordinates.latitude,
        props.plan.patientCoordinates.longitude,
    ];
    map = L.map(mapCanvas.value, {
        zoomControl: true,
        attributionControl: true,
        scrollWheelZoom: false,
    });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
    const icon = (
        kind: 'hospital' | 'patient' | 'ambulance',
        svg: string,
        size = 44,
    ) =>
        L.divIcon({
            className: 'medipath-map-marker',
            html: `<span class="map-marker map-marker--${kind}" aria-hidden="true">${svg}</span>`,
            iconSize: [size, size],
            iconAnchor: [size / 2, kind === 'patient' ? size - 6 : size / 2],
        });
    const hospitalIcon = icon(
        'hospital',
        '<svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h6M9 14h6M12 7v10"/></svg>',
    );
    const patientIcon = icon(
        'patient',
        '<svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    );
    const ambulanceIcon = icon(
        'ambulance',
        '<svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM6 11h5M8.5 8.5v5M6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
        48,
    );
    L.marker(hospital, { icon: hospitalIcon })
        .addTo(map)
        .bindPopup(
            `<strong>${props.hospital.name}</strong><br>${props.hospital.address ?? 'Selected hospital'}`,
        );
    L.marker(patient, { icon: patientIcon })
        .addTo(map)
        .bindPopup(
            `<strong>Patient location</strong><br>${props.plan.patientLocation}`,
        );
    L.polyline([hospital, patient], {
        color: '#197f6d',
        weight: 5,
        opacity: 0.84,
        dashArray: '10 12',
        lineCap: 'round',
    }).addTo(map);
    ambulanceMarker = L.marker(hospital, {
        icon: ambulanceIcon,
        keyboard: false,
        zIndexOffset: 1000,
    }).addTo(map);
    showWholeRoute();
    isMapReady.value = true;
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches)
        startDemo();
});
onBeforeUnmount(() => {
    pauseDemo();
    map?.remove();
    map = null;
    ambulanceMarker = null;
});
</script>

<template>
    <Head title="Ambulance tracking demo" />
    <section
        class="tracking-board overflow-hidden rounded-2xl bg-[#102d27] text-white shadow-[0_22px_60px_-38px_rgba(16,45,39,0.75)]"
    >
        <header class="px-5 py-6 sm:px-8 sm:py-8">
            <div
                class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#dff2ec] text-[#116655]"
                        ><Ambulance class="size-6" aria-hidden="true"
                    /></span>
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1
                                class="text-2xl font-semibold tracking-[-0.025em] sm:text-3xl"
                            >
                                Ambulance journey demo
                            </h1>
                            <span
                                class="rounded-full border border-white/20 px-2.5 py-1 text-xs font-semibold text-[#dff2ec]"
                                >Simulation</span
                            >
                        </div>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-[#c5ddd5]"
                        >
                            Follow an illustrative journey between
                            {{ hospital.name }} and the supplied patient
                            location on a real map of Ghana.
                        </p>
                    </div>
                </div>
                <a
                    :href="`tel:${emergencyContact.phone}`"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-md bg-[#db5364] px-4 text-sm font-semibold text-white shadow-[0_10px_24px_-14px_rgba(219,83,100,0.9)] transition-colors outline-none hover:bg-[#c84355] focus-visible:ring-3 focus-visible:ring-[#f7a4af]/50"
                    ><Phone class="size-4" aria-hidden="true" />Call
                    {{ emergencyContact.phone }}</a
                >
            </div>
        </header>

        <div
            class="grid bg-white text-slate-950 lg:grid-cols-[minmax(0,1.5fr)_minmax(19rem,0.5fr)]"
        >
            <div
                class="min-w-0 border-b border-slate-200 lg:border-r lg:border-b-0"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-8"
                >
                    <div>
                        <p
                            class="font-semibold text-slate-950"
                            aria-live="polite"
                        >
                            {{ currentStage.title }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Updated {{ lastUpdated }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p
                            class="text-xl font-semibold tracking-[-0.02em] text-teal-800"
                        >
                            {{ etaLabel }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Estimated {{ plan.etaMinMinutes }}–{{
                                plan.etaMaxMinutes
                            }}
                            min
                        </p>
                    </div>
                </div>
                <div
                    class="relative h-[26rem] overflow-hidden bg-[#e4eee9] sm:h-[34rem]"
                >
                    <div
                        ref="mapCanvas"
                        class="absolute inset-0 z-0"
                        aria-label="Interactive map showing the simulated ambulance journey"
                    />
                    <div
                        v-if="!isMapReady"
                        class="absolute inset-0 z-10 grid place-items-center bg-[#e4eee9] text-sm font-medium text-teal-950"
                    >
                        Loading map…
                    </div>
                    <button
                        type="button"
                        class="absolute top-4 right-4 z-[500] inline-flex size-11 items-center justify-center rounded-md bg-white text-teal-900 shadow-[0_8px_24px_-12px_rgba(22,53,47,0.55)] outline-none hover:bg-teal-50 focus-visible:ring-3 focus-visible:ring-teal-700/30"
                        aria-label="Show the whole route"
                        @click="showWholeRoute"
                    >
                        <LocateFixed class="size-5" aria-hidden="true" />
                    </button>
                    <div
                        class="pointer-events-none absolute right-4 bottom-8 left-4 z-[500] flex items-end"
                    >
                        <div
                            class="max-w-xs rounded-xl bg-[#102d27]/95 px-4 py-3 text-white shadow-[0_12px_30px_-18px_rgba(0,0,0,0.8)]"
                        >
                            <p class="text-xs font-semibold text-[#aee0d2]">
                                Approximate demo path
                            </p>
                            <p class="mt-1 text-sm leading-5 text-[#e8f4f0]">
                                Map geography is real. The route and vehicle
                                position are simulated, not live dispatch data.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-5 sm:px-8">
                    <div
                        class="h-1.5 overflow-hidden rounded-full bg-slate-100"
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
                            ><Play class="size-4" aria-hidden="true" />{{
                                progress > 0 ? 'Resume' : 'Start'
                            }}</Button
                        >
                        <Button
                            v-if="isRunning"
                            type="button"
                            variant="outline"
                            @click="pauseDemo"
                            ><Pause
                                class="size-4"
                                aria-hidden="true"
                            />Pause</Button
                        >
                        <Button
                            type="button"
                            variant="ghost"
                            @click="restartDemo"
                            ><RotateCw
                                class="size-4"
                                aria-hidden="true"
                            />Restart</Button
                        >
                    </div>
                </div>
            </div>

            <aside class="p-5 sm:p-8">
                <div class="grid gap-4 border-b border-slate-200 pb-6">
                    <div class="flex gap-3">
                        <Building2
                            class="mt-0.5 size-5 shrink-0 text-teal-800"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="text-xs font-medium text-slate-500">
                                Hospital
                            </p>
                            <p
                                class="mt-1 text-sm font-semibold text-slate-950"
                            >
                                {{ hospital.name }}
                            </p>
                            <p
                                v-if="hospital.address"
                                class="mt-1 text-xs leading-5 text-slate-500"
                            >
                                {{ hospital.address }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <MapPin
                            class="mt-0.5 size-5 shrink-0 text-teal-800"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="text-xs font-medium text-slate-500">
                                Patient location
                            </p>
                            <p
                                class="mt-1 text-sm font-semibold text-slate-950"
                            >
                                {{ plan.patientLocation }}
                            </p>
                        </div>
                    </div>
                </div>
                <h2 class="mt-6 font-semibold text-slate-950">
                    Journey status
                </h2>
                <ol class="mt-5 space-y-0">
                    <li
                        v-for="(stage, index) in stages"
                        :key="stage.title"
                        class="relative flex gap-3 pb-5 last:pb-0"
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
                                      : 'border-slate-300 bg-white text-teal-950'
                            "
                            ><Check
                                v-if="index < currentStageIndex"
                                class="size-3.5"
                                aria-hidden="true" /><Clock3
                                v-else
                                class="size-3.5"
                                aria-hidden="true"
                        /></span>
                        <span
                            class="pt-0.5 text-sm"
                            :class="
                                index <= currentStageIndex
                                    ? 'font-medium text-slate-900'
                                    : 'text-slate-500'
                            "
                            >{{ stage.title }}</span
                        >
                    </li>
                </ol>
                <dl class="mt-7 border-t border-slate-200 pt-5 text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-slate-500">Distance</dt>
                        <dd class="font-medium text-slate-900">
                            {{ plan.distanceKm }} km
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
                <div class="mt-6 flex gap-3 bg-amber-50 p-4 text-amber-950">
                    <ShieldAlert
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p class="text-sm leading-6">
                        No ambulance has been dispatched. In an emergency, call
                        {{ emergencyContact.phone }}.
                    </p>
                </div>
                <Button as-child variant="outline" class="mt-6 w-full"
                    ><Link href="/intake/submission/complete"
                        >Back to submission</Link
                    ></Button
                >
            </aside>
        </div>
    </section>
</template>

<style>
.tracking-board .leaflet-container {
    font: inherit;
    background: #e4eee9;
}
.tracking-board .leaflet-control-attribution {
    color: #365a51;
    font-size: 10px;
}
.tracking-board .leaflet-control-attribution a {
    color: #116655;
}
.tracking-board .leaflet-bar {
    border: 0;
    box-shadow: 0 8px 24px -12px rgba(22, 53, 47, 0.55);
}
.tracking-board .leaflet-bar a {
    color: #173a33;
    border-color: #d2e2dc;
}
.medipath-map-marker {
    background: transparent;
    border: 0;
}
.map-marker {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    border: 3px solid #fff;
    box-shadow: 0 10px 28px -12px rgba(16, 45, 39, 0.75);
}
.map-marker svg {
    width: 22px;
    height: 22px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.9;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.map-marker--hospital {
    color: #fff;
    background: #116655;
}
.map-marker--patient {
    color: #173a33;
    background: #dff2ec;
}
.map-marker--ambulance {
    width: 48px;
    height: 48px;
    color: #fff;
    background: #db5364;
    border-radius: 50%;
    box-shadow:
        0 0 0 7px rgba(219, 83, 100, 0.18),
        0 12px 30px -14px rgba(16, 45, 39, 0.9);
}
@media (prefers-reduced-motion: reduce) {
    .map-marker--ambulance {
        box-shadow: 0 12px 30px -14px rgba(16, 45, 39, 0.9);
    }
}
</style>
