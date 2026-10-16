<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronRight,
    Clock3,
    Inbox,
    RotateCcw,
    Search,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CaseSummaryPanel, {
    type Admission,
} from '@/components/dashboard/CaseSummaryPanel.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dashboard } from '@/routes';

type PaginatedAdmissions = {
    data: Admission[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    hospital: {
        name: string;
        fullName: string;
        acceptingSubmissions: boolean;
    } | null;
    counts: { highPriority: number; waiting: number; inTriage: number };
    submissions: PaginatedAdmissions;
    filters: { search: string; severity: string; status: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Admissions', href: dashboard() }] },
});

const search = ref(props.filters.search);
const severity = ref(props.filters.severity);
const status = ref(props.filters.status);
const selectedId = ref<string | null>(props.submissions.data[0]?.id ?? null);
const mobilePanelOpen = ref(false);
const closeDialogOpen = ref(false);
const processing = ref(false);
const isLoading = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let removeStartListener: (() => void) | undefined;
let removeFinishListener: (() => void) | undefined;

const selectedAdmission = computed(
    () =>
        props.submissions.data.find((item) => item.id === selectedId.value) ??
        props.submissions.data[0] ??
        null,
);
const hasFilters = computed(
    () =>
        search.value !== '' ||
        severity.value !== 'ALL' ||
        status.value !== 'ACTIVE',
);

watch(
    () => props.submissions.data,
    (admissions) => {
        if (!admissions.some((item) => item.id === selectedId.value))
            selectedId.value = admissions[0]?.id ?? null;
    },
);

onMounted(() => {
    removeStartListener = router.on('start', () => {
        isLoading.value = true;
    });
    removeFinishListener = router.on('finish', () => {
        isLoading.value = false;
    });
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    removeStartListener?.();
    removeFinishListener?.();
});

function loadFilters(): void {
    router.get(
        '/dashboard',
        {
            search: search.value || undefined,
            severity: severity.value === 'ALL' ? undefined : severity.value,
            status: status.value === 'ACTIVE' ? undefined : status.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['counts', 'submissions', 'filters'],
        },
    );
}

function queueSearch(): void {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadFilters, 350);
}

function resetFilters(): void {
    search.value = '';
    severity.value = 'ALL';
    status.value = 'ACTIVE';
    loadFilters();
}

function selectAdmission(admission: Admission): void {
    selectedId.value = admission.id;
    mobilePanelOpen.value = true;
}

function requestAction(
    nextStatus: 'ACKNOWLEDGED' | 'IN_TRIAGE' | 'CLOSED',
): void {
    if (nextStatus === 'CLOSED') closeDialogOpen.value = true;
    else updateStatus(nextStatus);
}

