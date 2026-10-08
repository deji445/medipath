<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CircleCheck,
    ShieldAlert,
    Siren,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

const goBack = () => window.history.back();
</script>

<template>
    <Head title="Emergency safety check" />

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <div class="grid lg:grid-cols-[0.78fr_1.22fr]">
            <aside class="bg-amber-50 p-6 sm:p-8">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-amber-950 underline underline-offset-4"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Back
                </button>
                <div
                    class="mt-7 flex size-11 items-center justify-center rounded-xl bg-amber-100 text-amber-900"
                    aria-hidden="true"
                >
                    <ShieldAlert class="size-5" />
                </div>
                <h1
                    class="mt-7 max-w-sm text-3xl leading-tight font-semibold tracking-tight text-amber-950"
                >
                    Could this be an emergency?
                </h1>
                <p class="mt-4 max-w-md leading-7 text-amber-950/75">
                    Think about what is happening right now. Choose the safer
                    answer if you are unsure.
                </p>

                <ul class="mt-7 space-y-3 text-sm leading-6 text-amber-950">
                    <li>Severe trouble breathing or chest pressure</li>
                    <li>Unconsciousness, seizure, or sudden stroke signs</li>
                    <li>Heavy bleeding, major injury, burn, or poisoning</li>
                    <li>Severe allergic reaction or immediate risk of harm</li>
                </ul>
            </aside>

            <div class="flex flex-col justify-center p-6 sm:p-8 lg:p-10">
                <h2 class="text-xl font-semibold text-slate-950">
                    Does any warning sign apply?
                </h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-600">
                    One choice is enough. You will not be asked to confirm it
                    again.
                </p>

                <div class="mt-7 grid gap-4">
                    <Form
                        action="/intake/assessment/safety"
                        method="post"
                        v-slot="{ processing }"
                    >
                        <input
                            type="hidden"
                            name="safety_status"
                            value="NONE"
                        />
                        <input
                            type="hidden"
                            name="answers_confirmed"
                            value="1"
                        />
                        <Button
                            class="min-h-20 w-full justify-between bg-teal-700 px-5 text-left text-white hover:bg-teal-800"
                            :disabled="processing"
                        >
                            <span class="flex min-w-0 items-center gap-4">
                                <CircleCheck
                                    class="size-6 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    <strong class="block text-base"
                                        >No, none of these</strong
                                    >
                                    <span
                                        class="mt-1 block text-sm text-teal-50"
                                        >Continue to the assessment</span
                                    >
                                </span>
                            </span>
                            <Spinner v-if="processing" />
                            <ArrowRight
                                v-else
                                class="size-5 shrink-0"
                                aria-hidden="true"
                            />
                        </Button>
                    </Form>

                    <Form
                        action="/intake/assessment/safety"
                        method="post"
                        v-slot="{ processing }"
                    >
                        <input
                            type="hidden"
                            name="safety_status"
                            value="CONCERN"
                        />
                        <input
                            type="hidden"
                            name="answers_confirmed"
                            value="1"
                        />
                        <Button
                            variant="outline"
                            class="min-h-20 w-full justify-between border-red-300 bg-red-50 px-5 text-left text-red-950 hover:bg-red-100"
                            :disabled="processing"
                        >
                            <span class="flex min-w-0 items-center gap-4">
                                <Siren
                                    class="size-6 shrink-0 text-red-700"
                                    aria-hidden="true"
                                />
                                <span>
                                    <strong class="block text-base"
                                        >Yes, or I’m not sure</strong
                                    >
                                    <span
                                        class="mt-1 block text-sm text-red-800"
                                        >Show emergency help now</span
                                    >
                                </span>
                            </span>
                            <Spinner v-if="processing" />
                            <ArrowRight
                                v-else
                                class="size-5 shrink-0"
                                aria-hidden="true"
                            />
                        </Button>
                    </Form>
                </div>

                <p class="mt-6 text-sm leading-6 text-slate-600">
                    Already in immediate danger?
                    <Link
                        href="/emergency"
                        class="font-semibold text-red-700 underline underline-offset-4"
                        >Open emergency help</Link
                    >
                    or call 112.
                </p>
            </div>
        </div>
    </section>
</template>
