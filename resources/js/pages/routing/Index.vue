<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    CircleAlert,
    ExternalLink,
    LockKeyhole,
    MapPin,
    ShieldCheck,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Hospital = {
    id: string;
    name: string;
    fullName: string;
    address?: string | null;
    capability: 'ER_CAPABLE' | 'CLINIC_ONLY';
    routingMode: 'AUTO' | 'MANUAL';
    distanceKm?: number | null;
    availabilityUpdatedAt?: string | null;
    isLivePartner: boolean;
    directorySourceUrl?: string | null;
    directoryVerifiedAt?: string | null;
    reasons: string[];
    recommended: boolean;
    requiresOverrideConfirmation: boolean;
};

defineProps<{
    severity: 'LOW' | 'MEDIUM' | 'HIGH';
    guidance: {
        summary: string;
        recommendedAction: string;
        reasonCodes: string[];
    };
    location?: string | null;
    hospitals: Hospital[];
    selectedHospital?: { id: string; name: string } | null;
}>();
</script>

<template>
    <Head title="Guidance and hospital" />

    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
        <header class="flex items-start gap-3">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800"
            >
                <Building2 class="size-5" aria-hidden="true" />
            </div>
            <div>
                <h1 class="text-2xl font-semibold text-balance text-slate-950">
                    Choose a hospital
                </h1>
                <p
                    class="mt-1.5 flex max-w-2xl items-start gap-2 text-sm leading-6 text-slate-600"
                >
                    <LockKeyhole
                        class="mt-1 size-4 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <span>
                        Nothing is shared with a hospital without explicit
                        consent.
                    </span>
                </p>
            </div>
        </header>

        <div
            :class="[
                'mt-6 flex items-start gap-3 rounded-xl border px-4 py-3',
                severity === 'MEDIUM'
                    ? 'border-amber-200 bg-amber-50 text-amber-950'
                    : 'border-teal-200 bg-teal-50 text-teal-950',
            ]"
        >
            <CircleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div>
                <h2 class="font-semibold">
                    {{
                        severity === 'MEDIUM'
                            ? 'Seek professional care soon'
                            : 'Routine care may be appropriate'
                    }}
                </h2>
                <p class="mt-0.5 text-sm leading-5">
                    {{ guidance.summary }}
                </p>
            </div>
        </div>

        <div
            v-if="location"
            class="mt-4 flex items-center gap-2 text-sm text-slate-600"
        >
            <MapPin class="size-4 text-teal-700" aria-hidden="true" />
            <span>Location supplied: {{ location }}</span>
        </div>

        <div
            v-if="selectedHospital"
            class="mt-4 flex gap-3 rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-950"
        >
            <Check class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p>
                <strong>{{ selectedHospital.name }}</strong> is currently
                selected. Choosing another hospital will replace it.
            </p>
        </div>

        <div v-if="hospitals.length" class="mt-6 grid gap-3">
            <article
                v-for="hospital in hospitals"
                :key="hospital.id"
                :class="[
                    'rounded-xl border p-4 sm:p-5',
                    hospital.recommended
                        ? 'border-teal-300 bg-teal-50/30'
                        : 'border-slate-200',
                ]"
            >
                <div
                    class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-950">
                                {{ hospital.name }}
                            </h2>
                            <span
                                v-if="hospital.recommended"
                                class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-900"
                            >
                                Best match
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
                            >
                                {{
                                    hospital.capability === 'ER_CAPABLE'
                                        ? 'Emergency capable'
                                        : 'Clinic'
                                }}
                            </span>
                            <span class="text-xs font-medium text-slate-500">
                                {{
                                    hospital.isLivePartner
                                        ? 'MediPath connected'
                                        : 'Directory listing'
                                }}
                            </span>
                        </div>
                        <p
                            v-if="hospital.address"
                            class="mt-2 text-sm text-slate-600"
                        >
                            {{ hospital.address }}
                        </p>
                        <p
                            v-if="
                                hospital.distanceKm !== null &&
                                hospital.distanceKm !== undefined
                            "
                            class="mt-1.5 flex items-center gap-1.5 text-sm font-medium text-teal-800"
                        >
                            <MapPin class="size-4" aria-hidden="true" />
                            {{ hospital.distanceKm.toFixed(1) }} km away
                        </p>
                    </div>

                    <Form
                        action="/intake/routing"
                        method="post"
                        v-slot="{ processing, errors }"
                        class="w-full sm:w-auto"
                    >
                        <input type="hidden" name="streamlined" value="1" />
                        <input
                            type="hidden"
                            name="hospital_id"
                            :value="hospital.id"
                        />
                        <label
                            v-if="hospital.requiresOverrideConfirmation"
                            class="mb-3 flex max-w-60 items-start gap-2 text-sm leading-5 text-slate-700"
                        >
                            <input
                                type="checkbox"
                                name="override_confirmation"
                                value="1"
                                :aria-describedby="
                                    errors.override_confirmation
                                        ? `override-confirmation-error-${hospital.id}`
                                        : undefined
                                "
                                :aria-invalid="
                                    Boolean(errors.override_confirmation)
                                "
                                class="mt-1 size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                            />
                            <span>
                                I understand this is a less suitable option for
                                this assessment.
                            </span>
                        </label>
                        <Button
                            :variant="
                                hospital.recommended ? 'default' : 'outline'
                            "
                            :class="
                                hospital.recommended
                                    ? 'w-full bg-teal-700 text-white hover:bg-teal-800 sm:w-auto'
                                    : 'w-full sm:w-auto'
                            "
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            {{
                                selectedHospital?.id === hospital.id
                                    ? 'Keep'
                                    : 'Choose'
                            }}
                        </Button>
                        <InputError
                            class="mt-2 max-w-52"
                            :message="errors.hospital_id"
                        />
                        <InputError
                            :id="`override-confirmation-error-${hospital.id}`"
                            class="mt-2 max-w-52"
                            :message="errors.override_confirmation"
                        />
                    </Form>
                </div>

                <details
                    class="group mt-4 border-t border-slate-200 pt-3 text-sm text-slate-600"
                >
                    <summary
                        class="w-fit cursor-pointer rounded-sm font-medium text-teal-800 marker:text-slate-400 hover:text-teal-950 focus-visible:ring-3 focus-visible:ring-teal-700/25 focus-visible:outline-none"
                    >
                        Hospital details
                    </summary>
                    <div class="mt-3 max-w-2xl space-y-3 text-sm leading-5">
                        <ul v-if="hospital.reasons.length" class="grid gap-1.5">
                            <li
                                v-for="reason in hospital.reasons"
                                :key="reason"
                                class="flex gap-2"
                            >
                                <Check
                                    class="mt-0.5 size-4 shrink-0 text-teal-700"
                                    aria-hidden="true"
                                />
                                <span>{{ reason }}</span>
                            </li>
                        </ul>
                        <p v-if="hospital.isLivePartner">
                            Live connection enabled. This does not guarantee bed
                            capacity or an appointment.
                        </p>
                        <p v-else>
                            Directory details verified
                            {{ hospital.directoryVerifiedAt ?? 'recently' }}.
                            Live opening status and bed capacity are
                            unavailable.
                        </p>
                        <a
                            v-if="hospital.directorySourceUrl"
                            :href="hospital.directorySourceUrl"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center gap-1.5 font-medium text-teal-800 underline underline-offset-4 hover:text-teal-950 focus-visible:rounded-sm focus-visible:ring-3 focus-visible:ring-teal-700/25 focus-visible:outline-none"
                        >
                            View facility source
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                        </a>
                    </div>
                </details>
            </article>
        </div>

        <div
            v-else
            class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-950"
        >
            <div class="flex gap-3">
                <ShieldCheck
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <h2 class="font-semibold">
                        No eligible hospital is currently available
                    </h2>
                    <p class="mt-1 text-sm leading-6">
                        No active facility can safely receive this prototype
                        assessment. Seek professional care directly instead of
                        waiting for MediPath.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
