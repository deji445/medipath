<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Identity = {
    first_name?: string;
    last_name?: string;
    date_of_birth?: string | null;
    sex_at_birth?: string | null;
};

defineProps<{
    phoneNumber: string;
    existingPatient?: {
        maskedName: string;
        dateOfBirth?: string | null;
        sexAtBirth: string;
    } | null;
    identity?: Identity | null;
}>();
</script>

<template>
    <Head title="Patient details" />

    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >
        <h1 class="text-2xl font-semibold">Patient details</h1>
        <p class="mt-2 text-sm text-slate-600">Phone: {{ phoneNumber }}</p>

        <Form
            action="/intake/identity"
            method="post"
            class="mt-8 space-y-6"
            v-slot="{ errors, processing }"
        >
            <div
                v-if="existingPatient"
                class="rounded-xl border border-teal-200 bg-teal-50 p-4"
            >
                <p class="font-medium">We found a possible returning patient</p>
                <p class="mt-1 text-sm text-slate-700">
                    {{ existingPatient.maskedName }}
                    <span v-if="existingPatient.dateOfBirth">
                        · Born {{ existingPatient.dateOfBirth }}</span
                    >
                </p>
                <label class="mt-4 flex items-start gap-3 text-sm">
                    <input
                        type="checkbox"
                        name="use_existing"
                        value="1"
                        class="mt-0.5 size-4 accent-teal-700"
                        required
                    />
                    <span>This is the correct demo patient profile.</span>
                </label>
                <InputError :message="errors.use_existing" />
                <p class="mt-3 text-xs text-slate-600">
                    If this is not the correct profile, go back and use a
                    different number. MediPath never merges profiles
                    automatically.
                </p>
            </div>

            <template v-else>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            name="first_name"
                            required
                            maxlength="80"
                            :default-value="identity?.first_name ?? ''"
                        />
                        <InputError :message="errors.first_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            name="last_name"
                            required
                            maxlength="80"
                            :default-value="identity?.last_name ?? ''"
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="date_of_birth">Date of birth (optional)</Label>
                    <Input
                        id="date_of_birth"
                        name="date_of_birth"
                        type="date"
                        :default-value="identity?.date_of_birth ?? ''"
                    />
                    <InputError :message="errors.date_of_birth" />
                </div>
                <div class="grid gap-2">
                    <Label for="sex_at_birth">Sex at birth</Label>
                    <select
                        id="sex_at_birth"
                        name="sex_at_birth"
                        required
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-base text-slate-950 outline-none focus-visible:border-teal-700 focus-visible:ring-3 focus-visible:ring-teal-700/25"
                        :value="identity?.sex_at_birth ?? ''"
                    >
                        <option value="" disabled>Select one</option>
                        <option value="FEMALE">Female</option>
                        <option value="MALE">Male</option>
                        <option value="INTERSEX">Intersex</option>
                        <option value="PREFER_NOT_TO_SAY">
                            Prefer not to say
                        </option>
                    </select>
                    <InputError :message="errors.sex_at_birth" />
                </div>
            </template>

            <div class="flex items-center justify-between gap-4">
                <Link
                    href="/intake"
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
