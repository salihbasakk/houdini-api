<?php

namespace App\Controller;

use App\Entity\TraceLog;
use App\Repository\TraceLogRepository;
use App\Service\AiSuggestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TelemetryController extends AbstractController
{
    public function __construct(
        private TraceLogRepository  $traceLogRepository,
        private AiSuggestionService $aiSuggestionService
    )
    {
    }

    #[Route('/api/telemetry', name: 'api_telemetry', methods: ['POST'])]
    public function submitTelemetry(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Extract project_id from the data
            $projectId = $data['project_id'] ?? null;
            if (!$projectId) {
                return new JsonResponse(['error' => 'project_id is required'], Response::HTTP_BAD_REQUEST);
            }

            // Create a single TraceLog entry with the entire telemetry data as JSON
            $traceLog = new TraceLog();
            $traceLog->setProjectId($projectId);
            $traceLog->setTelemetryData($data);

            $this->traceLogRepository->save($traceLog, true);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Telemetry data saved successfully',
                'log_id' => $traceLog->getId()
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to process telemetry data',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/telemetry/{projectId}', name: 'api_telemetry_get', methods: ['GET'])]
    public function getTelemetryLogs(string $projectId, Request $request): Response
    {
        try {
            $page = max(1, (int)$request->query->get('page', 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;

            $logs = $this->traceLogRepository->findByProjectIdPaginated($projectId, $offset, $limit);
            $totalCount = $this->traceLogRepository->countByProjectId($projectId);
            $totalPages = (int)ceil($totalCount / $limit);

            $data = [];
            foreach ($logs as $log) {
                $telemetryData = $log->getTelemetryData();

                // Generate AI suggestions for exceptions in telemetry data
                $this->generateAiSuggestion($telemetryData);

                $logEntry = [
                    'id' => $log->getId(),
                    'project_id' => $log->getProjectId(),
                    'telemetry_data' => $telemetryData,
                    'created_at' => $log->getCreatedAt()->format('Y-m-d H:i:s')
                ];

                $data[] = $logEntry;
            }

            $responseData = [
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_count' => $totalCount,
                    'per_page' => $limit,
                    'has_next' => $page < $totalPages,
                    'has_previous' => $page > 1
                ]
            ];

            return new Response(
                json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                Response::HTTP_OK,
                ['Content-Type' => 'application/json']
            );
        } catch (\Exception $e) {
            $responseData = [
                'error' => 'Failed to retrieve telemetry data',
                'message' => $e->getMessage()
            ];
            return new Response(
                json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'application/json']
            );
        }
    }

    private function generateAiSuggestion(array &$telemetryData): void
    {
        try {
            if (!isset($telemetryData['telemetry_data']) || !is_array($telemetryData['telemetry_data'])) {
                return;
            }

            foreach ($telemetryData['telemetry_data'] as &$telemetry) {
                if (isset($telemetry['type']) && $telemetry['type'] == 'exception') {
                    $exceptionMessage = $telemetry['message'] ?? 'Unknown exception occurred';
                    $exceptionType = $telemetry['class'] ?? 'Exception';

                    // Prepare detailed exception context for better AI suggestions
                    $exceptionContext = [
                        'class' => $telemetry['class'] ?? 'Exception',
                        'message' => $telemetry['message'] ?? 'Unknown exception occurred',
                        'file' => $telemetry['file'] ?? null,
                        'line' => $telemetry['line'] ?? null,
                        'trace' => $telemetry['trace'] ?? null,
                        'timestamp' => $telemetry['timestamp'] ?? null,
                        'context' => $telemetry['context'] ?? null,
                        'service_name' => $telemetry['service_name'] ?? null,
                        'service_version' => $telemetry['service_version'] ?? null
                    ];

                    $aiSuggestion = $this->aiSuggestionService->generateSuggestionForException(
                        $exceptionMessage,
                        $exceptionType,
                        $exceptionContext
                    );

                    if ($aiSuggestion) {
                        $telemetry['suggestion'] = str_replace('"', '', $aiSuggestion);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to generate AI suggestion: " . $e->getMessage());
        }
    }
}
