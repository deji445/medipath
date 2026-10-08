<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock3,
    HeartPulse,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

export type Admission = {
    id: string;
    patient: {
        name: string;
        age: number | null;
        sexAtBirth: string | null;
        dateOfBirth: string | null;
        phone: string | null;
    };
    severity: 'HIGH' | 'MEDIUM' | 'LOW';
    recommendedAction: string;
    arrivalType: 'PRE_ARRIVAL' | 'WALK_IN_QR';
    status: 'RECEIVED' | 'ACKNOWLEDGED' | 'IN_TRIAGE' | 'CLOSED';
    receivedAt: string;
    acknowledgedAt: string | null;
    mainConcern: string;
    symptomSummary: string;
    redFlags: unknown[];
    conditions: unknown[];
    allergies: unknown[];
    medications: unknown[];
    vitals: { manual: unknown; wearable: unknown };
    location: {
        manual: string | null;
        latitude: string | null;
        longitude: string | null;
    };
    respondent: {
        fillingAs: string | null;
        name: string | null;
        relationship: string | null;
    };
    consentRecordedAt: string | null;
};

const props = defineProps<{ admission: Admission; processing?: boolean }>();
defineEmits<{ action: [status: 'ACKNOWLEDGED' | 'IN_TRIAGE' | 'CLOSED'] }>();

const nextAction = computed(() => {
    if (props.admission.status === 'RECEIVED')
        return { label: 'Acknowledge case', status: 'ACKNOWLEDGED' as const };
    if (props.admission.status === 'ACKNOWLEDGED')
        return { label: 'Start triage', status: 'IN_TRIAGE' as const };
    return null;
});

