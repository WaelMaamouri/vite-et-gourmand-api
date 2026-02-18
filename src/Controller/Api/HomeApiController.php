<?php

namespace App\Controller\Api;

use App\Repository\AvisRepository;
use App\Repository\HoraireRepository;
use App\Repository\MenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/home')]
class HomeApiController extends AbstractController
{
    #[Route('', name: 'api_home', methods: ['GET'])]
    public function index(
        AvisRepository $avisRepo,
        HoraireRepository $horaireRepo,
        MenuRepository $menuRepo
    ): JsonResponse {
        $avis = $avisRepo->findBy(['valide' => true], ['createdAt' => 'DESC'], 5);

        return $this->json([
            'avis' => array_map(fn($a) => [
                'id' => $a->getId(),
                'note' => $a->getNote(),
                'commentaire' => $a->getCommentaire(),
                'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i'),
                'utilisateur' => [
                    'prenom' => $a->getUtilisateur()?->getPrenom(),
                    'nom' => $a->getUtilisateur()?->getNom(),
                ],
                'menu' => [
                    'id' => $a->getMenu()?->getId(),
                    'titre' => $a->getMenu()?->getTitre(),
                ],
            ], $avis),
            'horaires' => array_map(fn($h) => [
                'id' => $h->getId(),
                'jour' => $h->getJour(),
                'ouverture' => $h->getOuverture()?->format('H:i'),
                'fermeture' => $h->getFermeture()?->format('H:i'),
            ], $horaireRepo->findAll()),
            'menus' => array_map(fn($m) => [
                'id' => $m->getId(),
                'titre' => $m->getTitre(),
                'prixMin' => $m->getPrixMin(),
                'theme' => $m->getTheme(),
                'regime' => $m->getRegime(),
                'image' => $m->getImage(),
            ], $menuRepo->findAll()),
        ]);
    }
}
