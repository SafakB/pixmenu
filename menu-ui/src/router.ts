import { createRouter, createWebHistory } from 'vue-router'
import SitePage from './pages/SitePage.vue'

/**
 *  /            → tek şube: menü; birden fazla: şube seçimi
 *  /{şube-slug} → şubenin menüsü
 * Şablon içi gezinme sorgu parametresiyle yapılır (ör. kategori şablonunda ?c=12);
 * böylece telefonun geri tuşu çalışır ve yol tabanlı şube rotasıyla çakışmaz.
 */
export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', component: SitePage },
    { path: '/:branch', component: SitePage, props: true },
    { path: '/:pathMatch(.*)*', component: () => import('./pages/NotFoundPage.vue') },
  ],
  scrollBehavior(_to, _from, savedPosition) {
    return savedPosition ?? { top: 0 }
  },
})
