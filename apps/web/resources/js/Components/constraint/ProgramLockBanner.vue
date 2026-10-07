<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import type { ConstraintSubmission } from '@/types/models';

defineProps<{ submission: ConstraintSubmission | null }>();
</script>

<template>
    <div v-if="submission" class="space-y-3">
        <div
            v-if="submission.is_locked"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            role="status"
        >
            <strong>Data terkunci.</strong>
            {{
                submission.constraint_status === 'accepted'
                    ? 'Constraint program studi sudah diterima fakultas.'
                    : 'Constraint program studi sudah di-submit dan menunggu review fakultas.'
            }}
            Hubungi admin fakultas untuk mengembalikan ke draft bila perlu mengubah data.
        </div>
        <Alert v-else-if="submission.constraint_return_note" :title="`Dikembalikan fakultas: ${submission.constraint_return_note}`" />
    </div>
</template>
