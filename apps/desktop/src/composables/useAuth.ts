import { readonly, ref } from 'vue';

// Module-level so every caller and the router guard share one session.
const isLoggedIn = ref(false);

export function useAuth() {
  return {
    isLoggedIn: readonly(isLoggedIn),
    login: () => { isLoggedIn.value = true; },
    logout: () => { isLoggedIn.value = false; },
  };
}
