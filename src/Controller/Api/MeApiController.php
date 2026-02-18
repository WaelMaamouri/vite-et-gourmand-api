<?php

namespace App\Controller\Api;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Contrôleur API “Me” :
 * permet à l’utilisateur connecté de récupérer ses infos et ses commandes
 */
#[Route('/api/me')]
class MeApiController extends AbstractController
{
    /**
     * Retourne la liste des commandes de l’utilisateur connecté.
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/commandes', name: 'api_me_commandes', methods: ['GET'])]
    public function commandes(CommandeRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $cmds = $repo->findBy(['utilisateur' => $user], ['id' => 'DESC']);

        $data = array_map(fn(Commande $c) => [
            'id' => $c->getId(),
            'statut' => $c->getStatut(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i'),
            'menu' => [
                'id' => $c->getMenu()?->getId(),
                'titre' => $c->getMenu()?->getTitre(),
            ],
            'prestation' => [
                'date' => $c->getDatePrestation()?->format('Y-m-d'),
                'heure' => $c->getHeureLivraison()?->format('H:i'),
                'ville' => $c->getVillePrestation(),
                'prixTotal' => $c->getPrixTotal(),
            ],
        ], $cmds);

        return $this->json($data);
    }

    /**
     * Retourne les informations de l’utilisateur connecté.
     */
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'api_me', methods: ['GET'])]
    public function me(Security $security): JsonResponse
    {
        $u = $security->getUser();
        if (!$u || !($u instanceof User)) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        return $this->json([
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'prenom' => $u->getPrenom(),
            'nom' => $u->getNom(),
            'roles' => $u->getRoles(),
        ]);
    }
}
