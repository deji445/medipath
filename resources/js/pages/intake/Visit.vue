<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    LocateFixed,
    MapPinned,
    Mic,
    Square,
    UserRound,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type VisitDraft = {
    age_years?: string | number;
    filling_as?: string;
    respondent_name?: string;
    relationship_to_patient?: string;
    pregnancy_status?: string;
    main_complaint?: string;
    manual_location?: string;
    latitude?: string | number | null;
    longitude?: string | number | null;
    temperature_c?: string | number;
    heart_rate_bpm?: string | number;
    oxygen_saturation_percent?: string | number;
};

type SpeechResultEvent = Event & {
    resultIndex: number;
    results: {
        length: number;
        [index: number]: {
            isFinal: boolean;
            0: { transcript: string };
        };
    };
};

type SpeechRecognitionInstance = {
    continuous: boolean;
    interimResults: boolean;
    lang: string;
    start: () => void;
    stop: () => void;
    abort: () => void;
    onresult: ((event: SpeechResultEvent) => void) | null;
    onerror: (() => void) | null;
    onend: (() => void) | null;
};

type SpeechRecognitionConstructor = new () => SpeechRecognitionInstance;

const props = defineProps<{
    visit?: VisitDraft | null;
    patientAge?: number | null;
    sexAtBirth: string;
    formAction?: string;
    formMethod?: 'post' | 'patch';
    backHref?: string;
}>();

const fillingAs = ref(String(props.visit?.filling_as ?? 'SELF'));
const mainComplaint = ref(String(props.visit?.main_complaint ?? ''));
const speechSupported = ref(false);
const isListening = ref(false);
const speechStatus = ref('');
const draftStatus = ref('');
const latitude = ref<string>(String(props.visit?.latitude ?? ''));
const longitude = ref<string>(String(props.visit?.longitude ?? ''));
const locationPermissionGranted = ref(
    props.visit?.latitude != null && props.visit?.longitude != null,
);
const locating = ref(false);
const locationStatus = ref(
    locationPermissionGranted.value
        ? 'Precise location added for distance-based hospital matching.'
        : '',
);
let recognition: SpeechRecognitionInstance | null = null;
const concernDraftKey = 'medipath:intake:current-concern';

const concernLength = computed(() => mainComplaint.value.length);
const fieldClass =
    'h-10 w-full appearance-none rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-xs outline-none focus-visible:border-teal-700 focus-visible:ring-[3px] focus-visible:ring-teal-700/25';

const toggleDictation = () => {
    if (!recognition) return;

    if (isListening.value) {
        recognition.stop();
        return;
    }

    speechStatus.value = 'Listening';
    isListening.value = true;

    try {
        recognition.start();
    } catch {
        isListening.value = false;
        speechStatus.value =
            'Speech input could not start. You can keep typing.';
    }
};

const useCurrentLocation = () => {
    if (!navigator.geolocation) {
        locationStatus.value =
            'This browser cannot share location. Enter your area or landmark instead.';
        return;
    }

    locating.value = true;
    locationStatus.value = 'Finding your current location…';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            latitude.value = position.coords.latitude.toFixed(6);
            longitude.value = position.coords.longitude.toFixed(6);
            locationPermissionGranted.value = true;
            locating.value = false;
            locationStatus.value =
                'Location added. Hospitals will be ranked by distance where coordinates are available.';
        },
        () => {
            locating.value = false;
            locationStatus.value =
                'Location was not shared. Enter your area or landmark instead.';
        },
        {
            enableHighAccuracy: false,
            timeout: 10000,
            maximumAge: 300000,
        },
    );
};

function clearConcernDraft() {
    try {
        sessionStorage.removeItem(concernDraftKey);
    } catch {
        // Browsers may block storage in private or restricted contexts.
    }
}

watch(mainComplaint, (value) => {
    if (props.visit?.main_complaint) return;

    try {
        if (value.trim()) {
            sessionStorage.setItem(concernDraftKey, value);
            draftStatus.value = 'Draft saved in this tab';
        } else {
            clearConcernDraft();
            draftStatus.value = '';
        }
    } catch {
        draftStatus.value = '';
    }
});

