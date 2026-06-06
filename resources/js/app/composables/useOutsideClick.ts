import { type Ref, watch, onUnmounted } from 'vue'

export function useOutsideClick(
  target: Ref<HTMLElement | null>,
  onOutside: () => void,
  active: Ref<boolean>,
): void {
  function handler(e: MouseEvent) {
    if (target.value && !target.value.contains(e.target as Node)) {
      onOutside()
    }
  }

  watch(active, (isActive) => {
    if (isActive) document.addEventListener('mousedown', handler)
    else          document.removeEventListener('mousedown', handler)
  })

  onUnmounted(() => document.removeEventListener('mousedown', handler))
}
