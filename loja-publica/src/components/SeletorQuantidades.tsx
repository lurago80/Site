'use client';

import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

export interface OpcaoSeletor {
    id: number;
    tamanho: string;
    estoque_atual: number;
}

export interface GrupoSeletor {
    chave: string;
    titulo: string;
    alvo: number;
    // 'exato': precisa fechar exatamente `alvo` (kit). 'minimo': `alvo` ou mais (venda mínima).
    modo: 'exato' | 'minimo';
    opcoes: OpcaoSeletor[];
    // Só informativo: unidades do mesmo produto que já estão no carrinho.
    jaNoCarrinho?: number;
}

export type SelecaoSeletor = Record<string, Record<number, number>>;

function somaGrupo(selecao: Record<number, number> | undefined) {
    return Object.values(selecao ?? {}).reduce((acc, q) => acc + q, 0);
}

export default function SeletorQuantidades({
    titulo,
    grupos,
    textoConfirmar,
    onConfirmar,
    onFechar,
}: {
    titulo: string;
    grupos: GrupoSeletor[];
    textoConfirmar: string;
    onConfirmar: (selecao: SelecaoSeletor) => void;
    onFechar: () => void;
}) {
    const [selecao, setSelecao] = useState<SelecaoSeletor>({});

    useEffect(() => {
        const aoTeclar = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onFechar();
        };
        const overflowAnterior = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', aoTeclar);
        return () => {
            window.removeEventListener('keydown', aoTeclar);
            document.body.style.overflow = overflowAnterior;
        };
    }, [onFechar]);

    function alterar(grupo: GrupoSeletor, opcaoId: number, delta: number) {
        setSelecao((atual) => {
            const doGrupo = { ...(atual[grupo.chave] ?? {}) };
            const novo = (doGrupo[opcaoId] ?? 0) + delta;
            if (novo <= 0) delete doGrupo[opcaoId];
            else doGrupo[opcaoId] = novo;
            return { ...atual, [grupo.chave]: doGrupo };
        });
    }

    const completo = grupos.every((g) => {
        const soma = somaGrupo(selecao[g.chave]);
        return g.modo === 'exato' ? soma === g.alvo : soma >= g.alvo;
    });

    return createPortal(
        <div
            role="dialog"
            aria-modal="true"
            aria-label={titulo}
            onClick={onFechar}
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 1000,
                background: 'rgba(10,12,16,.7)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 16,
            }}
        >
            <div
                className="cartao"
                onClick={(e) => e.stopPropagation()}
                style={{ width: '100%', maxWidth: 480, maxHeight: '90vh', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 16 }}
            >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
                    <h2 style={{ fontSize: 17, margin: 0 }}>{titulo}</h2>
                    <button className="botao-secundario" onClick={onFechar} aria-label="Fechar" style={{ padding: '4px 10px' }}>
                        ×
                    </button>
                </div>

                {grupos.map((grupo) => {
                    const soma = somaGrupo(selecao[grupo.chave]);
                    const cheio = grupo.modo === 'exato' && soma >= grupo.alvo;
                    const ok = grupo.modo === 'exato' ? soma === grupo.alvo : soma >= grupo.alvo;

                    return (
                        <div key={grupo.chave} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'baseline' }}>
                                <strong style={{ fontSize: 14 }}>{grupo.titulo}</strong>
                                <span style={{ fontSize: 12.5, fontWeight: 600, color: ok ? 'var(--cor-ok-texto)' : 'var(--cor-texto-suave)' }}>
                                    {soma} de {grupo.alvo}
                                    {grupo.modo === 'minimo' ? ' (mínimo)' : ''}
                                </span>
                            </div>
                            {!!grupo.jaNoCarrinho && grupo.jaNoCarrinho > 0 && (
                                <span style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>
                                    Você já tem {grupo.jaNoCarrinho} no carrinho.
                                </span>
                            )}

                            {grupo.opcoes.map((opcao) => {
                                const qtd = selecao[grupo.chave]?.[opcao.id] ?? 0;
                                const semEstoque = opcao.estoque_atual <= 0;
                                const noLimite = qtd >= opcao.estoque_atual;

                                return (
                                    <div
                                        key={opcao.id}
                                        style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, opacity: semEstoque ? 0.5 : 1 }}
                                    >
                                        <span style={{ fontSize: 14 }}>
                                            {opcao.tamanho}
                                            {semEstoque && <em style={{ fontSize: 12 }}> (sem estoque)</em>}
                                        </span>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                            <button
                                                className="botao-secundario"
                                                disabled={qtd === 0}
                                                onClick={() => alterar(grupo, opcao.id, -1)}
                                                style={{ padding: '6px 12px', lineHeight: 1 }}
                                                aria-label={`Diminuir ${opcao.tamanho}`}
                                            >
                                                −
                                            </button>
                                            <span style={{ minWidth: 22, textAlign: 'center', fontSize: 15, fontWeight: 600 }}>{qtd}</span>
                                            <button
                                                className="botao-secundario"
                                                disabled={semEstoque || noLimite || cheio}
                                                onClick={() => alterar(grupo, opcao.id, 1)}
                                                style={{ padding: '6px 12px', lineHeight: 1 }}
                                                aria-label={`Aumentar ${opcao.tamanho}`}
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    );
                })}

                <button className="botao-primario" disabled={!completo} onClick={() => onConfirmar(selecao)} style={{ width: '100%' }}>
                    {textoConfirmar}
                </button>
            </div>
        </div>,
        document.body,
    );
}
