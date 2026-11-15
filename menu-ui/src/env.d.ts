/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** PixMenu API kökü, ör. https://pixmenu.com/api/v1 (boş → aynı origin /api/v1). */
  readonly VITE_API_URL?: string
  /** Sabit restoran slug'ı; boşsa restoran host'tan çözülür. */
  readonly VITE_RESTAURANT?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
