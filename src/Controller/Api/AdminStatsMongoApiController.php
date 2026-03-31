<?php

namespace App\Controller\Api;

use App\Document\CommandeEvent;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/stats')]
#[IsGranted('ROLE_ADMIN')]
class AdminStatsMongoApiController extends AbstractController
{
    private function toArraySafe(mixed $row): array
    {
        if (is_array($row)) return $row;
        try { return (array) $row; } catch (\Throwable) { return []; }
    }

    private function formatCreatedAt(mixed $createdAt): ?string
    {
        if ($createdAt instanceof UTCDateTime) return $createdAt->toDateTime()->format('Y-m-d H:i:s');
        if ($createdAt instanceof \DateTimeInterface) return $createdAt->format('Y-m-d H:i:s');
        if (is_string($createdAt) && trim($createdAt) !== '') return $createdAt;
        return null;
    }

    private function buildBaseMatch(Request $request): array
    {
        $days = (int)($request->query->get('days') ?? 0);
        $menuId = (int)($request->query->get('menuId') ?? 0);

        $match = [];

        if ($days > 0) {
            $from = (new \DateTimeImmutable())->modify("-{$days} days");
            $match['createdAt'] = ['$gte' => new UTCDateTime($from)];
        }

        if ($menuId > 0) {
            $match['menuId'] = $menuId;
        }

        return $match;
    }

    #[Route('/ping-mongo', name: 'api_admin_stats_ping_mongo', methods: ['GET'])]
    public function pingMongo(DocumentManager $dm): JsonResponse
    {
        try {
            $db = $dm->getDocumentDatabase(CommandeEvent::class);
            $result = $db->command(['ping' => 1])->toArray();

            return $this->json(['ok' => true, 'mongo' => $result[0] ?? $result]);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'message' => 'Mongo indisponible'], 500);
        }
    }

    /**
     * GET /api/admin/stats/summary?days=30&menuId=12
     */
    #[Route('/summary', name: 'api_admin_stats_summary', methods: ['GET'])]
    public function summary(Request $request, DocumentManager $dm): JsonResponse
    {
        try {
            $coll = $dm->getDocumentCollection(CommandeEvent::class);

            $baseMatch = $this->buildBaseMatch($request);
            $baseStage = $baseMatch ? [['$match' => $baseMatch]] : [];

            $totalEvents = $baseMatch ? $coll->countDocuments($baseMatch) : $coll->countDocuments();

            $byStatutRaw = $coll->aggregate([
                ...$baseStage,
                ['$group' => ['_id' => '$statut', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
            ])->toArray();

            $byTypeRaw = $coll->aggregate([
                ...$baseStage,
                ['$group' => ['_id' => '$type', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
            ])->toArray();

            $createdMatch = array_merge($baseMatch, ['type' => 'created']);
            $createdStage = [['$match' => $createdMatch]];

            $caTotalRaw = $coll->aggregate([
                ...$createdStage,
                ['$group' => [
                    '_id' => null,
                    'ca' => ['$sum' => ['$ifNull' => ['$prixTotal', 0]]],
                    'count' => ['$sum' => 1],
                ]],
            ])->toArray();

            $caTotal = (float)($caTotalRaw[0]['ca'] ?? 0);
            $nbCommandes = (int)($caTotalRaw[0]['count'] ?? 0);

            $caParMenuRaw = $coll->aggregate([
                ...$createdStage,
                ['$group' => [
                    '_id' => ['$ifNull' => ['$menuTitre', '—']],
                    'menuId' => ['$max' => '$menuId'],
                    'ca' => ['$sum' => ['$ifNull' => ['$prixTotal', 0]]],
                    'count' => ['$sum' => 1],
                ]],
                ['$sort' => ['ca' => -1]],
                ['$limit' => 50],
            ])->toArray();

            $lastRaw = $coll->find($baseMatch ?: [], [
                'sort' => ['createdAt' => -1],
                'limit' => 20,
                'projection' => [
                    'commandeId' => 1,
                    'type' => 1,
                    'statut' => 1,
                    'menuId' => 1,
                    'menuTitre' => 1,
                    'prixTotal' => 1,
                    'details' => 1,
                    'createdAt' => 1,
                ],
            ])->toArray();

            $byStatut = array_map(function ($row) {
                $a = $this->toArraySafe($row);
                return ['statut' => $a['_id'] ?? null, 'count' => (int)($a['count'] ?? 0)];
            }, $byStatutRaw);

            $byType = array_map(function ($row) {
                $a = $this->toArraySafe($row);
                return ['type' => $a['_id'] ?? null, 'count' => (int)($a['count'] ?? 0)];
            }, $byTypeRaw);

            $caParMenu = array_map(function ($row) {
                $a = $this->toArraySafe($row);
                return [
                    'menuId' => isset($a['menuId']) ? (int)$a['menuId'] : null,
                    'menuTitre' => (string)($a['_id'] ?? '—'),
                    'ca' => (float)($a['ca'] ?? 0),
                    'count' => (int)($a['count'] ?? 0),
                ];
            }, $caParMenuRaw);

            $lastEvents = array_map(function ($doc) {
                $a = $this->toArraySafe($doc);
                return [
                    'commandeId' => (int)($a['commandeId'] ?? 0),
                    'type' => (string)($a['type'] ?? ''),
                    'statut' => (string)($a['statut'] ?? ''),
                    'menuId' => isset($a['menuId']) ? (int)$a['menuId'] : null,
                    'menuTitre' => $a['menuTitre'] ?? null,
                    'prixTotal' => isset($a['prixTotal']) ? (float)$a['prixTotal'] : null,
                    'details' => $a['details'] ?? null,
                    'createdAt' => $this->formatCreatedAt($a['createdAt'] ?? null),
                ];
            }, $lastRaw);

            return $this->json([
                'ok' => true,
                'filters' => [
                    'days' => $request->query->get('days') !== null ? (int)$request->query->get('days') : null,
                    'menuId' => $request->query->get('menuId') !== null ? (int)$request->query->get('menuId') : null,
                ],
                'totalEvents' => $totalEvents,

                'nbCommandes' => $nbCommandes,
                'caTotal' => $caTotal,
                'caParMenu' => $caParMenu,

                'byType' => $byType,
                'byStatut' => $byStatut,
                'lastEvents' => $lastEvents,
            ]);
        } catch (\Throwable) {
            return $this->json([
                'ok' => false,
                'message' => 'Erreur stats Mongo',
                'filters' => [
                    'days' => $request->query->get('days') !== null ? (int)$request->query->get('days') : null,
                    'menuId' => $request->query->get('menuId') !== null ? (int)$request->query->get('menuId') : null,
                ],
                'totalEvents' => 0,
                'nbCommandes' => 0,
                'caTotal' => 0,
                'caParMenu' => [],
                'byType' => [],
                'byStatut' => [],
                'lastEvents' => [],
            ], 200);
        }
    }
}
