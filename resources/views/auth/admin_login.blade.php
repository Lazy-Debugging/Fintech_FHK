<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0a0f1d">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>Masuk Admin | Fresh Hydration Kios</title>
	<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💧</text></svg>">

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

	<style>
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		:root {
			--cyan:#06b6d4; --blue:#0284c7; --purple:#8b5cf6;
			--bg:#030712; --bg2:#0a0f1d;
			--panel:rgba(17,24,39,.72); --border:rgba(148,163,184,.14);
			--text:#f8fafc; --muted:#94a3b8; --danger:#f87171;
		}
		body { min-height:100vh; font-family:'Plus Jakarta Sans',sans-serif; color:var(--text); display:grid; place-items:center; padding:1.5rem;
			background:
				radial-gradient(ellipse 60% 50% at 85% -10%, rgba(139,92,246,.16) 0%, transparent 60%),
				radial-gradient(ellipse 55% 45% at 10% 110%, rgba(6,182,212,.12) 0%, transparent 60%),
				linear-gradient(180deg, var(--bg2) 0%, var(--bg) 100%);
		}
		.card { width:min(100%,420px); padding:clamp(26px,4vw,42px); border-radius:22px;
			background:var(--panel); border:1px solid var(--border);
			backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
			box-shadow:0 24px 60px -20px rgba(0,0,0,.6), 0 0 40px rgba(139,92,246,.08); }
		.brand { display:flex; align-items:center; gap:.75rem; margin-bottom:1.6rem; }
		.brand-mark { width:46px; height:46px; border-radius:14px; display:grid; place-items:center; color:#fff; font-size:1.2rem;
			background:linear-gradient(135deg, var(--purple), var(--blue)); box-shadow:0 8px 24px rgba(139,92,246,.35); }
		.brand-name { font-weight:800; font-size:.95rem; letter-spacing:-.01em; }
		.brand-sub { font-size:.6rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--purple); }
		.eyebrow { display:flex; align-items:center; gap:.45rem; margin-bottom:.5rem; color:var(--purple); font-size:.68rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; }
		h2 { font-size:1.6rem; font-weight:800; letter-spacing:-.02em; margin-bottom:.4rem; }
		.subcopy { color:var(--muted); font-size:.83rem; line-height:1.55; margin-bottom:1.6rem; }

		.button, input { width:100%; min-height:50px; border-radius:14px; font:inherit; font-size:.92rem; }
		.button { display:flex; align-items:center; justify-content:center; gap:.6rem; border:0; cursor:pointer; font-weight:700; transition:all .2s; }
		.admin-button { color:#fff; background:linear-gradient(135deg, var(--purple), var(--blue)); box-shadow:0 10px 26px -10px rgba(139,92,246,.55); }
		.admin-button:hover { transform:translateY(-1px); box-shadow:0 14px 32px -10px rgba(139,92,246,.7); }

		.admin-label { display:block; margin-bottom:.45rem; color:#cbd5e1; font-size:.72rem; font-weight:700; }
		.field { margin-bottom:1rem; position:relative; }
		.field i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#475569; font-size:.85rem; pointer-events:none; }
		input { padding:0 1rem 0 2.5rem; border:1px solid var(--border); color:var(--text); background:rgba(2,6,23,.6); outline:none; transition:border-color .2s, box-shadow .2s; }
		input::placeholder { color:#475569; }
		input:focus { border-color:var(--purple); box-shadow:0 0 0 3px rgba(139,92,246,.15); }

		.alert { display:flex; align-items:flex-start; gap:.6rem; margin-bottom:1.2rem; padding:.75rem .9rem; border-radius:12px;
			color:#fecaca; background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3); font-size:.8rem; line-height:1.45; }
		.alert i { color:var(--danger); margin-top:.1rem; }

		.footer { margin-top:1.6rem; color:#475569; text-align:center; font-size:.72rem; }
		.footer a { color:var(--cyan); text-decoration:none; font-weight:600; }
		.footer a:hover { text-decoration:underline; }
	</style>
</head>
<body>
	<div class="card">
		<div class="brand">
			<div class="brand-mark"><i class="fa-solid fa-gauge-high"></i></div>
			<div>
				<div class="brand-name">FHK Admin Panel</div>
				<div class="brand-sub">Fresh Hydration Kios</div>
			</div>
		</div>

		<p class="eyebrow"><i class="fa-solid fa-user-shield"></i> Akses terbatas</p>
		<h2>Masuk sebagai pengelola</h2>
		<p class="subcopy">Halaman ini khusus untuk administrator kios. Pelanggan silakan masuk lewat halaman login utama.</p>

		@if ($errors->any())
			<div class="alert"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
		@endif

		<form method="post" action="{{ route('login.admin') }}">
			@csrf
			<label class="admin-label" for="username">Username admin</label>
			<div class="field"><i class="fa-solid fa-user-shield"></i><input id="username" name="username" placeholder="Masukkan username" value="{{ old('username') }}" required autofocus></div>
			<label class="admin-label" for="password">Password</label>
			<div class="field"><i class="fa-solid fa-lock"></i><input id="password" name="password" type="password" placeholder="Masukkan password" required></div>
			<button class="button admin-button" type="submit"><i class="fa-solid fa-right-to-bracket"></i>Masuk ke Admin Panel</button>
		</form>

		<p class="footer"><a href="{{ route('login') }}">&larr; Kembali ke login pelanggan</a></p>
	</div>
</body>
</html>
