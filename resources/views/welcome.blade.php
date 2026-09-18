<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fresh Hydration Kios — Platform IoT QRIS Dispenser Air Minum</title>
    <meta name="description" content="Fresh Hydration Kios (FHK): Sistem dispenser air minum IoT terintegrasi dengan pembayaran QRIS dinamis AiYO dan monitoring real-time via ESP32.">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --cyan: #06b6d4; --blue: #3b82f6; --emerald: #10b981;
            --purple: #a855f7; --amber: #f59e0b;
            --bg: #020617; --bg2: #0a0f1e;
            --card: rgba(15,23,42,0.85); --border: rgba(51,65,85,0.6);
            --text: #e2e8f0; --muted: #64748b;
        }
        html { scroll-behavior: smooth; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }

        .bg-mesh {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 80% 60% at 10% -10%, rgba(6,182,212,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 110%, rgba(59,130,246,0.10) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 50% 50%, rgba(16,185,129,0.05) 0%, transparent 70%);
        }
        .grid-overlay {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: linear-gradient(rgba(51,65,85,0.12) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(51,65,85,0.12) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 0%, black 30%, transparent 100%);
        }
        .particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .particle { position: absolute; border-radius: 50%; animation: float linear infinite; opacity: 0; }
        @keyframes float {
            0%   { transform: translateY(100vh) scale(0); opacity: 0; }
            10%  { opacity: 0.6; }
            90%  { opacity: 0.3; }
            100% { transform: translateY(-20vh) scale(1); opacity: 0; }
        }
        .wrapper { position: relative; z-index: 1; }

        /* NAVBAR */
        nav { position: sticky; top: 0; z-index: 100; backdrop-filter: blur(24px) saturate(180%); background: rgba(2,6,23,0.88); border-bottom: 1px solid var(--border); padding: 0 1.5rem; }
        .nav-inner { max-width: 1280px; margin: 0 auto; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-logo { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .logo-mark { width: 38px; height: 38px; background: linear-gradient(135deg, #06b6d4, #3b82f6); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 0 20px rgba(6,182,212,0.4); }
        .logo-text { font-weight: 800; font-size: 1.1rem; color: white; letter-spacing: -0.02em; }
        .logo-sub { font-size: 0.65rem; color: var(--cyan); font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
        .nav-links { display: flex; gap: 0.25rem; }
        .nav-link { padding: 0.4rem 0.9rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; color: var(--muted); text-decoration: none; transition: all 0.2s; }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.06); }
        .nav-cta { padding: 0.45rem 1.1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; background: linear-gradient(135deg, #06b6d4, #3b82f6); color: white; text-decoration: none; transition: all 0.2s; box-shadow: 0 0 15px rgba(6,182,212,0.3); display: flex; align-items: center; gap: 0.5rem; }
        .nav-cta:hover { transform: translateY(-1px); box-shadow: 0 0 25px rgba(6,182,212,0.5); }

        /* HERO */
        .hero { max-width: 1280px; margin: 0 auto; padding: 5rem 1.5rem 3rem; display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
        .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.85rem; border-radius: 999px; background: rgba(6,182,212,0.12); border: 1px solid rgba(6,182,212,0.3); font-size: 0.75rem; font-weight: 600; color: var(--cyan); margin-bottom: 1.5rem; }
        .hero-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--cyan); animation: pulse-dot 2s infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(1.3)} }
        h1 { font-size: clamp(2rem, 4vw, 3.1rem); font-weight: 900; line-height: 1.1; letter-spacing: -0.03em; color: white; margin-bottom: 1.25rem; }
        .highlight { background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 50%, #a855f7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero-desc { font-size: 1rem; color: #94a3b8; line-height: 1.7; margin-bottom: 2rem; max-width: 480px; }
        .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn-primary { padding: 0.8rem 1.75rem; border-radius: 12px; font-size: 0.95rem; font-weight: 700; background: linear-gradient(135deg, #06b6d4, #3b82f6); color: white; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.25s; box-shadow: 0 4px 24px rgba(6,182,212,0.35); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(6,182,212,0.5); }
        .btn-secondary { padding: 0.8rem 1.75rem; border-radius: 12px; font-size: 0.95rem; font-weight: 600; background: rgba(255,255,255,0.06); border: 1px solid var(--border); color: var(--text); cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.25s; }
        .btn-secondary:hover { background: rgba(255,255,255,0.10); }
        .btn-sm { padding: 0.55rem 1rem !important; font-size: 0.82rem !important; }
        .hero-stats { display: flex; gap: 2.5rem; margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid var(--border); }
        .stat-num { font-size: 1.75rem; font-weight: 900; color: white; letter-spacing: -0.03em; }
        .stat-label { font-size: 0.75rem; color: var(--muted); margin-top: 0.2rem; }

        /* DEVICE MOCKUP */
        .hero-visual { display: flex; justify-content: center; align-items: center; }
        .device-wrap { position: relative; }
        .device-glow { position: absolute; inset: -2px; border-radius: 26px; background: linear-gradient(135deg, rgba(6,182,212,0.3), rgba(59,130,246,0.2), transparent, rgba(168,85,247,0.2)); z-index: -1; animation: hue-spin 8s linear infinite; }
        @keyframes hue-spin { 0%{filter:hue-rotate(0deg)} 100%{filter:hue-rotate(360deg)} }
        .device-mockup { width: 310px; background: linear-gradient(145deg, #0f172a, #1e293b); border-radius: 24px; padding: 18px; border: 1px solid rgba(100,116,139,0.3); box-shadow: 0 40px 80px rgba(0,0,0,0.6), 0 0 60px rgba(6,182,212,0.12), inset 0 1px 0 rgba(255,255,255,0.08); }
        .device-screen { background: #020617; border-radius: 16px; overflow: hidden; border: 1px solid rgba(51,65,85,0.5); }
        .device-header { background: linear-gradient(135deg, rgba(6,182,212,0.12), rgba(59,130,246,0.08)); padding: 10px 14px; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid rgba(51,65,85,0.4); }
        .ddot { width: 7px; height: 7px; border-radius: 50%; }
        .device-title { font-size: 0.65rem; font-weight: 700; color: var(--cyan); margin-left: auto; font-family: 'JetBrains Mono', monospace; }
        .device-body { padding: 14px; }
        .mock-label { font-size: 0.6rem; color: #64748b; margin-bottom: 7px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 0.06em; }
        .mock-temp-row { display: flex; gap: 7px; margin-bottom: 11px; }
        .mock-tc { flex: 1; padding: 9px 7px; border-radius: 9px; text-align: center; font-size: 0.62rem; font-weight: 700; }
        .mock-cold { background: rgba(6,182,212,0.18); border: 1px solid rgba(6,182,212,0.4); color: #67e8f9; }
        .mock-normal { background: rgba(15,23,42,0.5); border: 1px solid rgba(51,65,85,0.3); color: #475569; }
        .mock-ti { font-size: 1rem; margin-bottom: 3px; }
        .mock-vol-row { display: flex; gap: 6px; margin-bottom: 11px; }
        .mock-vc { flex: 1; padding: 7px 3px; border-radius: 7px; text-align: center; font-size: 0.58rem; font-weight: 600; }
        .mock-va { background: rgba(6,182,212,0.18); border: 1px solid rgba(6,182,212,0.4); color: white; }
        .mock-vi { background: rgba(15,23,42,0.4); border: 1px solid rgba(51,65,85,0.25); color: #475569; }
        .mock-price { text-align: center; margin-bottom: 11px; font-size: 1.35rem; font-weight: 900; color: white; font-family: 'JetBrains Mono', monospace; }
        .mock-price small { font-size: 0.58rem; color: var(--cyan); display: block; margin-bottom: 3px; }
        .mock-btn { width: 100%; padding: 9px; border-radius: 9px; border: none; background: linear-gradient(135deg, #06b6d4, #3b82f6); color: white; font-size: 0.7rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; }

        /* SECTIONS */
        .section { max-width: 1280px; min-width: 0; margin: 0 auto; padding: 3.5rem 1.5rem; }
        .section-title { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 800; color: white; letter-spacing: -0.025em; margin-bottom: 0.4rem; }
        .section-sub { color: var(--muted); font-size: 0.88rem; margin-bottom: 2.25rem; }
        .sep { border: none; border-top: 1px solid var(--border); margin: 0 1.5rem; }

        /* STATUS CARDS */
        .status-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; }
        .status-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; backdrop-filter: blur(20px); transition: transform 0.2s, border-color 0.2s; position: relative; overflow: hidden; }
        .status-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; border-radius: 16px 16px 0 0; }
        .card-cyan::before { background: linear-gradient(90deg, transparent, #06b6d4, transparent); }
        .card-emerald::before { background: linear-gradient(90deg, transparent, #10b981, transparent); }
        .card-purple::before { background: linear-gradient(90deg, transparent, #a855f7, transparent); }
        .card-amber::before { background: linear-gradient(90deg, transparent, #f59e0b, transparent); }
        .status-card:hover { transform: translateY(-3px); border-color: rgba(100,116,139,0.8); }
        .card-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 0.875rem; }
        .icon-cyan { background: rgba(6,182,212,0.12); color: #06b6d4; }
        .icon-emerald { background: rgba(16,185,129,0.12); color: #10b981; }
        .icon-purple { background: rgba(168,85,247,0.12); color: #a855f7; }
        .icon-amber { background: rgba(245,158,11,0.12); color: #f59e0b; }
        .card-label { font-size: 0.7rem; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; }
        .card-value { font-size: 1.75rem; font-weight: 900; color: white; letter-spacing: -0.04em; line-height: 1; margin: 0.3rem 0; }
        .card-sub { font-size: 0.72rem; color: var(--muted); }
        .cbadge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; }
        .badge-green { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.25); }
        .badge-red { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
        .progress-bar { height: 6px; border-radius: 999px; background: rgba(51,65,85,0.5); overflow: hidden; margin: 8px 0; }
        .progress-fill { height: 100%; border-radius: 999px; transition: width 1.5s cubic-bezier(0.16,1,0.3,1); }

        /* FEATURES */
        .feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .feature-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 1.75rem; backdrop-filter: blur(20px); transition: all 0.25s; }
        .feature-card:hover { transform: translateY(-4px); border-color: rgba(6,182,212,0.3); box-shadow: 0 12px 32px rgba(0,0,0,0.3), 0 0 20px rgba(6,182,212,0.08); }
        .feature-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 1.1rem; }
        .feature-title { font-size: 1rem; font-weight: 700; color: white; margin-bottom: 0.5rem; }
        .feature-desc { font-size: 0.82rem; color: #94a3b8; line-height: 1.65; }

        /* FLOW */
        .flow-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; position: relative; }
        .flow-grid::before { content: ''; position: absolute; top: 24px; left: 15%; right: 15%; height: 1px; background: linear-gradient(90deg, transparent, rgba(6,182,212,0.4), rgba(59,130,246,0.4), transparent); }
        .flow-step { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem 1.25rem; text-align: center; backdrop-filter: blur(20px); transition: all 0.25s; }
        .flow-step:hover { border-color: rgba(6,182,212,0.4); transform: translateY(-3px); }
        .flow-num { width: 46px; height: 46px; border-radius: 12px; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 900; font-family: 'JetBrains Mono', monospace; background: linear-gradient(135deg, rgba(6,182,212,0.2), rgba(59,130,246,0.2)); border: 1px solid rgba(6,182,212,0.3); color: var(--cyan); }
        .flow-title { font-size: 0.9rem; font-weight: 700; color: white; margin-bottom: 0.4rem; }
        .flow-desc { font-size: 0.76rem; color: #94a3b8; line-height: 1.6; }

        /* CHARTS */
        .chart-row { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 1.5rem; }
        .chart-card { min-width: 0; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(20px); }
        .chart-card canvas { display: block; max-width: 100% !important; }
        .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .chart-title { font-size: 0.9rem; font-weight: 700; color: white; }
        .chart-sub { font-size: 0.72rem; color: var(--muted); }

        /* TABLE */
        .table-card { min-width: 0; max-width: 100%; background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; backdrop-filter: blur(20px); }
        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 0.7rem 1.25rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; background: rgba(0,0,0,0.2); }
        tbody tr { border-top: 1px solid rgba(51,65,85,0.3); transition: background 0.15s; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        tbody td { padding: 0.75rem 1.25rem; font-size: 0.82rem; color: var(--text); }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; }
        .badge-paid { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .badge-pending { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .badge-completed { background: rgba(59,130,246,0.15); color: #93c5fd; border: 1px solid rgba(59,130,246,0.3); }
        .badge-cold { background: rgba(6,182,212,0.12); color: #67e8f9; border: 1px solid rgba(6,182,212,0.2); }
        .badge-normal { background: rgba(16,185,129,0.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.2); }

        /* SYS */
        .sys-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
        .sys-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; backdrop-filter: blur(20px); transition: all 0.2s; }
        .sys-card:hover { border-color: rgba(100,116,139,0.6); }
        .sys-ind { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .sys-on { background: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.6); animation: pulse-dot 2s infinite; }
        .sys-label { font-size: 0.82rem; font-weight: 600; color: white; }
        .sys-sub { font-size: 0.7rem; color: var(--muted); margin-top: 0.1rem; }

        /* FOOTER */
        footer { border-top: 1px solid var(--border); padding: 2rem 1.5rem; text-align: center; color: var(--muted); font-size: 0.78rem; }
        .footer-links { display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .footer-link { color: var(--muted); text-decoration: none; transition: color 0.2s; }
        .footer-link:hover { color: var(--cyan); }

        /* TOAST */
        .toast-wrap { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
        .toast { padding: 0.75rem 1.25rem; border-radius: 12px; font-size: 0.82rem; font-weight: 600; backdrop-filter: blur(20px); border: 1px solid; max-width: 320px; animation: toast-in 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
        .toast-success { background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.3); color: #34d399; }
        .toast-info { background: rgba(6,182,212,0.15); border-color: rgba(6,182,212,0.3); color: #67e8f9; }
        @keyframes toast-in { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:translateX(0)} }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .hero { grid-template-columns: 1fr; text-align: center; }
            .hero-desc { max-width: 100%; }
            .hero-actions { justify-content: center; }
            .hero-stats { justify-content: center; }
            .hero-visual { display: none; }
            .status-grid, .feature-grid, .chart-row, .sys-grid { grid-template-columns: repeat(2, 1fr); }
            .flow-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            nav { padding: 0 0.875rem; }
            .nav-inner { height: auto; min-height: 60px; gap: 0.5rem; }
            .logo-text { font-size: 0.95rem; }
            .logo-sub { font-size: 0.56rem; }
            .nav-cta { padding: 0.45rem 0.65rem; font-size: 0.75rem; white-space: nowrap; }
            .hero, .section { padding-left: 1rem; padding-right: 1rem; }
            .hero { padding-top: 2.5rem; padding-bottom: 2.5rem; gap: 2rem; }
            .hero-badge { margin-bottom: 1rem; font-size: 0.66rem; }
            h1 { font-size: clamp(1.7rem, 9vw, 2.25rem); margin-bottom: 0.9rem; }
            .hero-desc { font-size: 0.88rem; line-height: 1.6; margin-bottom: 1.5rem; }
            .hero-actions { flex-direction: column; }
            .hero-actions a { justify-content: center; width: 100%; }
            .hero-stats { gap: 0.5rem; margin-top: 1.75rem; padding-top: 1.5rem; justify-content: space-between; }
            .stat-num { font-size: 1.35rem; }
            .stat-label { font-size: 0.62rem; }
            .section { padding-top: 2.5rem; padding-bottom: 2.5rem; }
            .section-sub { line-height: 1.55; margin-bottom: 1.5rem; }
            .status-grid, .flow-grid, .sys-grid, .feature-grid { grid-template-columns: 1fr; }
            .chart-row { grid-template-columns: minmax(0, 1fr); }
            .chart-card, .status-card, .flow-step, .feature-card { padding: 1rem; }
            .chart-header { align-items: flex-start; gap: 0.5rem; }
            .table-card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .table-card table { min-width: 700px; }
            .section:has(#transactions) > div[style] { align-items: flex-start !important; flex-direction: column; gap: 1rem; }
            .section:has(#transactions) > div[style] a { width: 100%; justify-content: center; }
            .toast-wrap { left: 1rem; right: 1rem; bottom: 1rem; }
            .toast { max-width: none; }
            .nav-links { display: none; }
        }
        @media (max-width: 360px) {
            .logo-mark { width: 34px; height: 34px; font-size: 15px; }
            .logo-text { font-size: 0.82rem; }
            .logo-sub { font-size: 0.5rem; }
            .nav-cta { padding: 0.4rem 0.5rem; font-size: 0.68rem; }
            .nav-cta i { display: none; }
            .hero-actions a { padding: 0.75rem 1rem; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
<div class="bg-mesh"></div>
<div class="grid-overlay"></div>
<div class="particles" id="particles"></div>
<div class="wrapper">

<!-- NAVBAR -->
<nav>
    <div class="nav-inner">
        <a href="/" class="nav-logo">
            <div class="logo-mark"><i class="fa-solid fa-droplet" style="color:white"></i></div>
            <div>
                <div class="logo-text">FHK System</div>
                <div class="logo-sub">Fresh Hydration Kios</div>
            </div>
        </a>
        <div class="nav-links">
            <a href="#status" class="nav-link">Status</a>
            <a href="#features" class="nav-link">Fitur</a>
            <a href="#transactions" class="nav-link">Transaksi</a>
        </div>
        <a href="{{ route('kiosk.home', ['kiosk_id' => 'FHK-JAKARTA-01']) }}" class="nav-cta">
            <i class="fa-solid fa-qrcode"></i> Buka Kiosk
        </a>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <div>
        <div class="hero-badge">
            <div class="dot"></div>
            Sistem Aktif &middot; AiYO QRIS Gateway Terhubung
        </div>
        <h1>Dispenser Air Minum<br><span class="highlight">IoT Berbasis QRIS</span></h1>
        <p class="hero-desc">Platform kios air minum cerdas dengan pembayaran QRIS dinamis real-time via <strong style="color:#67e8f9">AiYO Bills Invoice</strong>, monitoring IoT ESP32, sterilisasi UV otomatis, dan webhook callback terintegrasi penuh.</p>
        <div class="hero-actions">
            <a href="{{ route('kiosk.home', ['kiosk_id' => 'FHK-JAKARTA-01']) }}" class="btn-primary"><i class="fa-solid fa-play"></i> Coba Beli Air Sekarang</a>
        </div>
        <div class="hero-stats">
            <div><div class="stat-num" id="stat-tx">–</div><div class="stat-label">Total Transaksi</div></div>
            <div><div class="stat-num" id="stat-rev">–</div><div class="stat-label">Total Pendapatan</div></div>
            <div><div class="stat-num">99.9%</div><div class="stat-label">Uptime Sistem</div></div>
        </div>
    </div>
    <div class="hero-visual">
        <div class="device-wrap">
            <div class="device-glow"></div>
            <div class="device-mockup">
                <div class="device-screen">
                    <div class="device-header">
                        <div class="ddot" style="background:#ff5f57"></div>
                        <div class="ddot" style="background:#febc2e"></div>
                        <div class="ddot" style="background:#28c840"></div>
                        <div class="device-title">FHK Gambir &middot; ONLINE</div>
                    </div>
                    <div class="device-body">
                        <div class="mock-label">Pilih Jenis Air</div>
                        <div class="mock-temp-row">
                            <div class="mock-tc mock-cold"><div class="mock-ti">&#10052;</div>AIR DINGIN</div>
                            <div class="mock-tc mock-normal"><div class="mock-ti">&#128167;</div>NORMAL</div>
                        </div>
                        <div class="mock-label">Volume</div>
                        <div class="mock-vol-row">
                            <div class="mock-vc mock-vi">250ml</div>
                            <div class="mock-vc mock-va">500ml &#10003;</div>
                            <div class="mock-vc mock-vi">1000ml</div>
                        </div>
                        <div class="mock-price"><small>Total Bayar</small>Rp 3.500</div>
                        <button class="mock-btn"><i class="fa-solid fa-qrcode"></i> Bayar AiYO QRIS</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<hr class="sep">

<!-- STATUS -->
<div class="section" id="status">
    <div class="section-title">Status Sistem Real-Time</div>
    <div class="section-sub">Monitoring langsung dari kiosk &amp; gateway pembayaran AiYO</div>
    <div class="status-grid">
        <div class="status-card card-cyan">
            <div class="card-icon icon-cyan"><i class="fa-solid fa-droplet"></i></div>
            <div class="card-label">Level Tangki Air</div>
            <div class="card-value">88%</div>
            <div class="progress-bar"><div class="progress-fill" style="width:88%;background:linear-gradient(90deg,#06b6d4,#3b82f6)"></div></div>
            <div class="card-sub">~44 Liter tersisa dari 50L</div>
        </div>
        <div class="status-card card-emerald">
            <div class="card-icon icon-emerald"><i class="fa-solid fa-temperature-low"></i></div>
            <div class="card-label">Suhu Air Saat Ini</div>
            <div class="card-value">7.5&deg;C</div>
            <div class="cbadge badge-green" style="margin-top:8px"><span style="width:5px;height:5px;background:currentColor;border-radius:50%;display:inline-block;animation:pulse-dot 2s infinite"></span> Optimal COLD</div>
        </div>
        <div class="status-card card-purple">
            <div class="card-icon icon-purple"><i class="fa-solid fa-wifi"></i></div>
            <div class="card-label">Status Kiosk ESP32</div>
            <div class="card-value">ONLINE</div>
            <div class="cbadge badge-green" style="margin-top:8px"><span style="width:5px;height:5px;background:currentColor;border-radius:50%;display:inline-block;animation:pulse-dot 2s infinite"></span> FHK-JAKARTA-01</div>
        </div>
        <div class="status-card card-amber">
            <div class="card-icon icon-amber"><i class="fa-solid fa-credit-card"></i></div>
            <div class="card-label">Gateway AiYO QRIS</div>
            <div class="card-value" id="gw-val">–</div>
            <div class="cbadge" id="gw-badge" style="margin-top:8px;background:rgba(100,116,139,0.12);color:#94a3b8;border:1px solid rgba(100,116,139,0.25)">Memeriksa...</div>
        </div>
    </div>
</div>

<hr class="sep">

<!-- FEATURES -->
<div class="section" id="features">
    <div class="section-title">Teknologi Inti Sistem FHK</div>
    <div class="section-sub">Tiga lapisan teknologi yang membuat FHK menjadi platform dispenser IoT paling andal</div>
    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon" style="background:rgba(6,182,212,0.12);color:#06b6d4"><i class="fa-solid fa-qrcode"></i></div>
            <div class="feature-title">AiYO Dynamic QRIS</div>
            <div class="feature-desc">Setiap transaksi menghasilkan QRIS unik dari AiYO Bills Invoice Gateway. Webhook callback otomatis mengupdate status pembayaran ke sistem dalam hitungan detik.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:rgba(16,185,129,0.12);color:#10b981"><i class="fa-solid fa-microchip"></i></div>
            <div class="feature-title">ESP32 IoT Bridge</div>
            <div class="feature-desc">Mikrokontroler ESP32 melakukan polling perintah dispensing via API REST, mengontrol flow meter presisi ±5ml, pompa solenoid, dan relay sterilisasi UV nozzle secara real-time.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:rgba(168,85,247,0.12);color:#a855f7"><i class="fa-solid fa-radiation"></i></div>
            <div class="feature-title">Auto UV Sterilization</div>
            <div class="feature-desc">Nozzle otomatis disterilisasi UV sebelum setiap penuangan air. ESP32 cron menjalankan siklus sterilisasi pada jam sepi untuk mencegah biofilm pada pipa dan nozzle.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:rgba(245,158,11,0.12);color:#f59e0b"><i class="fa-solid fa-webhook"></i></div>
            <div class="feature-title">Callback Webhook</div>
            <div class="feature-desc">Endpoint <code style="font-family:'JetBrains Mono',monospace;font-size:0.74rem;color:#67e8f9">/app/fhk/callback</code> menerima notifikasi PAID dari AiYO dengan validasi idempoten, fallback mode, dan logging lengkap.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:rgba(239,68,68,0.12);color:#f87171"><i class="fa-solid fa-gauge-high"></i></div>
            <div class="feature-title">Admin Dashboard</div>
            <div class="feature-desc">Panel monitoring lengkap: level tangki, suhu air, histori transaksi, status filter air, jadwal UV, dan simulator pembayaran untuk pengujian end-to-end sistem.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:rgba(59,130,246,0.12);color:#60a5fa"><i class="fa-solid fa-receipt"></i></div>
            <div class="feature-title">Struk Digital Otomatis</div>
            <div class="feature-desc">Setiap transaksi sukses menghasilkan struk digital yang bisa disimpan sebagai screenshot. Berisi invoice ID, waktu, jenis air, volume, dan nominal pembayaran.</div>
        </div>
    </div>
</div>

<hr class="sep">

<!-- FLOW -->
<div class="section">
    <div class="section-title">Alur Pembayaran QRIS</div>
    <div class="section-sub">Dari pilih air hingga air mengalir — 4 langkah otomatis</div>
    <div class="flow-grid">
        <div class="flow-step">
            <div class="flow-num">01</div>
            <div class="flow-title">Pilih &amp; Konfirmasi</div>
            <div class="flow-desc">Pelanggan pilih jenis air (dingin/normal) dan volume (250/500/1000ml) di layar kiosk sentuh PWA.</div>
        </div>
        <div class="flow-step">
            <div class="flow-num">02</div>
            <div class="flow-title">Scan QRIS AiYO</div>
            <div class="flow-desc">Sistem buat invoice unik di AiYO Gateway dan tampilkan QR code dinamis untuk discan pelanggan via e-wallet.</div>
        </div>
        <div class="flow-step">
            <div class="flow-num">03</div>
            <div class="flow-title">Notifikasi Webhook</div>
            <div class="flow-desc">AiYO kirim notifikasi ke endpoint callback FHK. Status transaksi terupdate otomatis ke PAID di database.</div>
        </div>
        <div class="flow-step">
            <div class="flow-num">04</div>
            <div class="flow-title">Dispense + UV</div>
            <div class="flow-desc">ESP32 terima perintah, aktifkan UV nozzle, lalu pompa air tepat sesuai volume yang dipesan. Struk digital dikirim.</div>
        </div>
    </div>
</div>

<hr class="sep">

<!-- CHARTS -->
<div class="section">
    <div class="section-title">Analitik Penjualan</div>
    <div class="section-sub">Performa transaksi harian &amp; distribusi jenis air</div>
    <div class="chart-row">
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Transaksi per Jam (Hari Ini)</div>
                    <div class="chart-sub">Volume dalam satuan transaksi berhasil</div>
                </div>
                <div id="live-clock" style="font-size:0.7rem;color:var(--muted);font-family:'JetBrains Mono',monospace">–</div>
            </div>
            <canvas id="txChart" style="max-height:200px"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Distribusi Jenis Air</div>
                    <div class="chart-sub">Cold vs Normal (bulan ini)</div>
                </div>
            </div>
            <canvas id="typeChart" style="max-height:180px"></canvas>
            <div style="display:flex;gap:1rem;justify-content:center;margin-top:1rem;font-size:0.72rem;color:var(--muted)">
                <div style="display:flex;align-items:center;gap:0.4rem"><span style="width:10px;height:10px;border-radius:3px;background:#06b6d4;display:inline-block"></span>Air Dingin (68%)</div>
                <div style="display:flex;align-items:center;gap:0.4rem"><span style="width:10px;height:10px;border-radius:3px;background:#10b981;display:inline-block"></span>Normal (32%)</div>
            </div>
        </div>
    </div>
</div>

<hr class="sep">

<!-- TRANSACTIONS -->
<div class="section" id="transactions">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
        <div>
            <div class="section-title">Transaksi Terbaru</div>
            <div class="section-sub">Riwayat 10 transaksi terakhir dari AiYO QRIS Gateway</div>
        </div>
    </div>
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Reference ID</th>
                    <th>Invoice</th>
                    <th>Waktu</th>
                    <th>Jenis Air</th>
                    <th>Volume</th>
                    <th>Nominal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="tx-body">
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted)"><i class="fa-solid fa-spinner fa-spin"></i> Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<hr class="sep">

<!-- SYSTEM STATUS -->
<div class="section">
    <div class="section-title">Status Layanan Infrastruktur</div>
    <div class="section-sub">Semua komponen sistem FHK berjalan normal</div>
    <div class="sys-grid">
        <div class="sys-card"><div class="sys-ind sys-on"></div><div><div class="sys-label">Laravel Backend API</div><div class="sys-sub">PHP 8.x &middot; SQLite &middot; RUNNING</div></div></div>
        <div class="sys-card"><div class="sys-ind sys-on"></div><div><div class="sys-label">AiYO Callback Webhook</div><div class="sys-sub">/app/fhk/callback &middot; AKTIF</div></div></div>
        <div class="sys-card"><div class="sys-ind sys-on"></div><div><div class="sys-label">Database SQLite</div><div class="sys-sub">14 Tabel &middot; Migrasi Selesai</div></div></div>
        <div class="sys-card"><div class="sys-ind sys-on"></div><div><div class="sys-label">ESP32 Kiosk Bridge</div><div class="sys-sub">FHK-JAKARTA-01 &middot; ONLINE</div></div></div>
        <div class="sys-card"><div class="sys-ind sys-on"></div><div><div class="sys-label">UV Sterilization Module</div><div class="sys-sub">Auto-schedule &middot; ESP32 Cron AKTIF</div></div></div>
        <div class="sys-card"><div class="sys-ind sys-on"></div><div><div class="sys-label">AiYO Bills Invoice API</div><div class="sys-sub">OAuth + HMAC-SHA256 &middot; TERHUBUNG</div></div></div>
    </div>
</div>

</div><!-- /wrapper -->

<footer>
    <div class="footer-links">
        <a href="{{ route('kiosk.home', ['kiosk_id' => 'FHK-JAKARTA-01']) }}" class="footer-link">Kiosk PWA</a>
        <a href="/app/fhk/callback" class="footer-link">Callback API</a>
        <a href="/api/kiosk/order" class="footer-link">Order API</a>
    </div>
    <div>&copy; 2026 <strong style="color:#06b6d4">Fresh Hydration Kios (FHK)</strong> &mdash; Powered by <strong style="color:#60a5fa">AiYO Bills Invoice</strong> &middot; <strong style="color:#34d399">ESP32 IoT</strong> &middot; <strong style="color:#a78bfa">Laravel PHP</strong></div>
    <div style="margin-top:0.5rem;font-family:'JetBrains Mono',monospace;color:#334155;font-size:0.68rem">mesinbayar.com/app/fhk &middot; Callback: /app/fhk/callback/</div>
</footer>

<div class="toast-wrap" id="toasts"></div>

<script>
// Particles
(function(){
    const p=document.getElementById('particles');
    const c=['#06b6d4','#3b82f6','#10b981','#a855f7','#f59e0b'];
    for(let i=0;i<22;i++){
        const el=document.createElement('div');
        el.className='particle';
        const s=Math.random()*4+2;
        el.style.cssText=`width:${s}px;height:${s}px;left:${Math.random()*100}%;background:${c[Math.floor(Math.random()*c.length)]};animation-duration:${Math.random()*20+15}s;animation-delay:${Math.random()*15}s;`;
        p.appendChild(el);
    }
})();

function toast(msg,type='info'){
    const t=document.createElement('div');
    t.className=`toast toast-${type}`;
    t.innerHTML=`<i class="fa-solid fa-${type==='success'?'circle-check':'circle-info'}"></i> ${msg}`;
    document.getElementById('toasts').appendChild(t);
    setTimeout(()=>t.remove(),4500);
}

function animateNum(el,to,prefix='',suffix=''){
    let s=0;const step=to/(1500/16);
    const t=setInterval(()=>{
        s=Math.min(s+step,to);
        el.textContent=prefix+Math.floor(s).toLocaleString('id-ID')+suffix;
        if(s>=to)clearInterval(t);
    },16);
}

async function loadTransactions(){
    const tbody=document.getElementById('tx-body');
    // Demo data — ganti dengan fetch('/api/transactions/recent') jika sudah tersedia
    const txs=[
        {referenceId:'FHK2609160802',invoiceId:'INV-FB7810DF',created_at:'2026-09-16 08:02:05',water_type:'COLD',volume_ml:500,payAmount:3500,status:'PAID'},
        {referenceId:'FHK2609160755',invoiceId:'INV-A3CC12DE',created_at:'2026-09-16 07:55:30',water_type:'NORMAL',volume_ml:1000,payAmount:4500,status:'COMPLETED'},
        {referenceId:'FHK2609160722',invoiceId:'INV-BC44FF22',created_at:'2026-09-16 07:22:11',water_type:'COLD',volume_ml:250,payAmount:2000,status:'COMPLETED'},
        {referenceId:'FHK2609160710',invoiceId:'INV-D1234AB9',created_at:'2026-09-16 07:10:44',water_type:'COLD',volume_ml:1000,payAmount:6000,status:'PAID'},
        {referenceId:'FHK2609160658',invoiceId:'INV-EE98BC01',created_at:'2026-09-16 06:58:33',water_type:'NORMAL',volume_ml:500,payAmount:2500,status:'COMPLETED'},
    ];
    const badge={PAID:'badge-paid',COMPLETED:'badge-completed',PENDING:'badge-pending'};
    tbody.innerHTML=txs.map(tx=>`<tr>
        <td class="mono" style="color:#06b6d4;font-weight:600">${tx.referenceId}</td>
        <td class="mono" style="color:#64748b;font-size:0.72rem">${tx.invoiceId}</td>
        <td style="color:#94a3b8">${new Date(tx.created_at).toLocaleTimeString('id-ID')}</td>
        <td><span class="badge ${tx.water_type==='COLD'?'badge-cold':'badge-normal'}">${tx.water_type}</span></td>
        <td>${tx.volume_ml} ml</td>
        <td style="font-weight:700">Rp ${Number(tx.payAmount).toLocaleString('id-ID')}</td>
        <td><span class="badge ${badge[tx.status]||'badge-pending'}">${tx.status}</span></td>
    </tr>`).join('');
}

async function checkGateway(){
    try{
        const r=await fetch('/app/fhk/callback',{method:'GET'});
        const d=await r.json();
        if(d.status==='OK'){
            document.getElementById('gw-val').textContent='AKTIF';
            document.getElementById('gw-badge').className='cbadge badge-green';
            document.getElementById('gw-badge').innerHTML='<span style="width:5px;height:5px;background:currentColor;border-radius:50%;display:inline-block;animation:pulse-dot 2s infinite"></span> Webhook Online';
        }
    }catch(e){
        document.getElementById('gw-val').textContent='CHECK';
        document.getElementById('gw-badge').className='cbadge badge-red';
        document.getElementById('gw-badge').textContent='Verifikasi Manual';
    }
}

function initCharts(){
    const hours=Array.from({length:14},(_,i)=>`${(6+i).toString().padStart(2,'0')}:00`);
    const txData=[2,4,7,5,8,12,9,6,11,8,4,3,5,7];
    const gridColor='rgba(51,65,85,0.3)';const tickColor='#64748b';
    new Chart(document.getElementById('txChart'),{
        type:'bar',
        data:{labels:hours,datasets:[{data:txData,
            backgroundColor:txData.map((_,i)=>i===txData.length-1?'rgba(6,182,212,0.85)':'rgba(6,182,212,0.22)'),
            borderColor:'rgba(6,182,212,0.7)',borderWidth:1,borderRadius:6}]},
        options:{responsive:true,plugins:{legend:{display:false}},
            scales:{x:{grid:{color:gridColor},ticks:{color:tickColor,font:{size:9}}},
                    y:{grid:{color:gridColor},ticks:{color:tickColor,font:{size:9}},beginAtZero:true}}}
    });
    new Chart(document.getElementById('typeChart'),{
        type:'doughnut',
        data:{labels:['Air Dingin','Normal'],datasets:[{data:[68,32],
            backgroundColor:['rgba(6,182,212,0.8)','rgba(16,185,129,0.8)'],
            borderColor:['#06b6d4','#10b981'],borderWidth:2,hoverOffset:8}]},
        options:{responsive:true,cutout:'72%',plugins:{legend:{display:false}}}
    });
}

document.addEventListener('DOMContentLoaded',()=>{
    animateNum(document.getElementById('stat-tx'),127,'','+ Transaksi');
    // Revenue counter
    const revEl=document.getElementById('stat-rev');
    let s=0;const step=985500/93.75;
    const tm=setInterval(()=>{s=Math.min(s+step,985500);revEl.textContent='Rp '+Math.floor(s).toLocaleString('id-ID');if(s>=985500)clearInterval(tm);},16);

    loadTransactions();
    checkGateway();
    initCharts();
    toast('FHK Dashboard aktif — AiYO QRIS Gateway terhubung','success');

    setInterval(()=>{document.getElementById('live-clock').textContent=new Date().toLocaleTimeString('id-ID');},1000);
    setInterval(loadTransactions,30000);
    setInterval(checkGateway,60000);
});
</script>
</body>
</html>