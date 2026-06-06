import { computed, ref } from "vue";

export type ThemeMode = "system" | "light" | "dark";

const STORAGE_KEY = "tuts-theme-mode";

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
const systemTheme = ref<"light" | "dark">(getSystemTheme());

const resolvedTheme = computed<"light" | "dark">(() => {
    return themeMode.value === "system" ? systemTheme.value : themeMode.value;
});

function applyTheme(): void {
    if (typeof document === "undefined") return;

    const theme = resolvedTheme.value;
    const root = document.documentElement;

    root.dataset.theme = theme;
    root.dataset.themeMode = themeMode.value;

    root.classList.toggle("dark", theme === "dark");
    root.classList.toggle("light", theme === "light");

    root.style.colorScheme = theme;
}

export function setThemeMode(mode: ThemeMode): void {
    themeMode.value = mode;

    if (typeof window !== "undefined") {
        window.localStorage.setItem(STORAGE_KEY, mode);
    }

    applyTheme();
}

export function initTheme(): void {
    applyTheme();

    if (typeof window === "undefined") return;

    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");

    mediaQuery.addEventListener("change", () => {
        systemTheme.value = getSystemTheme();
        applyTheme();
    });

    window.addEventListener("storage", (event) => {
        if (event.key !== STORAGE_KEY) return;

        const next = event.newValue;

        if (next === "light" || next === "dark" || next === "system") {
            themeMode.value = next;
            applyTheme();
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
