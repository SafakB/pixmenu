/**
 * localStorage erişimi; gizli pencere / engellenmiş site verisinde sessizce devre dışı kalır.
 * Yalnızca ziyaretçi kolaylıkları (dil tercihi, son şablon) için kullanılır.
 */
export function storageGet(key: string): string | null {
  try {
    return window.localStorage.getItem(key)
  } catch {
    return null
  }
}

export function storageSet(key: string, value: string): void {
  try {
    window.localStorage.setItem(key, value)
  } catch {
    // depolama kapalı: tercih bu oturumla sınırlı kalır
  }
}
