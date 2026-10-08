<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CirclePlus,
    Network,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Hospital = {
    id: string;
    name: string;
    slug: string;
    brandName: string | null;
    brandColor: string | null;
    address: string | null;
    capability: 'ER_CAPABLE' | 'CLINIC_ONLY';
    routingMode: 'AUTO' | 'MANUAL';
    acceptingSubmissions: boolean;
    isActive: boolean;
    staffCount: number;
    submissionCount: number;
};

const props = defineProps<{
    hospitals: Hospital[];
    counts: {
        hospitals: number;
        active: number;
        accepting: number;
        staff: number;
    };
    actor: { name: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Platform hospitals', href: '/platform' }],
    },
});

const selectedId = ref(props.hospitals[0]?.id ?? null);
const selected = computed(
    () =>
        props.hospitals.find((hospital) => hospital.id === selectedId.value) ??
        null,
);
const createOpen = ref(false);

const createForm = useForm({
    name: '',
    slug: '',
    brand_name: '',
    brand_color: '#0F766E',
    address: '',
    capability: 'ER_CAPABLE' as Hospital['capability'],
    routing_mode: 'AUTO' as Hospital['routingMode'],
    admin_first_name: '',
    admin_last_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const editForm = useForm({
    name: '',
    slug: '',
    brand_name: '',
    brand_color: '',
    address: '',
    capability: 'ER_CAPABLE' as Hospital['capability'],
    routing_mode: 'AUTO' as Hospital['routingMode'],
    accepting_submissions: true,
    is_active: true,
});

function loadSelected(hospital: Hospital | null): void {
    if (!hospital) return;
    editForm.defaults({
        name: hospital.name,
        slug: hospital.slug,
        brand_name: hospital.brandName ?? '',
        brand_color: hospital.brandColor ?? '#0F766E',
        address: hospital.address ?? '',
        capability: hospital.capability,
        routing_mode: hospital.routingMode,
        accepting_submissions: hospital.acceptingSubmissions,
        is_active: hospital.isActive,
    });
    editForm.reset();
    editForm.clearErrors();
}

watch(selected, loadSelected, { immediate: true });

function createHospital(): void {
    createForm.post('/platform/hospitals', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createOpen.value = false;
        },
    });
}

function updateHospital(): void {
    if (!selected.value) return;
    editForm.patch(`/platform/hospitals/${selected.value.id}`, {
        preserveScroll: true,
    });
}

function capabilityLabel(value: Hospital['capability']): string {
    return value === 'ER_CAPABLE' ? 'Emergency capable' : 'Clinic only';
}
</script>

