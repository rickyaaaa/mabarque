const initialPackages = [
  { id: 1, name: "Starter", price: 15000, matches: 1, active: true, ribbon: "Paling ringan", features: ["1 slot mabar", "Masuk antrean reguler"] },
  { id: 2, name: "Squad", price: 50000, matches: 5, active: true, ribbon: "Paling populer", features: ["5 slot mabar", "Prioritas antrean", "Request role"] },
  { id: 3, name: "Legend", price: 100000, matches: 12, active: true, ribbon: "VIP prioritas", features: ["12 slot mabar", "Prioritas paling depan", "Request hero & role"] },
  { id: 4, name: "Custom", price: 250000, matches: 30, active: false, ribbon: "Nonaktif", features: ["30 slot mabar", "Sesi private", "Atur jadwal khusus"] }
];
let packages = structuredClone(initialPackages);
const grid = document.querySelector("#package-grid");
const toast = document.querySelector("#package-toast");
const publicPreview = document.querySelector("#public-package-preview");
const emptyState = document.querySelector("#package-empty");
const modal = document.querySelector("#package-modal");
const form = document.querySelector("#package-form");
let editingId = null;
function rupiah(value){return new Intl.NumberFormat("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0}).format(value);}
function showToast(message){toast.textContent=message;toast.classList.add("is-visible");window.clearTimeout(showToast.timer);showToast.timer=window.setTimeout(()=>toast.classList.remove("is-visible"),2400);}
function renderPackages(){
  document.querySelector("#active-count").textContent=packages.filter((item)=>item.active).length;
  const activePackages = packages.filter((item)=>item.active);
  publicPreview.innerHTML = activePackages.map((item)=>`<div class="public-package-card"><strong>${item.name}</strong><span>${rupiah(item.price)} · ${item.matches} match</span></div>`).join("");
  emptyState.hidden = activePackages.length > 0;
  grid.innerHTML=packages.map((item)=>`<article class="package-card ${item.active?"":"is-inactive"}"><span class="package-ribbon">${item.ribbon}</span><h2>${item.name}</h2><div class="package-price">${rupiah(item.price)}</div><div class="package-matches">${item.matches} match per donasi</div><ul class="package-features">${item.features.map((feature)=>`<li>${feature}</li>`).join("")}</ul><div class="package-actions"><label class="package-toggle"><input type="checkbox" data-toggle="${item.id}" ${item.active?"checked":""} /><span class="package-status-label">${item.active?"Aktif":"Nonaktif"}</span></label><button type="button" data-edit="${item.id}">Edit paket</button></div></article>`).join("");
  grid.querySelectorAll("[data-toggle]").forEach((input)=>input.addEventListener("change",()=>{const item=packages.find((entry)=>entry.id===Number(input.dataset.toggle));item.active=input.checked;renderPackages();showToast(`${item.name} ${item.active?"diaktifkan":"dinonaktifkan"}.`);}));
  grid.querySelectorAll("[data-edit]").forEach((button)=>button.addEventListener("click",()=>openPackageForm(Number(button.dataset.edit))));
}
function openPackageForm(packageId = null){
  editingId = packageId;
  const item = packageId ? packages.find((entry)=>entry.id === packageId) : null;
  document.querySelector("#package-modal-title").textContent = item ? "Ubah paket VIP" : "Buat paket VIP";
  document.querySelector("#package-name").value = item?.name ?? "";
  document.querySelector("#package-price").value = item?.price ?? 50000;
  document.querySelector("#package-matches").value = item?.matches ?? 5;
  document.querySelector("#package-error").hidden = true;
  modal.showModal();
  document.querySelector("#package-name").focus();
}
document.querySelector("#add-package").addEventListener("click",()=>openPackageForm());
document.querySelector("#empty-add-package").addEventListener("click",()=>openPackageForm());
document.querySelector("#close-package-modal").addEventListener("click",()=>modal.close());
document.querySelector("#cancel-package-modal").addEventListener("click",()=>modal.close());
form.addEventListener("submit",(event)=>{
  event.preventDefault();
  const name = document.querySelector("#package-name").value.trim();
  const price = Number(document.querySelector("#package-price").value);
  const matches = Number(document.querySelector("#package-matches").value);
  const error = document.querySelector("#package-error");
  if(name.length < 2 || price < 1000 || matches < 1 || matches > 99){ error.textContent = name.length < 2 ? "Nama paket minimal 2 karakter." : "Harga minimal Rp1.000 dan jatah match 1–99."; error.hidden = false; return; }
  const item = editingId ? packages.find((entry)=>entry.id === editingId) : { id: Math.max(...packages.map((entry)=>entry.id)) + 1, active: true, ribbon: "Paket baru", features: ["Atur jatah match sendiri"] };
  item.name = name; item.price = price; item.matches = matches;
  if(!editingId) packages.push(item);
  renderPackages(); modal.close(); showToast(`${name} berhasil disimpan.`);
});
renderPackages();
