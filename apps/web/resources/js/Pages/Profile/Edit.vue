<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Profil Saya" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 max-w-4xl w-full mx-auto space-y-6">
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Profil Saya</h2>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm sm:p-8">
                <UpdateProfileInformationForm :status="status" class="max-w-xl" />
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>
        </div>
    </SuperadminLayout>
</template>
