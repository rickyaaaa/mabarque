// Halaman daftar: POST /api/auth/register { name, email, handle, password, password_confirmation, consent }.
// Validasi ringan di browser untuk umpan balik cepat; aturan final (email unik, slug) dari server.
const signupForm = document.querySelector("#signup-form");
const signupError = document.querySelector("#signup-error");
const successModal = document.querySelector("#signup-success");
const signupButton = signupForm.querySelector('button[type="submit"]');
const fieldFor = {
  name: "#signup-name",
  email: "#signup-email",
  handle: "#signup-handle",
  password: "#signup-password",
  password_confirmation: "#signup-password-confirm",
};

function showSignupError(message, selector) {
  signupError.textContent = message;
  signupError.hidden = false;
  const field = selector ? document.querySelector(selector) : null;
  if (field) field.focus();
}

signupForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const payload = {
    name: document.querySelector(fieldFor.name).value.trim(),
    email: document.querySelector(fieldFor.email).value.trim(),
    handle: document.querySelector(fieldFor.handle).value.trim(),
    password: document.querySelector(fieldFor.password).value,
    password_confirmation: document.querySelector(fieldFor.password_confirmation).value,
    consent: document.querySelector("#signup-consent").checked,
  };

  if (payload.password !== payload.password_confirmation) return showSignupError("Konfirmasi password tidak sama.", fieldFor.password_confirmation);
  if (!payload.consent) return showSignupError("Setujui syarat private alpha untuk melanjutkan.");

  signupError.hidden = true;
  const label = signupButton.textContent;
  signupButton.disabled = true;
  signupButton.textContent = "Membuat akun…";

  const result = await MQApi.post("/api/auth/register", payload);

  signupButton.disabled = false;
  signupButton.textContent = label;

  if (!result.ok) {
    const firstField = Object.keys(result.errors)[0];
    showSignupError(MQApi.firstError(result), fieldFor[firstField]);
    return;
  }

  document.querySelector("#success-message").textContent = result.data.beta_status === "active"
    ? `Akun @${result.data.slug} siap dipakai.`
    : `Akun @${result.data.slug} sudah dibuat. Akses private alpha aktif setelah disetujui admin — kami kabari lewat email.`;
  successModal.showModal();
});

document.querySelector("#go-dashboard").addEventListener("click", () => { window.location.href = "/account"; });
