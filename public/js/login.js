// Halaman masuk: POST /api/auth/login lalu arahkan sesuai status akses beta.
const loginForm = document.querySelector("#login-form");
const loginError = document.querySelector("#login-error");
const loginToast = document.querySelector("#login-toast");
const loginButton = loginForm.querySelector('button[type="submit"]');

function showLoginToast(message) {
  loginToast.textContent = message;
  loginToast.classList.add("is-visible");
  window.clearTimeout(showLoginToast.timeout);
  showLoginToast.timeout = window.setTimeout(() => loginToast.classList.remove("is-visible"), 2500);
}

function showLoginError(message) {
  loginError.textContent = message;
  loginError.hidden = !message;
}

document.querySelector("#toggle-password").addEventListener("click", (event) => {
  const field = document.querySelector("#login-password");
  const visible = field.type === "text";
  field.type = visible ? "password" : "text";
  event.currentTarget.textContent = visible ? "Tampilkan" : "Sembunyikan";
});

document.querySelector("#forgot-password").addEventListener("click", () => { window.location.href = "/forgot-password"; });

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const email = document.querySelector("#login-email").value.trim();
  const password = document.querySelector("#login-password").value;

  if (!email || !password) {
    showLoginError("Masukkan email dan password.");
    return;
  }

  showLoginError("");
  const label = loginButton.textContent;
  loginButton.disabled = true;
  loginButton.textContent = "Memeriksa…";

  const result = await MQApi.post("/api/auth/login", {
    email,
    password,
    remember: document.querySelector("#remember-login").checked,
  });

  if (result.ok) {
    showLoginToast("Berhasil masuk. Membuka workspace…");
    window.location.href = result.raw.redirect || "/dashboard";
    return;
  }

  loginButton.disabled = false;
  loginButton.textContent = label;
  showLoginError(MQApi.firstError(result, "Email atau password salah."));
});

// Pesan dari halaman lain (mis. setelah reset password atau keluar).
const notice = new URLSearchParams(window.location.search).get("notice");
if (notice === "reset") showLoginToast("Password diganti. Silakan masuk dengan password baru.");
if (notice === "logout") showLoginToast("Kamu sudah keluar.");
