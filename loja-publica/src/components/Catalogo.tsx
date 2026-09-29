'use client';

import { useState } from 'react';
import { useCarrinho } from '@/lib/cart';
import type { HorarioAgenda, Produto, ProdutoVariacao } from '@/lib/types';

function formatarMoeda(valor: string | number) {
    return `R$ ${Number(valor).toFixed(2)}`;
}

function formatarDataHora(iso: string) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function IniciaisProduto({ nome }: { nome: string }) {
    return (
        <div
            style={{
                width: '100%',
                aspectRatio: '1 / 1',
                borderRadius: 'var(--raio-sm)',
                background: 'linear-gradient(135deg, var(--cor-primaria-clara), var(--cor-borda))',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'var(--cor-primaria)',
                fontSize: 28,
                fontWeight: 700,
            }}
        >
            {nome.charAt(0).toUpperCase()}
        </div>
    );
}

function CardProduto({
    produto,
    onAdicionar,
}: {
    produto: Produto;
    onAdicionar: (variacao: ProdutoVariacao | null) => void;
}) {
    const [imagemQuebrada, setImagemQuebrada] = useState(false);
    const temVariacoes = !!produto.variacoes && produto.variacoes.length > 0;
    const [tamanhoEscolhido, setTamanhoEscolhido] = useState<number | ''>('');
    const variacaoEscolhida = temVariacoes
        ? produto.variacoes!.find((v) => v.id === tamanhoEscolhido) ?? null
        : null;
    const semEstoque = temVariacoes
        ? variacaoEscolhida !== null && variacaoEscolhida.estoque_atual <= 0
        : produto.estoque_atual !== null && produto.estoque_atual <= 0;
    const precisaEscolherTamanho = temVariacoes && tamanhoEscolhido === '';

    return (
        <div
            className="cartao"
            style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 10,
                padding: 12,
                overflow: 'hidden',
                transition: 'box-shadow .18s ease, transform .18s ease',
            }}
            onMouseEnter={(e) => {
                e.currentTarget.style.boxShadow = 'var(--sombra-md)';
                e.currentTarget.style.transform = 'translateY(-2px)';
            }}
            onMouseLeave={(e) => {
                e.currentTarget.style.boxShadow = 'var(--sombra-sm)';
                e.currentTarget.style.transform = 'none';
            }}
        >
            <div style={{ position: 'relative', borderRadius: 'var(--raio-sm)', overflow: 'hidden' }}>
                {produto.imagem_url && !imagemQuebrada ? (
                    // eslint-disable-next-line @next/next/no-img-element -- URL vem de qualquer host que o lojista cadastrar, não dá pra pré-configurar domínios do next/image
                    <img
                        src={produto.imagem_url}
                        alt={produto.nome}
                        style={{
                            width: '100%',
                            aspectRatio: '1 / 1',
                            objectFit: 'cover',
                            background: 'var(--cor-fundo)',
                            transition: 'transform .25s ease',
                        }}
                        onError={() => setImagemQuebrada(true)}
                        onMouseEnter={(e) => (e.currentTarget.style.transform = 'scale(1.05)')}
                        onMouseLeave={(e) => (e.currentTarget.style.transform = 'none')}
                    />
                ) : (
                    <IniciaisProduto nome={produto.nome} />
                )}
                {semEstoque && (
                    <span
                        style={{
                            position: 'absolute',
                            top: 8,
                            right: 8,
                            background: 'rgba(22,24,29,.75)',
                            color: '#fff',
                            fontSize: 11,
                            fontWeight: 600,
                            padding: '3px 8px',
                            borderRadius: 999,
                        }}
                    >
                        Esgotado
                    </span>
                )}
            </div>

            <div style={{ padding: '2px 4px 4px', display: 'flex', flexDirection: 'column', gap: 6, flex: 1 }}>
                <strong style={{ fontSize: 14, lineHeight: 1.3 }}>{produto.nome}</strong>
                {produto.descricao && (
                    <span style={{ fontSize: 12.5, color: 'var(--cor-texto-suave)', lineHeight: 1.4 }}>{produto.descricao}</span>
                )}

                <div style={{ marginTop: 'auto', display: 'flex', flexDirection: 'column', gap: 8, paddingTop: 6 }}>
                    <span style={{ fontSize: 18, fontWeight: 700, color: 'var(--cor-primaria)', letterSpacing: '-.01em' }}>
                        {formatarMoeda(produto.preco_venda)}
                    </span>
                    {temVariacoes && (
                        <select
                            value={tamanhoEscolhido}
                            onChange={(e) => setTamanhoEscolhido(e.target.value ? Number(e.target.value) : '')}
                            style={{ width: '100%' }}
                        >
                            <option value="">Escolha o tamanho</option>
                            {produto.variacoes!.map((v) => (
                                <option key={v.id} value={v.id} disabled={v.estoque_atual <= 0}>
                                    {v.tamanho}
                                    {v.estoque_atual <= 0 ? ' (sem estoque)' : ''}
                                </option>
                            ))}
                        </select>
                    )}
                    <button
                        className="botao-primario"
                        disabled={semEstoque || precisaEscolherTamanho}
                        onClick={() => onAdicionar(variacaoEscolhida)}
                        style={{ width: '100%' }}
                    >
                        {semEstoque ? 'Sem estoque' : 'Adicionar ao carrinho'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function Catalogo({
    produtos,
    agenda,
    moduloAgendamentoAtivo,
}: {
    produtos: Produto[];
    agenda: HorarioAgenda[];
    moduloAgendamentoAtivo: boolean;
}) {
    const { adicionarProduto, definirAgenda } = useCarrinho();
    const [quantidadesAgenda, setQuantidadesAgenda] = useState<Record<number, number>>({});

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 40 }}>
            {produtos.length > 0 && (
                <section>
                    <div style={{ display: 'flex', alignItems: 'baseline', gap: 10, marginBottom: 18 }}>
                        <h2 style={{ fontSize: 20, margin: 0, letterSpacing: '-.01em' }}>Produtos</h2>
                        <span style={{ fontSize: 13, color: 'var(--cor-texto-suave)' }}>
                            {produtos.length} {produtos.length === 1 ? 'item' : 'itens'}
                        </span>
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(auto-fill, minmax(210px, 1fr))',
                            gap: 20,
                        }}
                    >
                        {produtos.map((produto) => (
                            <CardProduto
                                key={produto.id}
                                produto={produto}
                                onAdicionar={(variacao) =>
                                    adicionarProduto(
                                        produto.id,
                                        variacao ? `${produto.nome} (${variacao.tamanho})` : produto.nome,
                                        Number(produto.preco_venda),
                                        variacao ? { id: variacao.id, tamanho: variacao.tamanho } : null,
                                    )
                                }
                            />
                        ))}
                    </div>
                </section>
            )}

            {moduloAgendamentoAtivo && agenda.length > 0 && (
                <section>
                    <h2 style={{ fontSize: 20, margin: '0 0 18px', letterSpacing: '-.01em' }}>Agende sua visita</h2>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        {agenda.map((horario) => {
                            const quantidade = quantidadesAgenda[horario.id] ?? 1;
                            return (
                                <div
                                    key={horario.id}
                                    className="cartao"
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: 12,
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    <div>
                                        <strong style={{ fontSize: 14 }}>{formatarDataHora(horario.data_hora)}</strong>
                                        <div style={{ fontSize: 12.5, color: 'var(--cor-texto-suave)', marginTop: 2 }}>
                                            {horario.vagas_disponiveis} vagas disponíveis · {formatarMoeda(horario.valor_visita)} por pessoa
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <input
                                            type="number"
                                            min={1}
                                            max={horario.vagas_disponiveis}
                                            value={quantidade}
                                            style={{ width: 64 }}
                                            onChange={(e) =>
                                                setQuantidadesAgenda((atual) => ({
                                                    ...atual,
                                                    [horario.id]: Number(e.target.value),
                                                }))
                                            }
                                        />
                                        <button
                                            className="botao-primario"
                                            onClick={() =>
                                                definirAgenda(
                                                    horario.id,
                                                    `Visita em ${formatarDataHora(horario.data_hora)}`,
                                                    quantidade,
                                                    Number(horario.valor_visita),
                                                )
                                            }
                                        >
                                            Reservar
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>
            )}

            {produtos.length === 0 && agenda.length === 0 && (
                <div
                    className="cartao"
                    style={{ textAlign: 'center', padding: '64px 20px', color: 'var(--cor-texto-suave)' }}
                >
                    <div style={{ fontSize: 36, marginBottom: 12 }}>🛍️</div>
                    <p style={{ fontSize: 15, margin: 0 }}>Nenhum produto ou horário disponível no momento.</p>
                </div>
            )}
        </div>
    );
}
