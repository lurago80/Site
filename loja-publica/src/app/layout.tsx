import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
    title: 'Loja',
    description: 'Compre produtos ou agende sua visita.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
    return (
        <html lang="pt-BR">
            <body>{children}</body>
        </html>
    );
}
