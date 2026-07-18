<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Entrar — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #1f2933; height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
        .card { background: #fff; border-radius: 8px; padding: 32px; width: 320px; box-shadow: 0 4px 16px rgba(0,0,0,.2); }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.sub { font-size: 12px; color: #616e7c; margin: 0 0 20px; }
        label { display: block; font-size: 12px; color: #616e7c; margin-bottom: 4px; }
        input { width: 100%; padding: 8px 10px; margin-bottom: 14px; border-radius: 4px; border: 1px solid #cbd2d9; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #1a56db; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
        .erro { background: #ffe3e3; color: #b02525; font-size: 12px; padding: 8px 10px; border-radius: 4px; margin-bottom: 14px; }
    </style>
</head>
<body>
    <form class="card" method="POST" action="/login">
        @csrf
        <h1>Acessar sistema</h1>
        <p class="sub">{{ config('app.name') }} — o e-mail identifica automaticamente a empresa e o nível de acesso.</p>

        @if ($errors->any())
            <div class="erro">{{ $errors->first() }}</div>
        @endif

        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Entrar</button>
    </form>
</body>
</html>
