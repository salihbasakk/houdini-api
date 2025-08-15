<?php

namespace App\Repository;

use App\Entity\TraceLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TraceLog>
 */
class TraceLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TraceLog::class);
    }

    public function save(TraceLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TraceLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find telemetry logs by project ID with pagination, ordered by created_at DESC
     */
    public function findByProjectIdPaginated(string $projectId, int $offset, int $limit): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.projectId = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total telemetry logs for a project
     */
    public function countByProjectId(string $projectId): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.projectId = :projectId')
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
