import { createApp } from "vue";
import "@fontsource-variable/ibm-plex-sans";
import "@fontsource/ibm-plex-serif/600.css";
import "@fontsource/ibm-plex-mono/400.css";
import "@fontsource/ibm-plex-mono/600.css";
import "@/styles/main.css";
import App from "@/App.vue";
import router from "@/router";

createApp(App).use(router).mount("#app");
