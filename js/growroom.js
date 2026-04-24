
const STORAGE_KEY = "noyes_growroom_save_v2";

const DEFAULT_STATE = {
  credits: 1500,
  totalG: 124.5,
  env: { temp: 75, rh: 55 },
  plants: [
    { id: 1, name: "Atlas Kush", stage: "seedling", growth: 0, health: 84, water: 68, light: 75, disease: 0 },
    { id: 2, name: "Neon Haze", stage: "veg", growth: 78, health: 91, water: 54, light: 82, disease: 0 },
    { id: 3, name: "Velvet Berry", stage: "seedling", growth: 15, health: 76, water: 72, light: 63, disease: 0 },
    { id: 4, name: "Quantum Lime", stage: "flower", growth: 240, health: 89, water: 47, light: 90, disease: 1 }
  ]
};

const state = deepClone(DEFAULT_STATE);

function deepClone(x) { return JSON.parse(JSON.stringify(x)); }
function clamp(n, min, max) { return Math.max(min, Math.min(max, n)); }

function loadGame() {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) return;
  try {
    const loaded = JSON.parse(raw);
    Object.assign(state, deepClone(DEFAULT_STATE), loaded);
    if (!state.env) state.env = { temp: 75, rh: 55 };
    if (!Array.isArray(state.plants)) state.plants = deepClone(DEFAULT_STATE.plants);
    toast("Save loaded");
  } catch (err) {
    console.warn("Failed to load save", err);
  }
}

function saveGame(silent = false) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  if (!silent) toast("Game saved");
}

function resetDemo() {
  Object.assign(state, deepClone(DEFAULT_STATE));
  render();
  updateUI();
  toast("Demo reset");
  saveGame(true);
}

function getPlantFrame(p) {
  const frames = 3;
  const stage = p.stage === "harvest" ? "flower" : p.stage;
  const frame = (Math.floor(Date.now() / 420) % frames) + 1;
  return `assets/plants/${stage}/${frame}.png`;
}

function applyEnvironmentEffects(p) {
  const temp = state.env.temp;
  const rh = state.env.rh;

  if (temp > 84) p.health -= 0.55;
  if (temp < 68) p.health -= 0.35;
  if (rh > 70) p.disease += 0.22;
  if (rh < 40) p.health -= 0.18;
  if (p.water > 92) p.disease += 0.15;
  if (p.disease > 15) p.health -= 0.45;
}

function tick() {
  state.plants.forEach((p) => {
    // drain
    p.water = clamp(p.water - 0.8, 0, 100);
    p.light = clamp(p.light - 0.3, 0, 100);

    // growth
    let growthRate = 1.0;
    if (p.water > 45 && p.light > 50 && p.health > 55) growthRate = 1.8;
    if (p.health < 35) growthRate = 0.35;
    if (p.health > 90) growthRate = 2.0;
    p.growth += growthRate;

    // stage progression
    if (p.growth > 95 && p.stage === "seedling") p.stage = "veg";
    if (p.growth > 210 && p.stage === "veg") p.stage = "flower";
    if (p.growth > 340 && p.stage === "flower") p.stage = "harvest";

    // environment
    applyEnvironmentEffects(p);

    // disease/heal balance
    if (p.water < 25) p.health -= 0.9;
    if (p.light < 35) p.health -= 0.7;
    if (p.growth > 280 && p.stage === "flower" && p.health > 65) p.health += 0.08;

    // visuals/state clamp
    p.health = clamp(p.health, 0, 100);
    p.water = clamp(p.water, 0, 100);
    p.light = clamp(p.light, 0, 100);
    p.disease = clamp(p.disease, 0, 100);
  });

  state.totalG = round1(state.plants.reduce((sum, p) => sum + (p.stage === "harvest" ? p.health * 0.8 : 0), 0) / 10);
}

function round1(n){ return Math.round(n * 10) / 10; }

function harvestPlant(id) {
  const p = state.plants.find(x => x.id === id);
  if (!p || p.stage !== "harvest") return;
  const yieldAmount = Math.max(10, Math.round(p.health * 1.2));
  state.credits += yieldAmount;
  p.stage = "seedling";
  p.growth = 0;
  p.health = 82;
  p.water = 66;
  p.light = 74;
  p.disease = 0;
  toast(`Harvested +${yieldAmount} credits`);
  render();
  updateUI();
  saveGame(true);
}

function waterAll() {
  state.plants.forEach(p => p.water = clamp(p.water + 18, 0, 100));
  toast("Watered all plants");
  render();
}
function boostLight() {
  state.plants.forEach(p => p.light = clamp(p.light + 16, 0, 100));
  toast("Light boost applied");
  render();
}
function coolRoom() {
  state.env.temp = clamp(state.env.temp - 2, 60, 90);
  toast("Room cooled");
  updateUI();
}
function humidify() {
  state.env.rh = clamp(state.env.rh + 4, 30, 80);
  toast("Humidity increased");
  updateUI();
}

