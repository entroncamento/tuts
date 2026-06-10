<script setup>
import { ref } from "vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const form = useForm({
    name: "",
    email: "",
    role: "aluno", // Começa por defeito como aluno
    professor_key: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registo - TUT'S" />

        <form @submit.prevent="submit">
            <div class="mb-4">
                <InputLabel value="Tipo de Conta" />
                <div class="mt-2 flex gap-6">
                    <label class="flex items-center cursor-pointer">
                        <input
                            type="radio"
                            v-model="form.role"
                            value="aluno"
                            class="rounded-full border-[var(--color-border)] text-[var(--color-primary)] shadow-sm focus:ring-[var(--ring-focus)]"
                        />
                        <span class="ml-2 text-sm text-[var(--color-text-muted)]"
                            >Aluno (@ua.pt)</span
                        >
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input
                            type="radio"
                            v-model="form.role"
                            value="professor"
                            class="rounded-full border-[var(--color-border)] text-[var(--color-primary)] shadow-sm focus:ring-[var(--ring-focus)]"
                        />
                        <span class="ml-2 text-sm text-[var(--color-text-muted)]"
                            >Professor / Regente</span
                        >
                    </label>
                </div>
            </div>

            <div>
                <InputLabel for="name" value="Nome Completo" />
                <TextInput
                    id="name"
                    type="text"
                    class="app-input mt-1 block w-full rounded-md shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--ring-focus)]"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email Institucional" />
                <TextInput
                    id="email"
                    type="email"
                    class="app-input mt-1 block w-full rounded-md shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--ring-focus)]"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="exemplo@ua.pt"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div
                v-if="form.role === 'professor'"
                class="mt-4 rounded-md border border-[var(--color-success)] bg-[var(--color-success-soft)] p-4"
            >
                <InputLabel
                    for="professor_key"
                    value="Chave de Acesso (Enviada por Email)"
                    class="font-semibold text-[var(--color-success)]"
                />
                <TextInput
                    id="professor_key"
                    type="text"
                    class="app-input mt-1 block w-full rounded-md shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--ring-focus)]"
                    v-model="form.professor_key"
                    :required="form.role === 'professor'"
                    placeholder="Insira o código de regente..."
                />
                <InputError class="mt-2" :message="form.errors.professor_key" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="app-input mt-1 block w-full rounded-md shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--ring-focus)]"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar Password"
                />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="app-input mt-1 block w-full rounded-md shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--ring-focus)]"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="text-sm text-[var(--color-primary)] underline hover:text-[var(--color-primary-strong)] focus:outline-none focus:ring-2 focus:ring-[var(--ring-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-bg)]"
                >
                    Já tens conta? Entrar
                </Link>

                <PrimaryButton
                    class="bg-[var(--color-primary)] hover:bg-[var(--color-primary-strong)] focus:bg-[var(--color-primary-strong)] active:bg-[var(--color-primary-strong)]"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Criar Conta
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
