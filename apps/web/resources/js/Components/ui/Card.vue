<!-- Surface with optional #header and #footer bands, separated by borders. -->
<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <div v-if="$slots.header" class="border-b border-slate-100 bg-gradient-to-b from-slate-50 to-white px-6 pb-8 pt-12">
            <slot name="header" />
        </div>

        <div :class="$slots.header ? 'p-6' : 'px-6 py-8 sm:p-10'">
            <slot />
        </div>

        <div v-if="$slots.footer" class="border-t border-slate-100 bg-slate-50 px-6 py-2 text-xs text-slate-500">
            <slot name="footer" />
        </div>
    </div>
</template>
