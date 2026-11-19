<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

defineProps<{ error?: string }>();
const preview = ref('');
function clearPreview() {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = '';
}
function selectPhoto(event: Event) {
    clearPreview();
    const file = (event.target as HTMLInputElement).files?.[0];
    if (
        file &&
        ['image/jpeg', 'image/png'].includes(file.type) &&
        file.size <= 2097152
    ) {
        preview.value = URL.createObjectURL(file);
    }
}
onBeforeUnmount(clearPreview);
</script>

<template>
    <div class="grid gap-2 sm:col-span-2">
        <Label for="profile_photo">Patient photo (optional)</Label>
        <div class="flex min-w-0 items-center gap-3">
            <img
                v-if="preview"
                :src="preview"
                alt="Selected patient photo"
                class="size-14 shrink-0 rounded-full object-cover"
            />
            <input
                id="profile_photo"
                name="profile_photo"
                type="file"
                accept="image/jpeg,image/png"
                aria-describedby="photo-hint"
                class="max-w-full min-w-0 text-sm file:mr-3 file:rounded-md file:border file:border-slate-300 file:px-3 file:py-2"
                @change="selectPhoto"
            />
        </div>
        <p id="photo-hint" class="text-xs text-slate-600">
            JPEG or PNG, up to 2 MB.
        </p>
        <InputError :message="error" />
    </div>
</template>
