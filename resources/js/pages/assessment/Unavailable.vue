<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleAlert, Phone, Stethoscope } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    emergencyContact: {
        label: string;
        phone: string | null;
    };
}>();
</script>

<template>
    <Head title="Assessment unavailable" />

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="p-6 sm:p-8">
            <div
                class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-950"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-900"
                    >
                        <CircleAlert class="size-5" aria-hidden="true" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold text-balance">
                            We could not complete the assessment
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6">
                            Your answers were saved, but the assessment service
                            did not return a result we could safely use. No
                            severity level has been assigned.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-7 flex items-start gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-800"
                >
                    <Stethoscope class="size-5" aria-hidden="true" />
                </div>
                <div>
                    <h2 class="font-semibold text-slate-950">
                        Please seek professional assessment
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-700">
                        Contact a qualified healthcare professional or visit an
                        appropriate health facility. Do not use this service
                        failure as reassurance that it is safe to wait.
                    </p>
                </div>
            </div>

            <Form
                action="/intake/assessment/result/recover"
                method="post"
                class="mt-7"
                v-slot="{ processing }"
            >
                <Button type="submit" class="w-full" :disabled="processing">
                    {{
                        processing ? 'Preparing guidance…' : 'Use saved answers'
                    }}
                </Button>
                <p class="mt-2 text-center text-sm leading-6 text-slate-600">
                    MediPath will show cautious next-step guidance from the
                    information already provided. You will not need to answer
                    the question again.
                </p>
            </Form>

            <div
                class="mt-7 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm leading-6 text-rose-950"
            >
                If symptoms are severe, rapidly worsening, or an emergency
                warning sign appears, contact {{ emergencyContact.label }} or go
                to the nearest appropriate emergency facility immediately.
                <a
                    v-if="emergencyContact.phone"
                    :href="`tel:${emergencyContact.phone}`"
                    class="mt-3 flex w-fit items-center gap-2 font-semibold underline underline-offset-4"
                >
                    <Phone class="size-4" aria-hidden="true" />
                    {{ emergencyContact.phone }}
                </a>
            </div>

            <Button as-child variant="outline" class="mt-4 w-full">
                <Link href="/">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Return to the MediPath home page
                </Link>
            </Button>
        </div>
    </section>
</template>
