<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    LocateFixed,
    MapPin,
    PhoneCall,
    ShieldAlert,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Hospital = {
    id: string;
    name: string;
    fullName: string;
    address?: string | null;
    latitude?: number | null;
    longitude?: number | null;
};

type LocatedHospital = Hospital & { distanceKm: number | null };

const props = defineProps<{
    emergencyContact: { label: string; phone?: string | null };
    hospitals: Hospital[];
}>();

const locationState = ref<'idle' | 'locating' | 'ready' | 'error'>('idle');
const locationError = ref('');
const userLocation = ref<{ latitude: number; longitude: number } | null>(null);
const emergencyNumber = computed(
    () => props.emergencyContact.phone ?? 'your local emergency number',
);

const toRadians = (value: number): number => (value * Math.PI) / 180;

const distanceInKilometres = (
    latitude: number,
    longitude: number,
    hospital: Hospital,
): number | null => {
    if (hospital.latitude == null || hospital.longitude == null) {
        return null;
    }

    const earthRadius = 6371;
    const latFrom = toRadians(latitude);
    const latTo = toRadians(hospital.latitude);
    const latDelta = latTo - latFrom;
    const lonDelta = toRadians(hospital.longitude - longitude);

    const angle =
        2 *
        Math.asin(
            Math.sqrt(
                Math.sin(latDelta / 2) ** 2 +
                    Math.cos(latFrom) *
                        Math.cos(latTo) *
                        Math.sin(lonDelta / 2) ** 2,
            ),
        );

    return Math.round(earthRadius * angle * 10) / 10;
};

const rankedHospitals = computed<LocatedHospital[]>(() => {
    const location = userLocation.value;
    const hospitals = props.hospitals.map((hospital) => ({
        ...hospital,
        distanceKm: location
            ? distanceInKilometres(
                  location.latitude,
                  location.longitude,
                  hospital,
              )
            : null,
    }));

    return hospitals.sort((left, right) => {
        const leftDistance = left.distanceKm ?? Number.POSITIVE_INFINITY;
        const rightDistance = right.distanceKm ?? Number.POSITIVE_INFINITY;

        return (
            leftDistance - rightDistance || left.name.localeCompare(right.name)
        );
    });
});

const findNearbyHospital = (): void => {
    if (!navigator.geolocation) {
        locationState.value = 'error';
        locationError.value = `Location is unavailable on this device. Use the emergency facility list below or call ${emergencyNumber.value}.`;
        return;
    }

    locationState.value = 'locating';
    locationError.value = '';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            userLocation.value = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
            };
            locationState.value = 'ready';
        },
        () => {
            locationState.value = 'error';
            locationError.value = `We could not access your location. Call ${emergencyNumber.value} now if help is urgent, or use the facility list below.`;
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 },
    );
};
</script>

