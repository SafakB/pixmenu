const formatters = new Map<string, Intl.NumberFormat>()

function formatter(locale: string, currency: string): Intl.NumberFormat {
  const key = `${locale}|${currency}`
  let instance = formatters.get(key)

  if (instance === undefined) {
    // narrowSymbol: İngilizce menüde de "TRY 129.90" yerine "₺129.90".
    const options: Intl.NumberFormatOptions = { style: 'currency', currency, currencyDisplay: 'narrowSymbol' }

    try {
      instance = new Intl.NumberFormat(locale, options)
    } catch {
      // Geçersiz dil etiketi → tarayıcının varsayılanı.
      instance = new Intl.NumberFormat(undefined, options)
    }

    formatters.set(key, instance)
  }

  return instance
}

/** API fiyatı ("129.90") → yerel para birimi gösterimi ("₺129,90"). Sayı değilse olduğu gibi döner. */
export function formatPrice(price: string, currency: string, locale: string): string {
  const value = Number(price)

  if (price.trim() === '' || !Number.isFinite(value)) {
    return price
  }

  try {
    return formatter(locale, currency).format(value)
  } catch {
    return `${price} ${currency}` // geçersiz para birimi kodu
  }
}
