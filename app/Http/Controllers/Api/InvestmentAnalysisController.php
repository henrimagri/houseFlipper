<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinancingDetailRequest;
use App\Http\Requests\InvestmentAnalysisRequest;
use App\Http\Requests\InvestmentCostRequest;
use App\Http\Requests\InvestmentResultRequest;
use App\Http\Resources\InvestmentAnalysisResource;
use App\Http\Resources\ProjetoResource;
use App\Models\FinancingDetail;
use App\Models\InvestmentAnalysis;
use App\Models\InvestmentCost;
use App\Models\InvestmentResult;
use App\Models\Opportunity;
use App\Services\AnalysisProjectService;
use App\Services\FinancingReferenceService;
use App\Services\InvestmentAnalysisCalculatorService;
use App\Services\InvestmentCapitalService;
use App\Support\InvestmentFinancialPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestmentAnalysisController extends Controller
{
    public function __construct(private readonly InvestmentAnalysisCalculatorService $calculator, private readonly FinancingReferenceService $financingReference, private readonly InvestmentCapitalService $capital) {}

    public function convertToProject(InvestmentAnalysis $investmentAnalysis, AnalysisProjectService $service): JsonResponse
    {
        $this->authorize('update', $investmentAnalysis);
        abort_unless(auth()->user()->tenant_id, 403, 'É necessário pertencer a uma conta para criar projetos.');
        [$project, $created] = $service->convert($investmentAnalysis);

        return (new ProjetoResource($project))->response()->setStatusCode($created ? 201 : 200);
    }

    public function financingReference(Request $request, InvestmentAnalysis $investmentAnalysis): JsonResponse
    {
        $this->authorize('view', $investmentAnalysis);

        $request->validate([
            'system' => ['sometimes', 'in:SAC,PRICE'],
            'paid_installments' => ['sometimes', 'integer', 'min:0', 'max:'.$request->integer('installments', 360)],
            'monthly_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'data' => $this->financingReference->for(
                $investmentAnalysis,
                $request->string('bank')->toString() ?: null,
                $request->string('system', 'SAC')->toString(),
                $request->integer('installments', 360),
                $request->filled('principal') ? max(0, $request->float('principal')) : null,
                max(0, $request->integer('paid_installments', 0)),
                $request->filled('monthly_rate') ? max(0, $request->float('monthly_rate')) : null,
                $request->string('bank_name')->toString() ?: null,
            ),
            'banks' => $this->financingReference->banks(),
        ]);
    }

    public function store(InvestmentAnalysisRequest $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('view', $opportunity);
        $analysis = DB::transaction(function () use ($request, $opportunity) {
            $opportunity = Opportunity::lockForUpdate()->findOrFail($opportunity->id);
            $data = $request->validated();
            $primary = ! $opportunity->investmentAnalyses()->exists();

            return $opportunity->investmentAnalyses()->create($data + $this->calculator->calculateForStorage($data) + ['user_id' => $request->user()->id, 'is_primary' => $primary, 'status' => 'active']);
        });

        return (new InvestmentAnalysisResource($analysis->load('opportunity')))->response()->setStatusCode(201);
    }

    public function show(InvestmentAnalysis $investmentAnalysis): InvestmentAnalysisResource
    {
        $this->authorize('view', $investmentAnalysis);

        return new InvestmentAnalysisResource($investmentAnalysis->load(['opportunity.coverPhoto', 'financingDetail', 'financingSimulations', 'costs', 'result']));
    }

    public function update(InvestmentAnalysisRequest $request, InvestmentAnalysis $investmentAnalysis): InvestmentAnalysisResource
    {
        $this->authorize('update', $investmentAnalysis);

        return DB::transaction(function () use ($request, $investmentAnalysis) {
            $investmentAnalysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentAnalysis->id);
            $data = $request->validated();
            $calculationData = array_merge($investmentAnalysis->toArray(), $data);
            $investmentAnalysis->update($data + $this->calculator->calculateForStorage($calculationData));

            $investmentAnalysis->update(['viability_completed_at' => now()]);
            $this->syncDetailedCosts($investmentAnalysis);

            return new InvestmentAnalysisResource($investmentAnalysis->refresh()->load(['opportunity.coverPhoto', 'financingDetail', 'financingSimulations', 'costs', 'result']));
        });
    }

    public function financing(FinancingDetailRequest $request, InvestmentAnalysis $investmentAnalysis): InvestmentAnalysisResource
    {
        $this->authorize('update', $investmentAnalysis);

        return DB::transaction(function () use ($request, $investmentAnalysis) {
            $investmentAnalysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentAnalysis->id);
            $financing = $request->validated();
            $detail = $investmentAnalysis->financingSimulations()->where('is_selected', true)->first();
            if ($detail) {
                $detail->update($financing);
            } else {
                $detail = $investmentAnalysis->financingSimulations()->create($financing + ['is_selected' => true, 'label' => $financing['label'] ?? $financing['bank_name'] ?? 'Simulação principal']);
            }
            $analysisData = $investmentAnalysis->toArray();
            $analysisData['expected_installment_value'] = (float) ($financing['installment_value'] ?? 0);
            $analysisData['expected_outstanding_balance'] = (float) ($financing['outstanding_balance'] ?? 0);
            $investmentAnalysis->update([
                'expected_installment_value' => $analysisData['expected_installment_value'],
                'expected_outstanding_balance' => $analysisData['expected_outstanding_balance'],
            ] + $this->calculator->calculateForStorage($analysisData));
            if ($result = $investmentAnalysis->result()->first()) {
                $payload = [
                    'sale_price' => $result->sale_price, 'sale_date' => $result->sale_date?->toDateString(),
                    'sale_costs' => $result->sale_costs, 'payoff_amount' => (float) ($financing['payoff_amount'] ?? 0),
                    'flip_duration_months' => $result->flip_duration_months,
                ];
                $result->update($this->resultData($payload, $investmentAnalysis));
            }

            $investmentAnalysis->update(['financing_completed_at' => now()]);

            return new InvestmentAnalysisResource($investmentAnalysis->refresh()->load(['opportunity.coverPhoto', 'financingDetail', 'financingSimulations', 'costs', 'result']));
        });
    }

    public function storeFinancingSimulation(FinancingDetailRequest $request, InvestmentAnalysis $investmentAnalysis): JsonResponse
    {
        $this->authorize('update', $investmentAnalysis);

        return DB::transaction(function () use ($request, $investmentAnalysis) {
            $investmentAnalysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentAnalysis->id);
            $selected = ! $investmentAnalysis->financingSimulations()->exists();
            $simulation = $investmentAnalysis->financingSimulations()->create($request->validated() + ['is_selected' => $selected, 'label' => $request->string('label')->toString() ?: $request->string('bank_name')->toString()]);
            if ($selected) {
                $this->applySelectedFinancing($investmentAnalysis, $simulation);
            }

            return response()->json(InvestmentFinancialPayload::financing($simulation), 201);
        });
    }

    public function selectFinancingSimulation(FinancingDetail $financingDetail): InvestmentAnalysisResource
    {
        $analysis = $financingDetail->investmentAnalysis;
        $this->authorize('update', $analysis);
        DB::transaction(function () use ($analysis, $financingDetail) {
            $analysis = InvestmentAnalysis::lockForUpdate()->findOrFail($analysis->id);
            $financingDetail->refresh();
            $analysis->financingSimulations()->update(['is_selected' => false]);
            $financingDetail->update(['is_selected' => true]);
            $this->applySelectedFinancing($analysis, $financingDetail);
        });

        return new InvestmentAnalysisResource($analysis->refresh()->load(['opportunity.coverPhoto', 'financingDetail', 'financingSimulations', 'costs', 'result']));
    }

    public function destroyFinancingSimulation(FinancingDetail $financingDetail): JsonResponse
    {
        $analysis = $financingDetail->investmentAnalysis;
        $this->authorize('update', $analysis);

        return DB::transaction(function () use ($analysis, $financingDetail) {
            $analysis = InvestmentAnalysis::lockForUpdate()->findOrFail($analysis->id);
            $financingDetail->refresh();
            $wasSelected = $financingDetail->is_selected;
            $financingDetail->delete();
            if ($wasSelected && $next = $analysis->financingSimulations()->first()) {
                $next->update(['is_selected' => true]);
                $this->applySelectedFinancing($analysis, $next);
            } elseif ($wasSelected) {
                $analysis->update(['expected_installment_value' => 0, 'expected_outstanding_balance' => 0, 'financing_completed_at' => null]);
                $this->syncDetailedCosts($analysis);
            }

            return response()->json(['message' => 'Simulação excluída.']);
        });
    }

    public function storeCost(InvestmentCostRequest $request, InvestmentAnalysis $investmentAnalysis): JsonResponse
    {
        $this->authorize('update', $investmentAnalysis);

        return DB::transaction(function () use ($request, $investmentAnalysis) {
            $investmentAnalysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentAnalysis->id);
            $cost = $investmentAnalysis->costs()->create($request->validated() + ['user_id' => $request->user()->id]);
            $this->syncDetailedCosts($investmentAnalysis);
            $investmentAnalysis->update(['costs_completed_at' => now()]);

            return response()->json(InvestmentFinancialPayload::cost($cost), 201);
        });
    }

    public function updateCost(InvestmentCostRequest $request, InvestmentCost $investmentCost): JsonResponse
    {
        $this->authorize('update', $investmentCost->investmentAnalysis);

        return DB::transaction(function () use ($request, $investmentCost) {
            $analysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentCost->investment_analysis_id);
            $investmentCost->refresh();
            $investmentCost->update($request->validated());
            $this->syncDetailedCosts($analysis);

            return response()->json(InvestmentFinancialPayload::cost($investmentCost));
        });
    }

    public function destroyCost(InvestmentCost $investmentCost): JsonResponse
    {
        $this->authorize('update', $investmentCost->investmentAnalysis);

        return DB::transaction(function () use ($investmentCost) {
            $analysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentCost->investment_analysis_id);
            $investmentCost->refresh();
            $investmentCost->delete();
            $this->syncDetailedCosts($analysis);

            return response()->json(['message' => 'Custo excluído.']);
        });
    }

    public function result(InvestmentResultRequest $request, InvestmentAnalysis $investmentAnalysis): JsonResponse
    {
        $this->authorize('update', $investmentAnalysis);

        return DB::transaction(function () use ($request, $investmentAnalysis) {
            $investmentAnalysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentAnalysis->id);
            $result = $investmentAnalysis->result()->updateOrCreate([], $this->resultData($request->validated(), $investmentAnalysis));
            $investmentAnalysis->update(['result_completed_at' => now()]);

            return response()->json(InvestmentFinancialPayload::result($result));
        });
    }

    public function updateResult(InvestmentResultRequest $request, InvestmentResult $investmentResult): JsonResponse
    {
        $this->authorize('update', $investmentResult->investmentAnalysis);

        return DB::transaction(function () use ($request, $investmentResult) {
            $analysis = InvestmentAnalysis::lockForUpdate()->findOrFail($investmentResult->investment_analysis_id);
            $investmentResult->refresh();
            $investmentResult->update($this->resultData($request->validated(), $analysis));

            return response()->json(InvestmentFinancialPayload::result($investmentResult));
        });
    }

    private function resultData(array $data, InvestmentAnalysis $analysis): array
    {
        $months = max(1, (int) $data['flip_duration_months']);
        $data['payoff_amount'] = (float) ($analysis->financingDetail()->value('payoff_amount') ?? 0);
        $data['total_own_capital_invested'] = $this->capital->actual($analysis, $months);
        $net = (float) $data['sale_price'] - (float) ($data['sale_costs'] ?? 0) - (float) ($data['payoff_amount'] ?? 0);
        $profit = $net - (float) $data['total_own_capital_invested'];
        $roi = (float) $data['total_own_capital_invested'] > 0 ? $profit / (float) $data['total_own_capital_invested'] * 100 : 0;
        $growthFactor = max(0, 1 + $roi / 100);
        $monthly = $growthFactor > 0 ? (($growthFactor ** (1 / $months)) - 1) * 100 : -100;
        $annualized = $growthFactor > 0 ? (($growthFactor ** (12 / $months)) - 1) * 100 : -100;

        return $this->calculator->validateForStorage($data + ['net_amount_received_after_payoff' => round($net, 2), 'net_profit' => round($profit, 2), 'roi_percentage' => round($roi, 4), 'monthly_roi_percentage' => round($monthly, 4), 'annualized_roi_percentage' => round($annualized, 4)]);
    }

    private function syncDetailedCosts(InvestmentAnalysis $analysis): void
    {
        $analysis->refresh();
        $values = $analysis->toArray();
        $values['detailed_costs_total'] = $this->analysisCostsTotal($analysis);
        $analysis->update(['detailed_costs_total' => $values['detailed_costs_total']] + $this->calculator->calculateForStorage($values));
        if ($result = $analysis->result()->first()) {
            $payload = [
                'sale_price' => $result->sale_price, 'sale_date' => $result->sale_date?->toDateString(),
                'sale_costs' => $result->sale_costs, 'payoff_amount' => $result->payoff_amount,
                'flip_duration_months' => $result->flip_duration_months,
            ];
            $result->update($this->resultData($payload, $analysis));
        }
    }

    private function analysisCostsTotal(InvestmentAnalysis $analysis): float
    {
        $months = max(1, (int) $analysis->expected_months_until_sale);
        $monthlyCategories = ['condominium', 'property_tax', 'water', 'energy', 'internet', 'insurance'];
        $monthlyDescriptions = ['Custos operacionais mensais', 'Funcionário', 'Contador', 'Outros operacionais'];

        return round((float) $analysis->costs()->get()->sum(function (InvestmentCost $cost) use ($months, $monthlyCategories, $monthlyDescriptions) {
            $amount = (float) $cost->amount;
            $isMonthly = in_array($cost->category, $monthlyCategories, true) || in_array($cost->description, $monthlyDescriptions, true);

            return $isMonthly ? $amount * $months : $amount;
        }), 2);
    }

    private function applySelectedFinancing(InvestmentAnalysis $analysis, FinancingDetail $financing): void
    {
        $values = $analysis->toArray();
        $values['expected_installment_value'] = (float) $financing->installment_value;
        $values['expected_outstanding_balance'] = (float) $financing->outstanding_balance;
        $analysis->update(['expected_installment_value' => $values['expected_installment_value'], 'expected_outstanding_balance' => $values['expected_outstanding_balance'], 'financing_completed_at' => now()] + $this->calculator->calculateForStorage($values));
        $this->syncDetailedCosts($analysis);
    }
}
