<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Clock3,
    Plus,
    ShieldCheck,
    Trash2,
    UserRoundSearch,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Medication = {
    name: string;
    dose?: string | null;
    frequency?: string | null;
};
type History = {
    conditions: string[];
    allergies: string[];
    medications: Medication[];
};

const props = defineProps<{
    phoneNumber?: string | null;
    existingPatient?: {
        maskedName: string;
        sexAtBirth: string;
    } | null;
    history: History;
    resumeJourney?: {
        href: string;
        patientName: string;
        mainConcern: string;
    } | null;
}>();

const showProfile = computed(() => Boolean(props.phoneNumber));
const sexAtBirthLabels: Record<string, string> = {
    FEMALE: 'Female',
    MALE: 'Male',
    INTERSEX: 'Intersex',
    UNKNOWN: 'Not recorded',
    PREFER_NOT_TO_SAY: 'Prefer not to say',
};
const historyMode = ref(props.existingPatient ? 'UNCHANGED' : 'UPDATE');
const conditionsStatus = ref(
    props.history.conditions.length ? 'DETAILS' : 'NONE',
);
const allergiesStatus = ref(
    props.history.allergies.length ? 'DETAILS' : 'NONE',
);
const medicationsStatus = ref(
    props.history.medications.length ? 'DETAILS' : 'NONE',
);
const medications = ref<Medication[]>(
    props.history.medications.length
        ? props.history.medications.map((item) => ({ ...item }))
        : [{ name: '', dose: '', frequency: '' }],
);

const textareaClass =
    'min-h-24 w-full appearance-none rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-950 shadow-xs outline-none placeholder:text-slate-400 focus-visible:border-teal-700 focus-visible:ring-[3px] focus-visible:ring-teal-700/25';
const selectClass =
    'h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-base text-slate-950 outline-none focus-visible:border-teal-700 focus-visible:ring-[3px] focus-visible:ring-teal-700/25';
let lookupToastId: string | number | undefined;

function beginPatientLookup() {
    lookupToastId = toast.loading('Looking up patient profile', {
        description: 'Checking this number against saved patient records.',
    });
}

function finishPatientLookup() {
    if (lookupToastId !== undefined) toast.dismiss(lookupToastId);
    lookupToastId = undefined;
}

function addMedication() {
    medications.value.push({ name: '', dose: '', frequency: '' });
}

function removeMedication(index: number) {
    medications.value.splice(index, 1);
    if (!medications.value.length) addMedication();
}
</script>

