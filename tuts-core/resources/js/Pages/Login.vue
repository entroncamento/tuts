<template>
    <div class="login-root">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path
                            d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>

                <div>
                    <h1 class="logo-title">Tut's</h1>
                    <p class="logo-subtitle">Tutor Virtual · UA</p>
                </div>
            </div>

            <h2 class="login-heading">Entrar</h2>
            <p class="login-sub">Usa a tua conta para entrar no chat</p>

            <div v-if="erroGeral" class="login-error">
                <svg
                    class="err-icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                <span>{{ erroGeral }}</span>
            </div>

            <form class="login-form" @submit.prevent="submit">
                <div class="field">
                    <label class="field-label">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="field-input"
                        :class="{ 'field-input--error': form.errors.email }"
                        placeholder="utilizador@ua.pt"
                        :disabled="form.processing"
                        autocomplete="username"
                    />
                    <span v-if="form.errors.email" class="field-error">
                        {{ form.errors.email }}
                    </span>
                </div>

                <div class="field">
                    <label class="field-label">Password</label>

                    <div class="input-wrap">
                        <input
                            v-model="form.password"
                            :type="mostrarPassword ? 'text' : 'password'"
                            class="field-input"
                            :class="{
                                'field-input--error': form.errors.password,
                            }"
                            placeholder="••••••••"
                            :disabled="form.processing"
                            autocomplete="current-password"
                        />

                        <button
                            type="button"
                            class="toggle-pw"
                            @click="mostrarPassword = !mostrarPassword"
                            tabindex="-1"
                        >
                            <svg
                                v-if="!mostrarPassword"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>

                            <svg
                                v-else
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                                />
                            </svg>
                        </button>
                    </div>

                    <span v-if="form.errors.password" class="field-error">
                        {{ form.errors.password }}
                    </span>
                </div>

                <div class="login-row">
                    <label class="remember-wrap">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            :disabled="form.processing"
                        />
                        <span>Lembrar sessão</span>
                    </label>

                    <a href="/forgot-password" class="forgot-link">
                        Esqueceste-te da password?
                    </a>
                </div>

                <button
                    class="btn-login-primary"
                    :class="{ 'btn--loading': form.processing }"
                    :disabled="form.processing || !form.email || !form.password"
                    type="submit"
                >
                    <span v-if="!form.processing">Entrar</span>
                    <span v-else class="btn-spinner"></span>
                </button>

                <div class="divider">
                    <span class="divider-line"></span>
                    <span class="divider-text">ou</span>
                    <span class="divider-line"></span>
                </div>

                <a href="/register" class="btn-register-secondary">
                    <svg
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        style="width: 16px; height: 16px; flex-shrink: 0"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Criar nova conta
                </a>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useForm } from "@inertiajs/vue3";

const mostrarPassword = ref(false);

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const erroGeral = computed(() => {
    return form.errors.email || form.errors.password || "";
});

function submit() {
    if (form.processing) return;

    form.post("/login", {
        onFinish: () => {
            form.reset("password");
        },
    });
}
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap");

.login-root {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0e0d0c;
    background-image: radial-gradient(
        ellipse 60% 50% at 50% 40%,
        rgba(91, 79, 232, 0.08) 0%,
        transparent 70%
    );
    font-family: "Instrument Sans", sans-serif;
    padding: 24px;
    box-sizing: border-box;
    overflow-y: auto;
}

.login-card {
    width: 100%;
    max-width: 440px;
    background: #1a1917;
    border: 1px solid #2a2825;
    border-radius: 24px;
    padding: 44px 40px 40px;
    display: flex;
    flex-direction: column;
    gap: 0;
    box-shadow:
        0 0 0 1px rgba(91, 79, 232, 0.12) inset,
        0 24px 64px rgba(0, 0, 0, 0.5);
    max-height: calc(100vh - 48px);
    overflow-y: auto;
    scrollbar-width: none;
}
.login-card::-webkit-scrollbar {
    display: none;
}

.login-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 32px;
}

.logo-icon {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    background: #5b4fe8;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 16px rgba(91, 79, 232, 0.45);
}

