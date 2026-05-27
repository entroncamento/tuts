<script setup lang="ts">
import { computed } from 'vue'
import { useAppRoleStore } from '@/app/stores/appRole'

// ─── Lazy imports ─────────────────────────────────────────────────────────────
// Both components are loaded lazily to avoid bundling both for every user.
// Vue resolves defineAsyncComponent at render time, so only the active branch
// is ever downloaded.
import { defineAsyncComponent } from 'vue'

const TeacherUcsPage = defineAsyncComponent(
  () => import('@/app/pages/TeacherUcsPage.vue'),
)
const UcsSpacesPage = defineAsyncComponent(
  () => import('@/app/pages/UcsSpacesPage.vue'),
)

// ─── Role branch ─────────────────────────────────────────────────────────────
// Mirrors the React UcsRouteHandler component from routes.tsx exactly:
//   role === "teacher"  →  TeacherUcsPage
//   role === "student"  →  UcsSpacesPage  (wrapped in RoleGuard in Phase 5)
const roleStore   = useAppRoleStore()
const currentPage = computed(() =>
  roleStore.role === 'teacher' ? TeacherUcsPage : UcsSpacesPage,
)
</script>

<template>
  <component :is="currentPage" />
</template>