onMounted(() => {
    if (!mainComplaint.value) {
        try {
            const savedConcern = sessionStorage.getItem(concernDraftKey);

            if (savedConcern) {
                mainComplaint.value = savedConcern.slice(0, 500);
                draftStatus.value = 'Unsaved concern restored';
            }
        } catch {
            draftStatus.value = '';
        }
    }

    const speechWindow = window as typeof window & {
        SpeechRecognition?: SpeechRecognitionConstructor;
        webkitSpeechRecognition?: SpeechRecognitionConstructor;
    };
    const Recognition =
        speechWindow.SpeechRecognition ?? speechWindow.webkitSpeechRecognition;

    if (!Recognition) return;

    speechSupported.value = true;
    recognition = new Recognition();
    recognition.continuous = true;
    recognition.interimResults = false;
    recognition.lang = navigator.language || 'en-GB';
    recognition.onresult = (event) => {
        const spokenParts: string[] = [];

        for (
            let index = event.resultIndex;
            index < event.results.length;
            index++
        ) {
            if (event.results[index].isFinal) {
                spokenParts.push(event.results[index][0].transcript.trim());
            }
        }

        if (spokenParts.length > 0) {
            mainComplaint.value = [
                mainComplaint.value.trim(),
                spokenParts.join(' '),
            ]
                .filter(Boolean)
                .join(' ')
                .slice(0, 500);
        }
    };
    recognition.onerror = () => {
        isListening.value = false;
        speechStatus.value = 'Speech input stopped. You can continue typing.';
    };
    recognition.onend = () => {
        isListening.value = false;
        if (speechStatus.value === 'Listening') {
            speechStatus.value = 'Speech added to your concern.';
        }
    };
});

onBeforeUnmount(() => recognition?.abort());
</script>

