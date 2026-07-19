<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Esqueci minha senha — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/sistema.css">
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
        .voltar { display: block; text-align: center; font-size: 12px; margin-top: 14px; }
    </style>
</head>
<body>
    <form class="card" method="POST" action="/esqueci-senha">
        @csrf
        <img src="/images/logo.jpg" alt="Logo">
        <h1>Esqueci minha senha</h1>
        <p class="sub">Informe o e-mail cadastrado - se ele existir no sistema, enviamos um link para redefinir a senha.</p>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="erro">{{ $errors->first() }}</div>
        @endif

        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

        <button type="submit">Enviar link de redefinição</button>
        <a class="voltar" href="/login">Voltar ao login</a>
    </form>
</body>
</html>
