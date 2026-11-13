// Overlay OBS: render antrean dari data awal, lalu poll /api/overlay/{token} tiap 3 detik.
// Semua teks dimasukkan lewat textContent (nama pemain berasal dari donatur — jangan pakai innerHTML).
(function () {
  const POLL_MS = 3000;
  const PROVIDERS = { manual: "Manual", saweria: "Saweria", sociabuzz: "SociaBuzz", trakteer: "Trakteer" };
  const token = document.body.dataset.token;
  const card = document.querySelector("#overlay");
  const nowBox = document.querySelector("#overlay-now");
  const list = document.querySelector("#overlay-queue");
  const empty = document.querySelector("#overlay-empty");

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function render(data) {
    const settings = data.overlay_settings || {};
    const queue = data.queue || { status: "offline", entries: [] };
    const accent = /^#[0-9a-f]{6}$/i.test(settings.accent_color || "") ? settings.accent_color : "#D82A1C";

    document.documentElement.style.setProperty("--accent", accent);
    document.documentElement.style.setProperty("--scale", String((settings.font_scale || 100) / 100));
    document.body.classList.toggle("is-transparent", settings.transparent_mode !== false);
    document.querySelector("#overlay-channel").textContent = data.channel_name || "";

    const entries = queue.entries || [];
    const playing = entries.find((entry) => entry.status === "playing");
    const showNow = settings.show_now_playing !== false && playing;

    nowBox.hidden = !showNow;
    if (showNow) {
      document.querySelector("#overlay-now-name").textContent = playing.player_name;
      document.querySelector("#overlay-now-meta").textContent = `${PROVIDERS[playing.provider] || "VIP"} · ${playing.matches_remaining} match tersisa`;
    }

    const upcoming = entries.filter((entry) => entry.status === "ready").slice(0, settings.max_players || 5);
    list.replaceChildren(...upcoming.map((entry, index) => {
      const item = el("li", "overlay-entry");
      item.append(el("span", "overlay-entry-name", `${index + 1}. ${entry.player_name}`), el("small", "overlay-entry-left", `${entry.matches_remaining} match`));
      return item;
    }));

    empty.hidden = upcoming.length > 0;
    empty.textContent = queue.status === "live" ? "Antrean VIP masih kosong" : "Sesi mabar belum dimulai";
    card.classList.toggle("is-offline", queue.status !== "live");
  }

  async function poll() {
    try {
      const response = await fetch(`/api/overlay/${encodeURIComponent(token)}`, { headers: { Accept: "application/json" }, cache: "no-store" });
      if (response.ok) render((await response.json()).data);
    } catch (error) {
      // Jaringan putus sebentar: biarkan tampilan terakhir, coba lagi di siklus berikutnya.
    }
  }

  render(window.__OVERLAY__ || {});
  window.setInterval(poll, POLL_MS);
})();
