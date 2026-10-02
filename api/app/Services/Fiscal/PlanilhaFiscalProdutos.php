<?php

namespace App\Services\Fiscal;

use App\Models\Produto;
use App\Models\TabClassTrib;
use App\Models\TabCredPres;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Validator;

/**
 * Planilha de dados fiscais dos produtos: a empresa exporta, o contador
 * preenche e a mesma planilha volta para o banco. A chave de retorno é a
 * coluna "ID"; só os campos fiscais são regravados (nome, preço, estoque etc.
 * ficam como informativos e são ignorados na importação).
 */
class PlanilhaFiscalProdutos
{
    private const ABA = 'Produtos';

    /** Colunas só de consulta (cinza): ajudam o contador a identificar o produto. */
    private const INFORMATIVAS = [
        'id' => 'ID',
        'codigo' => 'Código',
        'nome' => 'Produto',
        'unidade' => 'Un.',
        'preco_venda' => 'Preço venda',
        'codigo_barras' => 'Cód. barras',
    ];

    /**
     * Campos fiscais editáveis: coluna => [cabeçalho, tipo, regra de validação].
     * tipo: t = texto (preserva zeros à esquerda), n = número.
     */
    private const FISCAIS = [
        'ncm' => ['NCM', 't', ['nullable', 'digits:8']],
        'cest' => ['CEST', 't', ['nullable', 'digits:7']],
        'tipo_produto_fiscal' => ['Tipo fiscal', 't', ['nullable', 'in:consumo,materia_prima,produto,servico,brinde']],
        'cfop_padrao' => ['CFOP (dentro do estado)', 't', ['nullable', 'digits:4']],
        'cfop_interestadual' => ['CFOP (interestadual)', 't', ['nullable', 'digits:4']],
        'grupo_fiscal' => ['Grupo fiscal', 't', ['nullable', 'string', 'max:255']],

        'cst_origem' => ['Origem (0-8)', 't', ['nullable', 'regex:/^[0-8]$/']],
        'cst_icms' => ['CST ICMS', 't', ['nullable', 'regex:/^\d{2}$/']],
        'aliquota_icms' => ['Alíq. ICMS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'reducao_base_calculo_icms' => ['Redução BC ICMS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'mva_percentual' => ['MVA %', 'n', ['nullable', 'numeric', 'min:0', 'max:999']],
        'fcp_percentual' => ['FCP %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'codigo_beneficio_fiscal' => ['Cód. benefício fiscal', 't', ['nullable', 'string', 'max:255']],

        'cst_pis' => ['CST PIS', 't', ['nullable', 'regex:/^\d{2}$/']],
        'aliquota_pis' => ['Alíq. PIS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'cst_cofins' => ['CST COFINS', 't', ['nullable', 'regex:/^\d{2}$/']],
        'aliquota_cofins' => ['Alíq. COFINS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'natureza_receita_pis_cofins' => ['Natureza receita PIS/COFINS', 't', ['nullable', 'string', 'max:255']],

        'cst_ipi' => ['CST IPI', 't', ['nullable', 'regex:/^\d{2}$/']],
        'aliquota_ipi' => ['Alíq. IPI %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'codigo_enquadramento_ipi' => ['Cód. enquadramento IPI', 't', ['nullable', 'string', 'max:255']],

        'situacao_novo_regime' => ['Situação novo regime (0/1/2)', 't', ['nullable', 'in:0,1,2']],
        'cst_ibs_cbs' => ['CST IBS/CBS', 't', ['nullable', 'regex:/^\d{3}$/']],
        'cclasstrib' => ['cClassTrib (código)', 't', ['nullable', 'string', 'max:10']],
        'aliquota_ibs' => ['Alíq. IBS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'aliquota_cbs' => ['Alíq. CBS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'reducao_base_calculo_ibs' => ['Redução BC IBS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'reducao_base_calculo_cbs' => ['Redução BC CBS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'percentual_credito_ibs' => ['% crédito IBS', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'percentual_credito_cbs' => ['% crédito CBS', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],
        'ccredpres' => ['cCredPres (código)', 't', ['nullable', 'string', 'max:10']],

        'sujeito_imposto_seletivo' => ['Sujeito a IS (SIM/NAO)', 't', ['nullable', 'in:SIM,NAO']],
        'tipo_imposto_seletivo' => ['Tipo IS', 't', ['nullable', 'in:veiculos,cigarros,bebidas_alcoolicas,bebidas_acucaradas,combustiveis_fosseis,bens_minerais']],
        'cclasstrib_is' => ['cClassTrib IS', 't', ['nullable', 'string', 'max:6']],
        'aliquota_is' => ['Alíq. IS %', 'n', ['nullable', 'numeric', 'min:0', 'max:100']],

        'destinacao_tributaria' => ['Destinação (RV/UC/AT/SV)', 't', ['nullable', 'in:RV,UC,AT,SV']],
        'tipo_credito' => ['Tipo crédito (IN/PA/NE)', 't', ['nullable', 'in:IN,PA,NE']],
    ];

    public function exportar(int $empresaId, string $caminho): int
    {
        $produtos = Produto::where('empresa_id', $empresaId)->orderBy('nome')->get();
        $classTrib = TabClassTrib::pluck('codigo', 'id');
        $credPres = TabCredPres::pluck('codigo', 'id');

        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet()->setTitle(self::ABA);

        $cabecalhos = array_merge(
            array_values(self::INFORMATIVAS),
            array_map(fn ($c) => $c[0], array_values(self::FISCAIS)),
        );
        $aba->fromArray($cabecalhos, null, 'A1');

        $qtdInfo = count(self::INFORMATIVAS);
        $colunaFiscal = array_keys(self::FISCAIS);

        foreach ($produtos as $i => $p) {
            $linha = $i + 2;
            $col = 1;
            foreach (array_keys(self::INFORMATIVAS) as $campo) {
                $this->gravar($aba, $col++, $linha, $p->{$campo}, $campo === 'preco_venda' ? 'n' : 't');
            }
            foreach ($colunaFiscal as $campo) {
                [, $tipo] = self::FISCAIS[$campo];
                $valor = match ($campo) {
                    'cclasstrib' => $classTrib[$p->cclasstrib_id] ?? null,
                    'ccredpres' => $credPres[$p->ccredpres_id] ?? null,
                    'sujeito_imposto_seletivo' => $p->sujeito_imposto_seletivo ? 'SIM' : 'NAO',
                    default => $p->{$campo},
                };
                $this->gravar($aba, $col++, $linha, $valor, $tipo);
            }
        }

        $total = count($cabecalhos);
        $ultimaLetra = Coordinate::stringFromColumnIndex($total);

        $aba->getStyle("A1:{$ultimaLetra}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $aba->getRowDimension(1)->setRowHeight(34);

        $ultimaInfo = Coordinate::stringFromColumnIndex($qtdInfo);
        $aba->getStyle("A1:{$ultimaInfo}1")->getFill()->getStartColor()->setRGB('7F7F7F');
        if ($produtos->isNotEmpty()) {
            $aba->getStyle("A2:{$ultimaInfo}".($produtos->count() + 1))->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDEDED');
        }

        for ($c = 1; $c <= $total; $c++) {
            $aba->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $aba->getColumnDimensionByColumn(3)->setAutoSize(false)->setWidth(42);
        $aba->freezePane('D2');
        $aba->setAutoFilter("A1:{$ultimaLetra}".max(2, $produtos->count() + 1));

        $planilha->createSheet()->setTitle('Instruções');
        $this->montarInstrucoes($planilha->getSheetByName('Instruções'));
        $planilha->setActiveSheetIndex(0);

        (IOFactory::createWriter($planilha, 'Xlsx'))->save($caminho);

        return $produtos->count();
    }

    /**
     * Lê a planilha devolvida e devolve [linhas válidas, erros, ignoradas].
     * Cada linha válida: ['produto' => Produto, 'dados' => array pronto para update].
     *
     * @return array{validas: array<int, array{produto: Produto, dados: array}>, erros: array<int, string>, sem_alteracao: int}
     */
    public function ler(int $empresaId, string $caminho): array
    {
        $aba = IOFactory::load($caminho)->getSheetByName(self::ABA)
            ?? throw new \RuntimeException('A planilha precisa ter a aba "'.self::ABA.'".');

        $linhas = $aba->toArray(null, true, false, false);
        $cabecalho = array_map(fn ($v) => trim((string) $v), array_shift($linhas));

        $mapa = [];
        foreach (array_merge(['id' => 'ID'], array_map(fn ($c) => $c[0], self::FISCAIS)) as $campo => $titulo) {
            $mapa[$campo] = array_search($titulo, $cabecalho, true);
        }
        if ($mapa['id'] === false) {
            throw new \RuntimeException('Coluna "ID" não encontrada: use a planilha gerada pelo sistema.');
        }
        $faltando = array_keys(array_filter($mapa, fn ($v) => $v === false));
        if ($faltando !== []) {
            throw new \RuntimeException('Colunas ausentes na planilha: '.implode(', ', $faltando));
        }

        $classTrib = TabClassTrib::pluck('id', 'codigo');
        $credPres = TabCredPres::pluck('id', 'codigo');
        $produtos = Produto::where('empresa_id', $empresaId)->get()->keyBy('id');

        $validas = [];
        $erros = [];
        $semAlteracao = 0;

        foreach ($linhas as $i => $linha) {
            $numero = $i + 2;
            $id = $linha[$mapa['id']] ?? null;
            if ($id === null || $id === '') {
                continue;
            }

            $produto = $produtos[(int) $id] ?? null;
            if (! $produto) {
                $erros[$numero] = "ID {$id} não existe nesta empresa.";

                continue;
            }

            $entrada = [];
            foreach (self::FISCAIS as $campo => [, $tipo]) {
                $entrada[$campo] = $this->normalizar($linha[$mapa[$campo]] ?? null, $tipo);
            }

            $validador = Validator::make($entrada, array_map(fn ($c) => $c[2], self::FISCAIS));
            if ($validador->fails()) {
                $erros[$numero] = "{$produto->nome}: ".implode(' | ', $validador->errors()->all());

                continue;
            }

            $dados = $entrada;
            $dados['cclasstrib_id'] = null;
            $dados['ccredpres_id'] = null;
            if ($entrada['cclasstrib'] !== null) {
                $dados['cclasstrib_id'] = $classTrib[$entrada['cclasstrib']] ?? null;
                if ($dados['cclasstrib_id'] === null) {
                    $erros[$numero] = "{$produto->nome}: cClassTrib {$entrada['cclasstrib']} não existe na tabela oficial.";

                    continue;
                }
            }
            if ($entrada['ccredpres'] !== null) {
                $dados['ccredpres_id'] = $credPres[$entrada['ccredpres']] ?? null;
                if ($dados['ccredpres_id'] === null) {
                    $erros[$numero] = "{$produto->nome}: cCredPres {$entrada['ccredpres']} não existe na tabela oficial.";

                    continue;
                }
            }
            unset($dados['cclasstrib'], $dados['ccredpres']);
            $dados['sujeito_imposto_seletivo'] = $entrada['sujeito_imposto_seletivo'] === 'SIM';

            $produto->fill($dados);
            if (! $produto->isDirty()) {
                $semAlteracao++;

                continue;
            }

            $validas[$numero] = ['produto' => $produto, 'dados' => $produto->getDirty()];
        }

        return ['validas' => $validas, 'erros' => $erros, 'sem_alteracao' => $semAlteracao];
    }

    private function gravar(Worksheet $aba, int $coluna, int $linha, mixed $valor, string $tipo): void
    {
        $celula = $aba->getCell([$coluna, $linha]);
        if ($valor === null || $valor === '') {
            if ($tipo === 't') {
                $celula->getStyle()->getNumberFormat()->setFormatCode('@');
            }

            return;
        }
        if ($tipo === 't') {
            $celula->setValueExplicit((string) $valor, DataType::TYPE_STRING);
            $celula->getStyle()->getNumberFormat()->setFormatCode('@');
        } else {
            $celula->setValue((float) $valor);
        }
    }

    private function normalizar(mixed $valor, string $tipo): string|float|null
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if ($tipo === 'n') {
            return is_numeric($valor) ? (float) $valor : (is_numeric(str_replace(',', '.', $texto)) ? (float) str_replace(',', '.', $texto) : $texto);
        }
        // Números "soltos" que o Excel guardou como inteiro/decimal (ex.: 2203090 ou 4.0).
        if (is_float($valor) && floor($valor) == $valor) {
            $texto = (string) (int) $valor;
        }

        return $texto;
    }

    private function montarInstrucoes(Worksheet $aba): void
    {
        $linhas = [
            ['Como preencher'],
            [''],
            ['• Preencha apenas as colunas fiscais (cabeçalho azul). As colunas cinzas (ID, código, produto...) são só de consulta - NÃO altere a coluna ID.'],
            ['• Células em branco = campo não informado. Percentuais devem ser números (ex.: 18 para 18%).'],
            ['• NCM: 8 dígitos | CEST: 7 dígitos | CFOP: 4 dígitos | Origem: 0 a 8 | CST ICMS: 2 dígitos | CST PIS/COFINS/IPI: 2 dígitos | CST IBS/CBS: 3 dígitos.'],
            ['• Tipo fiscal: consumo, materia_prima, produto, servico ou brinde.'],
            ['• Situação novo regime: 0, 1 ou 2.'],
            ['• Sujeito a IS: SIM ou NAO. Tipo IS: veiculos, cigarros, bebidas_alcoolicas, bebidas_acucaradas, combustiveis_fosseis ou bens_minerais.'],
            ['• Destinação: RV (revenda), UC (uso/consumo), AT (ativo), SV (serviço). Tipo crédito: IN, PA ou NE.'],
            ['• cClassTrib e cCredPres: informar o CÓDIGO da tabela oficial (a importação confere se existe).'],
            ['• Não renomeie as colunas nem a aba "'.self::ABA.'". Devolva o arquivo em .xlsx.'],
        ];
        $aba->fromArray($linhas, null, 'A1');
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $aba->getColumnDimension('A')->setWidth(150);
        $aba->getStyle('A3:A11')->getAlignment()->setWrapText(true);
    }
}
