<script setup lang="ts">
import { RouterView } from "vue-router";

// Eagerly initialise global stores so they are ready before any child route
// renders. Mirrors the React pattern of wrapping RouterProvider inside
// <ShellProvider><PlanningProvider> in App.tsx.
//
// Pinia stores are singletons — calling useXStore() here just ensures the store
// instance (and its seed data) is created before the first page mounts.
import { useShellStore } from "@/app/stores/shell";
import { usePlanningStore } from "@/app/stores/planning";
import { useAppRoleStore } from "@/app/stores/appRole";

useShellStore();
usePlanningStore();
useAppRoleStore();
</script>

<template>
    <RouterView />
</template>
