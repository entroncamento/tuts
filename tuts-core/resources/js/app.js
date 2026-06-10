import "../css/app.css";
import "./styles/theme.css";
import "./bootstrap";

import { createApp, h } from "vue";
import { createPinia } from "pinia";
import { createInertiaApp } from "@inertiajs/vue3";
import { ZiggyVue, route as ziggyRoute } from "ziggy-js";

import { initTheme } from "@/app/composables/useTheme";
import { router as figmaRouter } from "@/app/router";

const appName = import.meta.env.VITE_APP_NAME || "TUT'S";
const pinia = createPinia();

initTheme();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,

    resolve: (name) => {
        const pages = import.meta.glob("./Pages/**/*.vue", { eager: true });
        return pages[`./Pages/${name}.vue`];
    },

    setup({ el, App, props, plugin }) {
        const ziggyConfig = props.initialPage.props.ziggy;

        window.route = function (name, params, absolute, config = ziggyConfig) {
            return ziggyRoute(name, params, absolute, config);
        };

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(figmaRouter)
            .use(ZiggyVue, ziggyConfig)
            .mount(el);
    },

    progress: {
        color: "#009957",
    },
});
