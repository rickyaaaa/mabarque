const liveToast = document.querySelector("#live-toast");
const sessionState = document.querySelector("#session-state");
const toggleSession = document.querySelector("#toggle-session");
const controlStatus = document.querySelector("#control-status");
const startSessionModal = document.querySelector("#start-session-modal");
let donationsPaused = false;
let sessionActive = true;
const queueSeed = [
  { position: 1, name: "Nisa Gaming", detail: "Siap · 8 match" },
  { position: 2, name: "Bimo Stream", detail: "Menunggu · 5 match" },
  { position: 3, name: "Daffa ML", detail: "Siap · 14 match" },
  { position: 4, name: "Lutfi Pro", detail: "Siap · 3 match" }
];
let liveQueue = queueSeed.map((entry) => ({ ...entry }));

document.querySelector("#session-donation-total").textContent = new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(720000);

function showLiveToast(message) {
  liveToast.textContent = message;
  liveToast.classList.add("is-visible");
  window.clearTimeout(showLiveToast.timeout);
  showLiveToast.timeout = window.setTimeout(() => liveToast.classList.remove("is-visible"), 2600);
}

function setDonationState() {
  donationsPaused = !donationsPaused;
  toggleSession.textContent = donationsPaused ? "Lanjutkan donasi" : "Jeda donasi";
  controlStatus.textContent = donationsPaused ? "Donasi dijeda" : "Menerima donasi";
  document.querySelector("#accept-donations .control-toggle").classList.toggle("is-on", !donationsPaused);
  showLiveToast(donationsPaused ? "Donasi baru dijeda untuk sesi ini." : "Donasi baru diterima kembali.");
}

function renderLiveQueue() {
  const list = document.querySelector("#live-queue-list");
  const empty = document.querySelector("#live-queue-empty");
  list.innerHTML = liveQueue.map((entry) => `<div class="live-queue-item"><span class="live-queue-position">${entry.position}</span><div><strong>${entry.name}</strong><span>${entry.detail}</span></div></div>`).join("");
  list.hidden = liveQueue.length === 0;
  empty.hidden = liveQueue.length > 0;
}

async function loadArchivedSessions() {
  try {
    const response = await fetch("/api/live-sessions/archives", { headers: { Accept: "application/json" } });
    if (!response.ok) return;
    const payload = await response.json();
    const history = document.querySelector("#session-history");
    const sessions = Array.isArray(payload.data) ? payload.data : [];
    if (!sessions.length) return;
    history.innerHTML = sessions.slice(0, 3).map((session) => `<div class="session-history-row"><span class="session-history-icon">✓</span><div><strong>${session.title}</strong><small>${session.ended_at ? new Date(session.ended_at).toLocaleDateString("id-ID") : "Sesi selesai"}</small></div><span class="session-history-value">${session.total_matches ?? 0} match</span></div>`).join("");
  } catch {
    // Static prototype data remains visible when the API is unavailable.
  }
}

toggleSession.addEventListener("click", setDonationState);
document.querySelector("#accept-donations").addEventListener("click", setDonationState);
document.querySelector("#open-queue").addEventListener("click", () => { window.location.href = "/dashboard#queue"; });
document.querySelector("#show-overlay").addEventListener("click", () => { window.location.href = "/overlay-settings"; });
document.querySelector("#copy-public-queue").addEventListener("click", async () => {
  const link = "https://mabarqueue.test/andra/queue";
  try { await navigator.clipboard.writeText(link); showLiveToast("Link antrean publik disalin."); } catch { showLiveToast(`Link antrean tiruan: ${link}`); }
});

document.querySelector("#reset-live-queue").addEventListener("click", () => {
  if (!liveQueue.length) return;
  if (!window.confirm("Reset antrean sesi tiruan?")) return;
  liveQueue = [];
  renderLiveQueue();
  showLiveToast("Antrean sesi dikosongkan pada data tiruan.");
});
document.querySelector("#restore-live-queue").addEventListener("click", () => {
  liveQueue = queueSeed.map((entry) => ({ ...entry }));
  renderLiveQueue();
  showLiveToast("Antrean sesi tiruan dipulihkan.");
});

document.querySelector("#end-session").addEventListener("click", () => {
  if (!sessionActive) return;
  sessionActive = false;
  sessionState.innerHTML = "<i></i>Sesi selesai";
  sessionState.style.background = "var(--muted)";
  toggleSession.disabled = true;
  document.querySelector("#end-session").disabled = true;
  controlStatus.textContent = "Sesi selesai";
  showLiveToast("Sesi ditutup pada data tiruan.");
});

const settingsModal = document.querySelector("#session-settings-modal");
document.querySelector("#open-session-settings").addEventListener("click", () => settingsModal.showModal());
document.querySelector("#close-session-settings").addEventListener("click", () => settingsModal.close("cancel"));
document.querySelector("#session-settings-form").addEventListener("submit", (event) => {
  event.preventDefault();
  const name = document.querySelector("#session-name").value.trim();
  if (name) document.querySelector("#active-session-title").textContent = name;
  settingsModal.close();
  showLiveToast("Pengaturan sesi disimpan di data tiruan.");
});
document.querySelector("#new-session").addEventListener("click", () => startSessionModal.showModal());
document.querySelector("#load-more-sessions").addEventListener("click", (event) => {
  const history = document.querySelector("#session-history");
  history.insertAdjacentHTML("beforeend", '<div class="session-history-row"><span class="session-history-icon">✓</span><div><strong>Jumat komunitas malam</strong><small>24 Jan 2025 · 18:52–21:10</small></div><span class="session-history-value">34 match</span></div>');
  event.currentTarget.disabled = true;
  event.currentTarget.textContent = "Arsip lengkap";
  showLiveToast("Satu arsip sesi tambahan dimuat.");
});
document.querySelector("#close-start-session").addEventListener("click", () => startSessionModal.close("cancel"));
document.querySelector("#start-session-form").addEventListener("submit", (event) => {
  event.preventDefault();
  sessionActive = true;
  sessionState.innerHTML = "<i></i>Sedang live";
  sessionState.style.background = "var(--green)";
  toggleSession.disabled = false;
  document.querySelector("#end-session").disabled = false;
  controlStatus.textContent = "Menerima donasi";
  startSessionModal.close();
  showLiveToast("Sesi live baru dimulai pada data tiruan.");
});

let elapsedSeconds = 2 * 60 * 60 + 14 * 60 + 36;
renderLiveQueue();
loadArchivedSessions();
window.setInterval(() => {
  if (!sessionActive || donationsPaused) return;
  elapsedSeconds += 1;
  const hours = String(Math.floor(elapsedSeconds / 3600)).padStart(2, "0");
  const minutes = String(Math.floor((elapsedSeconds % 3600) / 60)).padStart(2, "0");
  const seconds = String(elapsedSeconds % 60).padStart(2, "0");
  document.querySelector("#elapsed-time").textContent = `${hours}:${minutes}:${seconds}`;
}, 1000);
