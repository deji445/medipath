<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CirclePause,
    Copy,
    QrCode,
    Route,
    ShieldCheck,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Hospital = {
    id: string;
    name: string;
    brandName: string | null;
    address: string | null;
    brandColor: string | null;
    capability: 'ER_CAPABLE' | 'CLINIC_ONLY';
    routingMode: 'AUTO' | 'MANUAL';
    acceptingSubmissions: boolean;
    isActive: boolean;
    walkInUrl: string;
    walkInQr: string;
};

const props = defineProps<{ hospital: Hospital }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Hospital settings', href: '/hospital/settings' },
        ],
    },
});

const form = useForm({
    brand_name: props.hospital.brandName ?? '',
    address: props.hospital.address ?? '',
    brand_color: props.hospital.brandColor ?? '#0F766E',
    capability: props.hospital.capability,
    routing_mode: props.hospital.routingMode,
    accepting_submissions: props.hospital.acceptingSubmissions,
});

function submit(): void {
    form.patch('/hospital/settings', { preserveScroll: true });
}

async function copyWalkInUrl(): Promise<void> {
    await navigator.clipboard.writeText(props.hospital.walkInUrl);
}
</script>

<template>
    <Head title="Hospital settings" />
    <main class="min-h-full bg-slate-50 px-5 py-7 text-slate-950 sm:px-8">
        <div class="mx-auto max-w-6xl">
            <header
                class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Hospital controls
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ hospital.name }}
                    </p>
                </div>
                <div
                    class="inline-flex w-fit items-center gap-2 border border-slate-200 bg-white px-3 py-2 text-sm"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="
                            hospital.isActive ? 'bg-emerald-500' : 'bg-red-500'
                        "
                    />
                    {{
                        hospital.isActive ? 'Tenant active' : 'Tenant inactive'
                    }}
                </div>
            </header>

            <form
                class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
                @submit.prevent="submit"
            >
                <div class="border border-slate-200 bg-white">
                    <section class="border-b border-slate-200 p-5 sm:p-6">
                        <div class="flex items-start gap-3">
                            <Route
                                class="mt-0.5 size-5 text-teal-700"
                                aria-hidden="true"
                            />
                            <div>
                                <h2 class="font-semibold">
                                    Routing and intake
                                </h2>
                                <p
                                    class="mt-1 text-sm leading-6 text-slate-600"
                                >
                                    Set how patients reach this hospital and
                                    whether new submissions can enter the queue.
                                </p>
                            </div>
                        </div>

                        <label
                            class="mt-6 flex cursor-pointer items-start justify-between gap-5 border border-slate-200 bg-slate-50 p-4"
                        >
                            <span>
                                <span class="block text-sm font-semibold"
                                    >Accept new submissions</span
                                >
                                <span
                                    class="mt-1 block text-sm leading-5 text-slate-600"
                                    >Pausing removes this hospital from new
                                    recommendations without hiding existing
                                    cases.</span
                                >
                            </span>
                            <input
                                v-model="form.accepting_submissions"
                                type="checkbox"
                                class="mt-1 size-5 accent-teal-700"
                            />
                        </label>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label for="capability"
                                    >Clinical capability</Label
                                >
                                <select
                                    id="capability"
                                    v-model="form.capability"
                                    class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-teal-700 focus:ring-3 focus:ring-teal-700/15"
                                >
                                    <option value="ER_CAPABLE">
                                        Emergency capable
                                    </option>
                                    <option value="CLINIC_ONLY">
                                        Clinic only
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.capability"
                                />
                            </div>
                            <div>
                                <Label for="routing-mode">Routing mode</Label>
                                <select
                                    id="routing-mode"
                                    v-model="form.routing_mode"
                                    class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-teal-700 focus:ring-3 focus:ring-teal-700/15"
                                >
                                    <option value="AUTO">
                                        Automatic ranking
                                    </option>
                                    <option value="MANUAL">Manual queue</option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.routing_mode"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="p-5 sm:p-6">
                        <div class="flex items-start gap-3">
                            <Building2
                                class="mt-0.5 size-5 text-teal-700"
                                aria-hidden="true"
                            />
                            <div>
                                <h2 class="font-semibold">
                                    Patient-facing identity
                                </h2>
                                <p
                                    class="mt-1 text-sm leading-6 text-slate-600"
                                >
                                    These details appear while a patient reviews
                                    and confirms the destination.
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label for="brand-name">Display name</Label>
                                <Input
                                    id="brand-name"
                                    v-model="form.brand_name"
                                    class="mt-2"
                                    autocomplete="organization"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.brand_name"
                                />
                            </div>
                            <div>
                                <Label for="brand-color">Brand colour</Label>
                                <div class="mt-2 flex gap-2">
                                    <input
                                        v-model="form.brand_color"
                                        type="color"
                                        class="h-10 w-12 rounded-md border border-slate-300 bg-white p-1"
                                        aria-label="Brand colour picker"
                                    />
                                    <Input
                                        id="brand-color"
                                        v-model="form.brand_color"
                                        class="font-mono uppercase"
                                    />
                                </div>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.brand_color"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <Label for="address">Address</Label>
                                <Input
                                    id="address"
                                    v-model="form.address"
                                    class="mt-2"
                                    autocomplete="street-address"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.address"
                                />
                            </div>
                        </div>
                    </section>

                    <footer
                        class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                    >
                        <p class="text-sm text-emerald-700" aria-live="polite">
                            {{
                                form.recentlySuccessful
                                    ? 'Hospital settings saved.'
                                    : ''
                            }}
                        </p>
                        <Button
                            class="bg-teal-700 text-white hover:bg-teal-800"
                            :disabled="form.processing || !form.isDirty"
                        >
                            {{
                                form.processing
                                    ? 'Saving…'
                                    : 'Save hospital settings'
                            }}
                        </Button>
                    </footer>
                </div>

                <aside
                    class="h-fit space-y-6 border border-slate-200 bg-white p-5"
                >
                    <section>
                        <QrCode
                            class="size-6 text-teal-700"
                            aria-hidden="true"
                        />
                        <h2 class="mt-4 font-semibold">Walk-in QR</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Display this at reception. Scanning it starts a
                            private intake with this hospital already selected.
                        </p>
                        <img
                            :src="hospital.walkInQr"
                            :alt="`QR code for ${hospital.name} walk-in intake`"
                            class="mx-auto mt-4 size-52 bg-white"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-4 w-full"
                            @click="copyWalkInUrl"
                        >
                            <Copy class="size-4" aria-hidden="true" />
                            Copy walk-in link
                        </Button>
                    </section>
                    <section class="border-t border-slate-200 pt-5">
                        <ShieldCheck
                            class="size-6 text-teal-700"
                            aria-hidden="true"
                        />
                        <h2 class="mt-4 font-semibold">Safety boundary</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Clinical capability affects which patients may be
                            routed here. High-priority cases can only be sent to
                            an emergency-capable hospital.
                        </p>
                        <div class="mt-5 border-t border-slate-200 pt-5">
                            <CirclePause
                                class="size-5 text-amber-600"
                                aria-hidden="true"
                            />
                            <p class="mt-2 text-sm font-medium">
                                Pause instead of deactivating
                            </p>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Use the intake switch for temporary capacity
                                changes. Only MediPath Super Admin can
                                deactivate the tenant.
                            </p>
                        </div>
                    </section>
                </aside>
            </form>
        </div>
    </main>
</template>
