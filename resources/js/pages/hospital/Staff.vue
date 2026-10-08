<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { ShieldCheck, UserPlus, Users } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Role = 'TRIAGE' | 'DOCTOR' | 'HOSPITAL_ADMIN';
type StaffMember = {
    id: string;
    name: string;
    email: string;
    role: Role;
    isActive: boolean;
    isCurrentUser: boolean;
};

const props = defineProps<{
    hospital: { name: string };
    staff: StaffMember[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Staff access', href: '/hospital/staff' }],
    },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    role: 'TRIAGE' as Role,
    password: '',
    password_confirmation: '',
});
const drafts = reactive<Record<string, { role: Role; is_active: boolean }>>(
    Object.fromEntries(
        props.staff.map((member) => [
            member.id,
            { role: member.role, is_active: member.isActive },
        ]),
    ),
);
const originals = reactive<Record<string, { role: Role; is_active: boolean }>>(
    Object.fromEntries(
        props.staff.map((member) => [
            member.id,
            { role: member.role, is_active: member.isActive },
        ]),
    ),
);
const updatingId = ref<string | null>(null);
const successId = ref<string | null>(null);
const updateError = ref('');
const activeCount = computed(
    () => props.staff.filter((member) => member.isActive).length,
);

function createStaff(): void {
    form.post('/hospital/staff', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function updateStaff(member: StaffMember): void {
    updatingId.value = member.id;
    successId.value = null;
    updateError.value = '';
    router.patch(`/hospital/staff/${member.id}`, drafts[member.id], {
        preserveScroll: true,
        onSuccess: () => {
            originals[member.id] = { ...drafts[member.id] };
            successId.value = member.id;
        },
        onError: (errors) => {
            updateError.value = String(
                errors.is_active ?? 'This staff account could not be updated.',
            );
        },
        onFinish: () => {
            updatingId.value = null;
        },
    });
}

function staffIsDirty(member: StaffMember): boolean {
    return (
        drafts[member.id].role !== originals[member.id].role ||
        drafts[member.id].is_active !== originals[member.id].is_active
    );
}
</script>

<template>
    <Head title="Staff access" />
    <main class="min-h-full bg-slate-50 px-5 py-7 text-slate-950 sm:px-8">
        <div class="mx-auto max-w-6xl">
            <header class="border-b border-slate-200 pb-6">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Staff access
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ hospital.name }} · {{ activeCount }} active staff
                </p>
            </header>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section class="border border-slate-200 bg-white">
                    <div
                        class="flex items-start gap-3 border-b border-slate-200 p-5 sm:p-6"
                    >
                        <Users
                            class="mt-0.5 size-5 text-teal-700"
                            aria-hidden="true"
                        />
                        <div>
                            <h2 class="font-semibold">Hospital team</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Roles control access; every account remains
                                bound to this hospital.
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="updateError"
                        class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm text-red-800"
                        role="alert"
                    >
                        {{ updateError }}
                    </p>

                    <div v-if="staff.length" class="divide-y divide-slate-200">
                        <article
                            v-for="member in staff"
                            :key="member.id"
                            class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_170px_120px] sm:items-end sm:px-6"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold">
                                        {{ member.name }}
                                    </h3>
                                    <span
                                        v-if="member.isCurrentUser"
                                        class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-800"
                                        >You</span
                                    >
                                    <span
                                        v-if="!drafts[member.id].is_active"
                                        class="text-xs font-medium text-red-700"
                                        >Inactive</span
                                    >
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ member.email }}
                                </p>
                            </div>
                            <label
                                class="block text-sm font-medium text-slate-700"
                            >
                                Role
                                <select
                                    v-model="drafts[member.id].role"
                                    :disabled="member.isCurrentUser"
                                    class="mt-2 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-sm outline-none focus:border-teal-700 focus:ring-3 focus:ring-teal-700/15"
                                >
                                    <option value="TRIAGE">Triage</option>
                                    <option value="DOCTOR">Doctor</option>
                                    <option value="HOSPITAL_ADMIN">
                                        Hospital admin
                                    </option>
                                </select>
                            </label>
                            <div class="flex flex-col gap-2">
                                <label
                                    class="flex items-center gap-2 text-sm text-slate-700"
                                >
                                    <input
                                        v-model="drafts[member.id].is_active"
                                        type="checkbox"
                                        class="size-4 accent-teal-700"
                                        :disabled="member.isCurrentUser"
                                    />
                                    Active
                                </label>
                                <span
                                    v-if="successId === member.id"
                                    class="text-xs font-medium text-emerald-700"
                                    aria-live="polite"
                                    >Saved</span
                                >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        member.isCurrentUser ||
                                        updatingId === member.id ||
                                        !staffIsDirty(member)
                                    "
                                    @click="updateStaff(member)"
                                    >{{
                                        updatingId === member.id
                                            ? 'Saving…'
                                            : 'Save'
                                    }}</Button
                                >
                            </div>
                        </article>
                    </div>
                    <div v-else class="p-10 text-center text-sm text-slate-600">
                        No hospital staff accounts yet.
                    </div>
                </section>

                <form
                    class="h-fit border border-slate-200 bg-white p-5 sm:p-6"
                    @submit.prevent="createStaff"
                >
                    <UserPlus class="size-6 text-teal-700" aria-hidden="true" />
                    <h2 class="mt-4 font-semibold">Add a staff account</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Share the temporary password securely and ask the staff
                        member to change it after signing in.
                    </p>
                    <div class="mt-5 grid gap-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="first-name">First name</Label
                                ><Input
                                    id="first-name"
                                    v-model="form.first_name"
                                    class="mt-2"
                                /><InputError
                                    class="mt-1"
                                    :message="form.errors.first_name"
                                />
                            </div>
                            <div>
                                <Label for="last-name">Last name</Label
                                ><Input
                                    id="last-name"
                                    v-model="form.last_name"
                                    class="mt-2"
                                /><InputError
                                    class="mt-1"
                                    :message="form.errors.last_name"
                                />
                            </div>
                        </div>
                        <div>
                            <Label for="staff-email">Email</Label
                            ><Input
                                id="staff-email"
                                v-model="form.email"
                                type="email"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="form.errors.email"
                            />
                        </div>
                        <div>
                            <Label for="staff-role">Role</Label
                            ><select
                                id="staff-role"
                                v-model="form.role"
                                class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-teal-700 focus:ring-3 focus:ring-teal-700/15"
                            >
                                <option value="TRIAGE">Triage</option>
                                <option value="DOCTOR">Doctor</option>
                                <option value="HOSPITAL_ADMIN">
                                    Hospital admin
                                </option></select
                            ><InputError
                                class="mt-1"
                                :message="form.errors.role"
                            />
                        </div>
                        <div>
                            <Label for="staff-password"
                                >Temporary password</Label
                            ><Input
                                id="staff-password"
                                v-model="form.password"
                                type="password"
                                class="mt-2"
                            /><InputError
                                class="mt-1"
                                :message="form.errors.password"
                            />
                        </div>
                        <div>
                            <Label for="staff-password-confirmation"
                                >Confirm password</Label
                            ><Input
                                id="staff-password-confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-2"
                            />
                        </div>
                    </div>
                    <Button
                        class="mt-5 w-full bg-teal-700 text-white hover:bg-teal-800"
                        :disabled="form.processing"
                        >{{
                            form.processing
                                ? 'Creating…'
                                : 'Create staff account'
                        }}</Button
                    >
                    <p
                        v-if="form.recentlySuccessful"
                        class="mt-3 text-sm text-emerald-700"
                        aria-live="polite"
                    >
                        Staff account created.
                    </p>
                    <div
                        class="mt-5 flex gap-2 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500"
                    >
                        <ShieldCheck
                            class="mt-0.5 size-4 shrink-0 text-teal-700"
                        />
                        Super Admin access cannot be granted from a hospital
                        workspace.
                    </div>
                </form>
            </div>
        </div>
    </main>
</template>
