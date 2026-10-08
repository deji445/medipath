<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    CircleAlert,
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

const reasonLabels: Record<string, string> = {
    NO_RED_FLAG_IDENTIFIED: 'No emergency warning sign was identified',
    SYMPTOMS_WORSENING: 'The concern is getting worse',
    SEVERE_REPORTED_INTENSITY: 'The reported intensity is severe',
    DAILY_ACTIVITY_LIMITED: 'Normal activities are limited',
    DAILY_ACTIVITY_PREVENTED: 'Normal activities cannot be managed',
    ASSOCIATED_SYMPTOMS_REPORTED: 'Additional symptoms were reported',
    PERSISTENT_SYMPTOMS: 'The concern has persisted for several days',
    ABNORMAL_VITAL_SIGNS_REPORTED:
        'One or more recorded vital signs are outside the usual adult range',
};
</script>

<template>
    <Head title="Guidance and hospital" />

    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >
        <div
            :class="[
                'rounded-xl border p-5',
                severity === 'MEDIUM'
                    ? 'border-amber-200 bg-amber-50 text-amber-950'
                    : 'border-teal-200 bg-teal-50 text-teal-950',
            ]"
        >
            <div class="flex items-start gap-4">
                <div
                    :class="[
                        'flex size-11 shrink-0 items-center justify-center rounded-xl',
                        severity === 'MEDIUM'
                            ? 'bg-amber-100 text-amber-900'
                            : 'bg-teal-100 text-teal-900',
                    ]"
                >
                    <CircleAlert class="size-5" aria-hidden="true" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-balance">
                        {{
                            severity === 'MEDIUM'
                                ? 'Seek professional care soon'
                                : 'Routine care may be appropriate'
                        }}
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6">
                        {{ guidance.summary }}
                    </p>
                </div>
            </div>
            <ul
                v-if="guidance.reasonCodes.length"
                class="mt-4 grid gap-2 border-t border-current/15 pt-4 text-sm sm:grid-cols-2"
            >
                <li
                    v-for="code in guidance.reasonCodes"
                    :key="code"
                    class="flex gap-2"
                >
                    <Check class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span>{{ reasonLabels[code] ?? code }}</span>
                </li>
            </ul>
        </div>

        <div class="mt-8 flex items-start gap-4 border-t border-slate-200 pt-7">
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800"
            >
                <Building2 class="size-5" aria-hidden="true" />
            </div>
            <div>
                <h1 class="text-2xl font-semibold text-balance text-slate-950">
                    Choose where to receive care
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    These hospitals can receive this assessment. They are ranked
                    by suitability, availability, and distance when location
                    coordinates are available.
                </p>
            </div>
        </div>

        <div
            class="mt-6 flex gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-950"
        >
            <LockKeyhole
                class="mt-0.5 size-5 shrink-0 text-teal-800"
                aria-hidden="true"
            />
            <div>
                <h2 class="font-semibold">Your intake remains private</h2>
                <p class="mt-1 leading-6">
                    Choosing a hospital does not send medical information. You
                    will review the destination and give explicit sharing
                    consent before anything is transmitted.
                </p>
            </div>
        </div>

        <div
            v-if="location"
            class="mt-6 flex items-center gap-2 text-sm text-slate-600"
        >
            <MapPin class="size-4 text-teal-700" aria-hidden="true" />
            <span>Location supplied: {{ location }}</span>
        </div>

        <div
            v-if="selectedHospital"
            class="mt-6 flex gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-950"
        >
            <Check class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p>
                <strong>{{ selectedHospital.name }}</strong> is currently
                selected. Choosing another hospital will replace it.
            </p>
        </div>

        <div v-if="hospitals.length" class="mt-7 grid gap-4">
            <article
                v-for="hospital in hospitals"
                :key="hospital.id"
                class="rounded-xl border border-slate-200 p-5"
            >
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-slate-950">
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
                        </div>
                        <p
                            v-if="hospital.address"
                            class="mt-2 text-sm text-slate-600"
                        >
                            {{ hospital.address }}
                        </p>
                        <ul
                            class="mt-4 grid gap-2 text-sm leading-5 text-slate-700"
                        >
                            <li
                                v-for="reason in hospital.reasons"
                                :key="reason"
                                class="flex gap-2.5"
                            >
                                <Check
                                    class="mt-0.5 size-4 shrink-0 text-teal-700"
                                    aria-hidden="true"
                                />
                                <span>{{ reason }}</span>
                            </li>
                        </ul>
                    </div>

                    <Form
                        action="/intake/routing"
                        method="post"
                        v-slot="{ processing, errors }"
                        class="shrink-0"
                    >
                        <input type="hidden" name="streamlined" value="1" />
                        <input
                            type="hidden"
                            name="hospital_id"
                            :value="hospital.id"
                        />
                        <label
                            v-if="hospital.requiresOverrideConfirmation"
                            class="mb-3 flex max-w-56 items-start gap-2 text-sm leading-5 text-slate-700"
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
                                    ? 'Keep this hospital'
                                    : 'Choose hospital'
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
