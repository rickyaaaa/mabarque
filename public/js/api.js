// Helper pemanggil /api MabarQueue dari halaman web (same-origin, session cookie + CSRF).
// Dipakai halaman: MQApi.get/post/put/patch/del → { ok, status, data, errors, message }.
(function () {
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? "";

  async function request(method, url, body) {
    let response;
    try {
      response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": csrfToken(),
          ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
      });
    } catch (error) {
      return { ok: false, status: 0, data: null, errors: {}, message: "Tidak bisa terhubung ke server. Cek koneksi internet kamu." };
    }

    const payload = await response.json().catch(() => ({}));
    const result = {
      ok: response.ok,
      status: response.status,
      data: payload.data ?? null,
      raw: payload,
      errors: payload.errors ?? {},
      message: payload.message ?? "",
    };

    if (response.status === 419) {
      result.message = "Sesi halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.";
    } else if (response.status === 429 && !result.message) {
      result.message = "Terlalu banyak percobaan. Tunggu sebentar.";
    } else if (response.status >= 500) {
      result.message = "Terjadi kesalahan di server. Coba lagi sebentar lagi.";
    }

    return result;
  }

  /** Pesan pertama dari error validasi Laravel (422), atau pesan umum. */
  function firstError(result, fallback = "Terjadi kesalahan. Coba lagi.") {
    const fields = Object.values(result.errors || {});
    return (fields.length && fields[0][0]) || result.message || fallback;
  }

  window.MQApi = {
    get: (url) => request("GET", url),
    post: (url, body = {}) => request("POST", url, body),
    put: (url, body = {}) => request("PUT", url, body),
    patch: (url, body = {}) => request("PATCH", url, body),
    del: (url) => request("DELETE", url),
    firstError,
  };
})();
