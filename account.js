const accountToast = document.querySelector("#account-toast");
const profileModal = document.querySelector("#profile-modal");
function showAccountToast(message) { accountToast.textContent = message; accountToast.classList.add("is-visible"); window.clearTimeout(showAccountToast.timeout); showAccountToast.timeout = window.setTimeout(() => accountToast.classList.remove("is-visible"), 2500); }
document.querySelector("#edit-profile").addEventListener("click", () => { window.location.href = "profile-settings.html"; });
document.querySelector("#close-profile").addEventListener("click", () => profileModal.close("cancel"));
document.querySelector("#profile-form").addEventListener("submit", (event) => { event.preventDefault(); document.querySelector(".profile-identity h2").textContent = document.querySelector("#profile-name").value.trim(); document.querySelector(".profile-identity p").innerHTML = `${document.querySelector("#profile-handle").value.trim()} <span>•</span> Mobile Legends streamer`; profileModal.close(); showAccountToast("Profil streamer disimpan di data tiruan."); });
document.querySelector("#view-plan").addEventListener("click", () => showAccountToast("Detail akses private alpha siap dihubungkan ke billing."));
document.querySelector("#logout-button").addEventListener("click", () => { window.location.href = "login.html"; });
