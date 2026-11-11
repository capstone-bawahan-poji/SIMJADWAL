<script setup lang="ts">
import { SUBMISSION_STATUS_LABELS, SUBMISSION_STATUS_TONES } from '@/lib/labels';
import type { SubmissionStatus } from '@/types/models';

defineProps<{ status: SubmissionStatus }>();
</script>

<template>
    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold" :class="SUBMISSION_STATUS_TONES[status]">
        {{ SUBMISSION_STATUS_LABELS[status] }}
    </span>
</template>
