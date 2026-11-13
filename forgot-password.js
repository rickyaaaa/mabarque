// Alur lupa password dengan data tiruan: minta link → cek email → password baru → selesai.
// Belum ada backend; akun terdaftar disimulasikan lewat MOCK_ACCOUNTS.
const MOCK_ACCOUNTS = {
  "andra@mabarqueue.id": "Andra Rizky",
  "demo@mabarqueue.id": "Demo Streamer",
};
const RESEND_SECONDS = 30;

const steps = document.querySelectorAll("[data-step]");
const indicators = document.querySelectorAll("[data-step-indicator]");
const toast = document.querySelector("#reset-toast");
const state = { email: "", countdownTimer: null };

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(showToast.timeout);
  showToast.timeout = window.setTimeout(() => toast.classList.remove("is-visible"), 2500);
}

function goToStep(name) {
  steps.forEach((step) => { step.hidden = step.dataset.step !== name; });
  const order = ["request", "sent", "reset", "done"];
  const current = order.indexOf(name);
  indicators.forEach((item) => {
    const index = order.indexOf(item.dataset.stepIndicator);
    item.classList.toggle("is-current", index === current);
    item.classList.toggle("is-done", index < current);
  });
  const heading = document.querySelector(`[data-step="${name}"] h2`);
  if (heading) heading.focus();
}

function showError(element, message) {
  element.textContent = message;
  element.hidden = !message;
}

function withLoading(button, label, action) {
  const original = button.textContent;
  button.disabled = true;
  button.textContent = label;
  window.setTimeout(() => {
    button.disabled = false;
    button.textContent = original;
    action();
  }, 700);
}

// Langkah 1 — minta link reset
const requestForm = document.querySelector("#request-form");
const emailInput = document.querySelector("#reset-email");
const requestError = document.querySelector("#request-error");

document.querySelectorAll("[data-fill-email]").forEach((button) => {
  button.addEventListener("click", () => { emailInput.value = button.dataset.fillEmail; emailInput.focus(); });
});

requestForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const email = emailInput.value.trim().toLowerCase();
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  emailInput.classList.toggle("is-invalid", !valid);
  if (!valid) {
    showError(requestError, "Masukkan alamat email yang valid, contoh kamu@email.com.");
    emailInput.focus();
    return;
  }
  showError(requestError, "");
  withLoading(requestForm.querySelector(".reset-submit"), "Mengirim…", () => {
    state.email = email;
    document.querySelector("#sent-email").textContent = email;
    // Pesan sama untuk email terdaftar/tidak, supaya tidak membocorkan akun mana yang ada.
    const name = MOCK_ACCOUNTS[email];
    document.querySelector("#mock-inbox-name").textContent = name || "streamer";
    document.querySelector(".mock-inbox").hidden = !name;
    startResendCountdown();
    goToStep("sent");
    if (!name) showToast("Email ini tidak ada di data tiruan — inbox kosong.");
  });
});

// Langkah 2 — cek email
const resendButton = document.querySelector("#resend-link");

function startResendCountdown() {
  let remaining = RESEND_SECONDS;
  resendButton.disabled = true;
  resendButton.innerHTML = `Kirim ulang (<span id="resend-countdown">${remaining}</span>)`;
  window.clearInterval(state.countdownTimer);
  state.countdownTimer = window.setInterval(() => {
    remaining -= 1;
    const counter = document.querySelector("#resend-countdown");
    if (counter) counter.textContent = remaining;
    if (remaining <= 0) {
      window.clearInterval(state.countdownTimer);
      resendButton.disabled = false;
      resendButton.textContent = "Kirim ulang link";
    }
  }, 1000);
}

resendButton.addEventListener("click", () => {
  document.querySelector("#mock-inbox-time").textContent = "baru saja (dikirim ulang)";
  showToast(`Link reset tiruan dikirim ulang ke ${state.email}.`);
  startResendCountdown();
});

document.querySelector("#change-email").addEventListener("click", () => {
  window.clearInterval(state.countdownTimer);
  goToStep("request");
  emailInput.select();
});

document.querySelector("#open-reset-link").addEventListener("click", () => {
  window.clearInterval(state.countdownTimer);
  document.querySelector("#reset-account").textContent = state.email;
  goToStep("reset");
  document.querySelector("#new-password").focus();
});

// Langkah 3 — password baru
const resetForm = document.querySelector("#reset-form");
const newPassword = document.querySelector("#new-password");
const confirmPassword = document.querySelector("#confirm-password");
const resetError = document.querySelector("#reset-error");
const strengthBars = document.querySelectorAll(".strength span");
const strengthLabel = document.querySelector("#strength-label");
const STRENGTH_TEXT = ["belum diisi", "lemah", "cukup", "kuat", "sangat kuat"];

document.querySelectorAll("[data-toggle-password]").forEach((button) => {
  button.addEventListener("click", () => {
    const field = document.querySelector(`#${button.dataset.togglePassword}`);
    const visible = field.type === "text";
    field.type = visible ? "password" : "text";
    button.textContent = visible ? "Tampilkan" : "Sembunyikan";
  });
});

function checkRules() {
  const value = newPassword.value;
  const rules = {
    length: value.length >= 8,
    mixed: /[a-zA-Z]/.test(value) && /\d/.test(value),
    match: value.length > 0 && value === confirmPassword.value,
  };
  Object.entries(rules).forEach(([rule, passed]) => {
    const item = document.querySelector(`[data-rule="${rule}"]`);
    item.classList.toggle("is-met", passed);
    item.querySelector("i").textContent = passed ? "✓" : "✕";
  });

  let score = 0;
  if (value.length >= 8) score += 1;
  if (value.length >= 12) score += 1;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
  if (/\d/.test(value) && /[^a-zA-Z0-9]/.test(value)) score += 1;
  if (value.length > 0 && score === 0) score = 1;
  strengthBars.forEach((bar, index) => bar.classList.toggle("is-on", index < score));
  strengthBars[0].parentElement.dataset.score = score;
  strengthLabel.textContent = `Kekuatan: ${STRENGTH_TEXT[score]}`;
  return rules;
}

newPassword.addEventListener("input", checkRules);
confirmPassword.addEventListener("input", checkRules);

resetForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const rules = checkRules();
  if (!rules.length || !rules.mixed) {
    newPassword.classList.add("is-invalid");
    showError(resetError, "Password minimal 8 karakter dan berisi huruf serta angka.");
    newPassword.focus();
    return;
  }
  newPassword.classList.remove("is-invalid");
  if (!rules.match) {
    confirmPassword.classList.add("is-invalid");
    showError(resetError, "Kedua password belum sama.");
    confirmPassword.focus();
    return;
  }
  confirmPassword.classList.remove("is-invalid");
  showError(resetError, "");
  withLoading(resetForm.querySelector(".reset-submit"), "Menyimpan…", () => goToStep("done"));
});

// Dukung link langsung dari email tiruan: forgot-password.html?token=demo&email=...
const params = new URLSearchParams(window.location.search);
if (params.get("token")) {
  state.email = (params.get("email") || "andra@mabarqueue.id").toLowerCase();
  document.querySelector("#reset-account").textContent = state.email;
  goToStep("reset");
}
