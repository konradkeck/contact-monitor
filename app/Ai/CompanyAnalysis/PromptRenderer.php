<?php

namespace App\Ai\CompanyAnalysis;

class PromptRenderer
{
    /**
     * Render a prompt template by replacing {{variable}} placeholders.
     *
     * Supports:
     * - {{var}} → base context value
     * - {{previous.step_key.field}} → nested field from previous step output
     * - {{previous.step_key}} → full JSON of previous step output
     *
     * Missing variables are replaced with empty string.
     */
    public function render(string $template, array $baseContext, array $previousOutputs = []): string
    {
        return preg_replace_callback('/\{\{([\w.]+)\}\}/', function ($matches) use ($baseContext, $previousOutputs) {
            $key = $matches[1];

            // Base context variable
            if (isset($baseContext[$key])) {
                return $this->stringify($baseContext[$key]);
            }

            // Previous step outputs: {{previous.step_key.field}} or {{previous.step_key}}
            if (str_starts_with($key, 'previous.')) {
                $parts = explode('.', $key, 3); // ['previous', 'step_key', 'field?']
                $stepKey = $parts[1] ?? null;

                if ($stepKey && isset($previousOutputs[$stepKey])) {
                    $stepData = $previousOutputs[$stepKey];

                    if (isset($parts[2])) {
                        // Specific field from step output
                        $field = $parts[2];
                        return $this->stringify($stepData[$field] ?? '');
                    }

                    // Full step output as JSON
                    return $this->stringify($stepData);
                }
            }

            return '';
        }, $template);
    }

    /**
     * Extract all variable placeholders from a template.
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{([\w.]+)\}\}/', $template, $matches);

        return array_unique($matches[1]);
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
