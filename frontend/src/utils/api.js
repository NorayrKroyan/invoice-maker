export async function fetchJson(url, opts = {}) {
    const method = opts.method || 'GET'
    const headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) }
    const body = opts.body ? JSON.stringify(opts.body) : undefined

    const res = await fetch(url, { method, headers, body })
    const text = await res.text()
    let data
    try { data = text ? JSON.parse(text) : null } catch { data = text }

    if (!res.ok) throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`)
    return data
}
