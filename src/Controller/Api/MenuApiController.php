<?php

namespace App\Controller\Api;

use App\Repository\MenuRepository;
use App\Entity\Menu;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * API des menus :
 */
#[Route('/api/menus')]
class MenuApiController extends AbstractController
{
    /**
     * Retourne la liste des menus.
     */
    #[Route('', name: 'api_menus_list', methods: ['GET'])]
    public function list(MenuRepository $menuRepository, Request $request): JsonResponse
    {
        $theme = $request->query->get('theme');
        $regime = $request->query->get('regime');
        $minPrix = $request->query->get('minPrix');
        $maxPrix = $request->query->get('maxPrix');
        $minPersonnes = $request->query->get('minPersonnes');

        $qb = $menuRepository->createQueryBuilder('m');

        if ($theme) {
            $qb->andWhere('m.theme = :theme')
               ->setParameter('theme', $theme);
        }

        if ($regime) {
            $qb->andWhere('m.regime = :regime')
               ->setParameter('regime', $regime);
        }

        if ($minPrix !== null && $minPrix !== '') {
            $qb->andWhere('m.prixMin >= :minPrix')
               ->setParameter('minPrix', $minPrix);
        }

        if ($maxPrix !== null && $maxPrix !== '') {
            $qb->andWhere('m.prixMin <= :maxPrix')
               ->setParameter('maxPrix', $maxPrix);
        }

        if ($minPersonnes !== null && $minPersonnes !== '') {
            $qb->andWhere('m.nbPersonnesMin >= :minPersonnes')
               ->setParameter('minPersonnes', (int) $minPersonnes);
        }

        $menus = $qb->getQuery()->getResult();


        $baseUrl = $this->getParameter('app_base_url');

        $data = array_map(function (Menu $m) use ($baseUrl) {
            return [
                'id' => $m->getId(),
                'titre' => $m->getTitre(),
                'description' => $m->getDescription(),
                'prixMin' => $m->getPrixMin(),
                'nbPersonnesMin' => $m->getNbPersonnesMin(),
                'theme' => $m->getTheme(),
                'regime' => $m->getRegime(),
                'image' => $m->getImage() ? ($baseUrl . $m->getImage()) : null,
                'conditions' => $m->getConditions(),
                'details' => $m->getDetails(),
                'entrees' => $m->getEntrees(),
                'plats' => $m->getPlats(),
                'desserts' => $m->getDesserts(),
            ];
        }, $menus);

        return $this->json($data, 200);
    }

    /**
     * Retourne le détail d’un menu par son id.
     */
    #[Route('/{id}', name: 'api_menus_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, MenuRepository $menuRepository): JsonResponse
    {
        $menu = $menuRepository->find($id);

        if (!$menu) {
            return $this->json(['message' => 'Menu introuvable'], 404);
        }

        $baseUrl = $this->getParameter('app_base_url');

        $data = [
            'id' => $menu->getId(),
            'titre' => $menu->getTitre(),
            'description' => $menu->getDescription(),
            'prixMin' => $menu->getPrixMin(),
            'nbPersonnesMin' => $menu->getNbPersonnesMin(),
            'theme' => $menu->getTheme(),
            'regime' => $menu->getRegime(),
            'image' => $menu->getImage(), // à harmoniser si besoin
            'conditions' => $menu->getConditions(),
            'entrees' => $menu->getEntrees(),
            'plats' => $menu->getPlats(),
            'desserts' => $menu->getDesserts(),
        ];

        return $this->json($data, 200);
    }
}
