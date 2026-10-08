<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Bot, Check, MessageCircle, ShieldCheck, UserRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Option = { value: string; label: string };
type Question = {
    key: string;
    question: string;
    help: string;
    options: Option[];
};
type Group = {
    key: string;
    title: string;
    description: string;
    questions: Question[];
};
type TranscriptTurn = { question: string; answer: string };

defineProps<{
    mainConcern?: string | null;
    group?: Group;
    question?: Question;
    conversationMode?: boolean;
    transcript?: TranscriptTurn[];
    answers?: Record<string, string>;
    progress: { current: number; total: number };
}>();
</script>

<template>
    <Head title="Symptom assessment" />
    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
            <div class="flex items-center justify-between gap-4 text-sm">
                <span class="font-medium text-teal-800">
                    {{
                        conversationMode
                            ? `Question ${progress.current}, up to ${progress.total}`
                            : `Assessment ${progress.current} of ${progress.total}`
                    }}
                </span>
                <span class="text-slate-500">Saved after each answer</span>
            </div>
            <div
                class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100"
                role="progressbar"
                :aria-valuenow="progress.current"
                aria-valuemin="1"
                :aria-valuemax="progress.total"
            >
                <div
                    class="h-full rounded-full bg-teal-700 transition-[width] duration-200"
                    :style="{
                        width: `${(progress.current / progress.total) * 100}%`,
                    }"
                />
            </div>
        </div>

        <div class="p-6 sm:p-8">
            <template v-if="conversationMode && question">
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-800"
                    >
                        <MessageCircle class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h1
                            class="text-3xl font-semibold tracking-tight text-balance text-slate-950"
                        >
                            Tell MediPath what you are experiencing
                        </h1>
                        <p class="mt-3 max-w-2xl leading-7 text-slate-600">
                            The next question is chosen from what you have
                            shared. The conversation ends as soon as there is
                            enough information to give safe guidance.
                        </p>
                    </div>
                </div>

                <div
                    v-if="mainConcern"
                    class="mt-6 flex gap-3 border-y border-slate-200 py-4"
                >
                    <ShieldCheck
                        class="mt-0.5 size-5 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-xs font-medium text-slate-500">
                            Concern being assessed
                        </p>
                        <p class="mt-1 text-sm text-slate-800">
                            {{ mainConcern }}
                        </p>
                    </div>
                </div>

                <details
                    v-if="transcript?.length"
                    class="mt-6 border-b border-slate-200 pb-5"
                >
                    <summary
                        class="cursor-pointer text-sm font-medium text-teal-800 underline-offset-4 hover:underline"
                    >
                        Review {{ transcript.length }} saved
                        {{ transcript.length === 1 ? 'answer' : 'answers' }}
                    </summary>
                    <ol class="mt-5 space-y-5">
                        <li
                            v-for="(turn, index) in transcript"
                            :key="index"
                            class="grid gap-3 sm:grid-cols-[2rem_1fr]"
                        >
                            <Bot
                                class="mt-0.5 size-5 text-teal-700"
                                aria-hidden="true"
                            />
                            <div>
                                <p class="text-sm font-medium text-slate-900">
                                    {{ turn.question }}
                                </p>
                                <div
                                    class="mt-2 flex gap-2 text-sm text-slate-600"
                                >
                                    <UserRound
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <p>{{ turn.answer }}</p>
                                </div>
                            </div>
                        </li>
                    </ol>
                </details>

                <Form
                    action="/intake/assessment"
                    method="post"
                    class="mt-7"
                    v-slot="{ errors, processing }"
                >
                    <input
                        type="hidden"
                        name="question_key"
                        :value="question.key"
                    />
                    <label
                        for="assessment_answer"
                        class="block text-xl leading-8 font-semibold text-slate-950"
                        >{{ question.question }}</label
                    >
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ question.help }}
                    </p>
                    <textarea
                        :key="question.key"
                        id="assessment_answer"
                        name="answer"
                        required
                        maxlength="1000"
                        autofocus
                        class="mt-4 min-h-32 w-full resize-y rounded-xl border border-slate-300 bg-white px-4 py-3 text-base leading-7 text-slate-950 outline-none placeholder:text-slate-400 focus-visible:border-teal-700 focus-visible:ring-3 focus-visible:ring-teal-700/20"
                        placeholder="Type your answer here"
                    />
                    <InputError class="mt-2" :message="errors.answer" />

                    <div
                        class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Link
                            href="/intake/visit/current"
                            class="text-center text-sm text-slate-600 underline underline-offset-4 hover:text-slate-950"
                            >Back</Link
                        >
                        <Button
                            class="h-11 bg-teal-700 px-6 text-white hover:bg-teal-800"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            {{ processing ? 'Checking' : 'Continue' }}
                        </Button>
                    </div>
                </Form>
            </template>

            <template v-else-if="group">
                <h1 class="text-3xl font-semibold tracking-tight text-balance">
                    {{ group.title }}
                </h1>
                <p class="mt-3 max-w-2xl leading-7 text-slate-600">
                    {{ group.description }}
                </p>

                <div
                    v-if="mainConcern"
                    class="mt-6 flex gap-3 border-y border-slate-200 py-4"
                >
                    <ShieldCheck
                        class="mt-0.5 size-5 shrink-0 text-teal-700"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-xs font-medium text-slate-500">
                            Concern being assessed
                        </p>
                        <p class="mt-1 text-sm text-slate-800">
                            {{ mainConcern }}
                        </p>
                    </div>
                </div>

                <Form
                    action="/intake/assessment"
                    method="post"
                    class="mt-7"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="group_key" :value="group.key" />
                    <div class="divide-y divide-slate-200">
                        <fieldset
                            v-for="item in group.questions"
                            :key="item.key"
                            class="py-7 first:pt-0"
                        >
                            <legend
                                class="text-lg font-semibold text-slate-950"
                            >
                                {{ item.question }}
                            </legend>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ item.help }}
                            </p>
                            <select
                                v-if="item.options.length > 5"
                                :name="`answers[${item.key}]`"
                                required
                                class="mt-4 h-12 w-full rounded-md border border-slate-300 bg-white px-3 text-base text-slate-950 outline-none focus-visible:border-teal-700 focus-visible:ring-3 focus-visible:ring-teal-700/25"
                                :value="answers?.[item.key] ?? ''"
                            >
                                <option value="" disabled>Select one</option>
                                <option
                                    v-for="option in item.options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <div v-else class="mt-4 grid gap-3 sm:grid-cols-2">
                                <label
                                    v-for="option in item.options"
                                    :key="option.value"
                                    class="group flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition-colors focus-within:border-teal-700 focus-within:ring-3 focus-within:ring-teal-700/20 hover:border-teal-300 hover:bg-teal-50/40 has-[:checked]:border-teal-700 has-[:checked]:bg-teal-50"
                                >
                                    <input
                                        type="radio"
                                        :name="`answers[${item.key}]`"
                                        :value="option.value"
                                        :checked="
                                            answers?.[item.key] === option.value
                                        "
                                        required
                                        class="mt-0.5 size-5 shrink-0 accent-teal-700"
                                    />
                                    <span
                                        class="text-sm leading-6 font-medium text-slate-800"
                                        >{{ option.label }}</span
                                    >
                                </label>
                            </div>
                            <InputError
                                class="mt-3"
                                :message="errors[`answers.${item.key}`]"
                            />
                        </fieldset>
                    </div>

                    <div
                        class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Link
                            :href="
                                progress.current === 1
                                    ? '/intake/visit/current'
                                    : '/intake/assessment?section=1'
                            "
                            class="text-center text-sm text-slate-600 underline underline-offset-4"
                            >Back</Link
                        >
                        <Button
                            class="h-11 bg-teal-700 px-6 text-white hover:bg-teal-800"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            Continue
                        </Button>
                    </div>
                    <p
                        class="mt-5 flex items-center gap-2 text-xs text-slate-500"
                    >
                        <Check
                            class="size-4 text-teal-700"
                            aria-hidden="true"
                        />Your answers are saved when you continue.
                    </p>
                </Form>
            </template>
        </div>
    </section>
</template>
