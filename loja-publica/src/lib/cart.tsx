'use client';

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import type { ItemCarrinho, ItemCarrinhoAgenda, ItemCarrinhoProduto } from './types';

interface CarrinhoContexto {
    itens: ItemCarrinho[];
    total: number;
    adicionarProduto: (produtoId: number, nome: string, valorUnitario: number) => void;
    definirAgenda: (agendaId: number, nome: string, quantidade: number, valorUnitario: number) => void;
    removerItem: (index: number) => void;
    limpar: () => void;
}

const Contexto = createContext<CarrinhoContexto | null>(null);

function chaveStorage(empresa: string) {
    return `carrinho:${empresa}`;
}

export function CarrinhoProvider({ empresa, children }: { empresa: string; children: ReactNode }) {
    const [itens, setItens] = useState<ItemCarrinho[]>([]);

    useEffect(() => {
        const salvo = localStorage.getItem(chaveStorage(empresa));
        if (salvo) {
            try {
                setItens(JSON.parse(salvo));
            } catch {
                // carrinho salvo corrompido - ignora e começa vazio
            }
        }
    }, [empresa]);

    useEffect(() => {
        localStorage.setItem(chaveStorage(empresa), JSON.stringify(itens));
    }, [empresa, itens]);

    function adicionarProduto(produtoId: number, nome: string, valorUnitario: number) {
        setItens((atual) => {
            const existente = atual.find(
                (i): i is ItemCarrinhoProduto => i.tipo === 'produto' && i.produtoId === produtoId,
            );
            if (existente) {
                return atual.map((i) =>
                    i === existente ? { ...existente, quantidade: existente.quantidade + 1 } : i,
                );
            }
            return [...atual, { tipo: 'produto', produtoId, nome, quantidade: 1, valorUnitario }];
        });
    }

    function definirAgenda(agendaId: number, nome: string, quantidade: number, valorUnitario: number) {
        setItens((atual) => {
            const semAgenda = atual.filter((i) => i.tipo !== 'agenda');
            const novoItem: ItemCarrinhoAgenda = { tipo: 'agenda', agendaId, nome, quantidade, valorUnitario };
            return [...semAgenda, novoItem];
        });
    }

    function removerItem(index: number) {
        setItens((atual) => atual.filter((_, i) => i !== index));
    }

    function limpar() {
        setItens([]);
    }

    const total = itens.reduce((soma, item) => soma + item.quantidade * item.valorUnitario, 0);

    return (
        <Contexto.Provider value={{ itens, total, adicionarProduto, definirAgenda, removerItem, limpar }}>
            {children}
        </Contexto.Provider>
    );
}

export function useCarrinho(): CarrinhoContexto {
    const contexto = useContext(Contexto);
    if (!contexto) {
        throw new Error('useCarrinho precisa estar dentro de um CarrinhoProvider.');
    }
    return contexto;
}