.logo-icon svg {
    width: 20px;
    height: 20px;
}

.logo-title {
    font-family: "Syne", sans-serif;
    font-size: 19px;
    font-weight: 800;
    color: #edebe8;
    line-height: 1.1;
    margin: 0;
    letter-spacing: -0.02em;
}

.logo-subtitle {
    font-size: 11.5px;
    color: #5f5d58;
    font-weight: 500;
    margin: 2px 0 0;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.login-heading {
    font-family: "Syne", sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: #edebe8;
    margin: 0 0 6px;
    letter-spacing: -0.02em;
}

.login-sub {
    font-size: 14px;
    color: #8a8880;
    margin: 0 0 28px;
    line-height: 1.5;
}

.login-error {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #f87171;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.err-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    margin-top: 1px;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #7a7874;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}

.field-input {
    width: 100%;
    background: #111010;
    border: 1.5px solid #272523;
    border-radius: 11px;
    padding: 12px 14px;
    font-family: "Instrument Sans", sans-serif;
    font-size: 14px;
    color: #edebe8;
    outline: none;
    transition:
        border-color 0.2s,
        box-shadow 0.2s;
    box-sizing: border-box;
}

.field-input:focus {
    border-color: #5b4fe8;
    box-shadow: 0 0 0 3px rgba(91, 79, 232, 0.15);
}

.field-input--error {
    border-color: rgba(239, 68, 68, 0.5) !important;
}

.field-input::placeholder {
    color: #4a4845;
}

.field-input:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.field-error {
    font-size: 12px;
    color: #f87171;
    margin-top: 2px;
}

.input-wrap {
    position: relative;
}

.input-wrap .field-input {
    padding-right: 44px;
}

.toggle-pw {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #4a4845;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    transition: color 0.15s;
}

.toggle-pw svg {
    width: 16px;
    height: 16px;
}

.toggle-pw:hover {
    color: #8a8880;
}

.login-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 2px;
}

.remember-wrap {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #b3b0aa;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
}

.remember-wrap input {
    accent-color: #5b4fe8;
}

.forgot-link {
    color: #a89cf0;
    text-decoration: none;
    font-size: 12.5px;
    font-weight: 600;
}

.forgot-link:hover {
    text-decoration: underline;
}

.btn-login-primary {
    margin-top: 6px;
    width: 100%;
    padding: 13px;
    background: #5b4fe8;
    color: white;
    border: none;
    border-radius: 11px;
    font-family: "Instrument Sans", sans-serif;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition:
        filter 0.2s,
        box-shadow 0.2s,
        opacity 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 50px;
    box-shadow: 0 2px 16px rgba(91, 79, 232, 0.4);
    letter-spacing: 0.01em;
}

.btn-login-primary:hover:not(:disabled) {
    filter: brightness(1.12);
    box-shadow: 0 6px 24px rgba(91, 79, 232, 0.5);
}

.btn-login-primary:disabled:not(.btn--loading) {
    opacity: 0.35;
    cursor: not-allowed;
    box-shadow: none;
}

.btn-spinner {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2.5px solid rgba(255, 255, 255, 0.25);
    border-top-color: white;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 4px 0;
}

.divider-line {
    flex: 1;
    height: 1px;
    background: #252320;
}

.divider-text {
    font-size: 12px;
    color: #4a4845;
    font-weight: 500;
    letter-spacing: 0.05em;
}

.btn-register-secondary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    width: 100%;
    padding: 13px;
    background: transparent;
    color: #a89cf0;
    border: 1.5px solid #3a3460;
    border-radius: 11px;
    font-family: "Instrument Sans", sans-serif;
    font-size: 14.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition:
        background 0.2s,
        border-color 0.2s,
        color 0.2s,
        box-shadow 0.2s;
    min-height: 50px;
    letter-spacing: 0.01em;
    box-sizing: border-box;
}

.btn-register-secondary:hover {
    background: rgba(91, 79, 232, 0.1);
    border-color: #5b4fe8;
    color: #c4b9ff;
    box-shadow: 0 0 0 3px rgba(91, 79, 232, 0.1);
}
</style>
