<script setup lang="ts">
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased">
        <main class="flex flex-1 items-center justify-center px-4 py-10">
            <slot />
        </main>

        <footer class="border-t border-slate-200/70 px-4 py-5 text-center text-xs text-slate-500">
            &copy; {{ year }} Capstone Kelompok 11
        </footer>
    </div>
</template>
