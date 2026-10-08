<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Building2, ClipboardCheck, ShieldCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    hospital: {
        name: string;
        fullName: string;
        address: string | null;
        capability: 'ER_CAPABLE' | 'CLINIC_ONLY';
    };
    startUrl: string;
}>();
</script>

<template>
    <Head :title="`Walk-in intake for ${hospital.name}`" />
    <main
        class="min-h-screen bg-slate-50 px-4 py-10 text-slate-950 sm:px-6 sm:py-16"
    >
        <div class="mx-auto max-w-2xl">
            <Link
                href="/"
                class="text-sm font-semibold text-teal-800 underline underline-offset-4"
                >MediPath</Link
            >
            <section
                class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <header
                    class="border-b border-teal-100 bg-teal-50 px-6 py-7 sm:px-8"
                >
                    <div class="flex items-start gap-4">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-teal-800 shadow-xs"
                        >
                            <Building2 class="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h1 class="text-3xl font-semibold tracking-tight">
                                Check in before you are seen
                            </h1>
                            <p class="mt-2 text-base font-medium text-teal-900">
                                {{ hospital.name }}
                            </p>
                            <p
                                v-if="hospital.address"
                                class="mt-1 text-sm text-slate-600"
                            >
                                {{ hospital.address }}
                            </p>
                        </div>
                    </div>
                </header>

                <div class="space-y-6 p-6 sm:p-8">
                    <p class="max-w-xl leading-7 text-slate-700">
                        Complete a short intake on your phone. Your summary
                        stays private until you review it and explicitly send it
                        to this hospital.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex gap-3 border-t border-slate-200 pt-4">
                            <ClipboardCheck
                                class="mt-0.5 size-5 shrink-0 text-teal-700"
                                aria-hidden="true"
                            />
                            <p class="text-sm leading-6 text-slate-600">
                                The hospital is preselected, so you will not
                                need to search for it later.
                            </p>
                        </div>
                        <div class="flex gap-3 border-t border-slate-200 pt-4">
                            <ShieldCheck
                                class="mt-0.5 size-5 shrink-0 text-teal-700"
                                aria-hidden="true"
                            />
                            <p class="text-sm leading-6 text-slate-600">
                                Emergency and capability checks still apply
                                before anything is shared.
                            </p>
                        </div>
                    </div>

                    <Form
                        :action="startUrl"
                        method="post"
                        v-slot="{ processing }"
                    >
                        <Button
                            class="h-11 w-full bg-teal-700 text-white hover:bg-teal-800"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            {{ processing ? 'Starting' : 'Start intake' }}
                        </Button>
                    </Form>
                    <p class="text-center text-xs leading-5 text-slate-500">
                        Prototype environment. Use dummy patient information
                        only.
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
