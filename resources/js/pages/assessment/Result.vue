<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleAlert,
    ClipboardCheck,
    ShieldCheck,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    result: {
        severity: 'LOW' | 'MEDIUM' | 'HIGH';
        recommendedAction: string;
        summary: string;
        reasonCodes: string[];
    };
}>();

const presentation = computed(() => {
    if (props.result.severity === 'MEDIUM') {
        return {
            title: 'Seek professional care soon',
            description:
                'The answers suggest the concern should be assessed by a healthcare professional soon.',
            tone: 'border-amber-200 bg-amber-50 text-amber-950',
            iconTone: 'bg-amber-100 text-amber-900',
            Icon: CircleAlert,
        };
    }

    return {
        title: 'Routine care may be appropriate',
        description:
            'No emergency warning sign was reported, and the answers fit the lower-acuity prototype pathway.',
        tone: 'border-teal-200 bg-teal-50 text-teal-950',
        iconTone: 'bg-teal-100 text-teal-900',
        Icon: ShieldCheck,
    };
});

const reasonLabels: Record<string, string> = {
    NO_RED_FLAG_IDENTIFIED: 'No emergency warning sign was identified',
    SYMPTOMS_WORSENING: 'The concern is getting worse',
    SEVERE_REPORTED_INTENSITY: 'The reported intensity is severe',
    DAILY_ACTIVITY_LIMITED: 'Normal activities are limited',
    DAILY_ACTIVITY_PREVENTED: 'Normal activities cannot be managed',
    ASSOCIATED_SYMPTOMS_REPORTED: 'Additional symptoms were reported',
    PERSISTENT_SYMPTOMS: 'The concern has persisted for several days',
    ABNORMAL_VITAL_SIGNS_REPORTED:
        'One or more recorded vital signs are outside the usual adult range',
};
</script>

<template>
    <Head title="Assessment result" />

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="p-6 sm:p-8">
            <div :class="['rounded-xl border p-5', presentation.tone]">
                <div class="flex items-start gap-4">
                    <div
                        :class="[
                            'flex size-11 shrink-0 items-center justify-center rounded-xl',
                            presentation.iconTone,
                        ]"
                    >
                        <component
                            :is="presentation.Icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold text-balance">
                            {{ presentation.title }}
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6">
                            {{ presentation.description }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-7">
                <div class="flex items-center gap-3">
                    <ClipboardCheck
                        class="size-5 text-teal-700"
                        aria-hidden="true"
                    />
                    <h2 class="font-semibold text-slate-950">
                        Summary of what was reported
                    </h2>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-700">
                    {{ result.summary }}
                </p>
            </div>

            <div class="mt-7 border-t border-slate-200 pt-6">
                <h2 class="font-semibold text-slate-950">
                    Why this guidance was shown
                </h2>
                <ul class="mt-3 grid gap-2 text-sm leading-6 text-slate-700">
                    <li
                        v-for="code in result.reasonCodes"
                        :key="code"
                        class="flex gap-3"
                    >
                        <span
                            class="mt-2.5 size-1.5 shrink-0 rounded-full bg-teal-700"
                            aria-hidden="true"
                        />
                        <span>{{ reasonLabels[code] ?? code }}</span>
                    </li>
                </ul>
            </div>

            <div
                class="mt-7 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600"
            >
                This result is guidance only. It does not diagnose a condition
                or confirm that waiting is safe. If symptoms become severe or a
                warning sign appears, seek urgent professional help.
            </div>

            <Button
                as-child
                class="mt-7 w-full bg-teal-700 text-white hover:bg-teal-800"
            >
                <Link href="/intake/routing">
                    Find a suitable hospital
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </Button>
        </div>
    </section>
</template>
