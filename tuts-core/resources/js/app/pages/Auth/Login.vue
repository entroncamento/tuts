<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

function submit() {
    form.post("/login", {
        onFinish: () => {
            form.reset("password");
        },
    });
}
</script>

<template>
    <Head title="Entrar" />

    <main
        style="
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7f7f7;
            font-family: Inter, sans-serif;
            padding: 24px;
        "
    >
        <section
            style="
                width: 100%;
                max-width: 420px;
                background: #ffffff;
                border: 1px solid #e5e5e5;
                border-radius: 20px;
                padding: 32px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            "
        >
            <div style="text-align: center; margin-bottom: 28px">
                <div
                    style="
                        width: 52px;
                        height: 52px;
                        border-radius: 16px;
                        background: #009957;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        margin-bottom: 16px;
                    "
                >
                    <span
                        style="
                            font-weight: 800;
                            font-size: 18px;
                            color: #ffffff;
                            letter-spacing: 0.06em;
                        "
                    >
                        T
                    </span>
                </div>

                <h1
                    style="
                        font-size: 26px;
                        font-weight: 800;
                        color: #1e1e1e;
                        margin: 0;
                    "
                >
                    Entrar no TUT'S
                </h1>

                <p style="font-size: 14px; color: #9e9e9e; margin: 8px 0 0">
                    Usa a tua conta institucional da UA.
                </p>
            </div>

            <p
                v-if="status"
                style="
                    background: #edf9ef;
                    color: #009957;
                    border: 1px solid rgba(0, 153, 87, 0.25);
                    border-radius: 12px;
                    padding: 12px;
                    font-size: 13px;
                    margin-bottom: 18px;
                "
            >
                {{ status }}
            </p>

            <form
                style="display: flex; flex-direction: column; gap: 16px"
                @submit.prevent="submit"
            >
                <div>
                    <label
                        for="email"
                        style="
                            display: block;
                            font-size: 13px;
                            font-weight: 700;
                            color: #1e1e1e;
                            margin-bottom: 8px;
                        "
                    >
                        Email
                    </label>

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        placeholder="aluno@ua.pt"
                        style="
                            width: 100%;
                            box-sizing: border-box;
                            border: 1px solid #e5e5e5;
                            border-radius: 12px;
                            padding: 12px 14px;
                            font-size: 14px;
                            outline: none;
                        "
                    />

                    <p
                        v-if="form.errors.email"
                        style="color: #e53935; font-size: 12px; margin: 7px 0 0"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        for="password"
                        style="
                            display: block;
                            font-size: 13px;
                            font-weight: 700;
                            color: #1e1e1e;
                            margin-bottom: 8px;
                        "
                    >
                        Palavra-passe
                    </label>

                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        placeholder="password123"
                        style="
                            width: 100%;
                            box-sizing: border-box;
                            border: 1px solid #e5e5e5;
                            border-radius: 12px;
                            padding: 12px 14px;
                            font-size: 14px;
                            outline: none;
                        "
                    />

                    <p
                        v-if="form.errors.password"
                        style="color: #e53935; font-size: 12px; margin: 7px 0 0"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <label
                    style="
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        font-size: 13px;
                        color: #656966;
                    "
                >
                    <input v-model="form.remember" type="checkbox" />
                    Manter sessão iniciada
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    style="
                        width: 100%;
                        border: none;
                        border-radius: 12px;
                        background: #009957;
                        color: #ffffff;
                        font-weight: 800;
                        font-size: 14px;
                        padding: 13px 16px;
                        cursor: pointer;
                    "
                    :style="{ opacity: form.processing ? 0.65 : 1 }"
                >
                    {{ form.processing ? "A entrar..." : "Entrar" }}
                </button>

                <div
                    style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-top: 4px;
                    "
                >
                    <Link
                        v-if="canResetPassword"
                        href="/forgot-password"
                        style="
                            font-size: 12px;
                            color: #656966;
                            text-decoration: none;
                        "
                    >
                        Esqueci-me da palavra-passe
                    </Link>

                    <Link
                        href="/register"
                        style="
                            font-size: 12px;
                            color: #009957;
                            font-weight: 700;
                            text-decoration: none;
                        "
                    >
                        Criar conta
                    </Link>
                </div>
            </form>

            <div
                style="
                    margin-top: 22px;
                    background: #f7f7f7;
                    border-radius: 12px;
                    padding: 12px;
                    font-size: 12px;
                    color: #656966;
                    line-height: 1.5;
                "
            >
                <strong>Conta de teste:</strong><br />
                aluno@ua.pt<br />
                password123
            </div>
        </section>
    </main>
</template>
