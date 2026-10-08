<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Medication = {
    name: string;
    dose?: string | null;
    frequency?: string | null;
};
type History = {
    conditions: string[];
    allergies: string[];
    medications: Medication[];
};
type Identity = {
    first_name: string;
    last_name: string;
    date_of_birth?: string | null;
};

defineProps<{
    identity: Identity;
    phoneNumber: string;
    history: History;
    visit: Record<string, string | number | boolean | null>;
}>();
const show = (value: unknown) =>
    value === null || value === undefined || value === ''
        ? 'Not provided'
        : String(value).replaceAll('_', ' ').toLowerCase();
</script>

<template>
    <Head title="Review intake" />
    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >
        <h1 class="text-2xl font-semibold">Review your details</h1>
        <p class="mt-2 text-sm text-slate-600">
            Check the information below before creating the intake record.
        </p>
        <div
            class="mt-8 grid border-y border-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-slate-200"
        >
            <section class="py-6 sm:pr-8">
                <h2 class="font-semibold">Patient</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-slate-500">Name</dt>
                        <dd>
                            {{ identity.first_name }} {{ identity.last_name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Phone</dt>
                        <dd>{{ phoneNumber }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Date of birth</dt>
                        <dd>{{ show(identity.date_of_birth) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Age</dt>
                        <dd>{{ visit.age_years }} years old</dd>
                    </div>
                </dl>
            </section>
            <section
                class="border-t border-slate-200 py-6 sm:border-t-0 sm:pl-8"
            >
                <h2 class="font-semibold">Current concern</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-slate-500">Main concern</dt>
                        <dd>{{ visit.main_complaint }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Started</dt>
                        <dd>{{ show(visit.symptom_onset) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Location</dt>
                        <dd>{{ show(visit.manual_location) }}</dd>
                    </div>
                </dl>
            </section>
            <section class="border-t border-slate-200 py-6 sm:col-span-2">
                <h2 class="font-semibold">Medical history</h2>
                <div class="mt-3 grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <p class="text-slate-500">Conditions</p>
                        <p>
                            {{
                                history.conditions.length
                                    ? history.conditions.join(', ')
                                    : 'None reported'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">Allergies</p>
                        <p>
                            {{
                                history.allergies.length
                                    ? history.allergies.join(', ')
                                    : 'None reported'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">Medications</p>
                        <p>
                            {{
                                history.medications.length
                                    ? history.medications
                                          .map((item) =>
                                              [
                                                  item.name,
                                                  item.dose,
                                                  item.frequency,
                                              ]
                                                  .filter(Boolean)
                                                  .join(' · '),
                                          )
                                          .join(', ')
                                    : 'None reported'
                            }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
        <div
            class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"
        >
            If symptoms are severe or life-threatening, contact local emergency
            services now. MediPath is not a diagnosis.
        </div>
        <Form
            action="/intake/review"
            method="post"
            class="mt-8 flex items-center justify-between gap-4"
            v-slot="{ processing }"
            ><Link
                href="/intake/visit"
                class="rounded-sm text-sm text-slate-600 underline underline-offset-4 outline-none hover:text-slate-950 focus-visible:ring-3 focus-visible:ring-teal-700/30"
                >Back and edit</Link
            ><Button
                class="bg-teal-700 text-white hover:bg-teal-800"
                :disabled="processing"
                ><Spinner v-if="processing" />Create intake</Button
            ></Form
        >
    </section>
</template>
