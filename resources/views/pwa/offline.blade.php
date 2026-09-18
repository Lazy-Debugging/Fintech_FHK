<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a0f1d">
    <title>Kios Sedang Offline | Fresh Hydration Kios</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💧</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 1.5rem;
            font-family: 'Plus Jakarta Sans', sans-serif; color: #f8fafc;
            background:
                radial-gradient(ellipse 70% 55% at 15% -10%, rgba(6,182,212,.16) 0%, transparent 60%),
                radial-gradient(ellipse 55% 45% at 90% 110%, rgba(2,132,199,.12) 0%, transparent 60%),
                linear-gradient(180deg, #0a0f1d 0%, #030712 100%);
        }
        main {
            width: min(100%, 26rem); text-align: center; padding: 2.5rem 2rem;
            border-radius: 22px; background: rgba(17,24,39,.72);
            border: 1px solid rgba(148,163,184,.14);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 24px 60px -20px rgba(0,0,0,.6);
        }
        .mark {
            width: 4.25rem; height: 4.25rem; display: grid; place-items: center; margin: 0 auto 1.5rem;
            border-radius: 1.25rem; font-size: 1.9rem; color: #67e8f9;
            background: linear-gradient(135deg, rgba(6,182,212,.2), rgba(2,132,199,.12));
            border: 1px solid rgba(6,182,212,.3); box-shadow: 0 10px 26px -10px rgba(6,182,212,.45);
        }
        .brand-sub { font-size: .62rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #06b6d4; margin-bottom: .4rem; }
        h1 { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; }
        p { margin-top: .75rem; color: #94a3b8; font-size: .88rem; line-height: 1.65; }
        button {
            margin-top: 1.5rem; width: 100%; min-height: 50px; border: 0; border-radius: 14px;
            font: inherit; font-size: .92rem; font-weight: 700; color: #082f49; cursor: pointer;
            background: linear-gradient(135deg, #22d3ee, #38bdf8);
            box-shadow: 0 10px 26px -10px rgba(6,182,212,.55); transition: all .2s;
        }
        button:hover { transform: translateY(-1px); box-shadow: 0 14px 32px -10px rgba(6,182,212,.7); }
        .foot { margin-top: 1.5rem; color: #475569; font-size: .72rem; }
    </style>
</head>
<body>
    <main>
        <div class="mark">💧</div>
        <div class="brand-sub">Fresh Hydration Kios</div>
        <h1>Kios sedang offline</h1>
        <p>Periksa koneksi internet perangkat, lalu muat ulang untuk melanjutkan transaksi air minum Anda.</p>
        <button type="button" onclick="window.location.reload()">Coba Lagi</button>
        <div class="foot">FHK PWA &middot; Mode offline didukung cache service worker</div>
    </main>
</body>
</html>
