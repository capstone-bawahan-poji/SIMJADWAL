<script setup lang="ts">
import Input from '@/components/ui/Input.vue';
import { Eye, EyeOff, LockKeyhole } from 'lucide-vue-next';
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ invalid?: boolean }>();

const model = defineModel<string>({ required: true });

const visible = ref(false);
</script>

<template>
    <Input v-model="model" v-bind="$attrs" :type="visible ? 'text' : 'password'" :invalid="invalid">
        <template #leading>
            <LockKeyhole class="size-4" aria-hidden="true" />
        </template>
        <template #trailing>
            <button
                type="button"
                class="rounded-md p-1.5 text-slate-400 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                :aria-label="visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                :aria-pressed="visible"
                @click="visible = !visible"
            >
                <component :is="visible ? EyeOff : Eye" class="size-4" aria-hidden="true" />
            </button>
        </template>
    </Input>
</template>
