<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Visual Identity Configuration — Portal Settings</title>
<style>
  /* ---------- Base ---------- */
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    font-family: "Inter", "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
    background: #f4f6fb;
    color: #0f172a;
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }
  a { color: inherit; text-decoration: none; }
  button { font-family: inherit; cursor: pointer; border: none; background: none; }

  /* ---------- Layout ---------- */
  .app {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: 100vh;
  }

  /* ---------- Sidebar ---------- */
  .sidebar {
    background: #0b1220;
    color: #cbd5e1;
    padding: 22px 18px;
    border-right: 1px solid #111a2e;
    display: flex;
    flex-direction: column;
    gap: 22px;
  }
  .brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 6px 14px;
    border-bottom: 1px solid #1f2a44;
  }
  .brand-mark {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: grid; place-items: center;
    color: #fff; font-weight: 700;
    box-shadow: 0 6px 18px rgba(99,102,241,.35);
  }
  .brand-name { color: #fff; font-weight: 600; font-size: 15px; }
  .brand-sub  { color: #64748b; font-size: 11px; }

  .nav-group-label {
    font-size: 10.5px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #475569;
    padding: 0 8px;
    margin-bottom: 6px;
  }
  .nav { display: flex; flex-direction: column; gap: 2px; }
  .nav a {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px;
    border-radius: 8px;
    color: #cbd5e1;
    font-size: 13.5px;
    transition: background .15s, color .15s;
  }
  .nav a:hover { background: #111a2e; color: #fff; }
  .nav a.active {
    background: linear-gradient(90deg, rgba(99,102,241,.18), rgba(99,102,241,.02));
    color: #fff;
    box-shadow: inset 0 0 0 1px rgba(99,102,241,.35);
  }
  .nav .dot { width: 6px; height: 6px; border-radius: 50%; background: #334155; }
  .nav a.active .dot { background: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.25); }

  .sidebar-footer {
    margin-top: auto;
    padding: 12px;
    border-radius: 10px;
    background: #0f1a30;
    display: flex; align-items: center; gap: 10px;
  }
  .avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg,#f59e0b,#ef4444);
    color: #fff; font-weight: 600;
    display: grid; place-items: center;
  }
  .user-name { color: #fff; font-weight: 500; font-size: 13px; }
  .user-role { color: #64748b; font-size: 11.5px; }

  /* ---------- Main ---------- */
  .main { padding: 26px 34px 60px; overflow-x: hidden; }

  .topbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 22px;
  }
  .crumbs { color: #64748b; font-size: 12.5px; }
  .crumbs span { color: #0f172a; font-weight: 500; }
  .topbar-actions { display: flex; gap: 10px; }

  .page-title {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 22px;
  }
  .page-title h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 650;
    letter-spacing: -0.01em;
  }
  .page-title p { margin: 6px 0 0; color: #64748b; font-size: 13.5px; max-width: 720px; }

  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 14px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 13px;
    transition: transform .08s, box-shadow .15s, background .15s;
  }
  .btn:active { transform: translateY(1px); }
  .btn-primary {
    background: #4f46e5;
    color: #fff;
    box-shadow: 0 6px 16px rgba(79,70,229,.28);
  }
  .btn-primary:hover { background: #4338ca; }
  .btn-ghost {
    background: #fff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
  }
  .btn-ghost:hover { background: #f8fafc; }

  /* ---------- Grid ---------- */
  .grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 22px;
  }
  .card {
    background: #fff;
    border: 1px solid #e5e9f2;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 1px 0 rgba(15,23,42,.02);
  }
  .card-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 14px;
  }
  .card-head h2 {
    margin: 0; font-size: 15px; font-weight: 600; color: #0f172a;
  }
  .card-head .sub { color: #64748b; font-size: 12.5px; margin-top: 2px; }
  .badge {
    font-size: 11px; font-weight: 500;
    padding: 3px 8px; border-radius: 999px;
    background: #eef2ff; color: #4338ca;
    border: 1px solid #e0e7ff;
  }
  .badge.success { background: #ecfdf5; color: #047857; border-color: #d1fae5; }
  .badge.warn   { background: #fff7ed; color: #b45309; border-color: #fed7aa; }

  /* ---------- Letterhead uploads ---------- */
  .uploads {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 18px;
  }
  .drop {
    border: 1.5px dashed #cbd5e1;
    border-radius: 12px;
    padding: 14px;
    background: #f8fafc;
    transition: border-color .15s, background .15s;
  }
  .drop:hover { border-color: #6366f1; background: #eef2ff; }
  .drop-label {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 12.5px; font-weight: 600; color: #0f172a;
    margin-bottom: 10px;
  }
  .drop-label small { color: #64748b; font-weight: 400; }
  .drop-preview {
    height: 96px;
    border-radius: 8px;
    background:
      linear-gradient(#fff,#fff) padding-box,
      linear-gradient(135deg,#e0e7ff,#c7d2fe) border-box;
    border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
    position: relative;
    overflow: hidden;
  }
  .drop-preview.header {
    background:
      linear-gradient(#fff,#fff) padding-box,
      linear-gradient(135deg,#e0e7ff,#c7d2fe) border-box;
  }
  .drop-preview.footer {
    background:
      linear-gradient(#fff,#fff) padding-box,
      linear-gradient(135deg,#d1fae5,#a7f3d0) border-box;
  }
  .mock-letterhead {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px;
    font-size: 10px;
    color: #334155;
  }
  .mock-letterhead .logo-chip {
    width: 28px; height: 28px; border-radius: 6px;
    background: linear-gradient(135deg,#4f46e5,#7c3aed);
    color: #fff; font-weight: 700; font-size: 12px;
    display: grid; place-items: center;
  }
  .mock-letterhead .meta { text-align: right; line-height: 1.35; }
  .mock-letterhead .meta b { color: #0f172a; display: block; font-size: 10.5px; }
  .drop-actions {
    display: flex; gap: 8px; margin-top: 10px;
  }
  .chip-btn {
    font-size: 11.5px;
    padding: 5px 10px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #475569;
  }
  .chip-btn:hover { color: #4f46e5; border-color: #c7d2fe; }

  /* ---------- Letterhead preview (PDF-like) ---------- */
  .pdf-preview {
    margin-top: 6px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
  }
  .pdf-page {
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 4px 14px rgba(15,23,42,.08);
    padding: 0;
    position: relative;
    aspect-ratio: 210 / 297;
    max-height: 420px;
    margin: 0 auto;
    width: 100%;
    overflow: hidden;
    display: flex; flex-direction: column;
  }
  .pdf-header, .pdf-footer {
    padding: 14px 22px;
    display: flex; align-items: center; justify-content: space-between;
    font-size: 10.5px;
    color: #334155;
    flex-shrink: 0;
  }
  .pdf-header {
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg,#fafbff,#fff);
  }
  .pdf-footer {
    border-top: 1px solid #e2e8f0;
    background: linear-gradient(0deg,#fafbff,#fff);
    margin-top: auto;
  }
  .pdf-header .logo-chip {
    width: 30px; height: 30px; border-radius: 7px;
    background: linear-gradient(135deg,#4f46e5,#7c3aed);
    color: #fff; font-weight: 700; font-size: 13px;
    display: grid; place-items: center;
  }
  .pdf-header .company { font-weight: 600; color: #0f172a; font-size: 12px; }
  .pdf-header .addr { font-size: 10px; color: #64748b; line-height: 1.35; text-align: right; }
  .pdf-body {
    flex: 1;
    padding: 18px 22px;
    display: flex; flex-direction: column; gap: 8px;
  }
  .pdf-body .line {
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
  }
  .pdf-body .line.short { width: 60%; }
  .pdf-body .line.mid   { width: 80%; }
  .pdf-body .line.long  { width: 95%; }
  .pdf-body .line.title { height: 10px; width: 40%; background: #cbd5e1; margin-bottom: 6px; }
  .pdf-body .table {
    margin-top: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
  }
  .pdf-body .table .row {
    display: grid; grid-template-columns: 2fr 1fr 1fr;
    font-size: 9.5px; color: #475569;
    border-bottom: 1px solid #f1f5f9;
    padding: 5px 8px;
  }
  .pdf-body .table .row:last-child { border-bottom: none; }
  .pdf-body .table .row.head { background: #f8fafc; color: #0f172a; font-weight: 600; }
  .boundary {
    position: absolute; inset: 10px;
    border: 1px dashed rgba(99,102,241,.45);
    border-radius: 4px;
    pointer-events: none;
  }
  .boundary::before {
    content: "Page-safe letterhead zone";
    position: absolute; top: -9px; left: 10px;
    background: #fff;
    padding: 0 6px;
    font-size: 9.5px;
    color: #4f46e5;
    font-weight: 500;
  }

  /* ---------- Templates ---------- */
  .templates {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }
  .tpl {
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
    background: #fff;
    transition: border-color .15s, box-shadow .15s, transform .1s;
    cursor: pointer;
    display: flex; flex-direction: column; gap: 10px;
  }
  .tpl:hover { border-color: #c7d2fe; transform: translateY(-1px); }
  .tpl.selected {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,.12);
  }
  .tpl-thumb {
    aspect-ratio: 3 / 4;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #eef2ff;
    padding: 10px;
    display: flex; flex-direction: column; gap: 5px;
    position: relative;
    overflow: hidden;
  }
  .tpl-thumb::before {
    content: "";
    position: absolute; inset: 6px;
    border: 1px dashed rgba(99,102,241,.25);
    border-radius: 3px;
    pointer-events: none;
  }
  .tpl-thumb .h { height: 14px; background: #cbd5e1; border-radius: 3px; }
  .tpl-thumb .h.modern { background: linear-gradient(90deg,#4f46e5,#8b5cf6); }
  .tpl-thumb .h.classic { background: #0f172a; height: 10px; }
  .tpl-thumb .h.compact { height: 8px; background: #94a3b8; }
  .tpl-thumb .b { flex: 1; display: flex; flex-direction: column; gap: 4px; }
  .tpl-thumb .b .l { height: 4px; background: #e2e8f0; border-radius: 2px; }
  .tpl-thumb .b .l.w70 { width: 70%; }
  .tpl-thumb .b .l.w50 { width: 50%; }
  .tpl-thumb .b .l.w85 { width: 85%; }
  .tpl-thumb .f { height: 10px; background: #e2e8f0; border-radius: 3px; }
  .tpl-thumb .f.modern { background: linear-gradient(90deg,#8b5cf6,#4f46e5); }
  .tpl-thumb .f.classic { background: #0f172a; height: 7px; }
  .tpl-thumb .f.compact { height: 5px; background: #94a3b8; }

  .tpl-name {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 13px; font-weight: 600; color: #0f172a;
  }
  .tpl-desc { font-size: 11.5px; color: #64748b; }
  .radio {
    width: 16px; height: 16px; border-radius: 50%;
    border: 1.5px solid #cbd5e1;
    display: inline-grid; place-items: center;
  }
  .tpl.selected .radio {
    border-color: #4f46e5;
  }
  .tpl.selected .radio::after {
    content: ""; width: 8px; height: 8px; border-radius: 50%;
    background: #4f46e5;
  }

  /* ---------- Custom HTML section ---------- */
  .custom-block {
    margin-top: 18px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
  }
  .custom-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
  }
  .custom-head .title {
    display: flex; align-items: center; gap: 10px;
    font-size: 13px; font-weight: 600; color: #0f172a;
  }
  .toggle {
    width: 36px; height: 20px;
    background: #e2e8f0;
    border-radius: 999px;
    position: relative;
    transition: background .15s;
  }
  .toggle::after {
    content: "";
    position: absolute; top: 2px; left: 2px;
    width: 16px; height: 16px; border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
    transition: left .15s;
  }
  .toggle.on { background: #4f46e5; }
  .toggle.on::after { left: 18px; }

  .code-pane {
    background: #0b1220;
    color: #cbd5e1;
    font-family: "JetBrains Mono", "Fira Code", Consolas, monospace;
    font-size: 12px;
    line-height: 1.6;
    padding: 16px 18px;
    max-height: 220px;
    overflow: auto;
  }
  .code-pane .kw  { color: #c084fc; }
  .code-pane .str { color: #86efac; }
  .code-pane .var { color: #fbbf24; }
  .code-pane .com { color: #64748b; font-style: italic; }
  .code-pane .tag { color: #7dd3fc; }

  /* ---------- Variables reference ---------- */
  .vars {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-top: 12px;
  }
  .var-chip {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 12px;
  }
  .var-chip code {
    font-family: "JetBrains Mono", Consolas, monospace;
    color: #4f46e5;
    background: #eef2ff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11.5px;
  }
  .var-chip span { color: #475569; }

  /* ---------- Footer actions ---------- */
  .footer-actions {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 26px;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e5e9f2;
    border-radius: 14px;
  }
  .footer-actions .hint { color: #64748b; font-size: 12.5px; }
  .footer-actions .hint b { color: #0f172a; }
  .footer-actions .group { display: flex; gap: 10px; }

  /* ---------- Small helpers ---------- */
  .divider { height: 1px; background: #eef2f7; margin: 14px 0; }
  .row { display: flex; align-items: center; gap: 10px; }
  .muted { color: #64748b; }
  .stack > * + * { margin-top: 10px; }
</style>
</head>
<body>
<div class="app">

  <!-- ============== SIDEBAR ============== -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">P</div>
      <div>
        <div class="brand-name">PayrollOS</div>
        <div class="brand-sub">Enterprise Suite</div>
      </div>
    </div>

    <div>
      <div class="nav-group-label">Workspace</div>
      <nav class="nav">
        <a href="#"><span class="dot"></span> Dashboard</a>
        <a href="#"><span class="dot"></span> Employees</a>
        <a href="#"><span class="dot"></span> Payroll Runs</a>
        <a href="#"><span class="dot"></span> Reports</a>
      </nav>
    </div>

    <div>
      <div class="nav-group-label">Configuration</div>
      <nav class="nav">
        <a href="#"><span class="dot"></span> Company Profile</a>
        <a href="#" class="active"><span class="dot"></span> Visual Identity</a>
        <a href="#"><span class="dot"></span> Payslip Templates</a>
        <a href="#"><span class="dot"></span> Integrations</a>
        <a href="#"><span class="dot"></span> Security &amp; Audit</a>
      </nav>
    </div>

    <div class="sidebar-footer">
      <div class="avatar">AM</div>
      <div>
        <div class="user-name">Amara Mensah</div>
        <div class="user-role">System Administrator</div>
      </div>
    </div>
  </aside>

  <!-- ============== MAIN ============== -->
  <main class="main">

    <div class="topbar">
      <div class="crumbs">Configuration &nbsp;/&nbsp; <span>Visual Identity</span></div>
      <div class="topbar-actions">
        <button class="btn btn-ghost">View audit log</button>
        <button class="btn btn-ghost">Preview PDF</button>
      </div>
    </div>

    <div class="page-title">
      <div>
        <h1>Visual Identity Configuration</h1>
        <p>Configure your company's letterhead, branding and payslip templates once in portal settings. Changes are applied globally without altering the system's core HTML structure.</p>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-ghost">Discard</button>
        <button class="btn btn-primary">Save configuration</button>
      </div>
    </div>

    <div class="grid">

      <!-- ============== LEFT COLUMN ============== -->
      <section class="stack">

        <!-- Letterhead uploads -->
        <div class="card">
          <div class="card-head">
            <div>
              <h2>Header &amp; Footer Banners</h2>
              <div class="sub">Upload letterhead images that frame every generated PDF. The core layout engine remains untouched.</div>
            </div>
            <span class="badge success">Synced</span>
          </div>

          <div class="uploads">
            <!-- Header upload -->
            <div class="drop">
              <div class="drop-label">
                Top Header Image
                <small>2480 × 320 px · PNG / SVG</small>
              </div>
              <div class="drop-preview header">
                <div class="mock-letterhead">
                  <div class="row" style="align-items:center;gap:10px;">
                    <div class="logo-chip">N</div>
                    <div>
                      <div style="font-weight:600;color:#0f172a;">Northwind Industries Ltd.</div>
                      <div style="color:#64748b;">Tax ID: GH-10293847 · Reg: CC-44210</div>
                    </div>
                  </div>
                  <div class="meta">
                    <b>Head Office</b>
                    14 Independence Ave<br/>
                    Accra, Ghana
                  </div>
                </div>
              </div>
              <div class="drop-actions">
                <button class="chip-btn">Replace image</button>
                <button class="chip-btn">Remove</button>
              </div>
            </div>

            <!-- Footer upload -->
            <div class="drop">
              <div class="drop-label">
                Bottom Footer Image
                <small>2480 × 180 px · PNG / SVG</small>
              </div>
              <div class="drop-preview footer">
                <div class="mock-letterhead" style="justify-content:space-between;">
                  <div style="line-height:1.4;">
                    <div style="font-weight:600;color:#0f172a;">northwind.com</div>
                    <div style="color:#64748b;">hello@northwind.com · +233 302 000 000</div>
                  </div>
                  <div class="meta">
                    <b>Confidential</b>
                    Generated by PayrollOS
                  </div>
                </div>
              </div>
              <div class="drop-actions">
                <button class="chip-btn">Replace image</button>
                <button class="chip-btn">Remove</button>
              </div>
            </div>
          </div>

          <!-- Live PDF preview -->
          <div class="pdf-preview">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
              <div style="font-size:12px;font-weight:600;color:#0f172a;">Live PDF preview — A4 portrait</div>
              <div class="row">
                <span class="badge">Letterhead boundaries active</span>
              </div>
            </div>

            <div class="pdf-page">
              <div class="boundary"></div>

              <div class="pdf-header">
                <div class="row" style="gap:10px;">
                  <div class="logo-chip">N</div>
                  <div>
                    <div class="company">Northwind Industries Ltd.</div>
                    <div style="font-size:9.5px;color:#64748b;">Tax ID: GH-10293847 · Reg: CC-44210</div>
                  </div>
                </div>
                <div class="addr">
                  14 Independence Ave<br/>
                  Accra, Ghana · northwind.com
                </div>
              </div>

              <div class="pdf-body">
                <div class="line title"></div>
                <div class="line long"></div>
                <div class="line mid"></div>
                <div class="line long"></div>
                <div class="line short"></div>

                <div class="table">
                  <div class="row head">
                    <div>Description</div><div>Hours</div><div style="text-align:right;">Amount</div>
                  </div>
                  <div class="row"><div>Basic Salary</div><div>—</div><div style="text-align:right;">GHS 8,500.00</div></div>
                  <div class="row"><div>Housing Allowance</div><div>—</div><div style="text-align:right;">GHS 1,200.00</div></div>
                  <div class="row"><div>Transport</div><div>—</div><div style="text-align:right;">GHS 650.00</div></div>
                  <div class="row"><div>SSNIT (13.5%)</div><div>—</div><div style="text-align:right;">(GHS 1,397.25)</div></div>
                </div>

                <div class="line long" style="margin-top:10px;"></div>
                <div class="line mid"></div>
              </div>

              <div class="pdf-footer">
                <div style="font-size:9.5px;color:#64748b;">
                  northwind.com · hello@northwind.com · +233 302 000 000
                </div>
                <div style="font-size:9.5px;color:#64748b;">
                  Confidential · Page <b style="color:#0f172a;">1</b> of 1
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Custom HTML upload -->
        <div class="card">
          <div class="card-head">
            <div>
              <h2>Custom HTML / CSS Template</h2>
              <div class="sub">Enterprise clients may upload raw HTML/CSS using system variable tags. Rendered via headless Puppeteer.</div>
            </div>
            <span class="badge warn">Enterprise</span>
          </div>

          <div class="custom-block">
            <div class="custom-head">
              <div class="title">
                <span style="width:8px;height:8px;border-radius:50%;background:#4f46e5;"></span>
                payslip-custom.html
                <span class="muted" style="font-size:11.5px;font-weight:400;">— last edited 2 hours ago</span>
              </div>
              <div class="row">
                <span class="muted" style="font-size:11.5px;">Enable custom template</span>
                <div class="toggle on"></div>
              </div>
            </div>
            <pre class="code-pane"><span class="com">&lt;!-- Custom payslip template — variable tags are resolved server-side --&gt;</span>
<span class="tag">&lt;section</span> class="payslip"<span class="tag">&gt;</span>
  <span class="tag">&lt;header</span> class="letterhead"<span class="tag">&gt;</span>
    <span class="tag">&lt;img</span> src="<span class="var">{{header_image_url}}</span>" alt="Company letterhead" /<span class="tag">&gt;</span>
  <span class="tag">&lt;/header&gt;</span>

  <span class="tag">&lt;div</span> class="employee-card"<span class="tag">&gt;</span>
    <span class="tag">&lt;h1&gt;</span><span class="var">{{employee_name}}</span><span class="tag">&lt;/h1&gt;</span>
    <span class="tag">&lt;p&gt;</span>Staff ID: <span class="var">{{employee_id}}</span> · Dept: <span class="var">{{department}}</span><span class="tag">&lt;/p&gt;</span>
  <span class="tag">&lt;/div&gt;</span>

  <span class="tag">&lt;table</span> class="earnings"<span class="tag">&gt;</span>
    <span class="com">&lt;!-- loop: {{#each earnings}} --&gt;</span>
    <span class="tag">&lt;tr&gt;</span>
      <span class="tag">&lt;td&gt;</span><span class="var">{{name}}</span><span class="tag">&lt;/td&gt;</span>
      <span class="tag">&lt;td&gt;</span><span class="var">{{amount | currency}}</span><span class="tag">&lt;/td&gt;</span>
    <span class="tag">&lt;/tr&gt;</span>
  <span class="tag">&lt;/table&gt;</span>

  <span class="tag">&lt;div</span> class="total"<span class="tag">&gt;</span>Net Pay: <span class="var">{{net_pay}}</span><span class="tag">&lt;/div&gt;</span>

  <span class="tag">&lt;footer&gt;</span>
    <span class="tag">&lt;img</span> src="<span class="var">{{footer_image_url}}</span>" alt="Footer" /<span class="tag">&gt;</span>
  <span class="tag">&lt;/footer&gt;</span>
<span class="tag">&lt;/section&gt;</span></pre>
          </div>

          <div class="divider"></div>

          <div style="font-size:12.5px;font-weight:600;color:#0f172a;">Available system variables</div>
          <div class="vars">
            <div class="var-chip"><code>{{employee_name}}</code><span>Full legal name</span></div>
            <div class="var-chip"><code>{{employee_id}}</code><span>Staff identifier</span></div>
            <div class="var-chip"><code>{{net_pay}}</code><span>Formatted net pay</span></div>
            <div class="var-chip"><code>{{pay_period}}</code><span>e.g. Aug 2026</span></div>
            <div class="var-chip"><code>{{department}}</code><span>Department name</span></div>
            <div class="var-chip"><code>{{bank_account}}</code><span>Masked account</span></div>
            <div class="var-chip"><code>{{header_image_url}}</code><span>Top banner</span></div>
            <div class="var-chip"><code>{{footer_image_url}}</code><span>Bottom banner</span></div>
          </div>
        </div>

      </section>

      <!-- ============== RIGHT COLUMN ============== -->
      <section class="stack">

        <!-- Template selection -->
        <div class="card">
          <div class="card-head">
            <div>
              <h2>Template Selection Engine</h2>
              <div class="sub">Built-in layouts rendered via headless HTML-to-PDF (Puppeteer / @react-pdf/renderer).</div>
            </div>
            <span class="badge">3 layouts</span>
          </div>

          <div class="templates">
            <!-- Modern -->
            <div class="tpl selected">
              <div class="tpl-thumb">
                <div class="h modern"></div>
                <div class="b">
                  <div class="l w85"></div>
                  <div class="l w70"></div>
                  <div class="l w85"></div>
                  <div class="l w50"></div>
                </div>
                <div class="f modern"></div>
              </div>
              <div class="tpl-name">
                Modern
                <span class="radio"></span>
              </div>
              <div class="tpl-desc">Gradient letterhead with generous whitespace. Recommended.</div>
            </div>

            <!-- Classic -->
            <div class="tpl">
              <div class="tpl-thumb">
                <div class="h classic"></div>
                <div class="b">
                  <div class="l w85"></div>
                  <div class="l w85"></div>
                  <div class="l w70"></div>
                  <div class="l w85"></div>
                  <div class="l w50"></div>
                </div>
                <div class="f classic"></div>
              </div>
              <div class="tpl-name">
                Classic
                <span class="radio"></span>
              </div>
              <div class="tpl-desc">Traditional centered letterhead with serif typography.</div>
            </div>

            <!-- Compact -->
            <div class="tpl">
              <div class="tpl-thumb">
                <div class="h compact"></div>
                <div class="b">
                  <div class="l w85"></div>
                  <div class="l w70"></div>
                  <div class="l w85"></div>
                  <div class="l w50"></div>
                  <div class="l w85"></div>
                  <div class="l w70"></div>
                </div>
                <div class="f compact"></div>
              </div>
              <div class="tpl-name">
                Compact
                <span class="radio"></span>
              </div>
              <div class="tpl-desc">Minimal header for dense, multi-page statements.</div>
            </div>
          </div>

          <div class="divider"></div>

          <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
              <div style="font-size:13px;font-weight:600;color:#0f172a;">Rendering engine</div>
              <div class="muted" style="font-size:12px;">Puppeteer 23.x · Chromium headless · A4 @ 96dpi</div>
            </div>
            <button class="btn btn-ghost">Run test render</button>
          </div>
        </div>

        <!-- Brand tokens -->
        <div class="card">
          <div class="card-head">
            <div>
              <h2>Brand Tokens</h2>
              <div class="sub">Applied across all built-in templates without touching core HTML.</div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <div style="font-size:12px;color:#64748b;margin-bottom:6px;">Primary color</div>
              <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="width:22px;height:22px;border-radius:6px;background:#4f46e5;"></div>
                <code style="font-size:12px;color:#0f172a;">#4F46E5</code>
              </div>
            </div>
            <div>
              <div style="font-size:12px;color:#64748b;margin-bottom:6px;">Accent color</div>
              <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="width:22px;height:22px;border-radius:6px;background:#0ea5e9;"></div>
                <code style="font-size:12px;color:#0f172a;">#0EA5E9</code>
              </div>
            </div>
            <div>
              <div style="font-size:12px;color:#64748b;margin-bottom:6px;">Primary font</div>
              <div style="padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:12.5px;color:#0f172a;">
                Inter <span class="muted">· 400 / 500 / 600</span>
              </div>
            </div>
            <div>
              <div style="font-size:12px;color:#64748b;margin-bottom:6px;">Page margins</div>
              <div style="padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:12.5px;color:#0f172a;">
                18mm <span class="muted">· all sides</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="card">
          <div class="card-head">
            <div>
              <h2>Deployment Status</h2>
              <div class="sub">Last published configuration</div>
            </div>
            <span class="badge success">Live</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:12.5px;">
            <div style="display:flex;justify-content:space-between;"><span class="muted">Header banner</span><span>letterhead-v3.png · 142 KB</span></div>
            <div style="display:flex;justify-content:space-between;"><span class="muted">Footer banner</span><span>footer-v2.png · 86 KB</span></div>
            <div style="display:flex;justify-content:space-between;"><span class="muted">Active template</span><span>Modern</span></div>
            <div style="display:flex;justify-content:space-between;"><span class="muted">Custom HTML</span><span>Enabled (enterprise)</span></div>
            <div style="display:flex;justify-content:space-between;"><span class="muted">Published by</span><span>Amara Mensah · 09 Sep 2026</span></div>
          </div>
        </div>

      </section>
    </div>

    <!-- Footer actions -->
    <div class="footer-actions">
      <div class="hint">
        Changes apply to <b>all future PDFs</b>. Existing documents remain unaltered. Core HTML structure is <b>never modified</b>.
      </div>
      <div class="group">
        <button class="btn btn-ghost">Export config</button>
        <button class="btn btn-primary">Save configuration</button>
      </div>
    </div>

  </main>
</div>
</body>
</html>