function updateStatus(
    nextStatus: 'ACKNOWLEDGED' | 'IN_TRIAGE' | 'CLOSED',
): void {
    if (!selectedAdmission.value) return;
    processing.value = true;
    router.patch(
        `/dashboard/submissions/${selectedAdmission.value.id}`,
        { status: nextStatus },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDialogOpen.value = false;
                if (nextStatus === 'CLOSED') mobilePanelOpen.value = false;
            },
            onError: () =>
                toast.error(
                    'The case could not be updated. Refresh the queue and try again.',
                ),
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function words(value: string): string {
    return value
        .toLowerCase()
        .replaceAll('_', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

function timeAgo(value: string): string {
    const minutes = Math.max(
        0,
        Math.floor((Date.now() - new Date(value).getTime()) / 60_000),
    );
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    return hours < 24 ? `${hours}h ago` : `${Math.floor(hours / 24)}d ago`;
}
</script>

<template>
    <Head title="Admissions dashboard" />
    <div
        class="min-h-full bg-slate-50 text-slate-950 dark:bg-slate-50 dark:text-slate-950"
    >
        <p class="sr-only" aria-live="assertive">
            {{
                counts.highPriority
                    ? `${counts.highPriority} high priority cases require attention.`
                    : 'No high priority cases.'
            }}
        </p>

        <header class="border-b border-slate-200 bg-white px-5 py-5 sm:px-8">
            <div
                class="mx-auto flex max-w-[1600px] flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Admissions queue
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ hospital?.fullName }}
                    </p>
                </div>
                <div
                    class="inline-flex w-fit items-center gap-2 border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="
                            hospital?.acceptingSubmissions
                                ? 'bg-emerald-500'
                                : 'bg-slate-400'
                        "
                        aria-hidden="true"
                    />
                    {{
                        hospital?.acceptingSubmissions
                            ? 'Receiving submissions'
                            : 'Submissions paused'
                    }}
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] px-5 py-5 sm:px-8 sm:py-7">
            <section
                aria-label="Live queue counts"
                class="grid border border-slate-200 bg-white sm:grid-cols-3"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:border-r sm:border-b-0"
                >
                    <div>
                        <p class="text-sm text-slate-500">High priority</p>
                        <p
                            class="mt-1 text-2xl font-semibold text-red-700 tabular-nums"
                        >
                            {{ counts.highPriority }}
                        </p>
                    </div>
                    <AlertTriangle
                        class="size-5 text-red-600"
                        aria-hidden="true"
                    />
                </div>
                <div
                    class="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:border-r sm:border-b-0"
                >
                    <div>
                        <p class="text-sm text-slate-500">Waiting</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">
                            {{ counts.waiting }}
                        </p>
                    </div>
                    <Clock3 class="size-5 text-amber-600" aria-hidden="true" />
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <div>
                        <p class="text-sm text-slate-500">In triage</p>
                        <p
                            class="mt-1 text-2xl font-semibold text-teal-800 tabular-nums"
                        >
                            {{ counts.inTriage }}
                        </p>
                    </div>
                    <SlidersHorizontal
                        class="size-5 text-teal-700"
                        aria-hidden="true"
                    />
                </div>
            </section>

            <section
                class="mt-5 grid min-h-[620px] overflow-hidden border border-slate-200 bg-white xl:grid-cols-[minmax(0,1fr)_410px]"
            >
                <div class="min-w-0 xl:border-r xl:border-slate-200">
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center"
                    >
                        <label class="relative min-w-0 flex-1">
                            <span class="sr-only">Search admissions</span
                            ><Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                aria-hidden="true"
                            />
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Search patient or concern"
                                class="h-10 w-full border border-slate-300 bg-white pr-3 pl-9 text-sm transition outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/15"
                                @input="queueSearch"
                            />
                        </label>
                        <div
                            class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 lg:flex"
                        >
                            <label class="block min-w-0"
                                ><span class="sr-only">Filter by severity</span
                                ><select
                                    v-model="severity"
                                    class="h-10 w-full max-w-full border border-slate-300 bg-white px-3 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/15"
                                    @change="loadFilters"
                                >
                                    <option value="ALL">All severity</option>
                                    <option value="HIGH">High</option>
                                    <option value="MEDIUM">Medium</option>
                                    <option value="LOW">Low</option>
                                </select></label
                            >
                            <label class="block min-w-0"
                                ><span class="sr-only">Filter by status</span
                                ><select
                                    v-model="status"
                                    class="h-10 w-full max-w-full border border-slate-300 bg-white px-3 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/15"
                                    @change="loadFilters"
                                >
                                    <option value="ACTIVE">Active queue</option>
                                    <option value="RECEIVED">Received</option>
                                    <option value="ACKNOWLEDGED">
                                        Acknowledged
                                    </option>
                                    <option value="IN_TRIAGE">In triage</option>
                                    <option value="CLOSED">Closed</option>
                                    <option value="ALL">All cases</option>
                                </select></label
                            >
                        </div>
                    </div>

                    <div
                        v-if="isLoading"
                        class="divide-y divide-slate-100"
                        aria-label="Loading admissions"
                    >
                        <div
                            v-for="item in 6"
                            :key="item"
                            class="grid animate-pulse gap-3 px-4 py-5 sm:grid-cols-4"
                        >
                            <div
                                v-for="column in 4"
                                :key="column"
                                class="h-5 bg-slate-100"
                            />
                        </div>
                    </div>

                    <div
                        v-else-if="submissions.data.length"
                        class="divide-y divide-slate-100"
                    >
                        <div
                            class="hidden grid-cols-[104px_minmax(150px,0.8fr)_minmax(190px,1.4fr)_96px_104px_24px] gap-3 bg-slate-50 px-4 py-2.5 text-xs font-medium text-slate-500 lg:grid"
                        >
                            <span>Priority</span><span>Patient</span
                            ><span>Main concern</span><span>Received</span
                            ><span>Status</span
                            ><span class="sr-only">Open</span>
                        </div>
                        <button
                            v-for="admission in submissions.data"
                            :key="admission.id"
                            type="button"
                            class="group grid w-full gap-3 border-l px-4 py-4 text-left transition hover:bg-slate-50 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:outline-none focus-visible:ring-inset lg:grid-cols-[104px_minmax(150px,0.8fr)_minmax(190px,1.4fr)_96px_104px_24px] lg:items-center"
                            :class="
                                selectedAdmission?.id === admission.id
                                    ? 'border-l-teal-700 bg-teal-50/40'
                                    : admission.severity === 'HIGH'
                                      ? 'border-l-red-500'
                                      : 'border-l-transparent'
                            "
                            @click="selectAdmission(admission)"
                        >
                            <span
                                class="flex items-center justify-between lg:block"
                                ><span
                                    class="text-xs font-semibold"
                                    :class="
                                        admission.severity === 'HIGH'
                                            ? 'text-red-700'
                                            : admission.severity === 'MEDIUM'
                                              ? 'text-amber-700'
                                              : 'text-teal-800'
                                    "
                                    >{{ words(admission.severity) }}</span
                                ><span
                                    class="text-xs text-slate-500 lg:hidden"
                                    >{{ timeAgo(admission.receivedAt) }}</span
                                ></span
                            >
                            <span
                                ><span
                                    class="block font-medium text-slate-950"
                                    >{{ admission.patient.name }}</span
                                ><span
                                    class="mt-0.5 block text-xs text-slate-500"
                                    >{{ admission.patient.age ?? 'Age unknown'
                                    }}<template
                                        v-if="admission.patient.sexAtBirth"
                                    >
                                        ·
                                        {{
                                            words(admission.patient.sexAtBirth)
                                        }}</template
                                    ></span
                                ></span
                            >
                            <span class="line-clamp-2 text-sm text-slate-600">{{
                                admission.mainConcern
                            }}</span
                            ><span
                                class="hidden text-sm text-slate-600 lg:block"
                                >{{ timeAgo(admission.receivedAt) }}</span
                            ><span class="text-xs font-medium text-slate-600">{{
                                words(admission.status)
                            }}</span
                            ><ChevronRight
                                class="hidden size-4 text-slate-400 transition group-hover:translate-x-0.5 lg:block"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <div
                        v-else
                        class="flex min-h-[410px] items-center justify-center px-6 py-16 text-center"
                    >
                        <div class="max-w-sm">
                            <component
                                :is="hasFilters ? Search : Inbox"
                                class="mx-auto size-7 text-teal-700"
                                aria-hidden="true"
                            />
                            <h2 class="mt-4 text-lg font-semibold">
                                {{
                                    hasFilters
                                        ? 'No matching admissions'
                                        : 'The admissions queue is clear'
                                }}
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                {{
                                    hasFilters
                                        ? 'Try a broader search or reset the filters to see the active queue.'
                                        : 'New patient submissions will appear here, ordered by clinical priority and waiting time.'
                                }}
                            </p>
                            <Button
                                v-if="hasFilters"
                                variant="outline"
                                class="mt-5 border-slate-300"
                                @click="resetFilters"
                                ><RotateCcw /> Reset filters</Button
                            >
                        </div>
                    </div>

                    <footer
                        v-if="submissions.last_page > 1"
                        class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm"
                    >
                        <p class="text-slate-500">
                            Showing {{ submissions.from }} to
                            {{ submissions.to }} of {{ submissions.total }}
                        </p>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!submissions.prev_page_url"
                                as-child
                                ><Link
                                    :href="submissions.prev_page_url ?? '#'"
                                    preserve-scroll
                                    preserve-state
                                    >Previous</Link
                                ></Button
                            ><Button
                                variant="outline"
                                size="sm"
                                :disabled="!submissions.next_page_url"
                                as-child
                                ><Link
                                    :href="submissions.next_page_url ?? '#'"
                                    preserve-scroll
                                    preserve-state
                                    >Next</Link
                                ></Button
                            >
                        </div>
                    </footer>
                </div>

                <aside class="hidden min-h-0 xl:block">
                    <CaseSummaryPanel
                        v-if="selectedAdmission"
                        :admission="selectedAdmission"
                        :processing="processing"
                        @action="requestAction"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center p-8 text-center text-sm text-slate-500"
                    >
                        Select an admission to review its case summary.
                    </div>
                </aside>
            </section>
        </main>

        <Sheet v-model:open="mobilePanelOpen">
            <SheetContent
                side="right"
                class="w-full max-w-none gap-0 border-0 p-0 sm:max-w-xl xl:hidden"
                ><SheetHeader class="sr-only"
                    ><SheetTitle>Admission case summary</SheetTitle
                    ><SheetDescription
                        >Review the selected patient submission and update its
                        queue status.</SheetDescription
                    ></SheetHeader
                ><CaseSummaryPanel
                    v-if="selectedAdmission"
                    :admission="selectedAdmission"
                    :processing="processing"
                    @action="requestAction"
            /></SheetContent>
        </Sheet>

        <Dialog v-model:open="closeDialogOpen">
            <DialogContent class="bg-white text-slate-950 sm:max-w-md"
                ><DialogHeader
                    ><DialogTitle>Close this case?</DialogTitle
                    ><DialogDescription class="text-slate-600"
                        >The case will leave the active queue. You can still
                        find it later with the Closed status
                        filter.</DialogDescription
                    ></DialogHeader
                ><DialogFooter
                    ><DialogClose as-child
                        ><Button variant="outline" :disabled="processing"
                            >Keep open</Button
                        ></DialogClose
                    ><Button
                        class="bg-teal-700 text-white hover:bg-teal-800"
                        :disabled="processing"
                        @click="updateStatus('CLOSED')"
                        >{{ processing ? 'Closing…' : 'Close case' }}</Button
                    ></DialogFooter
                ></DialogContent
            >
        </Dialog>
    </div>
</template>
