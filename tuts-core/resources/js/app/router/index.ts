import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

// ─── Lazy-loaded page components ─────────────────────────────────────────────
// Each import() will be replaced with the real .vue file as phases complete.
// Lazy loading is set up here from the start so the route tree never needs to
// change — only the imported component path changes per phase.
const AppShell        = () => import('@/app/layout/AppShell.vue')
const PlaceholderPage = () => import('@/app/pages/PlaceholderPage.vue')
const HomePage        = () => import('@/app/pages/HomePage.vue')
const ChatHubPage     = () => import('@/app/pages/ChatHubPage.vue')
const UCDetailPage    = () => import('@/app/pages/UCDetailPage.vue')
const ProfilePage     = () => import('@/app/pages/ProfilePage.vue')
const CalendarPage    = () => import('@/app/pages/CalendarPage.vue')
const UcsSpacesPage   = () => import('@/app/pages/UcsSpacesPage.vue')
const TeacherUcsPage  = () => import('@/app/pages/TeacherUcsPage.vue')
const PlanningPage    = () => import('@/app/pages/PlanningPage.vue')
const MyPlansPage     = () => import('@/app/pages/MyPlansPage.vue')
const SpaceDetailPage = () => import('@/app/pages/SpaceDetailPage.vue')
const DashboardPage   = () => import('@/app/pages/DashboardPage.vue')

// ─── /ucs branch component ────────────────────────────────────────────────────
// Mirrors the UcsRouteHandler component from React routes.tsx.
// Reads the Pinia store directly (stores are usable outside of setup() via
// the standard useStore() call after pinia is initialized).
//
// NOTE: This must be a component definition, not a route-level guard,
// because the React implementation renders RestrictedScreen in-place
// (not a redirect) — RoleGuard.vue handles that at the component level.
const UcsRouteHandler = () => import('@/app/pages/UcsRouteHandler.vue')

// ─── Route meta typing ────────────────────────────────────────────────────────
declare module 'vue-router' {
  interface RouteMeta {
    // Reserved for future meta — e.g. page title, analytics label
    pageTitle?: string
  }
}

// ─── Route definitions ────────────────────────────────────────────────────────
// Strategy: NO role-redirect guards in navigation hooks.
//
// The React codebase renders a RestrictedScreen component in-place when the
// role does not match — it never redirects. Pages in Phase 5 wrap their
// content with <RoleGuard required="student"> or <RoleGuard required="teacher">
// which reacts to Pinia store changes in real-time.
//
// This preserves the exact UX: if a teacher is on /home and switches role in
// TopNav, the page instantly transitions from RestrictedScreen to content —
// no navigation occurs.
const routes: RouteRecordRaw[] = [
  {
    path:      '/',
    component: AppShell,
    children:  [

      // ── Root redirect ────────────────────────────────────────────────────
      {
        path:     '',
        redirect: '/home',
      },

      // ── Student-primary routes ────────────────────────────────────────────
      // RoleGuard is applied inside each page component (Phase 5).
      {
        path:      'home',
        name:      'home',
        component: HomePage,
        meta:      { pageTitle: 'Homepage' },
      },
      {
        path:      'chat',
        name:      'chat',
        component: ChatHubPage,
        meta:      { pageTitle: 'Chat Hub' },
      },
      {
        path:      'planificacao',
        name:      'planning',
        component: PlanningPage,
        meta:      { pageTitle: 'Planificação' },
      },
      {
        path:      'planificacao/:id',
        name:      'planning-detail',
        component: PlanningPage,
        meta:      { pageTitle: 'Planificação' },
      },
      {
        path:      'meus-planos',
        name:      'my-plans',
        component: MyPlansPage,
        meta:      { pageTitle: 'Os Meus Planos' },
      },

      // ── Teacher-primary routes ─────────────────────────────────────────────
      {
        path:      'dashboard',
        name:      'dashboard',
        component: DashboardPage,
        meta:      { pageTitle: 'Dashboard Pedagógico' },
      },

      // ── Role-branched: /ucs ───────────────────────────────────────────────
      // UcsRouteHandler reads the role store and renders the correct page.
      // This preserves the React UcsRouteHandler pattern without a redirect.
      {
        path:      'ucs',
        name:      'ucs',
        component: UcsRouteHandler,
        meta:      { pageTitle: "UC's e Espaços" },
      },

      // ── Shared routes ─────────────────────────────────────────────────────
      {
        path:      'calendar',
        name:      'calendar',
        component: CalendarPage,
        meta:      { pageTitle: 'Calendário' },
      },
      {
        path:      'profile',
        name:      'profile',
        component: ProfilePage,
        meta:      { pageTitle: 'Perfil' },
      },

      // ── UC & Space detail ─────────────────────────────────────────────────
      {
        path:      'uc/:id',
        name:      'uc-detail',
        component: UCDetailPage,
      },
      {
        path:      'spaces',
        name:      'spaces',
        component: PlaceholderPage,
        props:     {
          icon:     '📁',
          title:    'Espaços',
          subtitle: 'Organiza as tuas conversas e materiais em espaços temáticos.',
        },
        meta: { pageTitle: 'Espaços' },
      },
      {
        path:      'space/:id',
        name:      'space-detail',
        component: SpaceDetailPage,
      },

      // ── Catch-all ─────────────────────────────────────────────────────────
      {
        path:     ':pathMatch(.*)*',
        redirect: '/home',
      },
    ],
  },
]

// ─── Router instance ──────────────────────────────────────────────────────────
export const router = createRouter({
  history: createWebHistory(),
  routes,
})
