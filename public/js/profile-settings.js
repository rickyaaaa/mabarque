// Pengaturan profil streamer: data dari GET /api/profile, disimpan lewat PUT /api/profile,
// dengan pratinjau langsung halaman publik + overlay OBS. Ketersediaan slug dicek server saat menyimpan.
const PROFILE_KEYS = ["channel_name", "slug", "tagline", "main_role", "live_schedule", "avatar_initials", "profile_accent"];
const ROLE_LABELS = { jungler: "Jungler", roamer: "Roamer", mid: "Mid Lane", gold: "Gold Lane", exp: "EXP Lane", flex: "Flex" };

const form = document.querySelector("#profile-form");
const fields = {
  channel_name: document.querySelector("#channel-name"),
  slug: document.querySelector("#profile-slug"),
  tagline: document.querySelector("#profile-tagline"),
  main_role: document.querySelector("#profile-role"),
  live_schedule: document.querySelector("#profile-schedule"),
  avatar_initials: document.querySelector("#profile-initials"),
};
const saveButton = document.querySelector("#save-profile");
const resetButton = document.querySelector("#reset-profile");
const saveState = document.querySelector("#save-state");
const slugStatus = document.querySelector("#slug-status");
const profileError = document.querySelector("#profile-error");
const toast = document.querySelector("#profile-toast");
let saved = null;

/** Samakan bentuk data server (null) dengan nilai form (string kosong) supaya deteksi perubahan akurat. */
function normalize(profile) {
  const result = {};
  PROFILE_KEYS.forEach((key) => { result[key] = profile[key] ?? ""; });
  if (!form.querySelector(`input[name="profile_accent"][value="${result.profile_accent}"]`)) {
    result.profile_accent = form.querySelector('input[name="profile_accent"]').value;
  }
  return result;
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(showToast.timeout);
  showToast.timeout = window.setTimeout(() => toast.classList.remove("is-visible"), 2500);
}

function initialsFrom(name) {
  return name.trim().split(/\s+/).slice(0, 2).map((part) => part[0] || "").join("").toUpperCase() || "MQ";
}

function readForm() {
  return {
    channel_name: fields.channel_name.value.trim(),
    slug: fields.slug.value.trim().toLowerCase(),
    tagline: fields.tagline.value.trim(),
    main_role: fields.main_role.value,
    live_schedule: fields.live_schedule.value.trim(),
    avatar_initials: fields.avatar_initials.value.trim().toUpperCase(),
    profile_accent: form.querySelector('input[name="profile_accent"]:checked').value,
  };
}

function fillForm(profile) {
  Object.entries(fields).forEach(([key, input]) => { input.value = profile[key]; });
  form.querySelector(`input[name="profile_accent"][value="${profile.profile_accent}"]`).checked = true;
}

function validateSlug(slug) {
  if (!slug) return { ok: false, message: "Alamat publik wajib diisi." };
  if (!/^[a-z0-9-]{3,24}$/.test(slug)) return { ok: false, message: "Pakai 3–24 huruf kecil, angka, atau tanda hubung." };
  if (slug.startsWith("-") || slug.endsWith("-")) return { ok: false, message: "Tidak boleh diawali atau diakhiri tanda hubung." };
  return { ok: true, message: slug === saved?.slug ? "Alamat kamu saat ini." : "Format valid — ketersediaan dicek saat menyimpan." };
}

function renderPreview(profile) {
  const name = profile.channel_name || "Nama channel";
  const initials = profile.avatar_initials || initialsFrom(name);
  document.querySelectorAll('[data-preview="channel"]').forEach((el) => { el.textContent = name; });
  document.querySelectorAll('[data-preview="initials"]').forEach((el) => { el.textContent = initials; });
  document.querySelector("#avatar-preview").textContent = initials;
  document.querySelector('[data-preview="role"]').textContent = ROLE_LABELS[profile.main_role] || "—";
  document.querySelector('[data-preview="schedule"]').textContent = profile.live_schedule || "—";
  const tagline = document.querySelector('[data-preview="tagline"]');
  tagline.textContent = profile.tagline;
  tagline.hidden = !profile.tagline;
  document.querySelector("#preview-url").textContent = `mabarqueue.id/${profile.slug || "…"}`;
  document.querySelector(".preview-panel").style.setProperty("--profile-accent", profile.profile_accent);
  document.querySelector("#avatar-preview").style.background = profile.profile_accent;
  document.querySelector("#channel-name-count").textContent = fields.channel_name.value.length;
}

