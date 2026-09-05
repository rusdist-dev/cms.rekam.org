/**
 * Central fetch helper (context.md §2). Every request to the internal API goes
 * through here — raw fetch() inside x-data is a review rejection.
 *
 * Errors are normalised into a single shape so callers never inspect a Response:
 *   { status, message, errors: { field: ['...'] } }
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

const MESSAGES = {
    0: 'Tidak dapat terhubung ke server. Periksa koneksi Anda.',
    401: 'Sesi Anda telah berakhir. Silakan masuk kembali.',
    403: 'Anda tidak memiliki izin untuk tindakan ini.',
    404: 'Data yang diminta tidak ditemukan.',
    419: 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
    422: 'Periksa kembali isian yang ditandai.',
    429: 'Terlalu banyak permintaan. Tunggu sejenak lalu coba lagi.',
    500: 'Terjadi kesalahan pada server. Silakan coba lagi.',
}

const fallbackMessage = (status) => MESSAGES[status] ?? 'Terjadi kesalahan. Silakan coba lagi.'

async function request(method, url, body = null, options = {}) {
    const isFormData = body instanceof FormData
    let res

    try {
        res = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
                ...(method !== 'GET' ? { 'X-CSRF-TOKEN': csrf() } : {}),
                ...(options.headers ?? {}),
            },
            body: body ? (isFormData ? body : JSON.stringify(body)) : null,
            credentials: 'same-origin',
            signal: options.signal ?? null,
        })
    } catch (e) {
        // AbortError is a caller-initiated cancel, not a failure to report.
        if (e.name === 'AbortError') throw e

        throw { status: 0, message: fallbackMessage(0), errors: {} }
    }

    // 401 during an XHR means the session died behind the user's back; a full
    // reload lets the normal web guard redirect to the login page.
    if (res.status === 401) {
        window.location.reload()
        throw { status: 401, message: fallbackMessage(401), errors: {} }
    }

    if (res.status === 204) return null

    const data = await res.json().catch(() => null)

    if (!res.ok) {
        throw {
            status: res.status,
            message: data?.message ?? fallbackMessage(res.status),
            errors: data?.errors ?? {},
        }
    }

    return data
}

/** Build a query string, dropping empty values so filters stay tidy. */
export const query = (params = {}) =>
    new URLSearchParams(
        Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    ).toString()

const api = {
    get: (url, options) => request('GET', url, null, options),
    post: (url, body, options) => request('POST', url, body, options),
    put: (url, body, options) => request('PUT', url, body, options),
    patch: (url, body, options) => request('PATCH', url, body, options),
    delete: (url, options) => request('DELETE', url, null, options),
    query,
}

window.api = api

export default api
