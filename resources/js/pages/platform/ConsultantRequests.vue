<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock3, MessageSquareText, PhoneCall } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type ConsultantRequest = {
    id: string;
    name: string;
    phone: string;
    concern: string | null;
    status: 'NEW' | 'CONTACTED' | 'CLOSED';
    createdAt: string | null;
    updatedAt: string | null;
};

defineProps<{
    requests: {
        data: ConsultantRequest[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filter: string | null;
    counts: { new: number; contacted: number; closed: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Consultant requests',
                href: '/platform/consultant-requests',
            },
        ],
    },
});

function setStatus(id: string, status: ConsultantRequest['status']): void {
    router.patch(
        `/platform/consultant-requests/${id}`,
        { status },
        { preserveScroll: true },
    );
}

function formatTime(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-GB', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not recorded';
}

function pageLabel(value: string): string {
    return value
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace(/<[^>]*>/g, '');
}
</script>

<template>
    <Head title="Consultant requests" />
    <main class="min-h-full bg-slate-50 px-5 py-7 text-slate-950 sm:px-8">
        <div class="mx-auto max-w-6xl">
            <header class="border-b border-slate-200 pb-6">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Consultant callback queue
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    Track requests submitted from the public consultation panel.
                    Mark contact attempts promptly and close only after the
                    request has been handled.
                </p>
            </header>

            <nav
                class="mt-6 flex flex-wrap gap-2"
                aria-label="Filter consultant requests"
            >
                <Link
                    href="/platform/consultant-requests"
                    class="rounded-md border px-3 py-2 text-sm font-medium"
                    :class="
                        !filter
                            ? 'border-teal-700 bg-teal-50 text-teal-900'
                            : 'border-slate-200 bg-white text-slate-600'
                    "
                    >All</Link
                >
                <Link
                    href="/platform/consultant-requests?status=NEW"
                    class="rounded-md border px-3 py-2 text-sm font-medium"
                    :class="
                        filter === 'NEW'
                            ? 'border-teal-700 bg-teal-50 text-teal-900'
                            : 'border-slate-200 bg-white text-slate-600'
                    "
                    >New {{ counts.new }}</Link
                >
                <Link
                    href="/platform/consultant-requests?status=CONTACTED"
                    class="rounded-md border px-3 py-2 text-sm font-medium"
                    :class="
                        filter === 'CONTACTED'
                            ? 'border-teal-700 bg-teal-50 text-teal-900'
                            : 'border-slate-200 bg-white text-slate-600'
                    "
                    >Contacted {{ counts.contacted }}</Link
                >
                <Link
                    href="/platform/consultant-requests?status=CLOSED"
                    class="rounded-md border px-3 py-2 text-sm font-medium"
                    :class="
                        filter === 'CLOSED'
                            ? 'border-teal-700 bg-teal-50 text-teal-900'
                            : 'border-slate-200 bg-white text-slate-600'
                    "
                    >Closed {{ counts.closed }}</Link
                >
            </nav>

            <section
                v-if="requests.data.length"
                class="mt-5 divide-y divide-slate-200 border border-slate-200 bg-white"
            >
                <article
                    v-for="item in requests.data"
                    :key="item.id"
                    class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="font-semibold">{{ item.name }}</h2>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="
                                    item.status === 'NEW'
                                        ? 'bg-amber-100 text-amber-900'
                                        : item.status === 'CONTACTED'
                                          ? 'bg-teal-100 text-teal-900'
                                          : 'bg-slate-100 text-slate-600'
                                "
                                >{{ item.status.toLowerCase() }}</span
                            >
                        </div>
                        <a
                            :href="`tel:${item.phone}`"
                            class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-teal-800 underline underline-offset-4"
                            ><PhoneCall class="size-4" aria-hidden="true" />{{
                                item.phone
                            }}</a
                        >
                        <p
                            v-if="item.concern"
                            class="mt-3 flex max-w-3xl gap-2 text-sm leading-6 text-slate-700"
                        >
                            <MessageSquareText
                                class="mt-0.5 size-4 shrink-0 text-slate-400"
                                aria-hidden="true"
                            />{{ item.concern }}
                        </p>
                        <p
                            class="mt-3 flex items-center gap-2 text-xs text-slate-500"
                        >
                            <Clock3
                                class="size-4"
                                aria-hidden="true"
                            />Requested {{ formatTime(item.createdAt) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <Button
                            v-if="item.status === 'NEW'"
                            variant="outline"
                            @click="setStatus(item.id, 'CONTACTED')"
                            >Mark contacted</Button
                        >
                        <Button
                            v-if="item.status !== 'CLOSED'"
                            class="bg-teal-700 text-white hover:bg-teal-800"
                            @click="setStatus(item.id, 'CLOSED')"
                            >Close request</Button
                        >
                        <Button
                            v-else
                            variant="outline"
                            @click="setStatus(item.id, 'NEW')"
                            >Reopen</Button
                        >
                    </div>
                </article>
            </section>

            <section
                v-else
                class="mt-5 border border-slate-200 bg-white px-6 py-14 text-center"
            >
                <MessageSquareText
                    class="mx-auto size-7 text-teal-700"
                    aria-hidden="true"
                />
                <h2 class="mt-4 font-semibold">No requests in this view</h2>
                <p class="mt-2 text-sm text-slate-600">
                    New callback requests will appear here.
                </p>
            </section>

            <nav
                v-if="requests.links.length > 3"
                class="mt-5 flex flex-wrap gap-2"
                aria-label="Pagination"
            >
                <Link
                    v-for="link in requests.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-md border px-3 py-2 text-sm"
                    :class="[
                        link.active
                            ? 'border-teal-700 bg-teal-50 text-teal-900'
                            : 'border-slate-200 bg-white text-slate-600',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]"
                    >{{ pageLabel(link.label) }}</Link
                >
            </nav>
        </div>
    </main>
</template>
