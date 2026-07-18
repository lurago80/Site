{{--
    Barra superior padrão do sistema interno - logo + nome do usuário +
    sair. Usado por login, painel fiscal, PDV e super admin. O dashboard
    tem seu próprio cabeçalho dentro da sidebar (ver dashboard/painel.blade.php).

    Espera (opcional): $titulo - texto extra ao lado do logo.
--}}
<div class="topo-sistema">
    <div class="marca">
        <img src="/images/logo.jpg" alt="Logo">
        @isset($titulo)
            <span class="titulo">{{ $titulo }}</span>
        @endisset
    </div>
    @auth
        <form method="POST" action="/logout" style="display:flex; align-items:center;">
            @csrf
            <span class="usuario">{{ auth()->user()->name }}</span>
            <button type="submit" class="secundario">Sair</button>
        </form>
    @endauth
</div>
