<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import AppLogo from '@/components/shared/AppLogo.vue';
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { UserRound } from 'lucide-vue-next';

defineProps<{
    canResetPassword: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
});

// Wrong credentials and throttling both come back on `email` (see LoginRequest).
const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Masuk" />

        <div class="w-full max-w-md">
            <Card>
                <div class="flex flex-col items-center text-center">
                    <AppLogo class="h-14" />
                    <h1 class="mt-5 font-display text-2xl font-bold text-slate-900">Sistem Penjadwalan Kuliah</h1>
                </div>

                <Alert v-if="status" variant="success" :title="status" class="mt-6" />

                <Alert
                    v-if="form.errors.email"
                    title="Gagal masuk"
                    dismissible
                    class="mt-6"
                    @dismiss="form.clearErrors('email')"
                >
                    {{ form.errors.email }}
                </Alert>

                <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
                    <FormField id="email" label="Email">
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="Contoh: dosen@itk.ac.id"
                            autocomplete="username"
                            required
                            autofocus
                            :invalid="!!form.errors.email"
                        >
                            <template #leading>
                                <UserRound class="size-4" aria-hidden="true" />
                            </template>
                        </Input>
                    </FormField>

                    <FormField id="password" label="Kata Sandi" :error="form.errors.password">
                        <template v-if="canResetPassword" #label-action>
                            <Link
                                :href="route('password.request')"
                                class="rounded text-xs font-medium text-blue-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            >
                                Lupa Kata Sandi?
                            </Link>
                        </template>

                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            placeholder="Masukkan kata sandi Anda"
                            autocomplete="current-password"
                            required
                            :invalid="!!form.errors.password"
                        />
                    </FormField>

                    <Button type="submit" class="w-full" :loading="form.processing">Masuk ke Sistem</Button>
                </form>
            </Card>
        </div>
    </AuthLayout>
</template>
