// Halaman akun: data dirender server; di sini hanya aksi Keluar (POST /api/auth/logout).
const accountToast = document.querySelector("#account-toast");
const logoutButton = document.querySelector("#logout-button");

function showAccountToast(message) {
  accountToast.textContent = message;
  accountToast.classList.add("is-visible");
  window.clearTimeout(showAccountToast.timeout);
  showAccountToast.timeout = window.setTimeout(() => accountToast.classList.remove("is-visible"), 2500);
}

logoutButton.addEventListener("click", async () => {
  logoutButton.disabled = true;
  const result = await MQApi.post("/api/auth/logout");

  if (result.ok || result.status === 401) {
    window.location.href = "/login?notice=logout";
    return;
  }

  logoutButton.disabled = false;
  showAccountToast(MQApi.firstError(result, "Gagal keluar. Coba lagi."));
});