<template>
    <Head title="Emergency help" />

    <section
        class="overflow-hidden rounded-2xl border border-[#efbdc5] bg-white shadow-sm"
    >
        <div class="bg-[#c53f52] px-6 py-8 text-white sm:px-8 sm:py-10">
            <div class="flex items-start gap-4">
                <ShieldAlert class="mt-1 size-8 shrink-0" aria-hidden="true" />
                <div class="min-w-0">
                    <h1
                        class="max-w-xl text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl"
                    >
                        <template v-if="emergencyContact.phone">
                            Call {{ emergencyContact.phone }} now
                        </template>
                        <template v-else>
                            Call emergency services now
                        </template>
                    </h1>
                    <p
                        id="call-first-guidance"
                        class="mt-3 max-w-2xl leading-7 text-[#fff0f2]"
                    >
                        If someone may be in immediate danger, call before using
                        MediPath’s facility finder or intake.
                    </p>
                </div>
            </div>

            <a
                v-if="emergencyContact.phone"
                :href="`tel:${emergencyContact.phone}`"
                aria-describedby="call-first-guidance"
                class="mt-7 inline-flex min-h-14 w-full items-center justify-center gap-3 rounded-md bg-white px-6 py-4 text-lg font-semibold text-[#8a2637] outline-none hover:bg-[#fff0f2] focus-visible:ring-3 focus-visible:ring-white/60 sm:w-auto"
            >
                <PhoneCall class="size-6" aria-hidden="true" />
                Call {{ emergencyContact.phone }} now
            </a>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-[#fff0f2]">
                {{ emergencyContact.label }}. If you cannot make the call, ask
                someone nearby to call.
            </p>
        </div>

        <div class="border-b border-slate-200 bg-white px-6 py-6 sm:px-8">
            <h2 class="text-xl font-semibold text-slate-950">
                While help is on the way
            </h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Use the location and facility tools only after
                {{ emergencyNumber }} has been called, or while another person
                is making the call.
            </p>
        </div>

        <div class="grid lg:grid-cols-[0.9fr_1.1fr]">
            <div
                class="border-b border-slate-200 p-6 sm:p-8 lg:border-r lg:border-b-0"
            >
                <h2 class="text-xl font-semibold text-slate-950">
                    Find a nearby listed demo facility
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Emergency mode treats the situation as high urgency and only
                    considers active, emergency-capable facilities. Your
                    location is used on this device and is not saved or sent.
                </p>

                <Button
                    class="mt-6 h-12 w-full bg-[#197f6d] text-white hover:bg-[#116655]"
                    :disabled="locationState === 'locating'"
                    @click="findNearbyHospital"
                >
                    <Spinner v-if="locationState === 'locating'" />
                    <LocateFixed v-else class="size-5" aria-hidden="true" />
                    {{
                        locationState === 'locating'
                            ? 'Finding your location…'
                            : locationState === 'ready'
                              ? 'Refresh nearby result'
                              : 'Use my location'
                    }}
                </Button>

                <p
                    v-if="locationState === 'ready'"
                    class="mt-4 flex items-start gap-2 text-sm leading-6 text-teal-800"
                    role="status"
                >
                    <LocateFixed
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    Facilities are now ordered by straight-line distance.
                </p>
                <p
                    v-if="locationState === 'error'"
                    class="mt-4 text-sm leading-6 text-[#a82f43]"
                    role="alert"
                >
                    {{ locationError }}
                </p>
            </div>

            <div class="bg-slate-50 p-6 sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-slate-950">
                        Emergency-capable facilities
                    </h2>
                    <span class="shrink-0 text-sm text-slate-500 tabular-nums"
                        >{{ hospitals.length }} found</span
                    >
                </div>

                <div v-if="rankedHospitals.length" class="mt-5 grid gap-3">
                    <article
                        v-for="(hospital, index) in rankedHospitals"
                        :key="hospital.id"
                        class="rounded-xl border border-slate-200 bg-white p-5"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#fff0f2] text-[#b53549]"
                            >
                                <Building2 class="size-5" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    v-if="
                                        locationState === 'ready' &&
                                        index === 0 &&
                                        hospital.distanceKm != null
                                    "
                                    class="text-xs font-semibold text-teal-700"
                                >
                                    Nearest listed emergency-capable demo
                                    facility
                                </p>
                                <h3 class="font-semibold text-slate-950">
                                    {{ hospital.name }}
                                </h3>
                                <p
                                    v-if="hospital.address"
                                    class="mt-1 flex items-start gap-1.5 text-sm leading-5 text-slate-600"
                                >
                                    <MapPin
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ hospital.address }}
                                </p>
                                <p
                                    v-if="hospital.distanceKm != null"
                                    class="mt-2 text-sm font-medium text-slate-800 tabular-nums"
                                >
                                    Approximately {{ hospital.distanceKm }} km
                                    away
                                </p>
                                <p
                                    v-else-if="locationState === 'ready'"
                                    class="mt-2 text-sm text-slate-500"
                                >
                                    Distance unavailable for this facility
                                </p>
                                <a
                                    v-if="
                                        hospital.latitude != null &&
                                        hospital.longitude != null
                                    "
                                    :href="`https://www.google.com/maps/dir/?api=1&destination=${hospital.latitude},${hospital.longitude}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-3 inline-flex items-center gap-2 rounded-sm text-sm font-semibold text-teal-700 outline-none hover:text-teal-800 focus-visible:ring-3 focus-visible:ring-teal-700/25"
                                >
                                    Get directions
                                    <ArrowRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-5 rounded-xl border border-slate-200 bg-white p-5"
                >
                    <p class="font-semibold text-slate-950">
                        No demo emergency facility is currently listed
                    </p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Call {{ emergencyNumber }} instead of waiting for
                        MediPath to find a destination.
                    </p>
                </div>

                <p class="mt-5 text-xs leading-5 text-slate-500">
                    Prototype data only. This list does not show live ambulance,
                    bed, or clinician availability. In an emergency, follow the
                    instructions from {{ emergencyNumber }}.
                </p>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 border-t border-slate-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8"
        >
            <div>
                <h2 class="font-semibold text-slate-950">
                    Not an immediate emergency?
                </h2>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                    Continue with the guided intake for urgency guidance and
                    hospital matching. It does not provide a diagnosis.
                </p>
            </div>
            <Button as-child variant="outline" class="h-11 shrink-0">
                <Link href="/intake">
                    Start guided intake
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </Button>
        </div>
    </section>
</template>
