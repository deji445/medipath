<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, PhoneCall, TriangleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Warning = {
    code: string;
    question: string;
};

defineProps<{
    matchedWarnings: Warning[];
    emergencyContact: { label: string; phone?: string | null };
}>();
</script>

<template>
    <Head title="Seek emergency care now" />

    <section
        class="overflow-hidden rounded-2xl border border-[#efbdc5] bg-white shadow-sm"
    >
        <div class="bg-[#c53f52] px-6 py-7 text-white sm:px-8">
            <TriangleAlert class="size-8" aria-hidden="true" />
            <h1 class="mt-4 text-2xl font-semibold text-balance">
                Seek emergency care now
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#fff0f2]">
                One or more answers may indicate an emergency. MediPath cannot
                diagnose the cause or confirm that it is safe to wait.
            </p>
        </div>

        <div class="p-6 sm:p-8">
            <div class="rounded-xl bg-[#fff0f2] p-5 text-[#7d2433]">
                <div class="flex items-start gap-3">
                    <PhoneCall
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <div>
                        <h2 class="font-semibold">Act now</h2>
                        <p class="mt-1 text-sm leading-6">
                            Contact {{ emergencyContact.label }} or go to the
                            nearest appropriate emergency facility. Ask someone
                            nearby to help if possible.
                        </p>
                        <a
                            v-if="emergencyContact.phone"
                            :href="`tel:${emergencyContact.phone}`"
                            class="mt-3 inline-flex font-semibold underline underline-offset-4"
                        >
                            Call {{ emergencyContact.phone }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <h2 class="font-semibold">Warning signs reported</h2>
                <ul class="mt-3 space-y-2 text-sm leading-6 text-slate-700">
                    <li
                        v-for="warning in matchedWarnings"
                        :key="warning.code"
                        class="flex gap-3"
                    >
                        <span
                            class="mt-2 size-1.5 shrink-0 rounded-full bg-[#c53f52]"
                            aria-hidden="true"
                        />
                        <span>{{ warning.question }}</span>
                    </li>
                </ul>
            </div>

            <p class="mt-6 text-sm leading-6 text-slate-600">
                The intake answers have been saved so they can be reviewed
                later. Do not continue the ordinary automated assessment while
                urgent help may be needed.
            </p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <Button
                    as-child
                    class="bg-[#c53f52] text-white hover:bg-[#ad3145]"
                >
                    <Link href="/intake/routing">
                        <Building2 class="size-4" aria-hidden="true" />
                        Find an emergency-capable hospital
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link href="/">Return to MediPath home</Link>
                </Button>
            </div>
        </div>
    </section>
</template>
