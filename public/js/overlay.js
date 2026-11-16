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
  const scoreBox = document.querySelector("#overlay-score");
  const packageList = document.querySelector("#overlay-packages");

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  // Pratinjau di halaman pengaturan: /o/{token}?preview=1&... menimpa pengaturan tersimpan dengan yang
  // sedang diatur (belum disimpan). Di OBS tidak ada parameter ini.
  const params = new URLSearchParams(window.location.search);
  const previewOverrides = params.get("preview") === "1" ? {
    layout: params.get("layout"),
    card_width: Number(params.get("w")) || undefined,
    card_height: params.get("h") === "auto" ? null : (Number(params.get("h")) || undefined),
    accent_color: params.get("accent") ? `#${params.get("accent")}` : undefined,
    font_scale: Number(params.get("scale")) || undefined,
    max_players: Number(params.get("players")) || undefined,
    show_now_playing: params.has("now") ? params.get("now") === "1" : undefined,
    show_score: params.has("score") ? params.get("score") === "1" : undefined,
    show_packages: params.has("packages") ? params.get("packages") === "1" : undefined,
    transparent_mode: params.has("transparent") ? params.get("transparent") === "1" : undefined,
    fit_source: params.has("fit") ? params.get("fit") === "1" : undefined,
  } : {};
  Object.keys(previewOverrides).forEach((key) => previewOverrides[key] === undefined && delete previewOverrides[key]);

  function render(data) {
    const settings = { ...(data.overlay_settings || {}), ...previewOverrides };
    const queue = data.queue || { status: "offline", entries: [] };
    const accent = /^#[0-9a-f]{6}$/i.test(settings.accent_color || "") ? settings.accent_color : "#C5F02E";

    document.documentElement.style.setProperty("--accent", accent);
    document.documentElement.style.setProperty("--scale", String((settings.font_scale || 100) / 100));
    document.body.classList.toggle("is-transparent", settings.transparent_mode !== false);

    // Bentuk & ukuran kartu diatur streamer → browser source OBS cukup diset seukuran ini (tidak di-scale).
    card.classList.toggle("is-horizontal", settings.layout === "horizontal");
    const fit = settings.fit_source !== false;
    document.body.classList.toggle("is-fit", fit);
    // Mode "ikuti Browser Source": kartu mengisi penuh ukuran yang diset di OBS (lewat CSS).
    card.style.width = fit ? "" : `${Math.max(240, Math.min(1920, settings.card_width || 440))}px`;
    card.style.height = fit ? "" : (settings.card_height ? `${settings.card_height}px` : "");
    document.querySelector("#overlay-channel").textContent = data.channel_name || "";

    const entries = queue.entries || [];
    const party = entries.filter((entry) => entry.status === "playing");
    const showNow = settings.show_now_playing !== false && party.length > 0;

    nowBox.hidden = !showNow;
    if (showNow) {
      // Party bisa berisi beberapa pemain sekaligus (streamer + N).
      document.querySelector("#overlay-now-name").textContent = party.map((entry) => entry.player_name).join(" · ");
      document.querySelector("#overlay-now-meta").textContent = party.length === 1
        ? `${PROVIDERS[party[0].provider] || "VIP"} · ${party[0].matches_remaining} game tersisa`
        : `${party.length} pemain di party`;
    }

    // Server sudah mengurutkan: party dulu, lalu urutan masuk party berikutnya.
    const upcoming = entries.filter((entry) => entry.status !== "playing").slice(0, settings.max_players || 5);
    list.replaceChildren(...upcoming.map((entry, index) => {
      const item = el("li", "overlay-entry");
      item.append(el("span", "overlay-entry-name", `${index + 1}. ${entry.player_name}`), el("small", "overlay-entry-left", `${entry.matches_remaining} game`));
      return item;
    }));

    // Skor Win/Lose sesi live.
    const score = data.score;
    scoreBox.hidden = !(settings.show_score !== false && score);
    if (score) {
      document.querySelector("#overlay-wins").textContent = String(score.wins);
      document.querySelector("#overlay-losses").textContent = String(score.losses);
    }

    // Harga paket VIP, mis. "25K · 2 game".
    const packages = data.packages || [];
    packageList.hidden = !(settings.show_packages !== false && packages.length);
    packageList.replaceChildren(...packages.map((pkg) => {
      const item = el("li", "overlay-package");
      item.append(el("strong", null, pkg.price), el("span", null, `${pkg.games} game`));
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
