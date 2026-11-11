<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import AppLogo from '@/components/shared/AppLogo.vue';
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import UiInput from '@/components/ui/Input.vue';
import { UserRound } from 'lucide-vue-next';

const router = useRouter();
const { login } = useAuth();

const form = reactive({
  email: '',
  password: '',
});

const errors = reactive({
  email: '',
  password: '',
});

const loginError = ref('');
const processing = ref(false);

function clearErrors() {
  errors.email = '';
  errors.password = '';
  loginError.value = '';
}

function validate() {
  let ok = true;
  if (!form.email.trim()) { errors.email = 'Wajib diisi.'; ok = false; }
  if (!form.password.trim()) { errors.password = 'Wajib diisi.'; ok = false; }
  return ok;
}

async function submit() {
  clearErrors();
  if (!validate()) return;
  processing.value = true;
  try {
    await login(form.email, form.password);
    router.push({ name: 'config' });
  } catch (e) {
    loginError.value = (e as Error).message;
  } finally {
    processing.value = false;
  }
}

</script>

<template>
  <div class="flex h-screen w-screen flex-col items-center justify-center gap-6 bg-canvas">
    <div class="w-full max-w-[448px] px-4">
      <Card no-padding class="p-8">
        <div class="flex flex-col items-center text-center">
          <AppLogo variant="full" class="h-14" />
          <h1 class="mt-6 font-serif text-xl font-semibold text-ink">Sistem Penjadwalan Perkuliahan</h1>
          <p class="mt-1 text-sm text-ink-muted">Masuk menggunakan akun admin program studi, admin TPB, atau super admin.</p>
        </div>

        <div v-if="loginError" class="mt-6">
          <Alert title="Gagal masuk" dismissible @dismiss="loginError = ''">
            {{ loginError }}
          </Alert>
        </div>

        <form class="mt-6 flex flex-col gap-6" novalidate @submit.prevent="submit">
          <FormField id="login-email" label="Email" :error="errors.email">
            <UiInput
              id="login-email"
              v-model="form.email"
              type="email"
              placeholder="Contoh: admin@itk.ac.id"
              autocomplete="username"
              autofocus
              :invalid="!!errors.email"
            >
              <template #leading>
                <UserRound class="size-4" aria-hidden="true" />
              </template>
            </UiInput>
          </FormField>

          <FormField id="login-password" label="Kata sandi" :error="errors.password">
            <PasswordInput
              id="login-password"
              v-model="form.password"
              placeholder="Masukkan kata sandi"
              autocomplete="current-password"
              :invalid="!!errors.password"
            />
          </FormField>

          <Button type="submit" variant="primary" size="lg" class="w-full" :loading="processing">
            Masuk
          </Button>
        </form>
      </Card>
    </div>

    <p class="text-xs text-ink-faint">© 2026 Capstone Kelompok 11</p>
  </div>
</template>
