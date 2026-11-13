const initialPlayers = [
  { id: 1, name: "Nisa Gaming", initials: "NG", provider: "SociaBuzz", status: "ready", matches: 8, note: "Siap masuk setelah match ini" },
  { id: 2, name: "Bimo Stream", initials: "BS", provider: "Manual", status: "waiting", matches: 5, note: "Konfirmasi via chat" },
  { id: 3, name: "Daffa ML", initials: "DM", provider: "Saweria", status: "ready", matches: 14, note: "VIP — request role jungler" },
  { id: 4, name: "Lutfi Pro", initials: "LP", provider: "SociaBuzz", status: "ready", matches: 3, note: "—" },
  { id: 5, name: "Citra ML", initials: "CM", provider: "Saweria", status: "ready", matches: 7, note: "Mabar bareng viewer" },
  { id: 6, name: "Rendi GG", initials: "RG", provider: "Manual", status: "offline", matches: 4, note: "Belum online (±5 menit)" },
  { id: 7, name: "Alya Esports", initials: "AE", provider: "SociaBuzz", status: "ready", matches: 6, note: "—" },
  { id: 8, name: "Fajar ML", initials: "FM", provider: "Saweria", status: "waiting", matches: 2, note: "Baru donasi, sedang konfirmasi" },
  { id: 9, name: "Icha Gaming", initials: "IG", provider: "Manual", status: "ready", matches: 9, note: "Request mid lane" },
  { id: 10, name: "Yudha ML", initials: "YM", provider: "SociaBuzz", status: "ready", matches: 11, note: "—" },
  { id: 11, name: "Salsa Stream", initials: "SS", provider: "Saweria", status: "ready", matches: 4, note: "Siap, tunggu giliran" }
];

let players = [...initialPlayers];
let currentFilter = "all";
let nextId = 12;
let currentPlayerId = 0;

const queueBody = document.querySelector("#queue-body");
const emptyState = document.querySelector("#empty-state");
const playerCardList = document.querySelector("#player-card-list");
const toast = document.querySelector("#toast");
const modal = document.querySelector("#add-player-modal");
const addForm = document.querySelector("#add-player-form");
const modalError = document.querySelector("#modal-error");

const statusLabels = { ready: "Siap", waiting: "Menunggu", offline: "Offline", playing: "Sedang bermain", skipped: "Dilewati" };

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(showToast.timeout);
  showToast.timeout = window.setTimeout(() => toast.classList.remove("is-visible"), 2600);
}

function avatarColor(index) {
  const colors = ["#3b1945", "#d82a1c", "#1f6faf", "#684780", "#238b39", "#a91b16"];
  return colors[index % colors.length];
}

function renderQueue() {
  const filtered = currentFilter === "all" ? players : players.filter((player) => player.status === currentFilter);
  queueBody.innerHTML = filtered.map((player) => `
    <tr class="${player.status === "playing" ? "is-playing" : ""} ${player.status === "offline" ? "is-offline" : ""}" data-player-id="${player.id}">
      <td>${players.findIndex((entry) => entry.id === player.id) + 1}</td>
      <td><div class="player-cell"><span class="avatar" style="background:${avatarColor(player.id)}">${player.initials}</span><span class="player-name">${player.name}</span></div></td>
      <td><span class="provider-pill ${player.provider.toLowerCase()}">${player.provider}</span></td>
      <td><span class="status-pill ${player.status}"><span class="status-dot"></span>${statusLabels[player.status]}</span></td>
      <td>${player.matches}x</td>
      <td>${player.note || "—"}</td>
      <td><div class="row-actions"><button class="row-action" type="button" data-action="up" aria-label="Naikkan ${player.name}">↑</button><button class="row-action" type="button" data-action="down" aria-label="Turunkan ${player.name}">↓</button><button class="row-action" type="button" data-action="toggle-status" aria-label="Ubah status ${player.name}">↺</button></div></td>
    </tr>
  `).join("");

  emptyState.hidden = filtered.length !== 0;
  playerCardList.innerHTML = filtered.map((player) => `
    <article class="player-card ${player.status === "playing" ? "is-playing" : ""} ${player.status === "offline" ? "is-offline" : ""}" data-player-id="${player.id}">
      <div class="player-card-head">
        <div class="player-card-name"><span class="player-card-position">#${players.findIndex((entry) => entry.id === player.id) + 1}</span><span class="avatar" style="width:32px;height:32px;font-size:10px;background:${avatarColor(player.id)}">${player.initials}</span><strong>${player.name}</strong></div>
        <span class="status-pill ${player.status}"><span class="status-dot"></span>${statusLabels[player.status]}</span>
      </div>
      <div class="player-card-meta"><div><span>Sumber donasi</span><strong><span class="provider-pill ${player.provider.toLowerCase()}">${player.provider}</span></strong></div><div><span>Total mabar</span><strong>${player.matches}x</strong></div></div>
      <div class="player-card-foot"><span class="player-card-note">${player.note || "—"}</span><span class="player-card-actions"><button class="row-action" type="button" data-action="up" aria-label="Naikkan ${player.name}">↑</button><button class="row-action" type="button" data-action="down" aria-label="Turunkan ${player.name}">↓</button><button class="row-action" type="button" data-action="toggle-status" aria-label="Ubah status ${player.name}">↺</button></span></div>
    </article>
  `).join("");
  document.querySelector("#sidebar-count").textContent = `${players.length} pemain`;
  document.querySelector("#queue-heading-count").textContent = `(${players.length} pemain)`;
  document.querySelector("#total-mabar").textContent = players.reduce((total, player) => total + player.matches, 0);
  document.querySelectorAll("[data-filter]").forEach((button) => {
    const count = button.dataset.filter === "all" ? players.length : players.filter((player) => player.status === button.dataset.filter).length;
    const countLabel = button.querySelector("span");
    if (countLabel) countLabel.textContent = `(${count})`;
  });
  document.querySelectorAll(".row-action").forEach((button) => button.addEventListener("click", handleRowAction));
}

