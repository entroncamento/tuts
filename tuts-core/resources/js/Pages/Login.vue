<template>
    <div class="login-root">
        <div class="login-card">
            <!-- Logo -->
            <div class="login-logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6">
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

            <h2 class="login-heading">Bem-vindo de volta</h2>
            <p class="login-sub">Entra para acederes às tuas cadeiras</p>

            <!-- Erro -->
            <div v-if="erro" class="login-error">
                <svg
                    class="w-4 h-4 flex-shrink-0"
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
                <span>{{ erro }}</span>
            </div>

            <!-- Formulário -->
            <div class="login-form">
                <div class="field">
                    <label class="field-label">Email institucional</label>
                    <input
                        v-model="email"
                        type="email"
                        class="field-input"
                        placeholder="utilizador@ua.pt"
                        :disabled="aCarregar"
                        @keydown.enter="entrar"
                        autocomplete="email"
                    />
                </div>

                <div class="field">
                    <label class="field-label">Password</label>
                    <div class="input-wrap">
                        <input
                            v-model="password"
                            :type="mostrarPassword ? 'text' : 'password'"
                            class="field-input"
                            placeholder="••••••••"
                            :disabled="aCarregar"
                            @keydown.enter="entrar"
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
                                class="w-4 h-4"
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
                                class="w-4 h-4"
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
                </div>

                <button
                    class="btn-entrar"
                    :class="{ 'btn-entrar--loading': aCarregar }"
                    :disabled="aCarregar || !email || !password"
                    @click="entrar"
                >
                    <span v-if="!aCarregar">Entrar</span>
                    <span v-else class="btn-spinner"></span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";

const emit = defineEmits(["autenticado"]);

const email = ref("");
const password = ref("");
const erro = ref("");
const aCarregar = ref(false);
const mostrarPassword = ref(false);

async function entrar() {
    if (!email.value || !password.value) return;
    erro.value = "";
    aCarregar.value = true;

    try {
        await window.axios.get("/sanctum/csrf-cookie");
        const { data } = await window.axios.post("/api/login", {
            email: email.value,
            password: password.value,
        });
        emit("autenticado", data.user);
    } catch (e) {
        erro.value =
            e.response?.data?.errors?.email?.[0] ??
            e.response?.data?.message ??
            "Credenciais incorrectas.";
    } finally {
        aCarregar.value = false;
    }
}

function getCsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap");

.login-root {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #111110;
    font-family: "Instrument Sans", sans-serif;
    padding: 24px;
}

.login-card {
    width: 100%;
    max-width: 400px;
    background: #1c1b1a;
    border: 1px solid #2e2c29;
    border-radius: 20px;
    padding: 40px 36px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.login-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 32px;
}

.logo-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #5b4fe8;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 12px rgba(91, 79, 232, 0.4);
}

.logo-title {
    font-family: "Syne", sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: #edecea;
    line-height: 1.1;
    margin: 0;
}

.logo-subtitle {
    font-size: 12px;
    color: #6a6860;
    font-weight: 500;
    margin: 0;
}

.login-heading {
    font-family: "Syne", sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: #edecea;
    margin: 0 0 6px;
}

.login-sub {
    font-size: 14px;
    color: #9a9893;
    margin: 0 0 28px;
}

.login-error {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field-label {
    font-size: 12px;
    font-weight: 600;
    color: #9a9893;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.input-wrap {
    position: relative;
}

.field-input {
    width: 100%;
    background: #111110;
    border: 1.5px solid #2e2c29;
    border-radius: 10px;
    padding: 11px 14px;
    font-family: "Instrument Sans", sans-serif;
    font-size: 14px;
    color: #edecea;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.field-input:focus {
    border-color: #5b4fe8;
}

.field-input::placeholder {
    color: #6a6860;
}

.field-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.input-wrap .field-input {
    padding-right: 42px;
}

.toggle-pw {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6a6860;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s;
}

.toggle-pw:hover {
    color: #9a9893;
}

.btn-entrar {
    margin-top: 8px;
    width: 100%;
    padding: 13px;
    background: #5b4fe8;
    color: white;
    border: none;
    border-radius: 10px;
    font-family: "Instrument Sans", sans-serif;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    box-shadow: 0 2px 12px rgba(91, 79, 232, 0.35);
}

.btn-entrar:hover:not(:disabled) {
    filter: brightness(1.1);
    box-shadow: 0 4px 20px rgba(91, 79, 232, 0.45);
}

.btn-entrar:disabled:not(.btn-entrar--loading) {
    opacity: 0.45;
    cursor: not-allowed;
    box-shadow: none;
}

.btn-spinner {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
