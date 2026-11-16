// Overlay OBS: render antrean dari data awal, lalu poll /api/overlay/{token} tiap 3 detik.
// Semua teks dimasukkan lewat textContent (nama pemain berasal dari donatur — jangan pakai innerHTML).
(function () {
  const POLL_MS = 3000;
  const OBS_RETRY_MS = 30000;
  const PROVIDERS = { manual: "Manual", saweria: "Saweria", sociabuzz: "SociaBuzz", trakteer: "Trakteer" };
  const token = document.body.dataset.token;
  const card = document.querySelector("#overlay");
  const nowBox = document.querySelector("#overlay-now");
  const list = document.querySelector("#overlay-queue");
  const empty = document.querySelector("#overlay-empty");
  const scoreBox = document.querySelector("#overlay-score");
  const packageList = document.querySelector("#overlay-packages");
  const partyList = document.querySelector("#overlay-party");
  // Widget terpisah (/o/{token}/w/{widget}): hanya satu bagian yang tampil, ukuran mengikuti browser source.
  const widget = document.body.dataset.widget || "";

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
    background_color: params.get("bg") ? `#${params.get("bg")}` : undefined,
    background_dim: params.has("dim") ? Number(params.get("dim")) : undefined,
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
    applyBackground(settings);
    document.documentElement.style.setProperty("--scale", String((settings.font_scale || 100) / 100));
    document.body.classList.toggle("is-transparent", settings.transparent_mode !== false);

    // Bentuk & ukuran kartu diatur streamer → browser source OBS cukup diset seukuran ini (tidak di-scale).
    // Widget terpisah selalu mengikuti lebar browser source (diatur lewat CSS).
    card.classList.toggle("is-horizontal", !widget && settings.layout === "horizontal");
    const fit = !widget && settings.fit_source !== false;
    document.body.classList.toggle("is-fit", fit);
    // Mode "ikuti Browser Source": kartu mengisi penuh ukuran yang diset di OBS (lewat CSS).
    card.style.width = fit || widget ? "" : `${Math.max(240, Math.min(1920, settings.card_width || 440))}px`;
    card.style.height = fit || widget ? "" : (settings.card_height ? `${settings.card_height}px` : "");
    document.querySelector("#overlay-channel").textContent = data.channel_name || "";

    const entries = queue.entries || [];
    const party = entries.filter((entry) => entry.status === "playing");
    const showNow = widget === "party" || (!widget && settings.show_now_playing !== false && party.length > 0);

    nowBox.hidden = !showNow;
    partyList.hidden = widget !== "party" || party.length === 0;
    if (widget === "party") {
      // Widget party: satu baris per pemain + sisa game.
      document.querySelector("#overlay-now-name").textContent = party.length ? "" : "Belum ada game";
      document.querySelector("#overlay-now-meta").textContent = party.length ? `${party.length} pemain di party` : (queue.status === "live" ? "Menunggu game dimulai" : "Sesi mabar belum dimulai");
      partyList.replaceChildren(...party.map((entry) => {
        const item = el("li", "overlay-party-member");
        item.append(el("span", null, entry.player_name), el("small", null, `sisa ${entry.matches_remaining}`));
        return item;
      }));
    } else if (showNow) {
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
    scoreBox.hidden = widget === "score" ? false : !(settings.show_score !== false && score);
    document.querySelector("#overlay-wins").textContent = String(score ? score.wins : 0);
    document.querySelector("#overlay-losses").textContent = String(score ? score.losses : 0);

    // Harga paket VIP, mis. "25K · 2 game".
    const packages = data.packages || [];
    packageList.hidden = widget === "packages" ? !packages.length : !(settings.show_packages !== false && packages.length);
    packageList.replaceChildren(...packages.map((pkg) => {
      const item = el("li", "overlay-package");
      item.append(el("strong", null, pkg.price), el("span", null, pkg.gets || `${pkg.games} game`));
      return item;
    }));

    empty.hidden = upcoming.length > 0 || (widget !== "" && widget !== "queue");
    empty.textContent = queue.status === "live" ? "Antrean VIP masih kosong" : "Sesi mabar belum dimulai";
    card.classList.toggle("is-offline", queue.status !== "live");
  }

  // Latar kartu: warna pilihan streamer (+ gambar opsional berlapis warna itu). Teks yang langsung di atas
  // latar otomatis putih bila latarnya gelap, supaya tetap terbaca.
  function applyBackground(settings) {
    const color = /^#[0-9a-f]{6}$/i.test(settings.background_color || "") ? settings.background_color : "#FFFFFF";
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(color.slice(i, i + 2), 16));
    const dim = Math.max(0, Math.min(90, Number.isFinite(settings.background_dim) ? settings.background_dim : 35)) / 100;
    const image = settings.background_image_url;

    card.style.backgroundColor = color;
    card.style.backgroundImage = image
      ? `linear-gradient(rgba(${r}, ${g}, ${b}, ${dim}), rgba(${r}, ${g}, ${b}, ${dim})), url("${encodeURI(image)}")`
      : "";
    card.classList.toggle("has-image", Boolean(image));
    card.classList.toggle("is-dark", (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255 < 0.55);
  }

  // Versi tampilan saat halaman dimuat. OBS tidak pernah memuat ulang browser source sendiri, jadi setelah
  // deploy halaman memuat ulang dirinya supaya CSS/JS baru terpakai.
  const loadedVersion = (window.__OVERLAY__ || {}).version;

  async function poll() {
    try {
      const response = await fetch(`/api/overlay/${encodeURIComponent(token)}`, { headers: { Accept: "application/json" }, cache: "no-store" });
      if (!response.ok) return;
      const data = (await response.json()).data;
      if (loadedVersion && data.version && data.version !== loadedVersion) {
        window.location.reload();
        return;
      }
      render(data);
    } catch (error) {
      // Jaringan putus sebentar: biarkan tampilan terakhir, coba lagi di siklus berikutnya.
    }
  }

  let obsStreaming;
  let obsStatusRevision = 0;
  let pendingObsEvent;
  let sendingObsEvent = false;
  let obsRetryAt = 0;

  async function sendPendingObsEvent() {
    if (!pendingObsEvent || sendingObsEvent || Date.now() < obsRetryAt) return;

    const event = pendingObsEvent;
    sendingObsEvent = true;
    try {
      const response = await fetch(`/api/overlay/${encodeURIComponent(token)}/obs`, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify({ event }),
      });

      if (pendingObsEvent === event) {
        if (response.ok || (response.status < 500 && response.status !== 429 && response.status !== 408)) {
          pendingObsEvent = undefined;
        } else {
          obsRetryAt = Date.now() + OBS_RETRY_MS;
        }
      }
      if (response.ok) poll();
    } catch (error) {
      if (pendingObsEvent === event) obsRetryAt = Date.now() + OBS_RETRY_MS;
    } finally {
      sendingObsEvent = false;
      if (pendingObsEvent && pendingObsEvent !== event) sendPendingObsEvent();
    }
  }

  function updateObsStatus(streaming, fromEvent = false) {
    if (streaming === obsStreaming) return;

    const wasStreaming = obsStreaming;
    obsStreaming = streaming;
    // OBS yang sudah berhenti saat source baru dibuka jangan menutup sesi manual.
    if (!streaming && wasStreaming === undefined && !fromEvent) return;

    pendingObsEvent = streaming ? "started" : "stopped";
    obsRetryAt = 0;
    sendPendingObsEvent();
  }

  function readObsStatus() {
    const revision = ++obsStatusRevision;
    window.obsstudio?.getStatus?.((status) => {
      if (revision === obsStatusRevision && typeof status?.streaming === "boolean") {
        updateObsStatus(status.streaming);
      }
    });
  }

  if (params.get("preview") !== "1") {
    const observeObsEvent = (streaming) => {
      // Respons getStatus yang diminta sebelum event ini tidak boleh menimpanya.
      obsStatusRevision++;
      updateObsStatus(streaming, true);
    };

    window.addEventListener("obsStreamingStarted", () => observeObsEvent(true));
    window.addEventListener("obsStreamingStopped", () => observeObsEvent(false));
    readObsStatus();
    window.setInterval(() => {
      readObsStatus();
      sendPendingObsEvent();
    }, POLL_MS);
  }

  render(window.__OVERLAY__ || {});
  window.setInterval(poll, POLL_MS);
})();
