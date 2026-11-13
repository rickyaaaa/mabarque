const overlayDefaults = { accent: "#D82A1C", scale: 100, maxPlayers: 5, showNow: true, transparent: true };
const overlayPlayers = [
  ["Nisa Gaming", "8x"], ["Bimo Stream", "5x"], ["Daffa ML", "14x"], ["Lutfi Pro", "3x"], ["Citra ML", "7x"], ["Rendi GG", "4x"], ["Alya Esports", "6x"], ["Fajar ML", "2x"], ["Icha Gaming", "9x"], ["Yudha ML", "11x"]
];
const preview = document.querySelector("#overlay-preview");
const previewQueue = document.querySelector("#preview-queue");
const stage = document.querySelector("#overlay-stage");
const toast = document.querySelector("#overlay-toast");
const color = document.querySelector("#accent-color");
const hex = document.querySelector("#accent-hex");
const scale = document.querySelector("#font-scale");
const maxPlayers = document.querySelector("#max-players");
const showNow = document.querySelector("#show-now-playing");
const transparent = document.querySelector("#transparent-mode");

function showToast(message) { toast.textContent = message; toast.classList.add("is-visible"); window.clearTimeout(showToast.timer); showToast.timer = window.setTimeout(() => toast.classList.remove("is-visible"), 2400); }

function renderPreview() {
  const accent = hex.value.toUpperCase();
  preview.style.setProperty("--preview-accent", accent);
  preview.style.setProperty("--preview-scale", Number(scale.value) / 100);
  stage.style.background = transparent.checked ? "repeating-linear-gradient(45deg, rgba(255,255,255,.05) 0 8px, transparent 8px 16px)" : "#201126";
  document.querySelector("#font-scale-value").value = `${scale.value}%`;
  document.querySelector("#font-scale-value").textContent = `${scale.value}%`;
  document.querySelector("#preview-now").hidden = !showNow.checked;
  previewQueue.innerHTML = overlayPlayers.slice(0, Number(maxPlayers.value)).map(([name, matches]) => `<div class="preview-entry"><span>${name}</span><small>${matches}</small></div>`).join("");
}

function settingsFromForm() {
  return {
    accent_color: hex.value.toUpperCase(),
    font_scale: Number(scale.value),
    max_players: Number(maxPlayers.value),
    show_now_playing: showNow.checked,
    transparent_mode: transparent.checked
  };
}

function applySettings(settings) {
  if (!settings) return;
  color.value = settings.accent_color || overlayDefaults.accent;
  hex.value = color.value.toUpperCase();
  scale.value = settings.font_scale ?? overlayDefaults.scale;
  maxPlayers.value = String(settings.max_players ?? overlayDefaults.maxPlayers);
  showNow.checked = settings.show_now_playing ?? overlayDefaults.showNow;
  transparent.checked = settings.transparent_mode ?? overlayDefaults.transparent;
  renderPreview();
}

async function loadSettings() {
  try {
    const response = await fetch('/api/overlay-settings', { credentials: 'include' });
    if (response.ok) {
      const payload = await response.json();
      applySettings(payload.data);
      return;
    }
  } catch {
    // Static preview keeps working when the API is unavailable.
  }

  const saved = window.localStorage.getItem('mabarqueue-overlay-settings');
  if (saved) {
    try {
      applySettings(JSON.parse(saved));
    } catch {
      window.localStorage.removeItem('mabarqueue-overlay-settings');
    }
  }
}

async function pollOverlayQueue() {
  const token = document.body.dataset.overlayToken;

  if (!token) return;

  try {
    const response = await fetch(`/api/overlay/${encodeURIComponent(token)}`, { cache: 'no-store' });
    if (!response.ok) return;
    const payload = await response.json();
    const queue = payload.data?.queue?.queue ?? [];
    previewQueue.innerHTML = queue.slice(0, Number(maxPlayers.value)).map((entry) => `<div class="preview-entry"><span>${entry.player_name}</span><small>${entry.matches_remaining}x</small></div>`).join('');
  } catch {
    // Keep the last preview visible while the public endpoint is unavailable.
  }
}

color.addEventListener("input", () => { hex.value = color.value.toUpperCase(); renderPreview(); });
hex.addEventListener("change", () => { if (/^#[0-9A-F]{6}$/i.test(hex.value)) { color.value = hex.value; renderPreview(); } else { hex.value = color.value.toUpperCase(); showToast("Gunakan format warna #RRGGBB."); } });
[scale, maxPlayers, showNow, transparent].forEach((control) => control.addEventListener("input", renderPreview));

document.querySelector("#save-overlay").addEventListener("click", async () => {
  const settings = settingsFromForm();
  window.localStorage.setItem('mabarqueue-overlay-settings', JSON.stringify(settings));

  try {
    const response = await fetch('/api/overlay-settings', {
      method: 'PUT',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(settings)
    });
    if (response.ok) {
      showToast('Pengaturan overlay tersimpan.');
      return;
    }
  } catch {
    // Local storage preserves the preview until the backend is available.
  }

  showToast('Pengaturan overlay disimpan untuk pratinjau ini.');
});
document.querySelector("#reset-overlay").addEventListener("click", () => { color.value = overlayDefaults.accent; hex.value = overlayDefaults.accent; scale.value = overlayDefaults.scale; maxPlayers.value = overlayDefaults.maxPlayers; showNow.checked = overlayDefaults.showNow; transparent.checked = overlayDefaults.transparent; renderPreview(); showToast("Pengaturan dikembalikan ke default."); });
document.querySelectorAll("#copy-overlay-link, #copy-overlay-link-small").forEach((button) => button.addEventListener("click", async () => { try { await navigator.clipboard.writeText("https://mabarqueue.id/o/andrarizky"); showToast("Link overlay disalin."); } catch { showToast("Link overlay: mabarqueue.id/o/andrarizky"); } }));
renderPreview();
loadSettings();
window.setInterval(pollOverlayQueue, 5000);
