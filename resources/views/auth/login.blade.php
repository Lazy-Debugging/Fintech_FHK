<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0a0f1d">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>Masuk | Fresh Hydration Kios</title>
	<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💧</text></svg>">

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

	<style>
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		:root {
			--cyan:#06b6d4; --blue:#0284c7; --sky:#38bdf8;
			--bg:#030712; --bg2:#0a0f1d;
			--panel:rgba(17,24,39,.72); --border:rgba(148,163,184,.14);
			--text:#f8fafc; --muted:#94a3b8; --danger:#f87171;
		}
		body { min-height:100vh; font-family:'Plus Jakarta Sans',sans-serif; color:var(--text); overflow-x:hidden;
			background:
				radial-gradient(ellipse 70% 55% at 15% -10%, rgba(6,182,212,.18) 0%, transparent 60%),
				radial-gradient(ellipse 55% 45% at 90% 110%, rgba(2,132,199,.14) 0%, transparent 60%),
				linear-gradient(180deg, var(--bg2) 0%, var(--bg) 100%);
		}

		/* Water bubbles backdrop */
		.bubbles { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
		.bubble { position:absolute; bottom:-40px; border-radius:50%;
			background:radial-gradient(circle at 30% 30%, rgba(103,232,249,.25), rgba(6,182,212,.06));
			border:1px solid rgba(103,232,249,.18);
			animation:rise linear infinite; opacity:0; }
		@keyframes rise {
			0% { transform:translateY(0) scale(.6); opacity:0; }
			12% { opacity:.7; }
			100% { transform:translateY(-110vh) scale(1.1); opacity:0; }
		}

		.page { position:relative; z-index:1; min-height:100vh; display:grid; grid-template-columns:minmax(280px,1fr) minmax(320px,1.05fr); }

		/* Left intro panel */
		.intro { display:flex; flex-direction:column; justify-content:space-between; gap:2rem; padding:clamp(28px,5vw,72px); border-right:1px solid var(--border); }
		.brand { display:flex; align-items:center; gap:.75rem; }
		.brand-mark { width:46px; height:46px; border-radius:14px; display:grid; place-items:center; color:#fff; font-size:1.25rem;
			background:linear-gradient(135deg, var(--cyan), var(--blue)); box-shadow:0 8px 24px rgba(6,182,212,.35); }
		.brand-name { font-weight:800; font-size:1rem; letter-spacing:-.01em; }
		.brand-sub { font-size:.62rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--cyan); }
		.intro-copy h1 { font-size:clamp(2.1rem,4.5vw,3.6rem); font-weight:800; line-height:1.06; letter-spacing:-.03em; margin-bottom:1.1rem; }
		.intro-copy h1 .highlight { background:linear-gradient(120deg, #67e8f9, var(--sky), #60a5fa); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
		.intro-copy p { max-width:26rem; color:var(--muted); font-size:.95rem; line-height:1.7; }
		.intro-pills { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:1.5rem; }
		.pill { display:inline-flex; align-items:center; gap:.45rem; padding:.4rem .8rem; border-radius:999px; font-size:.7rem; font-weight:600;
			background:rgba(6,182,212,.1); border:1px solid rgba(6,182,212,.25); color:#67e8f9; }
		.pill i { font-size:.75rem; }
		.intro-foot { display:flex; align-items:center; gap:.6rem; color:#64748b; font-size:.72rem; }
		.intro-foot .dot { width:7px; height:7px; border-radius:50%; background:#34d399; animation:pulse 2s infinite; }
		@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.35)} }

		/* Right form panel */
		.panel { display:grid; place-items:center; padding:2rem 1.25rem; }
		.card { width:min(100%,430px); padding:clamp(26px,4vw,42px); border-radius:22px;
			background:var(--panel); border:1px solid var(--border);
			backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
			box-shadow:0 24px 60px -20px rgba(0,0,0,.6), 0 0 40px rgba(6,182,212,.06); }
		.eyebrow { display:flex; align-items:center; gap:.45rem; margin-bottom:.5rem; color:var(--cyan); font-size:.68rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; }
		h2 { font-size:1.75rem; font-weight:800; letter-spacing:-.02em; margin-bottom:.4rem; }
		.subcopy { color:var(--muted); font-size:.83rem; line-height:1.55; margin-bottom:1.6rem; }

		.button, input { width:100%; min-height:50px; border-radius:14px; font:inherit; font-size:.92rem; }
		.button { display:flex; align-items:center; justify-content:center; gap:.6rem; border:0; cursor:pointer; text-decoration:none; font-weight:700; transition:all .2s; }
		.google { color:#082f49; background:linear-gradient(135deg, #22d3ee, #38bdf8); box-shadow:0 10px 26px -10px rgba(6,182,212,.55); }
		.google:hover { transform:translateY(-1px); box-shadow:0 14px 32px -10px rgba(6,182,212,.7); }
		.google-icon { display:grid; width:22px; height:22px; place-items:center; border-radius:50%; color:var(--blue); background:#fff; font-weight:800; font-size:.8rem; }
		.guest { color:#a5f3fc; border:1px solid rgba(6,182,212,.35); background:rgba(6,182,212,.08); }
		.guest:hover { background:rgba(6,182,212,.16); }

		.divider { display:flex; align-items:center; gap:.8rem; margin:1.4rem 0; color:#475569; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.12em; }
		.divider::before, .divider::after { content:''; height:1px; flex:1; background:var(--border); }

		.admin-label { display:block; margin-bottom:.45rem; color:#cbd5e1; font-size:.72rem; font-weight:700; }
		.field { margin-bottom:.8rem; position:relative; }
		.field i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#475569; font-size:.85rem; pointer-events:none; }
		input { padding:0 1rem 0 2.5rem; border:1px solid var(--border); color:var(--text); background:rgba(2,6,23,.6); outline:none; transition:border-color .2s, box-shadow .2s; }
		input::placeholder { color:#475569; }
		input:focus { border-color:var(--cyan); box-shadow:0 0 0 3px rgba(6,182,212,.15); }
		.admin-button { color:#67e8f9; border:1px solid rgba(6,182,212,.3); background:rgba(6,182,212,.1); }
		.admin-button:hover { background:rgba(6,182,212,.2); }

		.alert { display:flex; align-items:flex-start; gap:.6rem; margin-bottom:1.2rem; padding:.75rem .9rem; border-radius:12px;
			color:#fecaca; background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3); font-size:.8rem; line-height:1.45; }
		.alert i { color:var(--danger); margin-top:.1rem; }

		.footer { margin-top:1.6rem; color:#475569; text-align:center; font-size:.72rem; }
		.footer a { color:var(--cyan); text-decoration:none; font-weight:600; }
		.footer a:hover { text-decoration:underline; }

		@media (max-width:760px) {
			.page { display:block; }
			.intro { padding:2rem 1.4rem 2.2rem; border-right:0; border-bottom:1px solid var(--border); gap:1.6rem; }
			.intro-copy h1 { font-size:2.05rem; }
			.intro-pills { display:none; }
			.panel { padding:1.5rem 1rem 2.5rem; }
		}
	</style>
</head>
<body>
	<div class="bubbles" id="bubbles"></div>

	<div class="page">
		<section class="intro">
			<div class="brand">
				<div class="brand-mark"><i class="fa-solid fa-droplet"></i></div>
				<div>
					<div class="brand-name">FHK System</div>
					<div class="brand-sub">Fresh Hydration Kios</div>
				</div>
			</div>
			<div class="intro-copy">
				<h1>Segar dimulai<br>dari <span class="highlight">sini.</span></h1>
				<p>Pesan air minum UV-steril dengan pembayaran QRIS AiYO, lalu ambil langsung di kios pintar terdekat.</p>
				<div class="intro-pills">
					<span class="pill"><i class="fa-solid fa-shield-virus"></i> UV-C Sterilized 99.9%</span>
					<span class="pill"><i class="fa-solid fa-qrcode"></i> QRIS AiYO Dinamis</span>
					<span class="pill"><i class="fa-solid fa-microchip"></i> IoT ESP32 Realtime</span>
				</div>
			</div>
			<div class="intro-foot">
				<span class="dot"></span>
				<span>Kios <strong style="color:#cbd5e1">FHK-JAKARTA-01</strong> sedang online &amp; siap melayani.</span>
			</div>
		</section>

		<main class="panel">
			<div class="card">
				<p class="eyebrow"><i class="fa-solid fa-droplet"></i> Selamat datang kembali</p>
				<h2>Masuk ke akun</h2>
				<p class="subcopy">Pilih cara masuk yang paling nyaman untuk melanjutkan pemesanan air minum Anda.</p>

				@if ($errors->any())
					<div class="alert"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
				@endif

				<a class="button google" href="{{ route('google.redirect') }}"><span class="google-icon">G</span>Masuk dengan Google</a>

				<div class="divider">atau</div>

				<form method="post" action="{{ route('login.guest') }}">
					@csrf
					<button class="button guest" type="submit"><i class="fa-solid fa-user-clock"></i>Lanjut sebagai tamu</button>
				</form>

				<p class="footer"><a href="{{ route('home') }}">&larr; Kembali ke beranda</a> &middot; <a href="{{ route('admin.login') }}">Masuk sebagai pengelola</a></p>
			</div>
		</main>
	</div>

	<script>
		(function () {
			const wrap = document.getElementById('bubbles');
			for (let i = 0; i < 16; i++) {
				const b = document.createElement('div');
				b.className = 'bubble';
				const s = Math.random() * 18 + 6;
				b.style.cssText = `width:${s}px;height:${s}px;left:${Math.random() * 100}%;animation-duration:${Math.random() * 14 + 12}s;animation-delay:${Math.random() * 12}s;`;
				wrap.appendChild(b);
			}
		})();
	</script>
</body>
</html>
