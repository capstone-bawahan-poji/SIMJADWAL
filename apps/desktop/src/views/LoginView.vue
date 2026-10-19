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
  await new Promise((resolve) => setTimeout(resolve, 500));
  processing.value = false;
  login();
  router.push({ name: 'config' });
}
</script>

<template>
  <!-- Full-screen login: slate-50 bg, card centred -->
  <div class="flex h-screen w-screen items-center justify-center bg-slate-50 flex-col gap-6">
    <div class="w-full max-w-[448px] px-4">
      <Card>
        <!-- Logo + title -->
        <div class="flex flex-col items-center text-center">
          <AppLogo variant="full" class="h-14" />
          <h1 class="mt-5 font-display text-2xl font-bold text-slate-900 leading-tight">
            Sistem Penjadwalan Kuliah
          </h1>
        </div>

        <!-- Status / error alerts -->
        <div v-if="loginError" class="mt-6">
          <Alert title="Gagal masuk" dismissible @dismiss="loginError = ''">
            {{ loginError }}
          </Alert>
        </div>

        <!-- Form -->
        <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
          <!-- Email -->
          <FormField id="login-email" label="Email" :error="errors.email">
            <UiInput
              id="login-email"
              v-model="form.email"
              type="email"
              placeholder="Contoh: dosen@itk.ac.id"
              autocomplete="username"
              autofocus
              :invalid="!!errors.email"
            >
              <template #leading>
                <UserRound class="size-4" aria-hidden="true" />
              </template>
            </UiInput>
          </FormField>

          <!-- Password -->
          <FormField id="login-password" label="Kata Sandi" :error="errors.password">
            <template #label-action>
              <button
                type="button"
                class="text-xs font-medium text-blue-700 hover:underline rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
              >
                Lupa Kata Sandi?
              </button>
            </template>

            <PasswordInput
              id="login-password"
              v-model="form.password"
              placeholder="Masukkan kata sandi Anda"
              autocomplete="current-password"
              :invalid="!!errors.password"
            />
          </FormField>

          <!-- Submit -->
          <Button type="submit" class="w-full" :loading="processing">
            Masuk ke Sistem
          </Button>
        </form>
        <p class="mt-4 text-center text-xs text-gray-500">
          Mode demo: email dan kata sandi apa pun yang terisi dapat digunakan.
        </p>
      </Card>
    </div>

    <!-- Footer -->
    <div class="border-t border-slate-200 pt-4 w-full max-w-[448px] px-4 text-center">
      <p class="text-xs text-slate-400">© 2026 Capstone Kelompok 11</p>
    </div>
  </div>
</template>
