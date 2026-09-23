const KEY = "payslip-draft";
const fields = ["name", "role", "empId", "period", "currency", "basic", "allowance", "bonus", "taxRate", "other"];
const defaults = {
  name: "Amina Rahman",
  role: "Finance Lead",
  empId: "EMP-2041",
  period: "2026-09",
  currency: "USD",
  basic: "4800",
  allowance: "720",
  bonus: "250",
  taxRate: "12",
  other: "210",
};

const $ = (id) => document.getElementById(id);
const num = (v) => Number.parseFloat(v) || 0;

function read() {
  return Object.fromEntries(fields.map((f) => [f, $(f).value]));
}

function write(data) {
  fields.forEach((f) => {
    if (data[f] != null) $(f).value = data[f];
  });
}

function money(n, currency) {
  try {
    return new Intl.NumberFormat(undefined, { style: "currency", currency: currency || "USD" }).format(n);
  } catch {
    return `${currency} ${n.toFixed(2)}`;
  }
}

function periodLabel(ym) {
  if (!ym) return "—";
  const [y, m] = ym.split("-").map(Number);
  return new Date(y, m - 1, 1).toLocaleDateString(undefined, { month: "long", year: "numeric" });
}

function compute(d) {
  const gross = num(d.basic) + num(d.allowance) + num(d.bonus);
  const tax = gross * (num(d.taxRate) / 100);
  const deductions = num(d.other);
  const net = gross - tax - deductions;
  return { gross, tax, deductions, net };
}

function render() {
  const d = read();
  const c = compute(d);
  const cur = d.currency || "USD";
  $("p-name").textContent = d.name || "Employee name";
  $("p-role").textContent = d.role || "Role";
  $("p-id").textContent = d.empId || "—";
  $("p-period").textContent = periodLabel(d.period);
  $("p-basic").textContent = money(num(d.basic), cur);
  $("p-allowance").textContent = money(num(d.allowance), cur);
  $("p-bonus").textContent = money(num(d.bonus), cur);
  $("p-gross").textContent = money(c.gross, cur);
  $("p-tax").textContent = money(c.tax, cur);
  $("p-taxrate").textContent = `${num(d.taxRate)}%`;
  $("p-other").textContent = money(c.deductions, cur);
  $("p-deductions").textContent = money(c.tax + c.deductions, cur);
  $("p-net").textContent = money(c.net, cur);
}

function toast(msg) {
  const t = $("toast");
  t.textContent = msg;
  t.hidden = false;
  clearTimeout(toast._id);
  toast._id = setTimeout(() => {
    t.hidden = true;
  }, 1800);
}

function save() {
  localStorage.setItem(KEY, JSON.stringify(read()));
  toast("Draft saved on this device");
}

function load() {
  try {
    const raw = localStorage.getItem(KEY);
    write(raw ? { ...defaults, ...JSON.parse(raw) } : defaults);
  } catch {
    write(defaults);
  }
  render();
}

$("demo-form").addEventListener("input", render);
$("demo-form").addEventListener("change", render);
$("save").addEventListener("click", save);
$("print").addEventListener("click", () => window.print());
$("reset").addEventListener("click", () => {
  write(defaults);
  render();
  toast("Form reset");
});

const navToggle = $("nav-toggle");
const navPanel = $("nav-panel");
navToggle.addEventListener("click", () => {
  const open = navToggle.getAttribute("aria-expanded") === "true";
  navToggle.setAttribute("aria-expanded", String(!open));
  navPanel.hidden = open;
});
navPanel.querySelectorAll("a").forEach((a) => {
  a.addEventListener("click", () => {
    navToggle.setAttribute("aria-expanded", "false");
    navPanel.hidden = true;
  });
});

document.querySelectorAll("[data-faq]").forEach((btn) => {
  btn.addEventListener("click", () => {
    const wasOpen = btn.getAttribute("aria-expanded") === "true";
    document.querySelectorAll("[data-faq]").forEach((b) => {
      b.setAttribute("aria-expanded", "false");
      $(b.getAttribute("aria-controls")).hidden = true;
    });
    if (!wasOpen) {
      btn.setAttribute("aria-expanded", "true");
      $(btn.getAttribute("aria-controls")).hidden = false;
    }
  });
});

load();
