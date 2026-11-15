/** Satır içi SVG yolları (24×24, stroke). Ek istek ve ikon kütüphanesi yok. */
export const icons = {
  search: 'M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z',
  close: 'M6 6l12 12M18 6L6 18',
  chevronStart: 'M15 18l-6-6 6-6',
  chevronEnd: 'M9 18l6-6-6-6',
  chevronDown: 'M6 9l6 6 6-6',
  phone: 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z',
  pin: 'M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11zM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
} as const

export type IconName = keyof typeof icons
