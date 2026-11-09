<script setup lang="ts">
import type { ConstraintSubmission } from '@/types/models';
import { LockKeyhole, MessageSquareText } from 'lucide-vue-next';

defineProps<{ submission: ConstraintSubmission | null }>();
</script>

<template>
    <p
        v-if="submission?.is_locked"
        role="status"
        class="inline-flex items-center gap-2 text-sm text-gray-600"
        title="Hubungi admin fakultas untuk membuka kembali."
    >
        <LockKeyhole class="size-4 shrink-0 text-gray-500" aria-hidden="true" />
        <span>
            <strong class="font-medium text-gray-900">Terkunci</strong>,
            {{
                submission.constraint_status === 'accepted'
                    ? 'sudah disetujui fakultas'
                    : 'menunggu review fakultas'
            }}
        </span>
    </p>
    <div
        v-else-if="submission?.constraint_return_note"
        role="status"
        class="flex items-start gap-2 text-sm text-gray-600"
    >
        <MessageSquareText class="mt-0.5 size-4 shrink-0 text-amber-600" aria-hidden="true" />
        <p>
            <strong class="font-medium text-gray-800">Catatan fakultas:</strong>
            {{ submission.constraint_return_note }}
        </p>
    </div>
</template>
