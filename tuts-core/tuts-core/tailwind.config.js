import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

const hslVar = (name) => `hsl(var(${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",

    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",

        "./resources/js/**/*.vue",
        "./resources/js/**/*.js",
        "./resources/js/**/*.ts",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },

            colors: {
                border: hslVar("--border"),
                input: hslVar("--input"),
                "input-background": hslVar("--input-background"),
                "switch-background": hslVar("--switch-background"),
                ring: hslVar("--ring"),
                background: hslVar("--background"),
                foreground: hslVar("--foreground"),

                primary: {
                    DEFAULT: hslVar("--primary"),
                    foreground: hslVar("--primary-foreground"),
                },

                secondary: {
                    DEFAULT: hslVar("--secondary"),
                    foreground: hslVar("--secondary-foreground"),
                },

                destructive: {
                    DEFAULT: hslVar("--destructive"),
                    foreground: hslVar("--destructive-foreground"),
                },

                muted: {
                    DEFAULT: hslVar("--muted"),
                    foreground: hslVar("--muted-foreground"),
                },

                accent: {
                    DEFAULT: hslVar("--accent"),
                    foreground: hslVar("--accent-foreground"),
                },

                popover: {
                    DEFAULT: hslVar("--popover"),
                    foreground: hslVar("--popover-foreground"),
                },

                card: {
                    DEFAULT: hslVar("--card"),
                    foreground: hslVar("--card-foreground"),
                },

                app: {
                    bg: "var(--color-bg)",
                    "bg-muted": "var(--color-bg-muted)",
                    surface: "var(--color-surface)",
                    "surface-muted": "var(--color-surface-muted)",
                    elevated: "var(--color-surface-elevated)",
                    inset: "var(--color-surface-inset)",
                    text: "var(--color-text)",
                    muted: "var(--color-text-muted)",
                    soft: "var(--color-text-soft)",
                    inverse: "var(--color-text-inverse)",
                    border: "var(--color-border)",
                    "border-soft": "var(--color-border-soft)",
                    "border-strong": "var(--color-border-strong)",
                    primary: "var(--color-primary)",
                    "primary-soft": "var(--color-primary-soft)",
                    "primary-strong": "var(--color-primary-strong)",
                    "primary-contrast": "var(--color-primary-contrast)",
                },
            },

            borderRadius: {
                lg: "var(--radius)",
                md: "calc(var(--radius) - 2px)",
                sm: "calc(var(--radius) - 4px)",
            },
        },
    },

    plugins: [forms],
};
