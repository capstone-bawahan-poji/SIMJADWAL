<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import StepIndicator from '@/components/shared/StepIndicator.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { RESET_PASSWORD_STEPS } from '../resetPasswordSteps';
import PasswordRequirements from './partials/PasswordRequirements.vue';

const props = defineProps<{
    email: string | null;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

// An invalid or expired token comes back on `email` (see PasswordService::resetPassword).
const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Kata Sandi Baru" />

        <div class="w-full max-w-xl">
            <Card>
                <template #header>
                    <h1 class="text-center font-display text-xl font-bold text-slate-900">Atur Ulang Kata Sandi</h1>
                    <StepIndicator :steps="RESET_PASSWORD_STEPS" :current="2" class="mx-auto mt-4 max-w-sm" />
                </template>

                <Alert
                    v-if="form.errors.email"
                    title="Link reset tidak dapat dipakai"
                    dismissible
                    class="mb-6"
                    @dismiss="form.clearErrors('email')"
                >
                    {{ form.errors.email }}
                    <Link :href="route('password.request')" class="font-medium text-blue-700 hover:underline">
                        Minta link baru.
                    </Link>
                </Alert>

                <form class="space-y-5" novalidate @submit.prevent="submit">
                    <FormField id="email" label="Email Akademik Terdaftar">
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            :readonly="!!props.email"
                            :invalid="!!form.errors.email"
                        >
                            <template #leading>
                                <Mail class="size-4" aria-hidden="true" />
                            </template>
                        </Input>
                    </FormField>

                    <FormField id="password" label="Kata Sandi Baru" :error="form.errors.password">
                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            placeholder="Masukkan kata sandi baru"
                            autocomplete="new-password"
                            required
                            autofocus
                            :invalid="!!form.errors.password"
                        />
                    </FormField>

                    <FormField
                        id="password_confirmation"
                        label="Konfirmasi Kata Sandi"
                        :error="form.errors.password_confirmation"
                    >
                        <PasswordInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            placeholder="Ulangi kata sandi baru"
                            autocomplete="new-password"
                            required
                            :invalid="!!form.errors.password_confirmation"
                        />
                    </FormField>

                    <PasswordRequirements :password="form.password" :confirmation="form.password_confirmation" />

                    <div class="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:items-center">
                        <Button type="submit" class="w-full sm:flex-1" :loading="form.processing">
                            Simpan Kata Sandi Baru
                        </Button>
                        <Link
                            :href="route('login')"
                            class="rounded-lg px-4 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            Kembali ke Login
                        </Link>
                    </div>
                </form>

                <template #footer>Setelah disimpan, Anda akan diarahkan ke halaman login.</template>
            </Card>
        </div>
    </AuthLayout>
</template>
