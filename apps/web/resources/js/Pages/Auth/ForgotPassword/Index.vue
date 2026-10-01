<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import FormField from '@/components/shared/FormField.vue';
import StepIndicator from '@/components/shared/StepIndicator.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Mail } from 'lucide-vue-next';
import { RESET_PASSWORD_STEPS } from '../resetPasswordSteps';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout>
        <Head title="Atur Ulang Kata Sandi" />

        <div class="w-full max-w-xl">
            <Card>
                <template #header>
                    <h1 class="text-center font-display text-xl font-bold text-slate-900">Atur Ulang Kata Sandi</h1>
                    <StepIndicator :steps="RESET_PASSWORD_STEPS" :current="status ? 2 : 1" class="mx-auto mt-4 max-w-sm" />
                </template>

                <Alert v-if="status" variant="success" title="Cek email Anda" class="mb-6">
                    {{ status }} Buka email Anda dan ikuti link untuk membuat kata sandi baru.
                </Alert>

                <Alert
                    v-if="form.errors.email"
                    title="Email tidak valid"
                    dismissible
                    class="mb-6"
                    @dismiss="form.clearErrors('email')"
                >
                    {{ form.errors.email }}
                </Alert>

                <form class="space-y-6" novalidate @submit.prevent="submit">
                    <FormField
                        id="email"
                        label="Email Akademik Terdaftar"
                        hint="Gunakan email yang terdaftar pada sistem akademik."
                    >
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="Contoh: dosen@itk.ac.id"
                            autocomplete="email"
                            required
                            autofocus
                            :invalid="!!form.errors.email"
                            aria-describedby="email-hint"
                        >
                            <template #leading>
                                <Mail class="size-4" aria-hidden="true" />
                            </template>
                        </Input>
                    </FormField>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                        <Button type="submit" class="w-full sm:flex-1" :loading="form.processing">
                            Kirim Link Reset
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Button>
                        <Link
                            :href="route('login')"
                            class="rounded-lg px-4 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            Kembali ke Login
                        </Link>
                    </div>
                </form>
            </Card>
        </div>
    </AuthLayout>
</template>
