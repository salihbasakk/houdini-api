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

    public function generateSuggestionForException(string $exceptionMessage, string $exceptionType, ?array $telemetryData = null): ?string
    {
        try {
            $prompt = $this->buildPrompt($exceptionMessage, $exceptionType, $telemetryData);

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

    private function buildPrompt(string $exceptionMessage, string $exceptionType, ?array $telemetryData): string
    {
        $prompt = "Exception Type: {$exceptionType}\n";
        $prompt .= "Exception Message: {$exceptionMessage}\n\n";

        if ($telemetryData) {
            $prompt .= "Additional Context:\n";

            if (isset($telemetryData['url'])) {
                $prompt .= "- URL: {$telemetryData['url']}\n";
            }

            if (isset($telemetryData['user_agent'])) {
                $prompt .= "- User Agent: {$telemetryData['user_agent']}\n";
            }

            if (isset($telemetryData['stack_trace'])) {
                $prompt .= "- Stack Trace: " . substr($telemetryData['stack_trace'], 0, 500) . "\n";
            }

            if (isset($telemetryData['request_data'])) {
                $prompt .= "- Request Data: " . json_encode($telemetryData['request_data']) . "\n";
            }
        }

        $prompt .= "\nPlease provide a concise suggestion on how to fix this issue.";

        return $prompt;
    }
}
