<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

type LocaleCode = 'en' | 'tw';

const page = usePage();
const changing = ref(false);
const localization = computed(() => page.props.localization);

function syncDocumentLanguage(locale: LocaleCode): void {
    document.documentElement.lang = locale;
}

function changeLocale(locale: LocaleCode): void {
    if (locale === localization.value.current || changing.value) return;

    router.post(
        '/locale',
        { locale },
        {
            preserveScroll: true,
            preserveState: false,
            onStart: () => {
                changing.value = true;
            },
            onSuccess: () => {
                syncDocumentLanguage(locale);
                window.location.reload();
            },
            onFinish: () => {
                changing.value = false;
            },
        },
    );
}

onMounted(() => syncDocumentLanguage(localization.value.current));
watch(
    () => localization.value.current,
    (locale) => syncDocumentLanguage(locale),
);
</script>

<template>
    <div
        class="inline-flex shrink-0 items-center rounded-md border border-[#c6d8d1] bg-white p-0.5"
        role="group"
        :aria-label="localization.translations.language"
        :aria-busy="changing"
    >
        <button
            v-for="locale in localization.locales"
            :key="locale.code"
            type="button"
            class="inline-flex h-11 min-w-11 items-center justify-center rounded-[4px] px-2.5 text-xs font-semibold transition-colors outline-none focus-visible:ring-3 focus-visible:ring-[#197f6d]/30 disabled:cursor-wait disabled:opacity-70"
            :class="
                locale.code === localization.current
                    ? 'bg-[#dff2ec] text-[#116655]'
                    : 'text-[#60766f] hover:bg-[#f2f7f5] hover:text-[#173a33]'
            "
            :disabled="changing"
            :aria-pressed="locale.code === localization.current"
            :aria-label="`${localization.translations.language}: ${locale.nativeLabel}`"
            @click="changeLocale(locale.code)"
        >
            <span class="sm:hidden">{{
                locale.code === 'tw' ? 'TWI' : 'EN'
            }}</span>
            <span class="hidden sm:inline">{{ locale.nativeLabel }}</span>
        </button>
    </div>
</template>
