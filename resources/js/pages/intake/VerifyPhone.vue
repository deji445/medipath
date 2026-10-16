<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, LockKeyhole, RotateCw } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    phoneNumber: string;
    expiresAt?: string | null;
    demoCode?: string | null;
    status?: string | null;
}>();

const codeInput = ref<HTMLInputElement | null>(null);
const expiryLabel = computed(() => {
    if (!props.expiresAt) return 'The code expires shortly.';

    return `The code expires at ${new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(props.expiresAt))}.`;
});

onMounted(() => {
    toast.dismiss();
    codeInput.value?.focus();
});
</script>

<template>
    <Head title="Verify phone" />

    <section class="mx-auto max-w-xl">
        <Link
            href="/intake?restart=1"
            class="mb-5 inline-flex items-center gap-2 rounded-md text-sm font-medium text-slate-600 outline-none hover:text-slate-950 focus-visible:ring-3 focus-visible:ring-teal-700/30"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Use another number
        </Link>

        <div
            class="overflow-hidden rounded-2xl bg-white shadow-[0_18px_55px_-32px_rgba(15,118,110,0.45)] ring-1 ring-slate-200"
        >
            <div class="border-b border-teal-100 bg-teal-50 px-6 py-5 sm:px-8">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-white shadow-[0_8px_20px_-12px_rgba(15,118,110,0.9)]"
                    >
                        <LockKeyhole class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h1
                            class="text-2xl font-semibold tracking-[-0.025em] text-slate-950"
                        >
                            Verify your phone
                        </h1>
                        <p class="mt-1 text-sm text-teal-950/75">
                            We sent a 6-digit code to {{ phoneNumber }}.
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-7 sm:px-8 sm:py-8">
                <p
                    v-if="status"
                    role="status"
                    class="mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"
                >
                    <CheckCircle2 class="size-4 shrink-0" aria-hidden="true" />
                    {{ status }}
                </p>

                <div
                    v-if="demoCode"
                    class="mb-6 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-200"
                >
                    <p class="font-semibold">Local demo code</p>
                    <p class="mt-1">
                        Use
                        <strong class="font-mono text-base tracking-widest">{{
                            demoCode
                        }}</strong
                        >. This callout is disabled in production.
                    </p>
                </div>

                <Form
                    action="/intake/verify-phone"
                    method="post"
                    v-slot="{ errors, processing }"
                    class="space-y-5"
                >
                    <div>
                        <label
                            for="code"
                            class="text-sm font-semibold text-slate-900"
                        >
                            Verification code
                        </label>
                        <input
                            id="code"
                            ref="codeInput"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            required
                            aria-describedby="code-hint"
                            class="mt-2 h-14 w-full rounded-xl border border-slate-300 bg-white px-4 text-center text-2xl font-semibold tracking-[0.32em] text-slate-950 caret-teal-700 transition-shadow outline-none placeholder:text-slate-300 focus-visible:border-teal-700 focus-visible:ring-3 focus-visible:ring-teal-700/20"
                            placeholder="000000"
                        />
                        <p id="code-hint" class="mt-2 text-sm text-slate-600">
                            {{ expiryLabel }} Never share this code with anyone.
                        </p>
                        <InputError class="mt-2" :message="errors.code" />
                    </div>

                    <Button
                        class="h-11 w-full bg-teal-700 text-white hover:bg-teal-800"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        Continue
                    </Button>
                </Form>

                <Form
                    action="/intake/verify-phone/resend"
                    method="post"
                    v-slot="{ errors, processing }"
                    class="mt-5 text-center"
                >
                    <Button
                        type="submit"
                        variant="ghost"
                        class="text-slate-600 hover:bg-slate-100 hover:text-slate-950"
                        :disabled="processing"
                    >
                        <RotateCw class="size-4" aria-hidden="true" />
                        Resend code
                    </Button>
                    <InputError class="mt-2" :message="errors.resend" />
                </Form>
            </div>
        </div>
    </section>
</template>
