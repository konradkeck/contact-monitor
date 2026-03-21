<?php

namespace App\Http\Controllers;

use App\Ai\CompanyAnalysis\AnalysisPipeline;
use App\Ai\CompanyAnalysis\ContextBuilder;
use App\Ai\CompanyAnalysis\DomainSyncService;
use App\Ai\CompanyAnalysis\PromptRenderer;
use App\Ai\CompanyAnalysis\ResultExtractor;
use App\Models\AnalysisRun;
use App\Models\AnalysisStep;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyAnalysisController extends Controller
{
    public function preview(Company $company): JsonResponse
    {
        $steps = AnalysisStep::enabled()->ordered()->get(['id', 'key', 'name', 'description', 'prompt_template', 'sort_order']);
        $contextBuilder = new ContextBuilder();
        $context = $contextBuilder->build($company);
        $renderer = new PromptRenderer();

        $stepsData = $steps->map(function ($step) use ($context, $renderer) {
            $renderedPrompt = $renderer->render($step->prompt_template, $context);
            $variables = $renderer->extractVariables($step->prompt_template);

            return [
                'id' => $step->id,
                'key' => $step->key,
                'name' => $step->name,
                'description' => $step->description,
                'prompt_template' => $step->prompt_template,
                'rendered_prompt' => $renderedPrompt,
                'variables' => $variables,
            ];
        });

        return response()->json([
            'steps' => $stepsData,
            'context' => $context,
            'available_variables' => ContextBuilder::availableVariables(),
        ]);
    }

    public function run(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'step_ids' => 'required|array|min:1',
            'step_ids.*' => 'integer|exists:analysis_steps,id',
            'prompt_overrides' => 'nullable|array',
            'prompt_overrides.*' => 'nullable|string',
        ]);

        // Lazy domain sync
        (new DomainSyncService())->syncIfStale();

        try {
            set_time_limit(120);

            $pipeline = new AnalysisPipeline(
                new ContextBuilder(),
                new PromptRenderer(),
                new ResultExtractor(),
            );

            $run = $pipeline->run(
                $company,
                $data['step_ids'],
                $data['prompt_overrides'] ?? [],
                $request->user(),
            );

            $message = $run->status === 'completed'
                ? 'Analysis completed successfully.'
                : 'Analysis completed with some failures. Check results for details.';

            return redirect()->route('companies.show', $company)->with('success', $message);
        } catch (\RuntimeException $e) {
            return redirect()->route('companies.show', $company)->with('error', $e->getMessage());
        }
    }

    public function show(Company $company, AnalysisRun $run)
    {
        if ($run->company_id !== $company->id) {
            abort(404);
        }

        $run->load(['stepRuns', 'fields', 'entities', 'user']);

        return Inertia::render('CompanyAnalysis/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'user_name' => $run->user?->name,
                'base_context_json' => $run->base_context_json,
                'step_runs' => $run->stepRuns->map(fn ($sr) => [
                    'id' => $sr->id,
                    'step_key' => $sr->step_key,
                    'status' => $sr->status,
                    'rendered_prompt' => $sr->rendered_prompt,
                    'raw_response' => $sr->raw_response,
                    'parsed_response' => $sr->parsed_response,
                    'error_message' => $sr->error_message,
                    'model_name' => $sr->model_name,
                    'input_tokens' => $sr->input_tokens,
                    'output_tokens' => $sr->output_tokens,
                    'started_at' => $sr->started_at?->toIso8601String(),
                    'completed_at' => $sr->completed_at?->toIso8601String(),
                ]),
                'fields' => $run->fields->map(fn ($f) => [
                    'field_group' => $f->field_group,
                    'field_key' => $f->field_key,
                    'field_value' => $f->field_value,
                    'field_type' => $f->field_type,
                    'confidence' => $f->confidence,
                    'is_inferred' => $f->is_inferred,
                ]),
                'entities' => $run->entities->map(fn ($e) => [
                    'entity_type' => $e->entity_type,
                    'display_name' => $e->display_name,
                    'data_json' => $e->data_json,
                    'confidence' => $e->confidence,
                ]),
            ],
        ]);
    }

    public function latestSummary(Company $company): JsonResponse
    {
        $run = AnalysisRun::forCompany($company->id)->with(['fields', 'entities', 'user'])->first();

        if (!$run) {
            return response()->json(['run' => null]);
        }

        return response()->json([
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'completed_at' => $run->completed_at?->toIso8601String(),
                'user_name' => $run->user?->name,
                'fields' => $run->fields->map(fn ($f) => [
                    'field_group' => $f->field_group,
                    'field_key' => $f->field_key,
                    'field_value' => $f->field_value,
                    'confidence' => $f->confidence,
                ]),
                'entities' => $run->entities->map(fn ($e) => [
                    'entity_type' => $e->entity_type,
                    'display_name' => $e->display_name,
                    'data_json' => $e->data_json,
                ]),
            ],
        ]);
    }

    public function history(Company $company): JsonResponse
    {
        $runs = AnalysisRun::forCompany($company->id)
            ->with('user')
            ->paginate(10);

        return response()->json($runs);
    }
}