<template>
    <Head title="Platform hospitals" />
    <main class="min-h-full bg-slate-50 px-5 py-7 text-slate-950 sm:px-8">
        <div class="mx-auto max-w-[1500px]">
            <header
                class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Hospital network
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Platform governance · signed in as {{ actor.name }}
                    </p>
                </div>
                <Button
                    class="w-full bg-teal-700 text-white hover:bg-teal-800 sm:w-auto"
                    @click="createOpen = !createOpen"
                >
                    <CirclePlus />
                    {{
                        createOpen ? 'Close new hospital form' : 'Add hospital'
                    }}
                </Button>
            </header>

            <section
                aria-label="Platform summary"
                class="mt-6 flex flex-wrap gap-x-8 gap-y-3 border-y border-slate-200 bg-white px-5 py-4 text-sm"
            >
                <p>
                    <strong class="mr-1 text-lg tabular-nums">{{
                        counts.hospitals
                    }}</strong>
                    <span class="text-slate-600">hospitals</span>
                </p>
                <p>
                    <strong class="mr-1 text-lg tabular-nums">{{
                        counts.active
                    }}</strong>
                    <span class="text-slate-600">active tenants</span>
                </p>
                <p>
                    <strong class="mr-1 text-lg tabular-nums">{{
                        counts.accepting
                    }}</strong>
                    <span class="text-slate-600">accepting intake</span>
                </p>
                <p>
                    <strong class="mr-1 text-lg tabular-nums">{{
                        counts.staff
                    }}</strong>
                    <span class="text-slate-600">staff accounts</span>
                </p>
            </section>

            <form
                v-if="createOpen"
                class="mt-6 border border-teal-200 bg-white"
                @submit.prevent="createHospital"
            >
                <div
                    class="flex items-start gap-3 border-b border-slate-200 p-5 sm:p-6"
                >
                    <Building2
                        class="mt-0.5 size-5 text-teal-700"
                        aria-hidden="true"
                    />
                    <div>
                        <h2 class="font-semibold">Create a hospital tenant</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            The hospital and its first administrator are created
                            together so the tenant is never left unmanaged.
                        </p>
                    </div>
                </div>
                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
                    <fieldset class="grid gap-4">
                        <legend class="mb-1 font-semibold">
                            Hospital details
                        </legend>
                        <div>
                            <Label for="new-name">Registered name</Label
                            ><Input
                                id="new-name"
                                v-model="createForm.name"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="createForm.errors.name"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="new-brand">Display name</Label
                                ><Input
                                    id="new-brand"
                                    v-model="createForm.brand_name"
                                    class="mt-2"
                                /><InputError
                                    class="mt-1"
                                    :message="createForm.errors.brand_name"
                                />
                            </div>
                            <div>
                                <Label for="new-slug">URL slug</Label
                                ><Input
                                    id="new-slug"
                                    v-model="createForm.slug"
                                    class="mt-2"
                                    placeholder="hospital-name"
                                /><InputError
                                    class="mt-1"
                                    :message="createForm.errors.slug"
                                />
                            </div>
                        </div>
                        <div>
                            <Label for="new-address">Address</Label
                            ><Input
                                id="new-address"
                                v-model="createForm.address"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="createForm.errors.address"
                            />
                        </div>
                        <div>
                            <Label for="new-colour">Brand colour</Label
                            ><Input
                                id="new-colour"
                                v-model="createForm.brand_color"
                                class="mt-2 font-mono uppercase"
                            /><InputError
                                class="mt-1"
                                :message="createForm.errors.brand_color"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="new-capability">Capability</Label
                                ><select
                                    id="new-capability"
                                    v-model="createForm.capability"
                                    class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                >
                                    <option value="ER_CAPABLE">
                                        Emergency capable
                                    </option>
                                    <option value="CLINIC_ONLY">
                                        Clinic only
                                    </option>
                                </select>
                                <InputError
                                    class="mt-1"
                                    :message="createForm.errors.capability"
                                />
                            </div>
                            <div>
                                <Label for="new-routing">Routing</Label
                                ><select
                                    id="new-routing"
                                    v-model="createForm.routing_mode"
                                    class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                >
                                    <option value="AUTO">Automatic</option>
                                    <option value="MANUAL">Manual</option>
                                </select>
                                <InputError
                                    class="mt-1"
                                    :message="createForm.errors.routing_mode"
                                />
                            </div>
                        </div>
                    </fieldset>
                    <fieldset
                        class="grid gap-4 border-t border-slate-200 pt-6 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6"
                    >
                        <legend class="mb-1 font-semibold">
                            Initial hospital administrator
                        </legend>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="admin-first">First name</Label
                                ><Input
                                    id="admin-first"
                                    v-model="createForm.admin_first_name"
                                    class="mt-2"
                                /><InputError
                                    class="mt-1"
                                    :message="
                                        createForm.errors.admin_first_name
                                    "
                                />
                            </div>
                            <div>
                                <Label for="admin-last">Last name</Label
                                ><Input
                                    id="admin-last"
                                    v-model="createForm.admin_last_name"
                                    class="mt-2"
                                /><InputError
                                    class="mt-1"
                                    :message="createForm.errors.admin_last_name"
                                />
                            </div>
                        </div>
                        <div>
                            <Label for="admin-email">Email</Label
                            ><Input
                                id="admin-email"
                                v-model="createForm.admin_email"
                                type="email"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="createForm.errors.admin_email"
                            />
                        </div>
                        <div>
                            <Label for="admin-password"
                                >Temporary password</Label
                            ><Input
                                id="admin-password"
                                v-model="createForm.admin_password"
                                type="password"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="createForm.errors.admin_password"
                            />
                        </div>
                        <div>
                            <Label for="admin-password-confirmation"
                                >Confirm password</Label
                            ><Input
                                id="admin-password-confirmation"
                                v-model="createForm.admin_password_confirmation"
                                type="password"
                                class="mt-2"
                            />
                        </div>
                    </fieldset>
                </div>
                <div
                    class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6"
                >
                    <Button
                        class="bg-teal-700 text-white hover:bg-teal-800"
                        :disabled="createForm.processing"
                        >{{
                            createForm.processing
                                ? 'Creating…'
                                : 'Create hospital and administrator'
                        }}</Button
                    >
                </div>
            </form>

            <div
                class="mt-6 grid min-h-[620px] overflow-hidden border border-slate-200 bg-white xl:grid-cols-[minmax(420px,0.9fr)_minmax(520px,1.1fr)]"
            >
                <section
                    class="border-b border-slate-200 xl:border-r xl:border-b-0"
                >
                    <div
                        class="flex items-center gap-3 border-b border-slate-200 p-5"
                    >
                        <Network
                            class="size-5 text-teal-700"
                            aria-hidden="true"
                        />
                        <div>
                            <h2 class="font-semibold">Registered hospitals</h2>
                            <p class="text-sm text-slate-600">
                                Select a tenant to review its operating state.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="hospitals.length"
                        class="divide-y divide-slate-200"
                    >
                        <button
                            v-for="hospital in hospitals"
                            :key="hospital.id"
                            type="button"
                            class="grid w-full gap-3 border-l px-5 py-4 text-left transition hover:bg-slate-50 focus-visible:ring-3 focus-visible:ring-teal-700/20 focus-visible:outline-none sm:grid-cols-[minmax(0,1fr)_auto]"
                            :class="
                                selectedId === hospital.id
                                    ? 'border-l-teal-700 bg-teal-50/40'
                                    : 'border-l-transparent'
                            "
                            @click="selectedId = hospital.id"
                        >
                            <span
                                ><span class="flex flex-wrap items-center gap-2"
                                    ><span class="font-semibold">{{
                                        hospital.brandName || hospital.name
                                    }}</span
                                    ><span
                                        v-if="!hospital.isActive"
                                        class="text-xs font-medium text-red-700"
                                        >Inactive</span
                                    ><span
                                        v-else-if="
                                            !hospital.acceptingSubmissions
                                        "
                                        class="text-xs font-medium text-amber-700"
                                        >Paused</span
                                    ></span
                                ><span class="mt-1 block text-sm text-slate-600"
                                    >{{ capabilityLabel(hospital.capability) }}
                                    ·
                                    {{
                                        hospital.routingMode === 'AUTO'
                                            ? 'Automatic routing'
                                            : 'Manual routing'
                                    }}</span
                                ></span
                            >
                            <span
                                class="text-xs leading-5 text-slate-500 sm:text-right"
                                ><span class="block"
                                    >{{ hospital.staffCount }} staff</span
                                ><span class="block"
                                    >{{
                                        hospital.submissionCount
                                    }}
                                    submissions</span
                                ></span
                            >
                        </button>
                    </div>
                    <div v-else class="p-10 text-center text-sm text-slate-600">
                        No hospitals have been registered.
                    </div>
                </section>

                <form
                    v-if="selected"
                    class="flex min-w-0 flex-col"
                    @submit.prevent="updateHospital"
                >
                    <div
                        class="flex items-start gap-3 border-b border-slate-200 p-5 sm:p-6"
                    >
                        <ShieldCheck
                            class="mt-0.5 size-5 text-teal-700"
                            aria-hidden="true"
                        />
                        <div>
                            <h2 class="font-semibold">Tenant configuration</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Platform changes affect routing eligibility and
                                staff access for this hospital.
                            </p>
                        </div>
                    </div>
                    <div
                        class="grid flex-1 content-start gap-5 p-5 sm:grid-cols-2 sm:p-6"
                    >
                        <div class="sm:col-span-2">
                            <Label for="edit-name">Registered name</Label
                            ><Input
                                id="edit-name"
                                v-model="editForm.name"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="editForm.errors.name"
                            />
                        </div>
                        <div>
                            <Label for="edit-brand">Display name</Label
                            ><Input
                                id="edit-brand"
                                v-model="editForm.brand_name"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="editForm.errors.brand_name"
                            />
                        </div>
                        <div>
                            <Label for="edit-slug">URL slug</Label
                            ><Input
                                id="edit-slug"
                                v-model="editForm.slug"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="editForm.errors.slug"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label for="edit-address">Address</Label
                            ><Input
                                id="edit-address"
                                v-model="editForm.address"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="editForm.errors.address"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <Label for="edit-colour">Brand colour</Label
                            ><Input
                                id="edit-colour"
                                v-model="editForm.brand_color"
                                class="mt-2 font-mono uppercase"
                            /><InputError
                                class="mt-1"
                                :message="editForm.errors.brand_color"
                            />
                        </div>
                        <div>
                            <Label for="edit-capability">Capability</Label
                            ><select
                                id="edit-capability"
                                v-model="editForm.capability"
                                class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                            >
                                <option value="ER_CAPABLE">
                                    Emergency capable
                                </option>
                                <option value="CLINIC_ONLY">Clinic only</option>
                            </select>
                            <InputError
                                class="mt-1"
                                :message="editForm.errors.capability"
                            />
                        </div>
                        <div>
                            <Label for="edit-routing">Routing mode</Label
                            ><select
                                id="edit-routing"
                                v-model="editForm.routing_mode"
                                class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                            >
                                <option value="AUTO">Automatic</option>
                                <option value="MANUAL">Manual</option>
                            </select>
                            <InputError
                                class="mt-1"
                                :message="editForm.errors.routing_mode"
                            />
                        </div>
                        <div>
                            <label
                                class="flex items-start gap-3 border border-slate-200 bg-slate-50 p-4"
                                ><input
                                    v-model="editForm.accepting_submissions"
                                    type="checkbox"
                                    class="mt-0.5 size-5 accent-teal-700"
                                /><span
                                    ><span class="block text-sm font-semibold"
                                        >Accepting submissions</span
                                    ><span
                                        class="mt-1 block text-xs leading-5 text-slate-600"
                                        >Available to eligible patients.</span
                                    ></span
                                ></label
                            ><InputError
                                class="mt-1"
                                :message="editForm.errors.accepting_submissions"
                            />
                        </div>
                        <div>
                            <label
                                class="flex items-start gap-3 border border-slate-200 bg-slate-50 p-4"
                                ><input
                                    v-model="editForm.is_active"
                                    type="checkbox"
                                    class="mt-0.5 size-5 accent-teal-700"
                                /><span
                                    ><span class="block text-sm font-semibold"
                                        >Tenant active</span
                                    ><span
                                        class="mt-1 block text-xs leading-5 text-slate-600"
                                        >Allows staff access and routing.</span
                                    ></span
                                ></label
                            ><InputError
                                class="mt-1"
                                :message="editForm.errors.is_active"
                            />
                        </div>
                    </div>
                    <footer
                        class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                    >
                        <p class="text-sm text-emerald-700" aria-live="polite">
                            {{
                                editForm.recentlySuccessful
                                    ? 'Tenant configuration saved.'
                                    : ''
                            }}
                        </p>
                        <Button
                            class="bg-teal-700 text-white hover:bg-teal-800"
                            :disabled="editForm.processing || !editForm.isDirty"
                            >{{
                                editForm.processing
                                    ? 'Saving…'
                                    : 'Save tenant configuration'
                            }}</Button
                        >
                    </footer>
                </form>
                <div
                    v-else
                    class="flex items-center justify-center p-10 text-center text-sm text-slate-600"
                >
                    <div>
                        <Users class="mx-auto size-6 text-teal-700" />
                        <p class="mt-3">
                            Create a hospital to begin platform governance.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
