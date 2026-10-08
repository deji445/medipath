<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Building2, Check, LockKeyhole } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    hospital: { name: string; fullName: string; address?: string | null };
    severity: 'LOW' | 'MEDIUM' | 'HIGH';
}>();
</script>

<template>
    <Head title="Hospital selected" />

    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >
        <div
            class="flex size-12 items-center justify-center rounded-xl bg-teal-100 text-teal-900"
        >
            <Check class="size-6" aria-hidden="true" />
        </div>
        <h1 class="mt-5 text-2xl font-semibold text-balance text-slate-950">
            Hospital selected
        </h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
            {{ hospital.name }} is suitable for this
            {{ severity.toLowerCase() }}-priority assessment and is currently
            accepting prototype submissions.
        </p>

        <div class="mt-6 flex gap-4 rounded-xl bg-slate-50 p-5">
            <Building2
                class="mt-0.5 size-5 shrink-0 text-teal-700"
                aria-hidden="true"
            />
            <div>
                <h2 class="font-semibold text-slate-950">
                    {{ hospital.fullName }}
                </h2>
                <p v-if="hospital.address" class="mt-1 text-sm text-slate-600">
                    {{ hospital.address }}
                </p>
            </div>
        </div>

        <div class="mt-6 flex gap-3 rounded-xl border border-slate-200 p-4">
            <LockKeyhole
                class="mt-0.5 size-5 shrink-0 text-slate-600"
                aria-hidden="true"
            />
            <div>
                <h2 class="text-sm font-semibold text-slate-950">
                    Nothing has been shared yet
                </h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    The intake remains private until the patient reviews the
                    destination and gives explicit sharing consent.
                </p>
            </div>
        </div>

        <div class="mt-7 border-t border-slate-200 pt-6">
            <h2 class="text-sm font-semibold text-slate-950">
                Next: review and give sharing consent
            </h2>
            <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600">
                Review exactly what will be sent to {{ hospital.name }}. Nothing
                is shared until the patient gives explicit consent.
            </p>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                <Button as-child>
                    <Link href="/intake/submission">
                        Review sharing details
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link href="/intake/routing"
                        >Choose a different hospital</Link
                    >
                </Button>
            </div>
        </div>
    </section>
</template>
