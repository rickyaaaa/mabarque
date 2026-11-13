const donationSeed = [
  { id: 1, time: "20:16", donor: "Rizal Keren", initials: "RK", provider: "Saweria", amount: 50000, packageName: "VIP 5 Match", status: "new", note: "Request mid lane" },
  { id: 2, time: "20:12", donor: "Miaw Gaming", initials: "MG", provider: "SociaBuzz", amount: 100000, packageName: "VIP 10 Match", status: "new", note: "Siap setelah match ini" },
  { id: 3, time: "20:04", donor: "Fikri MLBB", initials: "FM", provider: "Trakteer", amount: 25000, packageName: "VIP 2 Match", status: "review", note: "Nominal cocok, nama belum jelas" },
  { id: 4, time: "19:58", donor: "Dinda Stream", initials: "DS", provider: "Saweria", amount: 50000, packageName: "VIP 5 Match", status: "queued", note: "Sudah masuk posisi #8" },
  { id: 5, time: "19:42", donor: "Akbar Pro", initials: "AP", provider: "SociaBuzz", amount: 100000, packageName: "VIP 10 Match", status: "queued", note: "Sudah masuk posisi #6" },
  { id: 6, time: "19:31", donor: "Lalaaa", initials: "LA", provider: "Trakteer", amount: 25000, packageName: "VIP 2 Match", status: "ignored", note: "Donasi dikembalikan" }
];

let donations = donationSeed.map((donation) => ({ ...donation }));
let statusFilter = "all";
let providerFilter = "all";
const readNotificationIds = new Set();
const connectionHistory = [
  { provider: "Saweria", status: "Berhasil", time: "2 mnt lalu" },
  { provider: "SociaBuzz", status: "Berhasil", time: "5 mnt lalu" },
  { provider: "Trakteer", status: "Berhasil", time: "8 mnt lalu" }
];

const donationBody = document.querySelector("#donation-body");
const donationCardList = document.querySelector("#donation-card-list");
const emptyState = document.querySelector("#donation-empty");
const toast = document.querySelector("#donation-toast");
const connectionModal = document.querySelector("#connection-modal");
const connectionForm = document.querySelector("#connection-form");
const connectionError = document.querySelector("#connection-error");
const connectionTest = document.querySelector("#connection-test");
const connectionTestLabel = document.querySelector("#connection-test-label");

const statusLabels = { new: "Baru", queued: "Masuk antrean", review: "Perlu cek", ignored: "Diabaikan" };

function formatRupiah(amount) {
  return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(amount).replace("Rp", "Rp ");
}

function avatarColor(id) {
  const colors = ["#3b1945", "#d82a1c", "#1f6faf", "#684780", "#238b39", "#a91b16"];
  return colors[id % colors.length];
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(showToast.timeout);
  showToast.timeout = window.setTimeout(() => toast.classList.remove("is-visible"), 2600);
}

function filteredDonations() {
  return donations.filter((donation) => (statusFilter === "all" || donation.status === statusFilter) && (providerFilter === "all" || donation.provider === providerFilter));
}

function actionMarkup(donation) {
  if (donation.status === "new") return `<button class="button button-primary" data-action="queue" data-id="${donation.id}" type="button">Masuk antrean</button><button class="button button-light" data-action="review" data-id="${donation.id}" type="button">Cek</button>`;
  if (donation.status === "review") return `<button class="button button-primary" data-action="queue" data-id="${donation.id}" type="button">Validasi &amp; masuk</button>`;
  return `<button class="button button-light" data-action="details" data-id="${donation.id}" type="button">Lihat detail</button>`;
}

function renderDonationCard(donation) {
  return `<article class="donation-card"><div class="donation-card-head"><div class="donor-cell"><span class="avatar" style="background:${avatarColor(donation.id)}">${donation.initials}</span><div><strong>${donation.donor}</strong><small>${donation.time} WIB</small></div></div><span class="donation-status ${donation.status}">${statusLabels[donation.status]}</span></div><div class="donation-card-meta"><div><span>Nominal</span><strong>${formatRupiah(donation.amount)}</strong></div><div><span>Paket</span><strong>${donation.packageName}</strong></div></div><div class="donation-card-foot"><small>${donation.note}</small><span class="donation-actions">${actionMarkup(donation)}</span></div></article>`;
}

