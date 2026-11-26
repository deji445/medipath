<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Check,
    ChevronDown,
    LockKeyhole,
    ShieldCheck,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    hospital: {
        name: string;
        fullName: string;
        address?: string | null;
        isLivePartner: boolean;
        isDemoHospital: boolean;
    };
    patient: { name: string; age: number };
    assessment: {
        severity: 'LOW' | 'MEDIUM' | 'HIGH';
        summary: string;
    };
    sharedGroups: string[];
}>();
</script>

<template>
    <Head title="Review sharing consent" />

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 sm:p-8">
            <Link
                href="/intake/routing"
                class="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-medium text-slate-600 outline-none hover:text-slate-950 focus-visible:ring-3 focus-visible:ring-teal-700/30"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Hospital selection
            </Link>

            <div class="mt-5 flex items-start gap-4">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800"
                    aria-hidden="true"
                >
                    <ShieldCheck class="size-5" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-semibold text-balance text-slate-950"
                    >
                        Confirm and save
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Your intake stays private until you select Save.
                    </p>
                </div>
            </div>

            <div
                class="mt-7 grid gap-6 border-y border-slate-200 py-6 md:grid-cols-2"
            >
                <div class="flex gap-3">
                    <Building2
                        class="mt-0.5 size-5 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-xs font-medium text-slate-500">
                            Destination
                        </p>
                        <h2 class="mt-1 font-semibold text-slate-950">
                            {{ hospital.fullName }}
                        </h2>
                        <p
                            v-if="hospital.address"
                            class="mt-1 text-sm text-slate-600"
                        >
                            {{ hospital.address }}
                        </p>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500">
                        Patient summary
                    </p>
                    <p class="mt-1 font-semibold text-slate-950">
                        {{ patient.name }} · age {{ patient.age }}
                    </p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        {{ assessment.summary }}
                    </p>
                    <p class="mt-2 text-xs font-medium text-teal-800">
                        {{ assessment.severity.toLowerCase() }}-priority
                    </p>
                </div>
            </div>

            <details class="group mt-5 border-b border-slate-200 pb-5">
                <summary
                    class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 rounded-md text-sm font-semibold text-slate-800 outline-none focus-visible:ring-3 focus-visible:ring-teal-700/30"
                >
                    What will be saved
                    <ChevronDown
                        class="size-4 transition-transform group-open:rotate-180 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </summary>
                <ul
                    class="mt-2 grid gap-2 text-sm text-slate-600 sm:grid-cols-2"
                >
                    <li
                        v-for="group in sharedGroups"
                        :key="group"
                        class="flex gap-2"
                    >
                        <Check
                            class="mt-0.5 size-4 shrink-0 text-teal-700"
                            aria-hidden="true"
                        />
                        <span>{{ group }}</span>
                    </li>
                </ul>
            </details>

            <div class="mt-5 flex gap-3 text-sm text-slate-600">
                <LockKeyhole
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <p class="max-w-2xl leading-6">
                    <template v-if="hospital.isLivePartner">
                        Only {{ hospital.name }} will receive this record.
                    </template>
                    <template v-else-if="hospital.isDemoHospital">
                        Saving adds this intake to the demo admissions queue.
                    </template>
                    <template v-else>
                        This is a MediPath demo record. {{ hospital.name }} will
                        not receive it.
                    </template>
                </p>
            </div>

            <Form
                action="/intake/submission"
                method="post"
                class="mt-5"
                v-slot="{ errors, processing }"
            >
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition-colors focus-within:border-teal-700 focus-within:ring-3 focus-within:ring-teal-700/20 hover:border-teal-300 has-[:checked]:border-teal-700 has-[:checked]:bg-teal-50"
                >
                    <input
                        type="checkbox"
                        name="share_consent"
                        value="1"
                        required
                        class="mt-0.5 size-5 shrink-0 accent-teal-700"
                    />
                    <span class="text-sm leading-6 text-slate-800">
                        <template v-if="hospital.isLivePartner">
                            I agree to share this intake with
                            {{ hospital.fullName }}.
                        </template>
                        <template v-else>
                            I agree to save this demo intake in MediPath.
                        </template>
                    </span>
                </label>
                <InputError class="mt-2" :message="errors.share_consent" />

                <div class="mt-6 flex justify-end">
                    <Button class="w-full sm:w-auto" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ processing ? 'Saving…' : 'Save' }}
                    </Button>
                </div>
            </Form>
        </div>
    </section>
</template>
