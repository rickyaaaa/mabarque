// Form daftar (data tiruan). Kontrak payload sama dengan POST /api/auth/register:
// { name, email, handle, password, password_confirmation, consent }.
const signupForm = document.querySelector("#signup-form");
const signupError = document.querySelector("#signup-error");
const successModal = document.querySelector("#signup-success");
const TAKEN_EMAILS = ["andra@mabarqueue.id", "demo@mabarqueue.id"]; // simulasi email yang sudah terdaftar

function showSignupError(message, field) {
  signupError.textContent = message;
  signupError.hidden = false;
  if (field) field.focus();
}

signupForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const nameField = document.querySelector("#signup-name");
  const emailField = document.querySelector("#signup-email");
  const handleField = document.querySelector("#signup-handle");
  const passwordField = document.querySelector("#signup-password");
  const confirmField = document.querySelector("#signup-password-confirm");
  const name = nameField.value.trim();
  const email = emailField.value.trim().toLowerCase();
  const handle = handleField.value.trim().replace(/^@/, "").toLowerCase().replace(/_/g, "-");

  if (name.length < 3) return showSignupError("Nama streamer minimal 3 karakter.", nameField);
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showSignupError("Format email tidak valid.", emailField);
  if (TAKEN_EMAILS.includes(email)) return showSignupError("Email ini sudah terdaftar. Silakan masuk atau gunakan lupa password.", emailField);
  if (!/^(?=.{3,24}$)[a-z0-9]+(?:-[a-z0-9]+)*$/.test(handle)) return showSignupError("Username 3–24 karakter: huruf kecil, angka, atau tanda hubung di tengah.", handleField);
  if (passwordField.value.length < 8 || !/[a-zA-Z]/.test(passwordField.value) || !/\d/.test(passwordField.value)) return showSignupError("Password minimal 8 karakter dan berisi huruf serta angka.", passwordField);
  if (passwordField.value !== confirmField.value) return showSignupError("Konfirmasi password tidak sama.", confirmField);
  if (!document.querySelector("#signup-consent").checked) return showSignupError("Setujui syarat private alpha untuk melanjutkan.");

  signupError.hidden = true;
  document.querySelector("#success-message").textContent = `Akun @${handle} dibuat. Akses private alpha aktif setelah disetujui admin.`;
  successModal.showModal();
});

document.querySelector("#go-dashboard").addEventListener("click", () => { window.location.href = "account.html"; });