function render() {
  const grid = document.getElementById("plantGrid");
  grid.innerHTML = "";

  state.plants.forEach((p, idx) => {
    const card = document.createElement("div");
    card.className = "plant-card";
    if (p.stage === "flower") card.style.borderColor = "rgba(168,85,247,0.28)";
    if (p.stage === "harvest") card.style.borderColor = "rgba(251,191,36,0.38)";

    const glow = p.health / 100;
    const frame = getPlantFrame(p);
    const sway = Math.sin(Date.now() / 620 + idx) * 1.5;

    card.style.boxShadow = `0 18px 44px rgba(0,0,0,.22), 0 0 ${18 * glow}px rgba(34,197,94,${0.10 * glow})`;
    card.innerHTML = `
      <div class="plant-sprite-wrap">
        <img class="plant-sprite" style="transform:rotate(${sway}deg)" src="${frame}" alt="${p.name}">
      </div>
      <div class="plant-meta">
        <div class="plant-stage">${p.stage}</div>
        <div class="plant-name">${p.name}</div>

        <div class="stats">
          <div class="statrow"><span>Health</span><div class="bar health"><span style="width:${p.health}%"></span></div><strong>${Math.round(p.health)}%</strong></div>
          <div class="statrow"><span>Water</span><div class="bar water"><span style="width:${p.water}%"></span></div><strong>${Math.round(p.water)}%</strong></div>
          <div class="statrow"><span>Light</span><div class="bar light"><span style="width:${p.light}%"></span></div><strong>${Math.round(p.light)}%</strong></div>
        </div>

        <div class="actions">
          <button class="btn secondary" data-act="water">💧 Water</button>
          <button class="btn secondary" data-act="light">💡 Light</button>
          ${p.stage === "harvest" ? `<button class="btn" data-act="harvest">✂️ Harvest</button>` : `<button class="btn secondary" disabled>Growing</button>`}
          <button class="btn secondary" data-act="boost">✨ Boost</button>
        </div>
      </div>
    `;

    card.querySelector('[data-act="water"]').addEventListener("click", () => {
      p.water = clamp(p.water + 24, 0, 100);
      p.health = clamp(p.health + 1.5, 0, 100);
      render(); updateUI(); saveGame(true);
    });
    card.querySelector('[data-act="light"]').addEventListener("click", () => {
      p.light = clamp(p.light + 18, 0, 100);
      render(); updateUI(); saveGame(true);
    });
    card.querySelector('[data-act="boost"]').addEventListener("click", () => {
      p.health = clamp(p.health + 4, 0, 100);
      p.disease = clamp(p.disease - 1, 0, 100);
      render(); updateUI(); saveGame(true);
    });
    const harvestBtn = card.querySelector('[data-act="harvest"]');
    if (harvestBtn) harvestBtn.addEventListener("click", () => harvestPlant(p.id));

    grid.appendChild(card);
  });
}

function updateUI() {
  document.getElementById("credits").textContent = state.credits.toLocaleString();
  document.getElementById("totalG").textContent = state.totalG.toFixed(1);
  document.getElementById("roomMood").textContent = moodLabel();
  document.getElementById("envChip").textContent = `${Math.round(state.env.temp)}°F / ${Math.round(state.env.rh)}%`;
  document.getElementById("tempVal").textContent = `${document.getElementById("tempSlider").value}°F`;
  document.getElementById("rhVal").textContent = `${document.getElementById("rhSlider").value}%`;
}

function moodLabel() {
  const avgHealth = state.plants.reduce((s,p)=>s+p.health,0) / state.plants.length;
  if (avgHealth > 85) return "Thriving";
  if (avgHealth > 65) return "Stable";
  if (avgHealth > 40) return "Warning";
  return "Critical";
}

let toastTimer = null;
function toast(msg) {
  let t = document.getElementById("toast");
  if (!t) {
    t = document.createElement("div");
    t.id = "toast";
    t.className = "toast";
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = "1";
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.style.opacity = "0"; }, 1800);
}

function bindUI() {
  const tempSlider = document.getElementById("tempSlider");
  const rhSlider = document.getElementById("rhSlider");
  tempSlider.value = state.env.temp;
  rhSlider.value = state.env.rh;

  tempSlider.addEventListener("input", () => {
    state.env.temp = Number(tempSlider.value);
    updateUI();
  });
  rhSlider.addEventListener("input", () => {
    state.env.rh = Number(rhSlider.value);
    updateUI();
  });
}

function autosaveEvery(ms = 5000) {
  setInterval(() => saveGame(true), ms);
}

function gameLoop() {
  tick();
  render();
  updateUI();
}

loadGame();
bindUI();
render();
updateUI();
setInterval(gameLoop, 1000);
autosaveEvery(5000);
