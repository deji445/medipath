<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    conditionsText?: string;
    allergiesText?: string;
    medicationsText?: string;
}>();

const textareaClass =
    'min-h-28 w-full appearance-none rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-xs outline-none placeholder:text-slate-400 focus-visible:border-teal-700 focus-visible:ring-[3px] focus-visible:ring-teal-700/25';
</script>

<template>
    <Head title="Medical history" />

    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >
        <h1 class="text-2xl font-semibold">Medical history</h1>
        <p class="mt-2 text-sm text-slate-600">
            Enter one item per line. Leave a section blank when there is nothing
            to report.
        </p>

        <Form
            action="/intake/history"
            method="post"
            class="mt-8 space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="conditions_text">Current medical conditions</Label>
                <textarea
                    id="conditions_text"
                    name="conditions_text"
                    :class="textareaClass"
                    :value="conditionsText"
                    placeholder="Asthma&#10;High blood pressure"
                />
                <InputError :message="errors.conditions_text" />
            </div>

            <div class="grid gap-2">
                <Label for="allergies_text">Allergies</Label>
                <textarea
                    id="allergies_text"
                    name="allergies_text"
                    :class="textareaClass"
                    :value="allergiesText"
                    placeholder="Penicillin"
                />
                <InputError :message="errors.allergies_text" />
            </div>

            <div class="grid gap-2">
                <Label for="medications_text">Current medications</Label>
                <textarea
                    id="medications_text"
                    name="medications_text"
                    :class="textareaClass"
                    :value="medicationsText"
                    placeholder="Medicine | Dose | Frequency&#10;Salbutamol | 2 puffs | As needed"
                />
                <p class="text-xs text-slate-500">
                    Format: medicine | dose | frequency
                </p>
                <InputError :message="errors.medications_text" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <Link
                    href="/intake/identity"
                    class="rounded-sm text-sm text-slate-600 underline underline-offset-4 outline-none hover:text-slate-950 focus-visible:ring-3 focus-visible:ring-teal-700/30"
                    >Back</Link
                >
                <Button
                    class="bg-teal-700 text-white hover:bg-teal-800"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Continue
                </Button>
            </div>
        </Form>
    </section>
</template>
