<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClipboardList, ShieldCheck, Users } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import CareRibbon from '@/components/CareRibbon.vue';
import LanguageSwitch from '@/components/LanguageSwitch.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="care-shell patient-shell grid min-h-svh bg-slate-50 text-slate-950 [--background:#f4f8f6] [--border:#d2e2dc] [--foreground:#16352f] [--input:#c6d8d1] [--muted-foreground:#60766f] [--primary-foreground:#ffffff] [--primary:#197f6d] lg:grid-cols-[1.05fr_0.95fr]"
    >
        <aside
            class="relative isolate hidden overflow-hidden border-r border-teal-200 bg-teal-50 p-12 lg:flex lg:flex-col xl:p-16"
        >
            <CareRibbon
                :interactive="false"
                :scroll-responsive="false"
                class="absolute inset-0 -z-10 opacity-65 dark:opacity-20"
            />
            <Link
                :href="home()"
                class="flex w-fit items-center gap-3 rounded-md text-xl font-semibold outline-none focus-visible:ring-3 focus-visible:ring-[#197f6d]/30"
            >
                <AppLogoIcon class="size-11 text-teal-700" />
                MediPath
            </Link>

            <div class="my-auto max-w-xl py-16">
                <h2
                    class="text-4xl leading-tight font-semibold tracking-[-0.03em] text-slate-950 xl:text-5xl"
                >
                    The patient story, ready before they arrive.
                </h2>
                <p class="mt-6 text-lg leading-8 text-slate-700">
                    Open the hospital workspace to review incoming cases,
                    understand urgency, and move patients through admissions.
                </p>

                <div class="mt-10 space-y-6 border-t border-teal-200 pt-8">
                    <div class="flex items-start gap-4">
                        <ClipboardList
                            class="mt-0.5 size-5 shrink-0 text-teal-700"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="font-semibold text-slate-950">
                                Structured admissions queue
                            </p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Priority, history, and concern stay together.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <Users
                            class="mt-0.5 size-5 shrink-0 text-teal-700"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="font-semibold text-slate-950">
                                Hospital-specific access
                            </p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Each team works inside its own tenant workspace.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-sm text-slate-500">
                Demonstration environment. Authorised staff only.
            </p>
        </aside>

        <main
            class="relative isolate flex min-h-svh items-center justify-center overflow-hidden p-6 sm:p-10"
        >
            <LanguageSwitch
                class="absolute top-5 right-5 z-20 sm:top-7 sm:right-7"
            />
            <CareRibbon
                :interactive="false"
                :scroll-responsive="false"
                class="absolute inset-0 z-0 opacity-55 lg:hidden dark:opacity-20"
            />
            <div class="relative z-10 w-full max-w-md">
                <Link
                    :href="home()"
                    class="mb-10 flex w-fit items-center gap-3 rounded-md text-lg font-semibold outline-none focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 lg:hidden"
                >
                    <AppLogoIcon class="size-9 text-teal-700" />
                    MediPath
                </Link>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-7 shadow-[0_24px_70px_-40px_rgba(25,85,71,0.4)] sm:p-9"
                >
                    <div class="mb-8">
                        <div
                            class="mb-5 flex size-11 items-center justify-center rounded-xl bg-teal-50 text-teal-700"
                        >
                            <ShieldCheck class="size-5" aria-hidden="true" />
                        </div>
                        <h1
                            class="text-3xl leading-tight font-semibold tracking-tight text-slate-950"
                        >
                            {{ title }}
                        </h1>
                        <p class="mt-3 text-sm leading-6 text-slate-500">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
