<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Ambulance,
    ArrowRight,
    Building2,
    Check,
    Clock3,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    submission: {
        reference: string;
        receivedAt?: string | null;
        status: 'RECEIVED' | 'ACKNOWLEDGED' | 'IN_TRIAGE' | 'CLOSED';
    };
    hospital: {
        name: string;
        fullName: string;
        address?: string | null;
        isLivePartner: boolean;
    };
}>();

const statusLabel = computed(() =>
    !props.hospital.isLivePartner
        ? 'Saved in MediPath demo'
        : {
              RECEIVED: 'Waiting for acknowledgement',
              ACKNOWLEDGED: 'Acknowledged by the hospital',
              IN_TRIAGE: 'Triage is in progress',
              CLOSED: 'Case closed by the hospital',
          }[props.submission.status],
);

const referenceGroups = computed(() => props.submission.reference.split('-'));

function dateTime(value?: string | null): string {
    if (!value) return 'Just now';

    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Intake sent" />

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="bg-teal-700 px-6 py-7 text-white sm:px-8">
            <div
                class="flex size-12 items-center justify-center rounded-xl bg-white/15"
                aria-hidden="true"
            >
                <Check class="size-6" />
            </div>
            <h1 class="mt-5 text-2xl font-semibold text-balance">
                {{
                    hospital.isLivePartner
                        ? 'Intake sent to'
                        : 'Demo intake saved for'
                }}
                {{ hospital.name }}
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-teal-50">
                <template v-if="hospital.isLivePartner">
                    The hospital admissions team can now review the patient’s
                    submitted information before arrival.
                </template>
                <template v-else>
                    This record is stored in MediPath for demonstration. It has
                    not been sent to the facility and does not reserve care.
                </template>
            </p>
        </div>

        <div class="p-6 sm:p-8">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex gap-3">
                    <Building2
                        class="mt-0.5 size-5 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-xs font-medium text-slate-500">
                            Destination
                        </p>
                        <p class="mt-1 font-semibold text-slate-950">
                            {{ hospital.fullName }}
                        </p>
                        <p
                            v-if="hospital.address"
                            class="mt-1 text-sm text-slate-600"
                        >
                            {{ hospital.address }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <Clock3
                        class="mt-0.5 size-5 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-xs font-medium text-slate-500">
                            Received
                        </p>
                        <p class="mt-1 font-semibold text-slate-950">
                            {{ dateTime(submission.receivedAt) }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            Status: {{ statusLabel }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-7 rounded-xl bg-slate-50 p-5">
                <p class="text-xs font-medium text-slate-500">
                    Submission reference
                </p>
                <p
                    class="mt-2 flex flex-wrap font-mono text-xs font-semibold tabular-nums sm:text-sm"
                >
                    <span
                        v-for="(group, index) in referenceGroups"
                        :key="`${group}-${index}`"
                        class="whitespace-nowrap"
                    >
                        {{ group
                        }}{{ index < referenceGroups.length - 1 ? '-' : '' }}
                    </span>
                </p>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Keep this reference for the demonstration and hospital
                    check-in.
                </p>
            </div>

            <div
                class="mt-7 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950"
            >
                <ShieldCheck
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <p class="text-sm leading-6">
                    <template v-if="hospital.isLivePartner">
                        Sending this intake does not confirm an appointment or
                        replace emergency care.
                    </template>
                    <template v-else>
                        This directory selection does not contact the facility,
                        request an ambulance, or confirm an appointment.
                    </template>
                    If symptoms become severe, seek urgent professional help
                    immediately.
                </p>
            </div>

            <section class="mt-7 border-t border-slate-200 pt-7">
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800"
                    >
                        <Ambulance class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-slate-950">
                                Preview the ambulance journey
                            </h2>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
                                >Simulation</span
                            >
                        </div>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-slate-600"
                        >
                            Follow a demonstration ambulance from
                            {{ hospital.name }} to the patient and back to the
                            hospital. This does not request or dispatch a real
                            ambulance.
                        </p>
                    </div>
                </div>
                <Button as-child class="mt-5">
                    <Link href="/intake/submission/ambulance-demo">
                        Open tracking demo
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </section>

            <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                <Button as-child variant="outline">
                    <Link href="/">Return to MediPath home</Link>
                </Button>
                <Button as-child variant="ghost">
                    <Link href="/intake">Start another intake</Link>
                </Button>
            </div>
        </div>
    </section>
</template>