function update() {
  if (saved === null) return;
  const profile = readForm();
  const slugCheck = validateSlug(profile.slug);
  slugStatus.textContent = slugCheck.message;
  slugStatus.classList.toggle("is-error", !slugCheck.ok);
  slugStatus.classList.toggle("is-ok", slugCheck.ok);
  fields.slug.parentElement.classList.toggle("is-invalid", !slugCheck.ok);
  renderPreview(profile);

  const dirty = JSON.stringify(profile) !== JSON.stringify(saved);
  saveButton.disabled = !dirty;
  resetButton.disabled = !dirty;
  saveState.textContent = dirty ? "Ada perubahan belum disimpan" : "Semua perubahan tersimpan";
  saveState.classList.toggle("is-dirty", dirty);
}

form.addEventListener("input", update);
form.addEventListener("change", update);

// Slug otomatis dirapikan saat mengetik (spasi → tanda hubung, huruf kecil).
fields.slug.addEventListener("input", () => {
  const cleaned = fields.slug.value.toLowerCase().replace(/\s+/g, "-").replace(/[^a-z0-9-]/g, "");
  if (cleaned !== fields.slug.value) fields.slug.value = cleaned;
});
fields.avatar_initials.addEventListener("input", () => { fields.avatar_initials.value = fields.avatar_initials.value.replace(/[^a-zA-Z0-9]/g, "").toUpperCase(); });

resetButton.addEventListener("click", () => {
  fillForm(saved);
  profileError.hidden = true;
  fields.channel_name.classList.remove("is-invalid");
  update();
  showToast("Perubahan dibatalkan.");
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const profile = readForm();
  const problems = [];
  if (profile.channel_name.length < 3) { problems.push("Nama channel minimal 3 karakter."); fields.channel_name.classList.add("is-invalid"); }
  else fields.channel_name.classList.remove("is-invalid");
  const slugCheck = validateSlug(profile.slug);
  if (!slugCheck.ok) problems.push(slugCheck.message);
  if (problems.length) {
    profileError.textContent = problems.join(" ");
    profileError.hidden = false;
    (profile.channel_name.length < 3 ? fields.channel_name : fields.slug).focus();
    return;
  }
  profileError.hidden = true;
  saveButton.disabled = true;
  saveButton.textContent = "Menyimpan…";

  const result = await MQApi.put("/api/profile", profile);
  saveButton.textContent = "Simpan profil";

  if (!result.ok) {
    saveButton.disabled = false;
    profileError.textContent = MQApi.firstError(result);
    profileError.hidden = false;
    const field = fields[Object.keys(result.errors)[0]];
    if (field) field.focus();
    return;
  }

  saved = normalize(result.data);
  fillForm(saved);
  update();
  showToast(result.message || "Profil streamer disimpan.");
});

window.addEventListener("beforeunload", (event) => {
  if (!saveButton.disabled) { event.preventDefault(); event.returnValue = ""; }
});

// Tab pratinjau (dengan navigasi panah kiri/kanan)
const tabs = [...document.querySelectorAll('[role="tab"]')];
function selectTab(tab) {
  tabs.forEach((item) => {
    const active = item === tab;
    item.setAttribute("aria-selected", String(active));
    item.tabIndex = active ? 0 : -1;
    document.querySelector(`#${item.getAttribute("aria-controls")}`).hidden = !active;
  });
  tab.focus();
}
tabs.forEach((tab, index) => {
  tab.addEventListener("click", () => selectTab(tab));
  tab.addEventListener("keydown", (event) => {
    if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") return;
    const next = (index + (event.key === "ArrowRight" ? 1 : tabs.length - 1)) % tabs.length;
    selectTab(tabs[next]);
  });
});

(async function loadProfile() {
  saveState.textContent = "Memuat profil…";
  const result = await MQApi.get("/api/profile");

  if (!result.ok) {
    profileError.textContent = MQApi.firstError(result, "Gagal memuat profil. Muat ulang halaman.");
    profileError.hidden = false;
    return;
  }

  saved = normalize(result.data);
  fillForm(saved);
  update();
})();
