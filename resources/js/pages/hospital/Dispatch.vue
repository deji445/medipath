<script setup lang="ts">
import { Form, Head, Link, usePoll } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

type RequestItem = {
    id: string;
    status: string;
    patientName: string;
    location: string;
    severity: string;
    vehicle: string | null;
    crew: string | null;
    etaMinutes: number | null;
    requestedAt: string;
    events: { status: string; at: string }[];
};
defineProps<{
    hospitalName: string;
    vehicles: { id: string; name: string; available: boolean }[];
    requests: {
        data: RequestItem[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const next: Record<string, string> = {
    REQUESTED: 'ASSIGNED',
    ASSIGNED: 'EN_ROUTE',
    EN_ROUTE: 'AT_PATIENT',
    AT_PATIENT: 'RETURNING',
    RETURNING: 'COMPLETED',
};
const labels: Record<string, string> = {
    REQUESTED: 'Waiting for assignment',
    ASSIGNED: 'Crew assigned',
    EN_ROUTE: 'Travelling to patient',
    AT_PATIENT: 'At patient',
    RETURNING: 'Returning to hospital',
    COMPLETED: 'Completed',
    CANCELLED: 'Cancelled',
};
usePoll(10000, { only: ['requests', 'vehicles'] });
</script>

<template>
    <Head title="Dispatch" />
    <main class="mx-auto w-full max-w-6xl p-4 sm:p-8">
        <header
            class="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-6"
        >
            <div>
                <h1 class="text-3xl font-semibold">Dispatch</h1>
                <p class="mt-2 text-muted-foreground">
                    {{ hospitalName }} · Simulated transport only
                </p>
            </div>
            <Button
                v-if="hospitalName === 'MediPath Demo Hospital'"
                as-child
                variant="outline"
                ><Link href="/walk-in/medipath-demo"
                    >Start demo intake</Link
                ></Button
            >
        </header>
        <section
            class="border-b border-border py-5"
            aria-label="Fleet availability"
        >
            <h2 class="font-semibold">Ambulances</h2>
            <ul class="mt-3 flex flex-wrap gap-x-8 gap-y-3">
                <li
                    v-for="vehicle in vehicles"
                    :key="vehicle.id"
                    class="text-sm"
                >
                    {{ vehicle.name }}
                    <span class="ml-2 font-semibold">{{
                        vehicle.available ? 'Available' : 'Assigned'
                    }}</span>
                </li>
            </ul>
            <p
                v-if="!vehicles.length"
                class="mt-2 text-sm text-muted-foreground"
            >
                No demonstration vehicles are configured for this hospital.
            </p>
        </section>
        <p class="py-5 text-sm text-muted-foreground">
            Assign an available vehicle and crew, then update the journey as the
            demonstration progresses. Patients see these updates automatically.
        </p>
        <p
            v-if="!requests.data.length"
            class="py-12 text-center text-muted-foreground"
        >
            No ambulance requests yet. Save a patient intake and select Request
            ambulance.
        </p>
        <article
            v-for="item in requests.data"
            :key="item.id"
            class="border-b border-border py-6"
        >
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ item.patientName }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ item.location }} · {{ item.severity }} priority
                    </p>
                </div>
                <p class="font-semibold" role="status">
                    {{ labels[item.status] }}
                </p>
            </div>
            <p v-if="item.vehicle" class="mt-3 text-sm">
                {{ item.vehicle }} · {{ item.crew
                }}<span v-if="item.etaMinutes">
                    · Estimated {{ item.etaMinutes }} minutes</span
                >
            </p>
            <Form
                v-if="next[item.status]"
                :action="`/hospital/dispatch/${item.id}`"
                method="patch"
                class="mt-5 flex flex-wrap items-end gap-4"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="status" :value="next[item.status]" />
                <template v-if="item.status === 'REQUESTED'">
                    <label class="grid gap-2 text-sm"
                        >Vehicle<select
                            name="vehicle_id"
                            required
                            class="min-h-11 rounded-md border border-input bg-background px-3"
                        >
                            <option value="">Choose an ambulance</option>
                            <option
                                v-for="vehicle in vehicles"
                                :key="vehicle.id"
                                :value="vehicle.id"
                                :disabled="!vehicle.available"
                            >
                                {{ vehicle.name
                                }}{{ vehicle.available ? '' : ' (assigned)' }}
                            </option>
                        </select></label
                    >
                    <label class="grid gap-2 text-sm"
                        >Crew / dispatcher name<input
                            name="crew"
                            required
                            maxlength="100"
                            class="min-h-11 rounded-md border border-input bg-background px-3"
                    /></label>
                </template>
                <label
                    v-if="
                        ['REQUESTED', 'ASSIGNED', 'AT_PATIENT'].includes(
                            item.status,
                        )
                    "
                    class="grid gap-2 text-sm"
                    >Estimated minutes (optional)<input
                        name="eta_minutes"
                        type="number"
                        min="1"
                        max="240"
                        class="min-h-11 w-32 rounded-md border border-input bg-background px-3"
                /></label>
                <Button :disabled="processing">{{
                    item.status === 'REQUESTED'
                        ? 'Assign'
                        : labels[next[item.status]]
                }}</Button>
                <div class="w-full">
                    <InputError
                        v-for="(message, field) in errors"
                        :key="field"
                        :message="message"
                    />
                </div>
            </Form>
            <Form
                v-if="next[item.status]"
                :action="`/hospital/dispatch/${item.id}`"
                method="patch"
                class="mt-3"
                v-slot="{ processing, errors }"
                ><input type="hidden" name="status" value="CANCELLED" /><Button
                    variant="outline"
                    :disabled="processing"
                    >Cancel request</Button
                ><InputError :message="errors.status"
            /></Form>
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer py-2">Journey history</summary>
                <ol class="space-y-2 text-muted-foreground">
                    <li
                        v-for="event in item.events"
                        :key="event.at + event.status"
                    >
                        {{ labels[event.status] }} ·
                        {{ new Date(event.at).toLocaleString() }}
                    </li>
                </ol>
            </details>
        </article>
        <nav class="mt-6 flex gap-4" aria-label="Dispatch pages">
            <Link v-if="requests.prev_page_url" :href="requests.prev_page_url"
                >Previous</Link
            ><Link v-if="requests.next_page_url" :href="requests.next_page_url"
                >Next</Link
            >
        </nav>
    </main>
</template>
