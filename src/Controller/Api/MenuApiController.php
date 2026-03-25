<?php

namespace App\Controller\Api;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/menus')]
class MenuApiController extends AbstractController
{
    private function toPublicImageUrl(Request $request, ?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return $request->getSchemeAndHttpHost() . '/' . ltrim($image, '/');
    }

    #[Route('', name: 'api_menus_list', methods: ['GET'])]
    public function list(MenuRepository $menuRepository, Request $request, LoggerInterface $logger): JsonResponse
    {
        $theme = $request->query->get('theme');
        $regime = $request->query->get('regime');
        $minPrix = $request->query->get('minPrix');
        $maxPrix = $request->query->get('maxPrix');
        $minPersonnes = $request->query->get('minPersonnes');

        try {
            $qb = $menuRepository->createQueryBuilder('m');

            if ($theme) {
                $qb->andWhere('m.theme = :theme')->setParameter('theme', $theme);
            }

            if ($regime) {
                $qb->andWhere('m.regime = :regime')->setParameter('regime', $regime);
            }

            if ($minPrix !== null && $minPrix !== '') {
                $qb->andWhere('m.prixMin >= :minPrix')->setParameter('minPrix', $minPrix);
            }

            if ($maxPrix !== null && $maxPrix !== '') {
                $qb->andWhere('m.prixMin <= :maxPrix')->setParameter('maxPrix', $maxPrix);
            }

            if ($minPersonnes !== null && $minPersonnes !== '') {
                $qb->andWhere('m.nbPersonnesMin >= :minPersonnes')->setParameter('minPersonnes', (int) $minPersonnes);
            }

            $menus = $qb->getQuery()->getResult();
        } catch (\Throwable $e) {
            $logger->error('Liste menus : échec BDD — ' . $e->getMessage(), ['exception' => $e]);

            return $this->json(['message' => 'Impossible de charger les menus pour le moment.'], 503);
        }

        $data = array_map(function (Menu $m) use ($request) {
            return [
                'id' => $m->getId(),
                'titre' => $m->getTitre(),
                'description' => $m->getDescription(),
                'prixMin' => $m->getPrixMin(),
                'nbPersonnesMin' => $m->getNbPersonnesMin(),
                'theme' => $m->getTheme(),
                'regime' => $m->getRegime(),
            'image' => $this->toPublicImageUrl($request, $m->getImage()),
                'conditions' => $m->getConditions(),
                'details' => $m->getDetails(),
                'entrees' => $m->getEntrees(),
                'plats' => $m->getPlats(),
                'desserts' => $m->getDesserts(),
            ];
        }, $menus);

        return $this->json($data, 200);
    }

    #[Route('/{id}', name: 'api_menus_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, MenuRepository $menuRepository, LoggerInterface $logger, Request $request): JsonResponse
    {
        try {
            $menu = $menuRepository->find($id);
        } catch (\Throwable $e) {
            $logger->error('Détail menu : échec BDD — ' . $e->getMessage(), ['exception' => $e]);

            return $this->json(['message' => 'Impossible de charger le menu pour le moment.'], 503);
        }

        if (!$menu) {
            return $this->json(['message' => 'Menu introuvable'], 404);
        }

        return $this->json([
            'id' => $menu->getId(),
            'titre' => $menu->getTitre(),
            'description' => $menu->getDescription(),
            'prixMin' => $menu->getPrixMin(),
            'nbPersonnesMin' => $menu->getNbPersonnesMin(),
            'theme' => $menu->getTheme(),
            'regime' => $menu->getRegime(),
            'image' => $this->toPublicImageUrl($request, $menu->getImage()),
            'conditions' => $menu->getConditions(),
            'details' => $menu->getDetails(),
            'entrees' => $menu->getEntrees(),
            'plats' => $menu->getPlats(),
            'desserts' => $menu->getDesserts(),
        ], 200);
    }
}
