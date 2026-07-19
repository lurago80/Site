<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Acesso bloqueado — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/sistema.css">
    <style>
        body { height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--cor-primaria-escura); }
        .card { width: 380px; box-shadow: 0 8px 24px rgba(0,0,0,.25); text-align: center; }
        .card img { height: 48px; margin-bottom: 12px; }
        h1 { font-size: 16px; margin: 0 0 12px; color: var(--cor-texto); }
        p { font-size: 13px; color: var(--cor-texto-suave); line-height: 1.5; margin: 0 0 20px; }
        button[type="submit"] { width: 100%; padding: 10px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <img src="/images/logo.jpg" alt="Logo">
        <h1>Acesso bloqueado</h1>
        <p>{{ $exception->getMessage() ?: 'Você não tem permissão para acessar esta página.' }}</p>
        <form method="POST" action="/logout">
            @csrf
            <button type="submit">Sair e voltar ao login</button>
        </form>
    </div>
</body>
</html>
