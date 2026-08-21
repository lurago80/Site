<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Entrar — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/sistema.css') }}">
    <style>
        body { height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--cor-primaria-escura); }
        .card { width: 340px; box-shadow: 0 8px 24px rgba(0,0,0,.25); text-align: center; }
        .card img { height: 48px; margin-bottom: 12px; }
        h1 { font-size: 16px; margin: 0 0 4px; color: var(--cor-texto); text-align: left; }
        p.sub { font-size: 12px; color: var(--cor-texto-suave); margin: 0 0 20px; text-align: left; }
        label { display: block; font-size: 12px; color: var(--cor-texto-suave); margin-bottom: 4px; text-align: left; }
        input { width: 100%; margin-bottom: 14px; box-sizing: border-box; }
        button[type="submit"] { width: 100%; padding: 10px; font-size: 14px; }
        .erro { background: var(--cor-perigo-bg); color: var(--cor-perigo-texto); font-size: 12px; padding: 8px 10px; border-radius: 4px; margin-bottom: 14px; text-align: left; }
        .status { background: var(--cor-sucesso-bg, #e6f7ec); color: var(--cor-sucesso-texto, #1e7e34); font-size: 12px; padding: 8px 10px; border-radius: 4px; margin-bottom: 14px; text-align: left; }
        .link-esqueci { display: block; text-align: right; font-size: 12px; margin: -6px 0 14px; }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ url('/login') }}">
        @csrf
        <img src="{{ asset('images/logo.jpg') }}" alt="Logo">
        <h1>Acessar sistema</h1>
        <p class="sub">{{ config('app.name') }} — o e-mail identifica automaticamente a empresa e o nível de acesso.</p>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="erro">{{ $errors->first() }}</div>
        @endif

        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required>

        <a class="link-esqueci" href="{{ url('/esqueci-senha') }}">Esqueci minha senha</a>

        <button type="submit">Entrar</button>
    </form>
</body>
</html>