function renderDonations() {
  const visible = filteredDonations();
  donationBody.innerHTML = visible.map((donation) => `<tr><td>${donation.time} WIB</td><td><div class="donor-cell"><span class="avatar" style="background:${avatarColor(donation.id)}">${donation.initials}</span><div><strong>${donation.donor}</strong><small>${donation.note}</small></div></div></td><td><span class="provider-pill ${donation.provider.toLowerCase()}">${donation.provider}</span></td><td class="donation-amount">${formatRupiah(donation.amount)}</td><td>${donation.packageName}</td><td><span class="donation-status ${donation.status}">${statusLabels[donation.status]}</span></td><td><span class="donation-actions">${actionMarkup(donation)}</span></td></tr>`).join("");
  donationCardList.innerHTML = visible.map(renderDonationCard).join("");
  emptyState.hidden = visible.length > 0;
  document.querySelectorAll("[data-status-filter]").forEach((button) => {
    const filter = button.dataset.statusFilter;
    const count = filter === "all" ? donations.length : donations.filter((donation) => donation.status === filter).length;
    button.classList.toggle("is-active", filter === statusFilter);
    const countLabel = button.querySelector("span");
    if (countLabel) countLabel.textContent = `(${count})`;
  });
  document.querySelector("#ready-donations").textContent = donations.filter((donation) => donation.status === "new").length + donations.filter((donation) => donation.status === "review").length;
  document.querySelector("#donation-total").textContent = `Rp${Math.round(donations.filter((donation) => donation.status !== "ignored").reduce((total, donation) => total + donation.amount, 0) / 1000)}K`;
  document.querySelectorAll("[data-action]").forEach((button) => button.addEventListener("click", handleAction));
}

function renderActivity() {
  const items = donations.slice(0, 5);
  const unreadCount = items.filter((donation) => (donation.status === "new" || donation.status === "review") && !readNotificationIds.has(donation.id)).length;
  document.querySelector("#notification-count").textContent = `${unreadCount} notifikasi baru`;
  document.querySelector("#activity-list").innerHTML = items.map((donation) => {
    const iconClass = donation.status === "queued" ? "is-green" : donation.status === "review" ? "is-orange" : donation.status === "ignored" ? "is-red" : "is-orange";
    const label = donation.status === "queued" ? `${donation.donor} masuk antrean` : donation.status === "ignored" ? `${donation.donor} ditandai diabaikan` : `${donation.donor} mengirim donasi`;
    const unread = (donation.status === "new" || donation.status === "review") && !readNotificationIds.has(donation.id);
    return `<div class="activity-item ${unread ? "is-unread" : ""}"><span class="activity-icon ${iconClass}" aria-hidden="true">${donation.status === "queued" ? "✓" : donation.status === "ignored" ? "×" : "↗"}</span><div><strong>${label}</strong><span>${donation.provider} · ${formatRupiah(donation.amount)}</span></div><span class="activity-time">${donation.time}</span><button class="notification-read" data-notification-id="${donation.id}" type="button" aria-label="Tandai notifikasi ${donation.donor} sudah dibaca">${unread ? "•" : "✓"}</button></div>`;
  }).join("");
  document.querySelectorAll("[data-notification-id]").forEach((button) => button.addEventListener("click", () => {
    readNotificationIds.add(Number(button.dataset.notificationId));
    renderActivity();
  }));
}

function renderQueuePreview() {
  const queueItems = donations.filter((donation) => donation.status === "queued" || donation.status === "new" || donation.status === "review").slice(0, 6);
  document.querySelector("#queue-preview").innerHTML = queueItems.map((donation, index) => {
    const waiting = donation.status !== "queued";
    return `<div class="queue-preview-card"><span class="queue-position">${index + 8}</span><div><strong>${donation.donor}</strong><span>${donation.packageName} · ${donation.provider}</span></div><span class="queue-marker ${waiting ? "is-waiting" : ""}">${waiting ? "Menunggu validasi" : "Otomatis masuk"}</span></div>`;
  }).join("");
}

function renderConnectionHistory() {
  document.querySelector("#connection-history-list").innerHTML = connectionHistory.slice(0, 3).map((entry) => `<div class="connection-history-item"><strong>${entry.provider}</strong><span class="connection-history-status">${entry.status}</span><span>${entry.time}</span></div>`).join("");
}

