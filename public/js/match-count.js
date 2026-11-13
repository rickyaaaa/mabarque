const defaultMatches = [
  { id: 1, name: "Raka ML", initials: "RM", provider: "Saweria", total: 12, played: 9 },
  { id: 2, name: "Nisa Gaming", initials: "NG", provider: "SociaBuzz", total: 8, played: 6 },
  { id: 3, name: "Bimo Stream", initials: "BS", provider: "Manual", total: 5, played: 3 },
  { id: 4, name: "Daffa ML", initials: "DM", provider: "Saweria", total: 14, played: 11 },
  { id: 5, name: "Fajar ML", initials: "FM", provider: "Saweria", total: 2, played: 0 },
  { id: 6, name: "Rendi GG", initials: "RG", provider: "Manual", total: 4, played: 3 }
];

let matchPlayers = structuredClone(defaultMatches);
const actionLocks = new Map();
let history = [
  { time: "19:42", player: "Raka ML", match: "Match 9", note: "Selesai · ranked" },
  { time: "19:35", player: "Nisa Gaming", match: "Match 6", note: "Selesai · classic" },
  { time: "19:28", player: "Daffa ML", match: "Match 11", note: "Selesai · ranked" },
  { time: "19:20", player: "Bimo Stream", match: "Match 3", note: "Selesai · classic" }
];
const list = document.querySelector("#match-list");
const historyList = document.querySelector("#history-list");
const toast = document.querySelector("#match-toast");

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove("is-visible"), 2400);
}

function renderMatches() {
  const totalPlayed = matchPlayers.reduce((sum, player) => sum + player.played, 0);
  const lowQuota = matchPlayers.filter((player) => player.total - player.played <= 2).length;
  document.querySelector("#matches-finished").textContent = totalPlayed;
  document.querySelector("#low-quota-count").textContent = lowQuota;
  list.innerHTML = matchPlayers.map((player) => {
    const remaining = player.total - player.played;
    const progress = Math.round((player.played / player.total) * 100);
    return `<article class="match-row ${remaining <= 2 ? "is-low" : ""}" data-id="${player.id}">
      <div class="match-row-name"><span class="avatar" style="background:${player.id % 2 ? "#3b1945" : "#d82a1c"}">${player.initials}</span><strong>${player.name}</strong></div>
      <div class="match-row-meta"><span>Sumber</span><strong>${player.provider}</strong></div>
      <div><span class="sr-only">Progres ${player.name}</span><div class="match-progress"><span style="width:${progress}%"></span></div></div>
      <div class="match-row-count">${remaining}x${remaining === 0 ? '<span class="quota-badge is-empty">Jatah habis</span>' : remaining <= 2 ? '<span class="quota-badge">Jatah menipis</span>' : ''}</div>
      <div class="match-row-actions"><button class="match-row-action" type="button" data-action="finish">+1 match</button><button class="match-row-action" type="button" data-action="reduce">−1</button></div>
    </article>`;
  }).join("");
  list.querySelectorAll("[data-action=finish]").forEach((button) => button.addEventListener("click", () => finishMatch(Number(button.closest(".match-row").dataset.id))));
  list.querySelectorAll("[data-action=reduce]").forEach((button) => button.addEventListener("click", () => reduceMatch(Number(button.closest(".match-row").dataset.id))));
  historyList.innerHTML = history.map((item) => `<div class="history-item"><span class="history-time">${item.time}</span><span class="history-player">${item.player}</span><span class="history-match">${item.match}</span><span class="history-note">${item.note}</span></div>`).join("");
}

function finishMatch(playerId) {
  if (isActionLocked(playerId)) return;
  const player = matchPlayers.find((entry) => entry.id === playerId);
  if (!player || player.played >= player.total) {
    showToast("Jatah pemain ini sudah habis.");
    return;
  }
  lockAction(playerId);
  player.played += 1;
  history.unshift({ time: new Date().toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" }), player: player.name, match: `Match ${player.played}`, note: "Selesai · data tiruan" });
  showToast(`Match ${player.name} dicatat. Sisa ${player.total - player.played}x.`);
  renderMatches();
}

function reduceMatch(playerId) {
  if (isActionLocked(playerId)) return;
  const player = matchPlayers.find((entry) => entry.id === playerId);
  if (!player || player.played === 0) {
    showToast("Tidak ada match tercatat yang bisa dikoreksi.");
    return;
  }
  lockAction(playerId);
  player.played -= 1;
  const historyIndex = history.findIndex((item) => item.player === player.name);
  if (historyIndex >= 0) history.splice(historyIndex, 1);
  showToast(`Catatan match ${player.name} dikoreksi. Sisa ${player.total - player.played}x.`);
  renderMatches();
}

function lockAction(playerId) {
  actionLocks.set(playerId, Date.now());
  window.setTimeout(() => actionLocks.delete(playerId), 500);
}

function isActionLocked(playerId) {
  const lockedAt = actionLocks.get(playerId);
  return lockedAt !== undefined && Date.now() - lockedAt < 500;
}

document.querySelector("#finish-current-match").addEventListener("click", () => finishMatch(1));
document.querySelector("#undo-current-match").addEventListener("click", () => reduceMatch(1));
document.querySelector("#reset-matches").addEventListener("click", () => { matchPlayers = structuredClone(defaultMatches); history = [
  { time: "19:42", player: "Raka ML", match: "Match 9", note: "Selesai · ranked" },
  { time: "19:35", player: "Nisa Gaming", match: "Match 6", note: "Selesai · classic" },
  { time: "19:28", player: "Daffa ML", match: "Match 11", note: "Selesai · ranked" },
  { time: "19:20", player: "Bimo Stream", match: "Match 3", note: "Selesai · classic" }
]; renderMatches(); showToast("Data tiruan dikembalikan."); });
renderMatches();
