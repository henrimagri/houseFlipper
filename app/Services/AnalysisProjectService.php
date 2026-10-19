<?php

namespace App\Services;

use App\Models\InvestmentAnalysis;
use App\Models\Projeto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AnalysisProjectService
{
    public function __construct(private readonly PropriedadeService $properties, private readonly ProjetoService $projects) {}

    /** @return array{0: Projeto, 1: bool} */
    public function convert(InvestmentAnalysis $analysis): array
    {
        return DB::transaction(function () use ($analysis) {
            $analysis = InvestmentAnalysis::lockForUpdate()->findOrFail($analysis->id);
            if ($analysis->projeto_id) {
                $project = Projeto::ativos()->with(['propriedade', 'etapas'])->find($analysis->projeto_id);
                if (! $project || ! $project->propriedade || $project->propriedade->deletado_flg) {
                    throw new ConflictHttpException('O projeto vinculado ou seu imóvel foi excluído. Revise o vínculo antes de converter novamente.');
                }

                return [$project, false];
            }

            // Descriptions were the only legacy link. Do not silently adopt or duplicate them.
            if (Projeto::where('descricao', 'like', "Projeto originado da análise financeira #{$analysis->id}.%")->exists()) {
                throw new ConflictHttpException('Há um projeto legado desta análise. Abra Projetos para revisá-lo antes de criar outro.');
            }

            $opportunity = $analysis->opportunity()->lockForUpdate()->firstOrFail();
            $result = $analysis->result()->first();
            $renovation = (float) $analysis->costs()->whereIn('category', ['renovation', 'materials', 'labor', 'painting', 'woodwork', 'electricity', 'plumbing'])->sum('amount');
            $budget = round($renovation ?: (float) $analysis->costs()->sum('amount'), 2);
            Validator::make(['orcamento_total' => $budget], ['orcamento_total' => ['required', 'numeric', 'min:0', 'max:999999999999']])->validate();

            $property = $this->properties->criar([
                'apelido' => mb_substr($opportunity->title, 0, 120),
                'tipo' => $opportunity->property_type ?? 'imovel',
                'area_m2' => $opportunity->area_m2,
                'valor_aquisicao' => $analysis->purchase_price,
                'valor_venda_alvo' => $result?->sale_price ?? $analysis->expected_sale_price,
                'status' => 'prospeccao',
                'endereco' => ['cidade' => mb_substr($opportunity->city ?? '', 0, 100), 'estado' => $opportunity->state],
            ]);

            $cents = (int) round($budget * 100);
            $remaining = $cents;
            $stages = [];
            $distribution = [['Mão de obra', 30], ['Acabamento', 22], ['Hidráulica', 12], ['Elétrica', 12], ['Demolição', 10], ['Pintura', 10], ['Outros', 4]];
            foreach ($distribution as $index => [$name, $share]) {
                $amount = $index === count($distribution) - 1 ? $remaining : (int) floor($cents * $share / 100);
                $remaining -= $amount;
                $stages[] = ['nome' => $name, 'ordem' => $index + 1, 'orcamento' => $amount / 100, 'status' => 'pendente'];
            }
            $project = $this->projects->criar([
                'propriedade_id' => $property->id,
                'nome' => mb_substr('Flip · '.$opportunity->title, 0, 150),
                'descricao' => "Projeto originado da análise financeira #{$analysis->id}. ROI ".number_format((float) ($result?->roi_percentage ?? $analysis->expected_roi_percentage), 2, ',', '.').'% · lucro R$ '.number_format((float) ($result?->net_profit ?? $analysis->expected_net_profit), 2, ',', '.').'.',
                'orcamento_total' => $budget, 'status' => 'planejamento', 'etapas' => $stages,
            ]);
            $analysis->update(['projeto_id' => $project->id]);

            return [$project, true];
        }, 3);
    }
}