function words(value: string): string {
    return value
        .toLowerCase()
        .replaceAll('_', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

function dateTime(value: string | null): string {
    if (!value) return 'Not recorded';
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function itemText(item: unknown): string {
    if (typeof item === 'string') return item;
    if (item && typeof item === 'object') {
        const entry = item as Record<string, unknown>;
        return [entry.name, entry.dose, entry.frequency]
            .filter(Boolean)
            .join(' · ');
    }
    return String(item);
}

function vitalEntries(value: unknown): Array<[string, string]> {
    if (!value || typeof value !== 'object') return [];
    return Object.entries(value as Record<string, unknown>)
        .filter(([, reading]) => reading !== null && reading !== '')
        .map(([label, reading]) => [words(label), String(reading)]);
}
</script>

<template>
    <article class="flex h-full flex-col bg-white text-slate-950">
        <header class="border-b border-slate-200 px-5 py-5">
            <div class="flex items-start justify-between gap-4 pr-8">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        {{ admission.patient.name }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ admission.patient.age ?? 'Age not recorded'
                        }}<span v-if="admission.patient.sexAtBirth">
                            · {{ words(admission.patient.sexAtBirth) }}</span
                        >
                    </p>
                    <p
                        v-if="admission.patient.phone"
                        class="mt-1 text-sm text-slate-500"
                    >
                        {{ admission.patient.phone }}
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="{
                        'bg-red-100 text-red-700':
                            admission.severity === 'HIGH',
                        'bg-amber-100 text-amber-800':
                            admission.severity === 'MEDIUM',
                        'bg-teal-50 text-teal-800':
                            admission.severity === 'LOW',
                    }"
                >
                    {{ words(admission.severity) }} priority
                </span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3 text-sm">
                <div>
                    <p class="text-xs text-slate-500">Received</p>
                    <p class="mt-0.5 font-medium">
                        {{ dateTime(admission.receivedAt) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Arrival</p>
                    <p class="mt-0.5 font-medium">
                        {{ words(admission.arrivalType) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Status</p>
                    <p class="mt-0.5 font-medium">
                        {{ words(admission.status) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Recommended action</p>
                    <p class="mt-0.5 font-medium">
                        {{ words(admission.recommendedAction) }}
                    </p>
                </div>
            </div>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
            <section>
                <div class="flex items-center gap-2 text-slate-500">
                    <HeartPulse class="size-4" aria-hidden="true" />
                    <h3 class="text-xs font-semibold tracking-wide uppercase">
                        Main concern
                    </h3>
                </div>
                <p class="mt-2 font-semibold text-slate-950">
                    {{ admission.mainConcern }}
                </p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ admission.symptomSummary }}
                </p>
            </section>

            <section
                v-if="admission.redFlags.length"
                class="border-l border-red-500 pl-4"
            >
                <div class="flex items-center gap-2 text-red-700">
                    <AlertTriangle class="size-4" aria-hidden="true" />
                    <h3 class="text-xs font-semibold tracking-wide uppercase">
                        Deterministic red flags
                    </h3>
                </div>
                <ul class="mt-2 space-y-1.5 text-sm text-slate-700">
                    <li
                        v-for="flag in admission.redFlags"
                        :key="itemText(flag)"
                    >
                        {{ itemText(flag) }}
                    </li>
                </ul>
            </section>

            <section
                class="grid gap-5 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2"
            >
                <div>
                    <div class="flex items-center gap-2 text-slate-500">
                        <UserRound class="size-4" aria-hidden="true" />
                        <h3
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            Clinical history
                        </h3>
                    </div>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Conditions</dt>
                            <dd class="mt-0.5 text-slate-800">
                                {{
                                    admission.conditions.length
                                        ? admission.conditions
                                              .map(itemText)
                                              .join(', ')
                                        : 'None recorded'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Allergies</dt>
                            <dd class="mt-0.5 text-slate-800">
                                {{
                                    admission.allergies.length
                                        ? admission.allergies
                                              .map(itemText)
                                              .join(', ')
                                        : 'None recorded'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Medications</dt>
                            <dd class="mt-0.5 text-slate-800">
                                {{
                                    admission.medications.length
                                        ? admission.medications
                                              .map(itemText)
                                              .join(', ')
                                        : 'None recorded'
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <div class="flex items-center gap-2 text-slate-500">
                        <Activity class="size-4" aria-hidden="true" />
                        <h3
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            Vitals
                        </h3>
                    </div>
                    <div class="mt-3 space-y-3 text-sm">
                        <div
                            v-if="vitalEntries(admission.vitals.manual).length"
                        >
                            <p class="text-xs font-medium text-slate-500">
                                Manual
                            </p>
                            <p
                                v-for="[label, reading] in vitalEntries(
                                    admission.vitals.manual,
                                )"
                                :key="label"
                                class="mt-1 flex justify-between gap-3 border-b border-slate-100 pb-1"
                            >
                                <span>{{ label }}</span
                                ><span class="font-medium">{{ reading }}</span>
                            </p>
                        </div>
                        <div
                            v-if="
                                vitalEntries(admission.vitals.wearable).length
                            "
                        >
                            <p class="text-xs font-medium text-slate-500">
                                Wearable
                            </p>
                            <p
                                v-for="[label, reading] in vitalEntries(
                                    admission.vitals.wearable,
                                )"
                                :key="label"
                                class="mt-1 flex justify-between gap-3 border-b border-slate-100 pb-1"
                            >
                                <span>{{ label }}</span
                                ><span class="font-medium">{{ reading }}</span>
                            </p>
                        </div>
                        <p
                            v-if="
                                !vitalEntries(admission.vitals.manual).length &&
                                !vitalEntries(admission.vitals.wearable).length
                            "
                            class="text-slate-600"
                        >
                            No vitals recorded.
                        </p>
                    </div>
                </div>
            </section>

            <section
                v-if="
                    admission.location.manual ||
                    admission.respondent.fillingAs !== 'SELF'
                "
                class="grid gap-5 border-t border-slate-200 pt-5 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2"
            >
                <div v-if="admission.location.manual">
                    <p
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Supplied location
                    </p>
                    <p class="mt-2 text-sm text-slate-800">
                        {{ admission.location.manual }}
                    </p>
                </div>
                <div v-if="admission.respondent.fillingAs !== 'SELF'">
                    <p
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Completed by
                    </p>
                    <p class="mt-2 text-sm text-slate-800">
                        {{
                            admission.respondent.name ??
                            'Companion or caregiver'
                        }}
                        <span v-if="admission.respondent.relationship">
                            · {{ admission.respondent.relationship }}</span
                        >
                    </p>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-4">
                <div class="flex items-start gap-3">
                    <ShieldCheck
                        class="mt-0.5 size-4 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <div>
                        <h3 class="text-sm font-medium">
                            Patient sharing consent
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Recorded {{ dateTime(admission.consentRecordedAt) }}
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <footer
            v-if="admission.status !== 'CLOSED'"
            class="border-t border-slate-200 bg-slate-50 px-5 py-4"
        >
            <div
                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end xl:flex-col-reverse 2xl:flex-row"
            >
                <Button
                    v-if="admission.status === 'IN_TRIAGE'"
                    variant="outline"
                    class="border-slate-300 text-slate-700"
                    :disabled="processing"
                    @click="$emit('action', 'CLOSED')"
                    ><CheckCircle2 /> Close case</Button
                >
                <Button
                    v-if="nextAction"
                    class="bg-teal-700 text-white hover:bg-teal-800"
                    :disabled="processing"
                    @click="$emit('action', nextAction.status)"
                    ><Clock3 v-if="processing" class="animate-spin" />{{
                        processing ? 'Updating…' : nextAction.label
                    }}</Button
                >
            </div>
        </footer>
    </article>
</template>
