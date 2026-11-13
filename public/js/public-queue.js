let publicPlayers = [
  { position: 1, name: "Nisa Gaming", provider: "SociaBuzz", matches: 8, status: "Siap" },
  { position: 2, name: "Bimo Stream", provider: "Manual", matches: 5, status: "Menunggu" },
  { position: 3, name: "Daffa ML", provider: "Saweria", matches: 14, status: "Siap" },
  { position: 4, name: "Lutfi Pro", provider: "SociaBuzz", matches: 3, status: "Siap" },
  { position: 5, name: "Citra ML", provider: "Saweria", matches: 7, status: "Siap" },
  { position: 6, name: "Rendi GG", provider: "Manual", matches: 4, status: "Offline" }
];
const publicList = document.querySelector("#public-queue-list");
const publicEmpty = document.querySelector("#public-empty");
const publicToast = document.querySelector("#public-toast");
let queueQuery = "";

function showPublicToast(message) {
  publicToast.textContent = message;
  publicToast.classList.add("is-visible");
  window.clearTimeout(showPublicToast.timeout);
  showPublicToast.timeout = window.setTimeout(() => publicToast.classList.remove("is-visible"), 2500);
}

function renderPublicQueue() {
  const visiblePlayers = publicPlayers.filter((player) => player.name.toLowerCase().includes(queueQuery));
  publicList.innerHTML = visiblePlayers.map((player) => `<article class="public-queue-item ${queueQuery ? "is-match" : ""}" aria-label="Posisi ${player.position}, ${player.name}, ${player.matches} match tersisa, status ${player.status}"><span class="public-queue-number" aria-hidden="true">${player.position}</span><div><strong>${player.name}</strong><span>${player.provider}</span></div><span class="public-match-left">${player.matches} <small>match</small></span><span class="public-ready ${player.status === "Menunggu" ? "public-waiting" : ""}">${player.status}</span></article>`).join("");
  publicEmpty.hidden = visiblePlayers.length > 0;
  document.querySelector("#queue-total").textContent = publicPlayers.length + 5;
  const positionNote = document.querySelector("#public-position-note");
  positionNote.classList.toggle("is-found", Boolean(queueQuery && visiblePlayers.length));
  positionNote.textContent = queueQuery && visiblePlayers.length ? `${visiblePlayers[0].name} berada di posisi #${visiblePlayers[0].position}.` : queueQuery ? "Nama belum ditemukan di antrean aktif." : "Cari nama kamu untuk melihat posisi di antrean.";
}

async function loadPublicQueue() {
  const channelId = document.body.dataset.channelId;
  if (!channelId) return;

  try {
    const response = await fetch(`/api/channels/${encodeURIComponent(channelId)}/queue`, { headers: { Accept: "application/json" } });
    if (!response.ok) return;
    const payload = await response.json();
    const queue = payload?.data?.queue;
    if (!Array.isArray(queue)) return;
    publicPlayers = queue.map((entry) => ({
      position: entry.position,
      name: entry.player_name,
      provider: entry.provider ?? "VIP",
      matches: entry.matches_remaining,
      status: entry.status === "playing" ? "Sedang bermain" : entry.status === "offline" ? "Offline" : "Siap"
    }));
    document.querySelector("#last-update").textContent = "Baru saja";
    renderPublicQueue();
  } catch {
    // Keep prototype data visible when the public API is unavailable.
  }
}

document.querySelector("#queue-search").addEventListener("input", (event) => {
  queueQuery = event.target.value.trim().toLowerCase();
  renderPublicQueue();
});

document.querySelector("#refresh-public-queue").addEventListener("click", () => {
  document.querySelector("#last-update").textContent = "Baru saja";
  showPublicToast("Antrean diperbarui dari data tiruan.");
  renderPublicQueue();
});
document.querySelector("#share-queue").addEventListener("click", async () => {
  const link = "https://mabarqueue.test/andra/queue";
  try { await navigator.clipboard.writeText(link); showPublicToast("Link antrean disalin."); } catch { showPublicToast(`Link antrean tiruan: ${link}`); }
});
window.setInterval(() => { document.querySelector("#last-update").textContent = "Baru saja"; renderPublicQueue(); loadPublicQueue(); }, 5000);
renderPublicQueue();
loadPublicQueue();