function handleAction(event) {
  const donation = donations.find((entry) => entry.id === Number(event.currentTarget.dataset.id));
  if (!donation) return;
  const action = event.currentTarget.dataset.action;
  if (action === "queue") {
    donation.status = "queued";
    showToast(`${donation.donor} dimasukkan ke antrean VIP.`);
  } else if (action === "review") {
    donation.status = "review";
    showToast(`${donation.donor} ditandai untuk dicek.`);
  } else {
    showToast(`${donation.donor} · ${donation.note}.`);
  }
  renderDonations();
  renderActivity();
  renderQueuePreview();
}

document.querySelectorAll("[data-status-filter]").forEach((button) => button.addEventListener("click", () => {
  statusFilter = button.dataset.statusFilter;
  renderDonations();
}));

document.querySelector("#provider-filter").addEventListener("change", (event) => {
  providerFilter = event.target.value;
  renderDonations();
});

document.querySelector("#refresh-donations").addEventListener("click", (event) => {
  const button = event.currentTarget;
  button.disabled = true;
  button.textContent = "↻ Mengecek...";
  window.setTimeout(() => {
    button.disabled = false;
    button.textContent = "↻ Refresh";
    document.querySelector("#last-synced").textContent = "Terakhir dicek barusan";
    showToast("Webhook dicek. Tidak ada donasi baru.");
  }, 650);
});

document.querySelector("#mark-all-read").addEventListener("click", () => {
  donations.forEach((donation) => readNotificationIds.add(donation.id));
  renderActivity();
  showToast("Semua notifikasi ditandai sudah dibaca.");
});

document.querySelector("#simulate-donation").addEventListener("click", () => {
  const nextId = donations.reduce((largest, donation) => Math.max(largest, donation.id), 0) + 1;
  donations.unshift({ id: nextId, time: "20:21", donor: "Viewer Baru", initials: "VB", provider: "Saweria", amount: 50000, packageName: "VIP 5 Match", status: "new", note: "Donasi baru dari webhook tiruan" });
  statusFilter = "all";
  providerFilter = "all";
  document.querySelector("#provider-filter").value = "all";
  renderDonations();
  renderActivity();
  renderQueuePreview();
  showToast("Donasi tiruan baru masuk dan menunggu validasi.");
});

document.querySelector("#open-connection").addEventListener("click", () => {
  connectionForm.reset();
  connectionError.hidden = true;
  connectionTest.hidden = true;
  connectionModal.showModal();
  document.querySelector("#connection-provider").focus();
});

document.querySelector("#close-connection").addEventListener("click", () => connectionModal.close("cancel"));

document.querySelector("#test-connection").addEventListener("click", () => {
  const url = document.querySelector("#connection-url").value.trim();
  const secret = document.querySelector("#connection-secret").value.trim();
  connectionError.hidden = true;
  if (!url || !secret) {
    connectionError.textContent = "Isi URL webhook dan signing secret sebelum mengetes koneksi.";
    connectionError.hidden = false;
    return;
  }
  connectionTest.hidden = false;
  connectionTestLabel.textContent = "Mengecek endpoint tiruan...";
  window.setTimeout(() => {
    connectionTestLabel.textContent = "Koneksi berhasil. Webhook siap diterima.";
    connectionHistory.unshift({ provider: document.querySelector("#connection-provider").value, status: "Berhasil", time: "baru saja" });
    renderConnectionHistory();
  }, 550);
});

connectionForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const provider = document.querySelector("#connection-provider").value;
  const url = document.querySelector("#connection-url").value.trim();
  const secret = document.querySelector("#connection-secret").value.trim();
  if (!url || !secret) {
    connectionError.textContent = "Lengkapi URL webhook dan signing secret.";
    connectionError.hidden = false;
    return;
  }
  connectionModal.close();
  showToast(`${provider} tersimpan sebagai koneksi aktif tiruan.`);
  connectionHistory.unshift({ provider, status: "Tersimpan", time: "baru saja" });
  renderConnectionHistory();
});

renderDonations();
renderActivity();
renderQueuePreview();
renderConnectionHistory();
