<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { Button } from '@/components/ui/button';
defineProps<{ reference: string }>();
</script>
<template>
    <Head title="Intake saved" />
    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-10"
    >
        <div
            class="mx-auto flex size-14 items-center justify-center rounded-full bg-teal-100 text-2xl text-teal-800"
            aria-hidden="true"
        >
            <Check class="size-7" />
        </div>
        <h1 class="mt-5 text-2xl font-semibold">Your intake has been saved</h1>
        <p class="mx-auto mt-3 max-w-lg text-sm text-slate-600">
            The record is safely stored as a draft and is ready for the
            assessment stage. Keep the reference below.
        </p>
        <div class="mx-auto mt-6 max-w-md rounded-xl bg-slate-100 p-4">
            <p class="text-xs tracking-wide text-slate-500 uppercase">
                Intake reference
            </p>
            <p class="mt-1 font-mono text-sm font-medium break-all">
                {{ reference }}
            </p>
        </div>
        <p class="mt-6 text-sm text-amber-800">
            Before the detailed assessment, MediPath will check for warning
            signs that may need immediate care.
        </p>
        <div class="mt-8 flex flex-col items-center gap-4">
            <Button
                as-child
                class="w-full bg-teal-700 text-white hover:bg-teal-800 sm:w-auto"
            >
                <Link href="/intake/assessment">Continue</Link>
            </Button>
            <Link
                href="/intake"
                class="rounded-sm text-sm text-slate-600 underline underline-offset-4 outline-none hover:text-slate-950 focus-visible:ring-3 focus-visible:ring-teal-700/30"
            >
                Start another intake
            </Link>
        </div>
    </section>
</template>
