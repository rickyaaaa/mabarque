// Halaman antrean publik: data awal dari server, diperbarui tiap 5 detik dari /api/channels/{slug}/queue.
// Nama pemain berasal dari donatur — selalu dimasukkan lewat textContent, tidak pernah innerHTML.
(function () {
  const POLL_MS = 5000;
  const STATUS = { ready: "Siap", offline: "Menunggu", skipped: "Dilewati", playing: "Bermain", capped: "Maks sesi" };
  const SORTS = {
    queue: (a, b) => a.number - b.number,
    most: (a, b) => b.matches_remaining - a.matches_remaining || a.number - b.number,
    least: (a, b) => a.matches_remaining - b.matches_remaining || a.number - b.number,
    newest: (a, b) => String(b.joined_at || "").localeCompare(String(a.joined_at || "")) || b.number - a.number,
  };
  const slug = document.body.dataset.channelId;
  const $ = (selector) => document.querySelector(selector);
  const list = $("#public-queue-list");
  const toast = $("#public-toast");
  let waiting = null; // null = belum ada data dari API, render awal dari server dipertahankan
  let query = "";
  let sort = "queue";
  let lastUpdate = Date.now();

  function showToast(message) {
    toast.textContent = message;
    toast.classList.add("is-visible");
    window.clearTimeout(showToast.timeout);
    showToast.timeout = window.setTimeout(() => toast.classList.remove("is-visible"), 2500);
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function fillNames(list, names, emptyText) {
    list.replaceChildren(...(names.length ? names.map((name) => el("li", null, name)) : [el("li", "is-empty", emptyText)]));
  }

  function meta(entry) {
    const parts = [entry.service_type === "joki" ? "Mainin akun" : "Mabar VIP"];
    if (entry.mlbb_id && !entry.player_name.startsWith("ID ")) parts.push(`ID ${entry.mlbb_id}`);
    return parts.join(" · ");
  }

  // Nama/ID bisa disensor ("WangSu*******"): cocokkan ke bagian yang terlihat, dan anggap cocok bila
  // yang diketik pemilik diawali bagian itu (pemilik mengetik nama lengkapnya sendiri).
  function fieldMatches(value, q) {
    const text = String(value || "").toLowerCase();
    if (!text) return false;
    if (!text.includes("*")) return text.includes(q);
    const visible = text.replace(/\*+$/, "");
    return visible.includes(q) || (q.startsWith(visible) && q.length <= text.length);
  }

  function renderList() {
    if (waiting === null) return;
    const q = query.toLowerCase();
    const visible = waiting
      .map((entry, index) => ({ ...entry, number: index + 1 }))
      .filter((entry) => !q || fieldMatches(entry.player_name, q) || fieldMatches(entry.mlbb_id, q))
      .sort(SORTS[sort] || SORTS.queue);

    list.replaceChildren(...visible.map((entry) => {
      const item = el("article", `public-queue-item${q ? " is-match" : ""}`);
      item.setAttribute("aria-label", `Posisi ${entry.number}, ${entry.player_name}, ${entry.matches_remaining} game tersisa`);
      const number = el("span", "public-queue-number", String(entry.number));
      number.setAttribute("aria-hidden", "true");
      const who = el("div");
      who.append(el("strong", null, entry.player_name), el("span", null, meta(entry)));
      const left = el("span", "public-match-left", `${entry.matches_remaining} `);
      left.append(el("small", null, "game"));
      const status = entry.capped ? "capped" : entry.status;
      item.append(number, who, left, el("span", `public-ready is-${status}`, STATUS[status] || "Siap"));
      return item;
    }));

    $("#public-empty").hidden = visible.length > 0;
    const note = $("#public-position-note");
    note.classList.toggle("is-found", Boolean(q && visible.length));
    note.textContent = q && visible.length
      ? `${visible[0].player_name} berada di posisi #${visible[0].number} (${visible[0].matches_remaining} game tersisa).`
      : q ? "Nama atau ID belum ditemukan di antrean." : "Cari nama atau ID kamu untuk melihat posisi dan sisa game.";
  }

  function render(data) {
    const live = data.status === "live";
    const entries = data.queue || [];
    waiting = entries.filter((entry) => entry.status !== "playing");

    $("#live-badge").classList.toggle("is-offline", !live);
    $("#live-label").textContent = live ? "Live sekarang" : "Belum live";
    $("#session-state").textContent = live ? "Live" : "Offline";
    $("#queue-total").textContent = String(waiting.length);
    $("#matches-done").textContent = String(data.session?.total_matches ?? 0);

    const party = data.party || [];
    const next = data.next || [];
    $("#current-section").hidden = !live || (party.length === 0 && next.length === 0);
    fillNames($("#party-list"), party, "Belum ada game berjalan");
    fillNames($("#next-list"), next, "Belum ada yang menunggu");
    $("#party-size").textContent = live ? `${party.length}/${data.rules?.party_size ?? 4} pemain + streamer` : "";

    const cap = data.rules?.session_cap || 0;
    $("#public-rules-note").hidden = cap < 1;
    if (cap > 0) $("#public-rules-note").textContent = `Maks ${cap} game per orang tiap sesi live. Sisa game tetap tersimpan untuk sesi berikutnya.`;

    renderList();
  }

  async function load(manual) {
    try {
      const response = await fetch(`/api/channels/${encodeURIComponent(slug)}/queue`, { headers: { Accept: "application/json" }, cache: "no-store" });
      if (!response.ok) throw new Error(String(response.status));
      render((await response.json()).data);
      lastUpdate = Date.now();
      if (manual) showToast("Antrean diperbarui.");
    } catch (error) {
      if (manual) showToast("Gagal memuat antrean. Coba lagi sebentar.");
    }
  }

  function tickLastUpdate() {
    const seconds = Math.round((Date.now() - lastUpdate) / 1000);
    $("#last-update").textContent = seconds < 5 ? "Baru saja" : `${seconds} detik lalu`;
  }

  $("#queue-search").addEventListener("input", (event) => {
    query = event.target.value.trim();
    if (waiting === null) {
      // Belum ada data API: ambil dulu supaya pencarian memakai daftar terbaru.
      load(false);
      return;
    }
    renderList();
  });

  $("#queue-sort").addEventListener("change", (event) => {
    sort = event.target.value;
    if (waiting === null) {
      load(false);
      return;
    }
    renderList();
  });

  $("#refresh-public-queue").addEventListener("click", () => load(true));

  $("#share-queue").addEventListener("click", async () => {
    const link = window.location.href.split("?")[0];
    try {
      if (navigator.share) {
        await navigator.share({ title: document.title, url: link });
      } else {
        await navigator.clipboard.writeText(link);
        showToast("Link antrean disalin.");
      }
    } catch (error) {
      if (error?.name !== "AbortError") showToast(`Salin link ini: ${link}`);
    }
  });

  window.setInterval(() => load(false), POLL_MS);
  window.setInterval(tickLastUpdate, 1000);
})();