function handleRowAction(event) {
  const row = event.currentTarget.closest("tr");
  const playerId = Number(row.dataset.playerId);
  const playerIndex = players.findIndex((player) => player.id === playerId);
  const action = event.currentTarget.dataset.action;
  if (action === "toggle-status") {
    players[playerIndex].status = players[playerIndex].status === "offline" ? "ready" : "offline";
    showToast(`${players[playerIndex].name} ditandai ${statusLabels[players[playerIndex].status].toLowerCase()}.`);
  } else {
    const targetIndex = action === "up" ? playerIndex - 1 : playerIndex + 1;
    if (targetIndex >= 0 && targetIndex < players.length) {
      [players[playerIndex], players[targetIndex]] = [players[targetIndex], players[playerIndex]];
      showToast(`${players[playerIndex].name} dipindahkan.`);
    }
  }
  renderQueue();
}

function setCurrentPlayer(player, message) {
  currentPlayerId = player?.id ?? currentPlayerId;
  document.querySelector("#current-player-name").textContent = player.name;
  document.querySelector("#current-player-provider").textContent = player.provider;
  document.querySelector("#current-player-matches").textContent = `${player.matches}x`;
  document.querySelector(".current-player-avatar").textContent = player.initials;
  document.querySelector("#current-turn-label").textContent = `${player.name} sedang bermain`;
  if (message) showToast(message);
}

document.querySelectorAll("[data-filter]").forEach((button) => {
  button.addEventListener("click", () => {
    currentFilter = button.dataset.filter;
    document.querySelectorAll("[data-filter]").forEach((item) => item.classList.toggle("is-active", item === button));
    renderQueue();
  });
});

document.querySelector("#open-add-player").addEventListener("click", () => {
  modal.showModal();
  document.querySelector("#player-name").focus();
});

document.querySelector("#close-modal").addEventListener("click", () => modal.close("cancel"));

addForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const formData = new FormData(addForm);
  const name = String(formData.get("player-name")).trim();
  const matches = Number(formData.get("player-matches"));
  const nameInput = document.querySelector("#player-name");
  const matchesInput = document.querySelector("#player-matches");
  nameInput.classList.toggle("is-invalid", name.length < 2);
  matchesInput.classList.toggle("is-invalid", !Number.isInteger(matches) || matches < 1 || matches > 99);
  if (name.length < 2 || !Number.isInteger(matches) || matches < 1 || matches > 99) {
    modalError.textContent = name.length < 2 ? "Masukkan nama pemain minimal 2 karakter." : "Total mabar harus berupa angka 1–99.";
    modalError.hidden = false;
    return;
  }
  modalError.hidden = true;
  const initials = name.split(/\s+/).map((part) => part[0]).join("").slice(0, 2).toUpperCase();
  players.push({
    id: nextId++,
    name,
    initials,
    provider: String(formData.get("player-provider")),
    status: "waiting",
    matches,
    note: String(formData.get("player-note")).trim() || "Konfirmasi via chat"
  });
  currentFilter = "all";
  document.querySelectorAll("[data-filter]").forEach((item) => item.classList.toggle("is-active", item.dataset.filter === "all"));
  renderQueue();
  modal.close();
  addForm.reset();
  showToast(`${name} masuk ke posisi terakhir antrean.`);
});

document.querySelector("#finish-match").addEventListener("click", () => {
  const current = players.find((player) => player.name === document.querySelector("#current-player-name").textContent);
  if (!current) return;
  current.matches = Math.max(0, current.matches - 1);
  const next = players[0];
  setCurrentPlayer(next, `Match selesai. Jatah ${current.name} tersisa ${current.matches}x.`);
  renderQueue();
});

document.querySelector("#skip-current").addEventListener("click", () => {
  const first = players.shift();
  if (!first) return;
  first.status = "skipped";
  players.push(first);
  setCurrentPlayer(players[0], `${first.name} dilewati sementara.`);
  renderQueue();
});

document.querySelector("#move-current").addEventListener("click", () => {
  if (players.length < 2) return;
  const first = players.shift();
  players.splice(2, 0, first);
  setCurrentPlayer(players[0], `${first.name} dipindahkan ke posisi 3.`);
  renderQueue();
});

document.querySelector("#remove-current").addEventListener("click", () => {
  const current = players[0];
  if (!current) return;
  const confirmed = window.confirm(`Hapus ${current.name} dari antrean?`);
  if (!confirmed) {
    showToast("Pemain tetap berada di antrean.");
    return;
  }
  const first = players.shift();
  if (players[0]) {
    setCurrentPlayer(players[0], `${first.name} dihapus dari antrean.`);
  } else {
    document.querySelector("#current-player-name").textContent = "Antrean kosong";
    document.querySelector("#current-turn-label").textContent = "Belum ada pemain berikutnya";
    showToast(`${first.name} dihapus dari antrean.`);
  }
  renderQueue();
});

document.querySelector("#end-session").addEventListener("click", () => {
  showToast("Sesi masih berupa data tiruan. Tombol ini siap dihubungkan ke backend.");
});

renderQueue();