<template>
    <Head title="Patient profile" />
    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="grid lg:grid-cols-[0.72fr_1.28fr]">
            <aside
                class="hidden border-b border-teal-200 bg-teal-50 p-6 lg:block lg:border-r lg:border-b-0 lg:p-8"
            >
                <div
                    class="flex size-11 items-center justify-center rounded-xl border border-teal-200 bg-white text-teal-700"
                >
                    <UserRoundSearch class="size-5" aria-hidden="true" />
                </div>
                <p class="mt-7 text-xl font-semibold text-teal-950">
                    Start with the patient
                </p>
                <p class="mt-3 text-sm leading-6 text-teal-900/75">
                    Find an existing profile or add the details needed for this
                    visit. Returning patients can continue without re-entering
                    unchanged history.
                </p>
                <div
                    class="mt-7 flex items-start gap-3 border-t border-teal-200 pt-5 text-sm leading-6 text-teal-950"
                >
                    <ShieldCheck
                        class="mt-0.5 size-5 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <span
                        >Nothing is shared with a hospital without explicit
                        consent.</span
                    >
                </div>
            </aside>

            <div class="p-6 sm:p-8 lg:p-10">
                <template v-if="!showProfile">
                    <div
                        v-if="resumeJourney"
                        class="mb-7 rounded-xl border border-teal-200 bg-teal-50 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <Clock3
                                class="mt-0.5 size-5 shrink-0 text-teal-800"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-teal-950">
                                    Continue your saved intake
                                </p>
                                <p class="mt-1 text-sm text-teal-900/80">
                                    {{ resumeJourney.patientName }} ·
                                    {{ resumeJourney.mainConcern }}
                                </p>
                                <div
                                    class="mt-4 flex flex-wrap items-center gap-4"
                                >
                                    <Button
                                        as-child
                                        class="bg-teal-700 text-white hover:bg-teal-800"
                                    >
                                        <Link :href="resumeJourney.href"
                                            >Continue</Link
                                        >
                                    </Button>
                                    <Link
                                        href="/intake?restart=1"
                                        class="text-sm font-medium text-teal-900 underline underline-offset-4"
                                        >Start a new intake</Link
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                    <h1
                        class="max-w-lg text-3xl leading-tight font-semibold tracking-tight sm:text-4xl"
                    >
                        Find or create the patient profile
                    </h1>
                    <p class="mt-4 max-w-xl leading-7 text-slate-600">
                        Enter a Ghanaian mobile number. We will use it only to
                        find the patient’s demo profile.
                    </p>
                    <div
                        class="mt-5 flex max-w-xl items-start gap-3 rounded-lg bg-teal-50 px-4 py-3 text-sm leading-6 text-teal-950 lg:hidden"
                    >
                        <ShieldCheck
                            class="mt-0.5 size-5 shrink-0 text-teal-700"
                            aria-hidden="true"
                        />
                        <p>
                            Used only to find your profile. Nothing is shared
                            without your consent.
                        </p>
                    </div>
                    <Form
                        action="/intake/lookup"
                        method="post"
                        class="mt-6 max-w-xl space-y-5 lg:mt-8"
                        v-slot="{ errors, processing }"
                        @start="beginPatientLookup"
                        @finish="finishPatientLookup"
                    >
                        <input type="hidden" name="streamlined" value="1" />
                        <div class="grid gap-2.5">
                            <Label for="phone_number">Mobile number</Label>
                            <Input
                                id="phone_number"
                                name="phone_number"
                                type="tel"
                                required
                                autofocus
                                autocomplete="tel"
                                placeholder="024 123 4567"
                                class="h-12 text-base md:text-base"
                                :aria-invalid="Boolean(errors.phone_number)"
                            />
                            <p class="text-sm text-slate-500">
                                Demo environment. Use dummy patient information
                                only.
                            </p>
                            <InputError :message="errors.phone_number" />
                        </div>
                        <Button
                            class="h-12 w-full bg-teal-700 text-base text-white hover:bg-teal-800"
                            :disabled="processing"
                            ><Spinner v-if="processing" />Continue<ArrowRight
                                v-if="!processing"
                                class="size-4"
                                aria-hidden="true"
                        /></Button>
                    </Form>
                </template>

                <template v-else>
                    <Link
                        href="/intake?restart=1"
                        class="mb-6 inline-flex items-center gap-2 rounded-md text-sm font-medium text-slate-500 transition-colors outline-none hover:text-[#197f6d] focus-visible:ring-3 focus-visible:ring-[#197f6d]/25"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Back
                    </Link>
                    <div>
                        <h1 class="text-3xl font-semibold tracking-tight">
                            Confirm the patient profile
                        </h1>
                        <p class="mt-2 text-sm text-slate-600">
                            Phone: {{ phoneNumber }}
                        </p>
                    </div>

                    <Form
                        action="/intake/profile"
                        method="post"
                        class="mt-8 space-y-8"
                        v-slot="{ errors, processing }"
                    >
                        <section
                            v-if="existingPatient"
                            class="border-y border-slate-200 py-6"
                        >
                            <p class="font-semibold text-slate-950">
                                Returning patient found
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ existingPatient.maskedName }}
                            </p>
                            <div
                                v-if="existingPatient.sexAtBirth === 'UNKNOWN'"
                                class="mt-4 grid max-w-sm gap-2"
                            >
                                <Label for="sex_at_birth_existing"
                                    >Sex at birth</Label
                                >
                                <select
                                    id="sex_at_birth_existing"
                                    name="sex_at_birth"
                                    required
                                    :class="selectClass"
                                >
                                    <option value="" disabled selected>
                                        Select one
                                    </option>
                                    <option value="FEMALE">Female</option>
                                    <option value="MALE">Male</option>
                                    <option value="INTERSEX">Intersex</option>
                                    <option value="PREFER_NOT_TO_SAY">
                                        Prefer not to say
                                    </option>
                                </select>
                                <InputError :message="errors.sex_at_birth" />
                            </div>
                            <p v-else class="mt-4 text-sm text-slate-700">
                                Sex at birth:
                                <strong>{{
                                    sexAtBirthLabels[
                                        existingPatient.sexAtBirth
                                    ] ?? 'Recorded'
                                }}</strong>
                            </p>
                            <p class="mt-4 text-sm leading-6 text-slate-600">
                                If this is not the right profile, go back and
                                use another mobile number.
                            </p>
                        </section>

                        <section v-else class="grid gap-5 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="first_name">First name</Label
                                ><Input
                                    id="first_name"
                                    name="first_name"
                                    required
                                    maxlength="80"
                                /><InputError :message="errors.first_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="last_name">Last name</Label
                                ><Input
                                    id="last_name"
                                    name="last_name"
                                    required
                                    maxlength="80"
                                /><InputError :message="errors.last_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="date_of_birth"
                                    >Date of birth (optional)</Label
                                ><Input
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    type="date"
                                /><InputError :message="errors.date_of_birth" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="sex_at_birth">Sex at birth</Label>
                                <select
                                    id="sex_at_birth"
                                    name="sex_at_birth"
                                    required
                                    :class="selectClass"
                                >
                                    <option value="" disabled selected>
                                        Select one
                                    </option>
                                    <option value="FEMALE">Female</option>
                                    <option value="MALE">Male</option>
                                    <option value="INTERSEX">Intersex</option>
                                    <option value="PREFER_NOT_TO_SAY">
                                        Prefer not to say
                                    </option>
                                </select>
                                <InputError :message="errors.sex_at_birth" />
                            </div>
                        </section>

                        <section>
                            <h2 class="text-lg font-semibold">
                                Medical history
                            </h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Confirm what is known now. “Not sure” is safer
                                than guessing.
                            </p>
                            <fieldset
                                v-if="existingPatient"
                                class="mt-5 grid gap-3 sm:grid-cols-2"
                            >
                                <legend class="sr-only">
                                    Saved medical history status
                                </legend>
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-teal-700 has-[:checked]:bg-teal-50"
                                    ><input
                                        v-model="historyMode"
                                        type="radio"
                                        name="history_mode"
                                        value="UNCHANGED"
                                        class="mt-0.5 size-5 accent-teal-700"
                                    /><span
                                        ><strong class="block text-sm"
                                            >No changes</strong
                                        ><span
                                            class="mt-1 block text-sm text-slate-600"
                                            >Use the saved conditions,
                                            allergies, and medicines.</span
                                        ></span
                                    ></label
                                >
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 has-[:checked]:border-teal-700 has-[:checked]:bg-teal-50"
                                    ><input
                                        v-model="historyMode"
                                        type="radio"
                                        name="history_mode"
                                        value="UPDATE"
                                        class="mt-0.5 size-5 accent-teal-700"
                                    /><span
                                        ><strong class="block text-sm"
                                            >Update history</strong
                                        ><span
                                            class="mt-1 block text-sm text-slate-600"
                                            >Review and change the saved
                                            information.</span
                                        ></span
                                    ></label
                                >
                            </fieldset>
                            <input
                                v-else
                                type="hidden"
                                name="history_mode"
                                value="UPDATE"
                            />
                            <InputError
                                class="mt-2"
                                :message="errors.history_mode"
                            />

                            <div
                                v-if="historyMode === 'UNCHANGED'"
                                class="mt-5 grid gap-4 border-t border-slate-200 pt-5 text-sm sm:grid-cols-3"
                            >
                                <div>
                                    <p class="font-medium">Conditions</p>
                                    <p class="mt-1 text-slate-600">
                                        {{
                                            history.conditions.join(', ') ||
                                            'None recorded'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">Allergies</p>
                                    <p class="mt-1 text-slate-600">
                                        {{
                                            history.allergies.join(', ') ||
                                            'None recorded'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium">Medicines</p>
                                    <p class="mt-1 text-slate-600">
                                        {{
                                            history.medications
                                                .map((item) => item.name)
                                                .join(', ') || 'None recorded'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-6 space-y-6 border-t border-slate-200 pt-6"
                            >
                                <div
                                    class="grid gap-3 sm:grid-cols-[13rem_1fr]"
                                >
                                    <div>
                                        <Label for="conditions_status"
                                            >Medical conditions</Label
                                        ><select
                                            id="conditions_status"
                                            v-model="conditionsStatus"
                                            name="conditions_status"
                                            :class="selectClass"
                                        >
                                            <option value="NONE">None</option>
                                            <option value="DETAILS">
                                                Add conditions
                                            </option>
                                            <option value="UNSURE">
                                                Not sure
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="conditionsStatus === 'DETAILS'">
                                        <Label for="conditions_text"
                                            >One condition per line</Label
                                        ><textarea
                                            id="conditions_text"
                                            name="conditions_text"
                                            :class="textareaClass"
                                            :value="
                                                history.conditions.join('\n')
                                            "
                                            placeholder="Asthma&#10;High blood pressure"
                                        />
                                    </div>
                                    <InputError
                                        class="sm:col-start-2"
                                        :message="
                                            errors.conditions_text ||
                                            errors.conditions_status
                                        "
                                    />
                                </div>
                                <div
                                    class="grid gap-3 sm:grid-cols-[13rem_1fr]"
                                >
                                    <div>
                                        <Label for="allergies_status"
                                            >Allergies</Label
                                        ><select
                                            id="allergies_status"
                                            v-model="allergiesStatus"
                                            name="allergies_status"
                                            :class="selectClass"
                                        >
                                            <option value="NONE">None</option>
                                            <option value="DETAILS">
                                                Add allergies
                                            </option>
                                            <option value="UNSURE">
                                                Not sure
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="allergiesStatus === 'DETAILS'">
                                        <Label for="allergies_text"
                                            >One allergy per line</Label
                                        ><textarea
                                            id="allergies_text"
                                            name="allergies_text"
                                            :class="textareaClass"
                                            :value="
                                                history.allergies.join('\n')
                                            "
                                            placeholder="Penicillin"
                                        />
                                    </div>
                                    <InputError
                                        class="sm:col-start-2"
                                        :message="
                                            errors.allergies_text ||
                                            errors.allergies_status
                                        "
                                    />
                                </div>
                                <div>
                                    <div class="max-w-xs">
                                        <Label for="medications_status"
                                            >Current medicines</Label
                                        ><select
                                            id="medications_status"
                                            v-model="medicationsStatus"
                                            name="medications_status"
                                            :class="selectClass"
                                        >
                                            <option value="NONE">None</option>
                                            <option value="DETAILS">
                                                Add medicines
                                            </option>
                                            <option value="UNSURE">
                                                Not sure
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        v-if="medicationsStatus === 'DETAILS'"
                                        class="mt-4 space-y-3"
                                    >
                                        <div
                                            v-for="(
                                                medicine, index
                                            ) in medications"
                                            :key="index"
                                            class="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-[1fr_0.7fr_0.8fr_auto] sm:items-end"
                                        >
                                            <div>
                                                <Label
                                                    :for="`medicine-name-${index}`"
                                                    >Medicine</Label
                                                ><Input
                                                    :id="`medicine-name-${index}`"
                                                    :name="`medications[${index}][name]`"
                                                    :default-value="
                                                        medicine.name
                                                    "
                                                />
                                            </div>
                                            <div>
                                                <Label
                                                    :for="`medicine-dose-${index}`"
                                                    >Dose</Label
                                                ><Input
                                                    :id="`medicine-dose-${index}`"
                                                    :name="`medications[${index}][dose]`"
                                                    :default-value="
                                                        medicine.dose ?? ''
                                                    "
                                                />
                                            </div>
                                            <div>
                                                <Label
                                                    :for="`medicine-frequency-${index}`"
                                                    >How often</Label
                                                ><Input
                                                    :id="`medicine-frequency-${index}`"
                                                    :name="`medications[${index}][frequency]`"
                                                    :default-value="
                                                        medicine.frequency ?? ''
                                                    "
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-11 text-slate-500"
                                                aria-label="Remove medicine"
                                                @click="removeMedication(index)"
                                                ><Trash2 class="size-4"
                                            /></Button>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="addMedication"
                                            ><Plus class="size-4" />Add another
                                            medicine</Button
                                        >
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="
                                            errors.medications_status ||
                                            errors.medications
                                        "
                                    />
                                </div>
                            </div>
                        </section>

                        <div
                            class="flex justify-end border-t border-slate-200 pt-6"
                        >
                            <Button
                                class="h-11 bg-teal-700 px-6 text-white hover:bg-teal-800"
                                :disabled="processing"
                                ><Spinner
                                    v-if="processing" />Continue<ArrowRight
                                    v-if="!processing"
                                    class="size-4"
                                    aria-hidden="true"
                            /></Button>
                        </div>
                    </Form>
                </template>
            </div>
        </div>
    </section>
</template>
