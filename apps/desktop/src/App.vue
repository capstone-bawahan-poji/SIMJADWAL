<script setup lang="ts">
import { ref } from 'vue';
import ViewLogin from './components/ViewLogin.vue';
import AppSidebar from './components/AppSidebar.vue';
import ViewConfig from './components/ViewConfig.vue';

const isLoggedIn = ref(false);
const activeView = ref('config');

function onLogout() {
  isLoggedIn.value = false;
}

</script>

<template>
  <Transition name="fade" mode="out-in">
    <!-- LOGIN -->
    <ViewLogin v-if="!isLoggedIn" @login-success="isLoggedIn = true" />

    <!-- MAIN APP -->
    <div v-else class="flex h-screen w-screen overflow-hidden bg-gray-50">
      <AppSidebar
        :active-view="activeView"
        admin-name="Ahmad Rasyid"
        admin-role="Admin Fakultas"
        @update:active-view="activeView = $event"
        @logout="onLogout"
      />

      <!-- Right: topbar + content -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Topbar -->
        <header class="h-16 bg-white/80 backdrop-blur-xl border-b border-gray-100 flex items-center justify-between px-6 shrink-0 z-40">
          <div class="text-sm font-semibold text-gray-900 capitalize">
            {{ activeView === 'config' ? 'Konfigurasi GA' : activeView === 'dashboard' ? 'Dashboard' : activeView === 'data' ? 'Data & Constraint' : activeView === 'run' ? 'Run' : 'Sinkronisasi' }}
          </div>
          <div class="text-right">
            <p class="text-xs font-bold text-gray-900 leading-tight">Ahmad Rasyid</p>
            <p class="text-[11px] font-medium text-gray-400 leading-tight">Admin Fakultas</p>
          </div>
        </header>

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto bg-gray-50">
          <ViewConfig v-if="activeView === 'config'" />
        </main>
      </div>
    </div>
  </Transition>
</template>