<?php

namespace App\Service;

use OpenAI;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AiSuggestionService
{
    private Client $openAiClient;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $apiKey = $parameterBag->get('app.openai_api_key');

        if (!$apiKey) {
            throw new \InvalidArgumentException('OpenAI API key is not configured in environment variables');
        }

        $this->openAiClient = OpenAI::client($apiKey);
    }

    public function generateSuggestionForException(string $exceptionMessage, string $exceptionType, ?array $exceptionContext = null): ?string
    {
        try {
            $prompt = $this->buildPrompt($exceptionMessage, $exceptionType, $exceptionContext);

            $response = $this->openAiClient->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful software debugging assistant. Provide concise, actionable suggestions for fixing exceptions based on the error message and context. Keep responses under 200 words and focus on practical solutions. Return plain text without JSON formatting or quotes.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.3,
            ]);

            $suggestion = $response->choices[0]->message->content ?? null;
            return $suggestion ?? null;
        } catch (\Exception $e) {
            error_log("AI Suggestion Service Error: " . $e->getMessage());
            return null;
        }
    }

    private function buildPrompt(string $exceptionMessage, string $exceptionType, ?array $exceptionContext = null): string
    {
        $prompt = "Exception Type: {$exceptionType}\n";
        $prompt .= "Exception Message: {$exceptionMessage}\n\n";

        if ($exceptionContext) {
            $prompt .= "Detailed Exception Context:\n";

            // File and line information
            if (isset($exceptionContext['file']) && $exceptionContext['file']) {
                $prompt .= "- File: {$exceptionContext['file']}\n";
            }

            if (isset($exceptionContext['line']) && $exceptionContext['line']) {
                $prompt .= "- Line: {$exceptionContext['line']}\n";
            }

            // Stack trace (limit to first 800 chars for context)
            if (isset($exceptionContext['trace']) && $exceptionContext['trace']) {
                $prompt .= "- Stack Trace: " . substr($exceptionContext['trace'], 0, 800) . "\n";
            }

            // Request context
            if (isset($exceptionContext['context']) && is_array($exceptionContext['context'])) {
                $prompt .= "- Request Context:\n";
                foreach ($exceptionContext['context'] as $key => $value) {
                    $prompt .= "  * {$key}: " . (is_scalar($value) ? $value : json_encode($value)) . "\n";
                }
            }

            // Service information
            if (isset($exceptionContext['service_name']) && $exceptionContext['service_name']) {
                $prompt .= "- Service: {$exceptionContext['service_name']}\n";
            }

            if (isset($exceptionContext['service_version']) && $exceptionContext['service_version']) {
                $prompt .= "- Version: {$exceptionContext['service_version']}\n";
            }

            // Timestamp
            if (isset($exceptionContext['timestamp']) && $exceptionContext['timestamp']) {
                $timestamp = is_numeric($exceptionContext['timestamp'])
                    ? date('Y-m-d H:i:s', $exceptionContext['timestamp'])
                    : $exceptionContext['timestamp'];
                $prompt .= "- Occurred at: {$timestamp}\n";
            }
        }

        $prompt .= "\nBased on this detailed exception information, please provide a specific, actionable suggestion to fix this issue. Focus on the exact file, line, and context provided.";

        return $prompt;
    }
}