<template>
    <Head title="Current concern" />
    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="border-b border-teal-100 bg-teal-50/70 px-6 py-6 sm:px-8">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-teal-800 uppercase"
            >
                Current concern
            </p>
            <h1
                class="mt-2 text-3xl leading-tight font-semibold tracking-tight text-slate-950"
            >
                What is happening now?
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Describe the main problem in your own words. MediPath will check
                it for urgent warning signs before the short assessment.
            </p>
        </div>

        <Form
            :action="formAction ?? '/intake/visit'"
            :method="formMethod ?? 'post'"
            class="space-y-8 p-6 sm:p-8"
            v-slot="{ errors, processing }"
            @success="clearConcernDraft"
        >
            <input type="hidden" name="streamlined" value="1" />
            <input type="hidden" name="latitude" :value="latitude" />
            <input type="hidden" name="longitude" :value="longitude" />
            <input
                type="hidden"
                name="location_permission_granted"
                :value="locationPermissionGranted ? '1' : '0'"
            />

            <section aria-labelledby="main-concern-label">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Label
                            id="main-concern-label"
                            for="main_complaint"
                            class="text-lg font-semibold text-slate-950"
                            >Main concern</Label
                        >
                        <p class="mt-1 text-sm text-slate-600">
                            Include what started, how it feels, and what
                            changed.
                        </p>
                    </div>
                    <Button
                        v-if="speechSupported"
                        type="button"
                        variant="outline"
                        class="border-teal-200 text-teal-800 hover:bg-teal-50"
                        :aria-pressed="isListening"
                        :aria-label="
                            isListening
                                ? 'Stop speech input'
                                : 'Describe the concern using speech'
                        "
                        @click="toggleDictation"
                    >
                        <Square
                            v-if="isListening"
                            class="size-4 fill-current"
                            aria-hidden="true"
                        />
                        <Mic v-else class="size-4" aria-hidden="true" />
                        {{ isListening ? 'Stop' : 'Speak' }}
                    </Button>
                </div>

                <textarea
                    id="main_complaint"
                    v-model="mainComplaint"
                    name="main_complaint"
                    required
                    maxlength="500"
                    class="mt-4 min-h-44 w-full resize-y rounded-xl border border-slate-300 bg-white px-4 py-4 text-lg leading-7 text-slate-950 shadow-xs outline-none placeholder:text-slate-400 focus-visible:border-teal-700 focus-visible:ring-3 focus-visible:ring-teal-700/20"
                    placeholder="For example: I have had a tight pain in my chest for 20 minutes and I feel short of breath."
                />
                <div
                    class="mt-2 flex min-h-5 items-start justify-between gap-4 text-xs text-slate-500"
                >
                    <p aria-live="polite">
                        {{
                            speechStatus ||
                            draftStatus ||
                            'Type or use speech input if your browser supports it.'
                        }}
                    </p>
                    <span class="shrink-0 tabular-nums"
                        >{{ concernLength }}/500</span
                    >
                </div>
                <InputError class="mt-2" :message="errors.main_complaint" />
            </section>

            <div
                class="grid gap-6 border-t border-slate-200 pt-8 lg:grid-cols-2"
            >
                <fieldset>
                    <legend
                        class="flex items-center gap-2 text-base font-semibold text-slate-950"
                    >
                        <UserRound
                            class="size-5 text-teal-700"
                            aria-hidden="true"
                        />
                        Who is completing this?
                    </legend>
                    <select
                        id="filling_as"
                        v-model="fillingAs"
                        name="filling_as"
                        :class="[fieldClass, 'mt-4']"
                        required
                    >
                        <option value="SELF">The patient</option>
                        <option value="COMPANION">A companion</option>
                        <option value="CAREGIVER">A caregiver</option>
                    </select>
                    <InputError class="mt-2" :message="errors.filling_as" />

                    <div v-if="fillingAs !== 'SELF'" class="mt-4 grid gap-4">
                        <div class="grid gap-2">
                            <Label for="respondent_name">Your name</Label>
                            <Input
                                id="respondent_name"
                                name="respondent_name"
                                required
                                :default-value="visit?.respondent_name ?? ''"
                            />
                            <InputError :message="errors.respondent_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="relationship_to_patient"
                                >Relationship to patient</Label
                            >
                            <Input
                                id="relationship_to_patient"
                                name="relationship_to_patient"
                                required
                                :default-value="
                                    visit?.relationship_to_patient ?? ''
                                "
                            />
                            <InputError
                                :message="errors.relationship_to_patient"
                            />
                        </div>
                    </div>
                </fieldset>

                <div class="grid content-start gap-5">
                    <div v-if="patientAge == null" class="grid gap-2">
                        <Label for="age_years">Patient age</Label>
                        <Input
                            id="age_years"
                            name="age_years"
                            type="number"
                            min="0"
                            max="120"
                            required
                            :default-value="visit?.age_years ?? ''"
                        />
                        <p class="text-xs text-slate-500">
                            Needed because no date of birth is saved.
                        </p>
                        <InputError :message="errors.age_years" />
                    </div>

                    <div v-if="sexAtBirth === 'FEMALE'" class="grid gap-2">
                        <Label for="pregnancy_status">Pregnancy status</Label>
                        <select
                            id="pregnancy_status"
                            name="pregnancy_status"
                            :class="fieldClass"
                            required
                            :value="visit?.pregnancy_status ?? ''"
                        >
                            <option value="" disabled>Select one</option>
                            <option value="PREGNANT">Pregnant</option>
                            <option value="NOT_PREGNANT">Not pregnant</option>
                            <option value="UNKNOWN">Unknown</option>
                        </select>
                        <InputError :message="errors.pregnancy_status" />
                    </div>
                </div>
            </div>

            <details
                class="group rounded-xl border border-slate-200 bg-slate-50/70 open:bg-white"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-3 px-4 py-4 font-medium text-slate-900 marker:content-none"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-teal-100 text-teal-800"
                    >
                        <MapPinned class="size-4" aria-hidden="true" />
                    </span>
                    <span>
                        Add location or known vital signs
                        <span class="block text-xs font-normal text-slate-500"
                            >Optional, but useful for hospital routing</span
                        >
                    </span>
                    <span
                        class="ml-auto text-sm text-teal-800 group-open:hidden"
                        >Add</span
                    >
                    <span
                        class="ml-auto hidden text-sm text-teal-800 group-open:inline"
                        >Close</span
                    >
                </summary>
                <div class="border-t border-slate-200 px-4 py-5">
                    <div
                        class="mb-5 flex flex-col gap-3 rounded-xl bg-teal-50 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex gap-3">
                            <CheckCircle2
                                v-if="locationPermissionGranted"
                                class="mt-0.5 size-5 shrink-0 text-teal-700"
                                aria-hidden="true"
                            />
                            <LocateFixed
                                v-else
                                class="mt-0.5 size-5 shrink-0 text-teal-700"
                                aria-hidden="true"
                            />
                            <div>
                                <p class="font-medium text-slate-950">
                                    {{
                                        locationPermissionGranted
                                            ? 'Current location added'
                                            : 'Find hospitals near me'
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-600"
                                    aria-live="polite"
                                >
                                    {{
                                        locationStatus ||
                                        'Your browser will ask before sharing your location.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            class="shrink-0 border-teal-300 bg-white text-teal-800 hover:bg-teal-100"
                            :disabled="locating"
                            @click="useCurrentLocation"
                        >
                            <Spinner v-if="locating" />
                            {{
                                locating
                                    ? 'Locating'
                                    : locationPermissionGranted
                                      ? 'Update'
                                      : 'Use my location'
                            }}
                        </Button>
                    </div>
                    <div class="grid gap-2">
                        <Label for="manual_location"
                            >Current town, area, or landmark</Label
                        >
                        <Input
                            id="manual_location"
                            name="manual_location"
                            :default-value="visit?.manual_location ?? ''"
                            placeholder="Town, area, or landmark"
                        />
                        <InputError :message="errors.manual_location" />
                        <InputError :message="errors.latitude" />
                        <InputError :message="errors.longitude" />
                        <p class="text-xs leading-5 text-slate-500">
                            If GPS is unavailable, MediPath will try to match
                            the entered area in Ghana. Location lookup uses
                            OpenStreetMap data.
                        </p>
                    </div>
                    <fieldset class="mt-5 border-t border-slate-200 pt-5">
                        <legend
                            class="flex items-center gap-2 pr-2 font-medium text-slate-900"
                        >
                            <Activity
                                class="size-4 text-teal-700"
                                aria-hidden="true"
                            />
                            Vitals, if known
                        </legend>
                        <div class="mt-3 grid gap-5 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="temperature_c"
                                    >Temperature °C</Label
                                >
                                <Input
                                    id="temperature_c"
                                    name="temperature_c"
                                    type="number"
                                    min="30"
                                    max="45"
                                    step="0.1"
                                    :default-value="visit?.temperature_c ?? ''"
                                />
                                <InputError :message="errors.temperature_c" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="heart_rate_bpm">Heart rate</Label>
                                <Input
                                    id="heart_rate_bpm"
                                    name="heart_rate_bpm"
                                    type="number"
                                    min="20"
                                    max="250"
                                    :default-value="visit?.heart_rate_bpm ?? ''"
                                />
                                <InputError :message="errors.heart_rate_bpm" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="oxygen_saturation_percent"
                                    >Oxygen %</Label
                                >
                                <Input
                                    id="oxygen_saturation_percent"
                                    name="oxygen_saturation_percent"
                                    type="number"
                                    min="50"
                                    max="100"
                                    :default-value="
                                        visit?.oxygen_saturation_percent ?? ''
                                    "
                                />
                                <InputError
                                    :message="errors.oxygen_saturation_percent"
                                />
                            </div>
                        </div>
                    </fieldset>
                </div>
            </details>

            <div
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950"
            >
                <input
                    type="hidden"
                    name="intake_consent_acknowledged"
                    value="1"
                />
                <p>
                    By continuing, you understand this is guidance, not a
                    diagnosis, and agree to the use of these health details for
                    this intake.
                </p>
                <InputError
                    class="mt-2"
                    :message="errors.intake_consent_acknowledged"
                />
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link
                    :href="backHref ?? '/intake'"
                    class="text-center text-sm font-medium text-slate-600 underline underline-offset-4 hover:text-slate-950"
                    >Back</Link
                >
                <Button
                    class="h-11 bg-teal-700 px-7 text-white hover:bg-teal-800"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Continue
                </Button>
            </div>
        </Form>
    </section>
</template>
