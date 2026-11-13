// Halaman antrean publik: data awal dari server, diperbarui tiap 5 detik dari /api/channels/{slug}/queue.
// Nama pemain berasal dari donatur — selalu dimasukkan lewat textContent, tidak pernah innerHTML.
(function () {
  const POLL_MS = 5000;
  const PROVIDERS = { manual: "Manual", saweria: "Saweria", sociabuzz: "SociaBuzz", trakteer: "Trakteer" };
  const slug = document.body.dataset.channelId;
  const $ = (selector) => document.querySelector(selector);
  const list = $("#public-queue-list");
  const toast = $("#public-toast");
  let waiting = null; // null = belum ada data dari API, render awal dari server dipertahankan
  let query = "";
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

  function initials(name) {
    return name.trim().split(/\s+/).slice(0, 2).map((part) => part[0] || "").join("").toUpperCase() || "?";
  }

  function renderList() {
    if (waiting === null) return;
    const q = query.toLowerCase();
    const visible = waiting.map((entry, index) => ({ ...entry, number: index + 1 })).filter((entry) => entry.player_name.toLowerCase().includes(q));

    list.replaceChildren(...visible.map((entry) => {
      const item = el("article", `public-queue-item${q ? " is-match" : ""}`);
      item.setAttribute("aria-label", `Posisi ${entry.number}, ${entry.player_name}, ${entry.matches_remaining} match tersisa`);
      const number = el("span", "public-queue-number", String(entry.number));
      number.setAttribute("aria-hidden", "true");
      const who = el("div");
      who.append(el("strong", null, entry.player_name), el("span", null, PROVIDERS[entry.provider] || "VIP"));
      const left = el("span", "public-match-left", `${entry.matches_remaining} `);
      left.append(el("small", null, "match"));
      item.append(number, who, left, el("span", "public-ready", "Siap"));
      return item;
    }));

    $("#public-empty").hidden = visible.length > 0;
    const note = $("#public-position-note");
    note.classList.toggle("is-found", Boolean(q && visible.length));
    note.textContent = q && visible.length
      ? `${visible[0].player_name} berada di posisi #${visible[0].number} (${visible[0].matches_remaining} match tersisa).`
      : q ? "Nama belum ditemukan di antrean aktif." : "Cari nama kamu untuk melihat posisi di antrean.";
  }

  function render(data) {
    const live = data.status === "live";
    const entries = data.queue || [];
    const playing = entries.find((entry) => entry.status === "playing");
    waiting = entries.filter((entry) => entry.status !== "playing");

    $("#live-badge").classList.toggle("is-offline", !live);
    $("#live-label").textContent = live ? "Live sekarang" : "Belum live";
    $("#session-state").textContent = live ? "Live" : "Offline";
    $("#queue-total").textContent = String(waiting.length);
    $("#matches-done").textContent = String(data.session?.total_matches ?? 0);

    $("#current-section").hidden = !playing;
    if (playing) {
      $("#public-current-title").textContent = playing.player_name;
      $("#current-meta").textContent = `${PROVIDERS[playing.provider] || "VIP"} · sisa ${playing.matches_remaining} match`;
      $("#current-avatar").textContent = initials(playing.player_name);
    }

    const emptyTitle = $("#public-empty strong");
    emptyTitle.textContent = live ? "Antrean masih kosong" : "Sesi belum dimulai";
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
