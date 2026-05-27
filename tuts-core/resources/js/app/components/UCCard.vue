<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { MoreVertical, Eye, EyeOff, Trash2 } from '@lucide/vue'

defineOptions({ name: 'UCCard' })

const props = defineProps<{
  id:           string
  name:         string
  teacher:      string
  year:         string
  academicYear: string
  cover:        string
  shortCode:    string
  description:  string
}>()

const router    = useRouter()
const menuOpen  = ref(false)
const isGradient = props.cover.startsWith('linear-gradient') || props.cover.startsWith('radial-gradient')

function navigate() {
  router.push({ name: 'uc-detail', params: { id: props.id } })
}

function toggleMenu(e: MouseEvent) {
  e.stopPropagation()
  menuOpen.value = !menuOpen.value
}

function closeMenu() {
  menuOpen.value = false
}
</script>

<template>
  <!-- Click-away overlay -->
  <div
    v-if="menuOpen"
    style="position: fixed; inset: 0; z-index: 40;"
    @click="closeMenu"
  />

  <div
    class="transition-shadow hover:shadow-md"
    style="background: #ffffff; border: 1px solid #E5E5E5; border-radius: 16px; overflow: hidden; cursor: pointer; display: flex; flex-direction: column;"
    @click="navigate"
  >
    <!-- Cover area -->
    <div style="position: relative; height: 120px; flex-shrink: 0; overflow: hidden;">
      <!-- Gradient cover -->
      <div
        v-if="isGradient"
        :style="{ background: cover, width: '100%', height: '100%' }"
      />
      <!-- Image cover -->
      <img
        v-else
        :src="cover"
        :alt="name"
        style="width: 100%; height: 100%; object-fit: cover;"
      />

      <!-- Overlay -->
      <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.25);" />

      <!-- Short code badge -->
      <div
        style="position: absolute; bottom: 12px; left: 12px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border-radius: 8px; padding: 4px 10px;"
      >
        <span
          style="font-family: Inter, sans-serif; font-weight: 700; font-size: 13px; color: #ffffff;"
        >
          {{ shortCode }}
        </span>
      </div>

      <!-- 3-dot menu -->
      <div style="position: absolute; top: 10px; right: 10px; z-index: 50;">
        <button
          class="transition-opacity hover:opacity-80"
          style="width: 28px; height: 28px; border-radius: 7px; background: rgba(255,255,255,0.22); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;"
          @click="toggleMenu"
        >
          <MoreVertical :size="14" :stroke-width="2" color="#ffffff" />
        </button>

        <!-- Dropdown menu -->
        <div
          v-if="menuOpen"
          style="position: absolute; top: 32px; right: 0; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.12); min-width: 160px; z-index: 100; overflow: hidden;"
          @click.stop
        >
          <button
            class="transition-colors hover:bg-gray-50"
            style="display: flex; align-items: center; gap: 8px; width: 100%; font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: none; border: none; cursor: pointer; padding: 10px 14px; text-align: left;"
            @click="closeMenu"
          >
            <Eye :size="14" :stroke-width="1.8" color="#656966" />
            Ver UC
          </button>
          <button
            class="transition-colors hover:bg-gray-50"
            style="display: flex; align-items: center; gap: 8px; width: 100%; font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #1E1E1E; background: none; border: none; cursor: pointer; padding: 10px 14px; text-align: left;"
            @click="closeMenu"
          >
            <EyeOff :size="14" :stroke-width="1.8" color="#656966" />
            Ocultar UC
          </button>
          <button
            class="transition-colors hover:bg-red-50"
            style="display: flex; align-items: center; gap: 8px; width: 100%; font-family: Inter, sans-serif; font-weight: 400; font-size: 13px; color: #E53E3E; background: none; border: none; cursor: pointer; padding: 10px 14px; text-align: left;"
            @click="closeMenu"
          >
            <Trash2 :size="14" :stroke-width="1.8" color="#E53E3E" />
            Remover UC
          </button>
        </div>
      </div>
    </div>

    <!-- Card body -->
    <div style="padding: 16px; display: flex; flex-direction: column; gap: 6px; flex: 1;">
      <p
        style="font-family: Inter, sans-serif; font-weight: 700; font-size: 15px; color: #1E1E1E; margin: 0; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"
      >
        {{ name }}
      </p>
      <p
        style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #9E9E9E; margin: 0;"
      >
        {{ teacher }}
      </p>
      <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
        <span
          style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #BDBABA;"
        >
          {{ year }}
        </span>
        <span style="color: #E5E5E5;">·</span>
        <span
          style="font-family: Inter, sans-serif; font-weight: 400; font-size: 12px; color: #BDBABA;"
        >
          {{ academicYear }}
        </span>
      </div>
    </div>
  </div>
</template>
