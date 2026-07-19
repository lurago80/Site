'use client';

import { useState } from 'react';
import { useCarrinho } from '@/lib/cart';
import type { HorarioAgenda, Produto } from '@/lib/types';

function formatarMoeda(valor: string | number) {
    return `R$ ${Number(valor).toFixed(2)}`;
}

function formatarDataHora(iso: string) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
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
        <div style={{ display: 'flex', flexDirection: 'column', gap: 32 }}>
            {produtos.length > 0 && (
                <section>
                    <h2 style={{ fontSize: 18, marginBottom: 12 }}>Produtos</h2>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
                            gap: 12,
                        }}
                    >
                        {produtos.map((produto) => (
                            <div key={produto.id} className="cartao" style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                <strong style={{ fontSize: 14 }}>{produto.nome}</strong>
                                {produto.descricao && (
                                    <span style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>{produto.descricao}</span>
                                )}
                                <span style={{ fontSize: 16, fontWeight: 700, color: 'var(--cor-primaria)' }}>
                                    {formatarMoeda(produto.preco_venda)}
                                </span>
                                <button
                                    className="botao-primario"
                                    disabled={produto.estoque_atual !== null && produto.estoque_atual <= 0}
                                    onClick={() => adicionarProduto(produto.id, produto.nome, Number(produto.preco_venda))}
                                >
                                    {produto.estoque_atual !== null && produto.estoque_atual <= 0
                                        ? 'Sem estoque'
                                        : 'Adicionar ao carrinho'}
                                </button>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {moduloAgendamentoAtivo && agenda.length > 0 && (
                <section>
                    <h2 style={{ fontSize: 18, marginBottom: 12 }}>Agende sua visita</h2>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {agenda.map((horario) => {
                            const quantidade = quantidadesAgenda[horario.id] ?? 1;
                            return (
                                <div
                                    key={horario.id}
                                    className="cartao"
                                    style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}
                                >
                                    <div>
                                        <strong style={{ fontSize: 14 }}>{formatarDataHora(horario.data_hora)}</strong>
                                        <div style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>
                                            {horario.vagas_disponiveis} vagas disponíveis · {formatarMoeda(horario.valor_visita)} por pessoa
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <input
                                            type="number"
                                            min={1}
                                            max={horario.vagas_disponiveis}
                                            value={quantidade}
                                            style={{ width: 60 }}
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
                <p style={{ color: 'var(--cor-texto-suave)' }}>Nenhum produto ou horário disponível no momento.</p>
            )}
        </div>
    );
}
