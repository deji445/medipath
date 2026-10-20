<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    Check,
    ClipboardList,
    LockKeyhole,
    ShieldCheck,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    hospital: { name: string; fullName: string; address?: string | null };
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

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="p-6 sm:p-8">
            <div class="flex items-start gap-4">
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
                        Review before sharing
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                        The intake is still private. Confirm the destination and
                        information below before sending it to the hospital.
                    </p>
                </div>
            </div>

            <div
                class="mt-6 flex gap-4 rounded-xl border border-teal-200 bg-teal-50 p-5"
            >
                <Building2
                    class="mt-0.5 size-5 shrink-0 text-teal-800"
                    aria-hidden="true"
                />
                <div>
                    <p class="text-xs font-medium text-teal-800">
                        Selected destination
                    </p>
                    <h2 class="mt-1 font-semibold text-teal-950">
                        {{ hospital.fullName }}
                    </h2>
                    <p
                        v-if="hospital.address"
                        class="mt-1 text-sm text-teal-900"
                    >
                        {{ hospital.address }}
                    </p>
                </div>
            </div>

            <div class="mt-7 grid gap-7 md:grid-cols-[1fr_0.9fr]">
                <div>
                    <div class="flex items-center gap-2">
                        <ClipboardList
                            class="size-5 text-teal-700"
                            aria-hidden="true"
                        />
                        <h2 class="font-semibold text-slate-950">
                            Information that will be shared
                        </h2>
                    </div>
                    <ul class="mt-4 grid gap-3 text-sm text-slate-700">
                        <li
                            v-for="group in sharedGroups"
                            :key="group"
                            class="flex items-start gap-3"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-teal-700"
                                aria-hidden="true"
                            />
                            <span>{{ group }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl bg-slate-50 p-5">
                    <p class="text-sm font-semibold text-slate-950">
                        {{ patient.name }} · age {{ patient.age }}
                    </p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ assessment.summary }}
                    </p>
                    <p class="mt-4 text-xs font-medium text-slate-500">
                        {{ assessment.severity.toLowerCase() }}-priority
                        assessment
                    </p>
                </div>
            </div>

            <div
                class="mt-7 flex gap-3 border-t border-slate-200 pt-6 text-sm text-slate-600"
            >
                <LockKeyhole
                    class="mt-0.5 size-5 shrink-0 text-slate-500"
                    aria-hidden="true"
                />
                <p class="max-w-2xl leading-6">
                    This creates a read-only clinical snapshot for
                    {{ hospital.name }}. It is not sent to other hospitals, and
                    hospital staff cannot change the facts the patient
                    submitted.
                </p>
            </div>

            <Form
                action="/intake/submission"
                method="post"
                class="mt-7"
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
                        I confirm that the patient agrees to share this intake
                        with {{ hospital.fullName }} for pre-arrival review.
                    </span>
                </label>
                <InputError class="mt-2" :message="errors.share_consent" />

                <div
                    class="mt-6 flex flex-col gap-3 sm:flex-row-reverse sm:items-center sm:justify-between"
                >
                    <Button class="w-full sm:w-auto" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ processing ? 'Sending securely…' : 'Send intake' }}
                        <ArrowRight
                            v-if="!processing"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </Button>
                    <Link
                        href="/intake/routing"
                        class="rounded-sm text-center text-sm text-slate-600 underline underline-offset-4 outline-none hover:text-slate-950 focus-visible:ring-3 focus-visible:ring-teal-700/30"
                    >
                        Back to hospital selection
                    </Link>
                </div>
            </Form>
        </div>
    </section>
</template>
