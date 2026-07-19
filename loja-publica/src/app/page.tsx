export default function PaginaInicial() {
    return (
        <div style={{ display: 'flex', minHeight: '100vh', alignItems: 'center', justifyContent: 'center', textAlign: 'center' }}>
            <div>
                <h1>Loja</h1>
                <p style={{ color: 'var(--cor-texto-suave)' }}>
                    Acesse a loja de uma empresa específica pela URL, ex.: <code>/nome-da-empresa</code>
                </p>
            </div>
        </div>
    );
}
