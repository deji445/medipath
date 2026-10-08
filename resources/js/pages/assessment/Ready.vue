<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, ShieldCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{ mainConcern?: string | null }>();
</script>

<template>
    <Head title="Safety check complete" />

    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >
        <div
            class="flex size-12 items-center justify-center rounded-xl bg-teal-100 text-teal-800"
        >
            <ShieldCheck class="size-6" aria-hidden="true" />
        </div>
        <h1 class="mt-5 text-2xl font-semibold text-balance">
            Safety check complete
        </h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
            No emergency warning sign was selected. This does not mean the
            symptoms are safe or provide a diagnosis; the detailed assessment
            still needs to review the concern.
        </p>

        <div v-if="mainConcern" class="mt-6 rounded-xl bg-slate-100 p-4">
            <p
                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
            >
                Main concern
            </p>
            <p class="mt-1 text-sm text-slate-900">{{ mainConcern }}</p>
        </div>

        <div
            class="mt-6 flex items-start gap-3 text-sm leading-6 text-slate-700"
        >
            <Check
                class="mt-0.5 size-5 shrink-0 text-teal-700"
                aria-hidden="true"
            />
            <p>
                Your answers were saved and the assessment is ready for detailed
                symptom questions.
            </p>
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <Button as-child class="bg-teal-700 text-white sm:w-auto">
                <Link href="/intake/assessment">Start detailed assessment</Link>
            </Button>
            <Button as-child variant="outline">
                <Link href="/">Return home</Link>
            </Button>
        </div>
    </section>
</template>
