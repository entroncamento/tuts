// ─── Shared application-level types ──────────────────────────────────────────

export type AppRole = 'student' | 'teacher'

export type ActivePageId =
  | 'home'
  | 'calendar'
  | 'ucs'
  | 'chat'
  | 'profile'
  | 'notifications'
  | 'gestao'
  | 'dashboard'

// ─── Planning types ───────────────────────────────────────────────────────────
// Extracted from React's PlanningContext.tsx so every page can import without
// going through the store. Identical shape to the React interfaces.

export interface ChatMessage {
  id:   string
  type: 'incoming' | 'outgoing'
  text: string
  time: string
}

export interface Goal {
  id:        string
  text:      string
  completed: boolean
}

export interface Plan {
  id:        string
  title:     string
  subject:   string
  aiSummary: string
  duration:  string
  dateRange: string
  messages:  ChatMessage[]
  goals:     Goal[]
}
