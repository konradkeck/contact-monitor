<?php

namespace App\Ai\CompanyAnalysis;

use App\Ai\Pricing\PricingRegistry;
use App\Ai\Providers\AiProviderFactory;
use App\Models\AiModelConfig;
use App\Models\AiUsageLog;
use App\Models\AnalysisRun;
use App\Models\AnalysisStep;
use App\Models\AnalysisStepRun;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AnalysisPipeline
{
    public function __construct(
        private ContextBuilder $contextBuilder,
        private PromptRenderer $promptRenderer,
        private ResultExtractor $resultExtractor,
    ) {}

    /**
     * Execute a multi-step analysis for a company.
     *
     * @param  Company  $company          Target company
     * @param  array    $stepIds          IDs of steps to run
     * @param  array    $promptOverrides  Keyed by step ID: [stepId => overridden template]
     * @param  User     $user             User who triggered
     */
    public function run(Company $company, array $stepIds, array $promptOverrides, User $user): AnalysisRun
    {
        // Validate AI model config
        $modelConfig = AiModelConfig::forAction('company_analysis');
        if (!$modelConfig || !$modelConfig->credential) {
            throw new \RuntimeException('No AI model configured for company_analysis. Configure it in Connect AI → Model Assignment.');
        }

        $provider = AiProviderFactory::make($modelConfig->credential);
        $modelName = $modelConfig->model_name;

        // Build base context
        $baseContext = $this->contextBuilder->build($company);

        // Create run
        $run = AnalysisRun::create([
            'company_id'       => $company->id,
            'user_id'          => $user->id,
            'status'           => 'running',
            'base_context_json' => $baseContext,
            'started_at'       => now(),
        ]);

        // Load steps
        $steps = AnalysisStep::whereIn('id', $stepIds)->ordered()->get();

        $previousOutputs = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $allFailed = true;

        foreach ($steps as $step) {
            $stepRun = AnalysisStepRun::create([
                'run_id'    => $run->id,
                'step_id'   => $step->id,
                'step_key'  => $step->key,
                'status'    => 'running',
                'started_at' => now(),
                'model_name' => $modelName,
            ]);

            try {
                // Use override template or default
                $template = $promptOverrides[$step->id] ?? $step->prompt_template;
                $stepRun->update(['prompt_template_used' => $template]);

                // Render prompt
                $renderedPrompt = $this->promptRenderer->render($template, $baseContext, $previousOutputs);
                $stepRun->update(['rendered_prompt' => $renderedPrompt]);

                // Call AI
                $messages = [
                    ['role' => 'system', 'content' => 'You are a company analysis agent. Always respond with valid JSON only. No markdown, no explanations outside the JSON.'],
                    ['role' => 'user', 'content' => $renderedPrompt],
                ];

                $result = $provider->complete($modelName, $messages, [
                    'max_tokens' => 4096,
                    'temperature' => 0.2,
                ]);

                $rawContent = $result['content'];
                $inputTokens = $result['input_tokens'] ?? 0;
                $outputTokens = $result['output_tokens'] ?? 0;
                $totalInputTokens += $inputTokens;
                $totalOutputTokens += $outputTokens;

                // Parse response
                $parsed = $this->resultExtractor->parseResponse($rawContent);

                $stepRun->update([
                    'raw_response'    => $rawContent,
                    'parsed_response' => $parsed,
                    'input_tokens'    => $inputTokens,
                    'output_tokens'   => $outputTokens,
                    'status'          => $parsed !== null ? 'completed' : 'failed',
                    'error_message'   => $parsed === null ? 'Failed to parse JSON from AI response' : null,
                    'completed_at'    => now(),
                ]);

                if ($parsed !== null) {
                    $allFailed = false;
                    // Store output for next steps
                    $previousOutputs[$step->key] = $parsed;
                    // Extract and persist structured data
                    $this->resultExtractor->extractAndPersist($parsed, $stepRun, $company->id, $run->id);
                }
            } catch (\Exception $e) {
                Log::warning("Analysis step {$step->key} failed for company {$company->id}: {$e->getMessage()}");

                $stepRun->update([
                    'status'        => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    'completed_at'  => now(),
                ]);
            }
        }

        // Finalize run
        $run->update([
            'status'       => $allFailed ? 'failed' : 'completed',
            'completed_at' => now(),
        ]);

        // Log usage
        if ($totalInputTokens > 0 || $totalOutputTokens > 0) {
            $inputPrice = PricingRegistry::getPrice($modelName);
            $costInput = $totalInputTokens / 1_000_000 * $inputPrice['input'];
            $costOutput = $totalOutputTokens / 1_000_000 * $inputPrice['output'];

            AiUsageLog::record(
                actionType: 'company_analysis',
                credentialId: $modelConfig->credential_id,
                modelName: $modelName,
                inputTokens: $totalInputTokens,
                outputTokens: $totalOutputTokens,
                costInputUsd: $costInput,
                costOutputUsd: $costOutput,
                promptExcerpt: "Company analysis: {$company->name}",
                entityType: Company::class,
                entityId: $company->id,
            );
        }

        return $run->load('stepRuns', 'fields', 'entities');
    }
}
