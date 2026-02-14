export function money(v) {
    const n = Number(v || 0)
    return n.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}