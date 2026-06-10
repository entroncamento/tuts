import { computed, ref } from "vue";

export type ThemeMode = "system" | "light" | "dark";

const STORAGE_KEY = "tuts-theme-mode";
const THEME_TRANSITION_CLASS = "theme-transitioning";
const THEME_TRANSITION_DURATION_MS = 420;

let themeTransitionTimeout: number | undefined;

function getSystemTheme(): "light" | "dark" {
    if (typeof window === "undefined") return "light";

    return window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light";
}

function getInitialThemeMode(): ThemeMode {
    if (typeof window === "undefined") return "system";

    const stored = window.localStorage.getItem(STORAGE_KEY);

    if (stored === "light" || stored === "dark" || stored === "system") {
        return stored;
    }

    return "system";
}

const themeMode = ref<ThemeMode>(getInitialThemeMode());

const resolvedTheme = computed<"light" | "dark">(() => {
    if (themeMode.value === "system") {
        return getSystemTheme();
    }

    return themeMode.value;
});

function shouldReduceMotion(): boolean {
    if (typeof window === "undefined") return true;

    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function applyTheme(animate = false): void {
    if (typeof document === "undefined") return;

    const theme = resolvedTheme.value;
    const root = document.documentElement;

    if (animate && !shouldReduceMotion()) {
        window.clearTimeout(themeTransitionTimeout);
        root.classList.add(THEME_TRANSITION_CLASS);
        root.offsetWidth;
    }

    root.dataset.theme = theme;
    root.dataset.themeMode = themeMode.value;

    root.classList.toggle("dark", theme === "dark");
    root.classList.toggle("light", theme === "light");

    root.style.colorScheme = theme;

    if (animate && !shouldReduceMotion()) {
        themeTransitionTimeout = window.setTimeout(() => {
            root.classList.remove(THEME_TRANSITION_CLASS);
        }, THEME_TRANSITION_DURATION_MS);
    } else {
        root.classList.remove(THEME_TRANSITION_CLASS);
    }
}

export function setThemeMode(mode: ThemeMode): void {
    themeMode.value = mode;

    if (typeof window !== "undefined") {
        window.localStorage.setItem(STORAGE_KEY, mode);
    }

    applyTheme(true);
}

export function initTheme(): void {
    applyTheme();

    if (typeof window === "undefined") return;

    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");

    mediaQuery.addEventListener("change", () => {
        if (themeMode.value === "system") {
            applyTheme(true);
        }
    });

    window.addEventListener("storage", (event) => {
        if (event.key !== STORAGE_KEY) return;

        const next = event.newValue;

        if (next === "light" || next === "dark" || next === "system") {
            themeMode.value = next;
            applyTheme(true);
        }
    });
}

export function useTheme() {
    return {
        themeMode,
        resolvedTheme,
        setThemeMode,
        initTheme,
    };
}
