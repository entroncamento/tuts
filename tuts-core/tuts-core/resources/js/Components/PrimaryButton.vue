<template>
    <button
        class="inline-flex items-center rounded-md border border-transparent bg-[var(--color-text)] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[var(--color-text-inverse)] transition duration-150 ease-in-out hover:bg-[var(--color-border-strong)] focus:bg-[var(--color-border-strong)] focus:outline-none focus:ring-2 focus:ring-[var(--ring-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-bg)] active:bg-[var(--color-text)]"
    >
        <slot />
    </button>
</template>
