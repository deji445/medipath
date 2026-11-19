<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>
<template>
    <Head title="Track an intake" />
    <section
        class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-6"
    >
        <h1 class="text-2xl font-semibold">Track an intake</h1>
        <p class="mt-2 text-sm text-slate-600">
            Enter your receipt reference and the phone number used for this
            intake.
        </p>
        <Form
            action="/intake/lookup"
            method="post"
            class="mt-6 grid gap-3"
            v-slot="{ processing, errors }"
        >
            <Label for="tracking_reference">Submission reference</Label>
            <Input
                id="tracking_reference"
                name="tracking_reference"
                maxlength="36"
                required
            />
            <InputError
                :message="
                    errors.tracking_reference ||
                    $page.props.errors.tracking_reference
                "
            />
            <Label for="tracking_phone">Mobile number</Label>
            <Input
                id="tracking_phone"
                type="tel"
                name="phone_number"
                autocomplete="tel"
                required
            />
            <InputError :message="errors.phone_number" />
            <Button :disabled="processing">Continue</Button>
        </Form>
    </section>
</template>
