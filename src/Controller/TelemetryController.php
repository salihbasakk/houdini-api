<?php

namespace App\Controller;

use App\Entity\TraceLog;
use App\Repository\TraceLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TelemetryController extends AbstractController
{
    public function __construct(
        private TraceLogRepository $traceLogRepository
    ) {
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
    public function getTelemetryLogs(string $projectId, Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;

            $logs = $this->traceLogRepository->findByProjectIdPaginated($projectId, $offset, $limit);
            $totalCount = $this->traceLogRepository->countByProjectId($projectId);
            $totalPages = (int) ceil($totalCount / $limit);

            $data = [];
            foreach ($logs as $log) {
                $data[] = [
                    'id' => $log->getId(),
                    'project_id' => $log->getProjectId(),
                    'telemetry_data' => $log->getTelemetryData(),
                    'created_at' => $log->getCreatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return new JsonResponse([
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
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to retrieve telemetry data',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
