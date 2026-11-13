const loginForm = document.querySelector("#login-form");
const loginError = document.querySelector("#login-error");
const loginToast = document.querySelector("#login-toast");
function showLoginToast(message) { loginToast.textContent = message; loginToast.classList.add("is-visible"); window.clearTimeout(showLoginToast.timeout); showLoginToast.timeout = window.setTimeout(() => loginToast.classList.remove("is-visible"), 2500); }
document.querySelector("#toggle-password").addEventListener("click", (event) => { const field = document.querySelector("#login-password"); const visible = field.type === "text"; field.type = visible ? "password" : "text"; event.currentTarget.textContent = visible ? "Tampilkan" : "Sembunyikan"; });
document.querySelector("#forgot-password").addEventListener("click", () => { window.location.href = "forgot-password.html"; });
loginForm.addEventListener("submit", (event) => { event.preventDefault(); const email = document.querySelector("#login-email").value.trim(); const password = document.querySelector("#login-password").value; if (!email || password.length < 6) { loginError.textContent = "Masukkan email dan password minimal 6 karakter."; loginError.hidden = false; return; } loginError.hidden = true; window.location.href = "account.html"; });
