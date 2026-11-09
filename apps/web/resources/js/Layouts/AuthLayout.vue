<script setup lang="ts">
defineProps<{
    backgroundImage?: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <div class="relative flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased">
        <div v-if="backgroundImage" class="pointer-events-none absolute inset-0 overflow-hidden bg-blue-950" aria-hidden="true">
            <img :src="backgroundImage" alt="" class="h-full w-full scale-105 object-cover opacity-40 blur-sm" />
        </div>
        <main class="relative flex flex-1 items-center justify-center px-4 py-10">
            <slot />
        </main>

        <footer :class="['relative border-t px-4 py-5 text-center text-xs', backgroundImage ? 'border-white/20 text-white' : 'border-slate-200/70 text-slate-600']">
            &copy; {{ year }} Capstone Kelompok 11
        </footer>
    </div>
</template>
