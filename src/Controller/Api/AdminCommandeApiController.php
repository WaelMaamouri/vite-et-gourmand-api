<?php

namespace App\Controller\Api;

use App\Entity\Commande;
use App\Entity\CommandeStatutHistorique;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\ApiTokenAuth;

/**
 * Contrôleur API pour la gestion des commande côté administrateur.
 */

#[IsGranted(attribute: 'ROLE_ADMIN')]

#[Route('/api/admin/commandes')]
class AdminCommandeApiController extends AbstractController
{
    #[Route('', methods:['GET'])]
    public function list(Request $request, CommandeRepository $repo, EntityManagerInterface $em): JsonResponse
    {


        $statut = $request->query->get('statut');
        $criteria = $statut ? ['statut' => $statut] : [];
        $cmds = $repo->findBy($criteria, ['id' => 'DESC']);

        $data = array_map(fn(Commande $c) => [
            'id' => $c->getId(),
            'statut' => $c->getStatut(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i'),
            'menu' => [
                'id' => $c->getMenu()?->getId(),
                'titre' => $c->getMenu()?->getTitre(),
                'prixMin' => $c->getMenu()?->getPrixMin(),
            ],
            'utilisateur' => [
                'id' => $c->getUtilisateur()?->getId(),
                'nom' => $c->getUtilisateur()?->getNom(),
                'prenom' => $c->getUtilisateur()?->getPrenom(),
                'email' => $c->getUtilisateur()?->getEmail(),
                'gsm' => $c->getUtilisateur()?->getGsm(),
            ],
            'prestation' => [
                'date' => $c->getDatePrestation()?->format('Y-m-d'),
                'heure' => $c->getHeureLivraison()?->format('H:i'),
                'adresse' => $c->getAdressePrestation(),
                'ville' => $c->getVillePrestation(),
                'km' => $c->getKmParcourus(),
                'nbPersonnes' => $c->getNbPersonnes(),
                'prixTotal' => $c->getPrixTotal(),
            ],
        ], $cmds);

        return $this->json($data);
    }



    #[Route('/{id}/statut', name: 'api_admin_commandes_update_statut', methods: ['PATCH'])]
    public function updateStatut(
        int $id,
        Request $request,
        CommandeRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $cmd = $repo->find($id);
        if (!$cmd) return $this->json(['message' => 'Commande introuvable'], 404);

        $data = json_decode($request->getContent(), true) ?? [];
        $statut = $data['statut'] ?? null;
        $motif = trim((string)($data['motifAnnulation'] ?? ''));

        $allowed = [
            Commande::STATUT_EN_ATTENTE,
            Commande::STATUT_ACCEPTE,
            Commande::STATUT_PREPARATION,
            Commande::STATUT_LIVRAISON,
            Commande::STATUT_LIVRE,
            Commande::STATUT_ATTENTE_MATERIEL,
            Commande::STATUT_TERMINEE,
            Commande::STATUT_ANNULEE,
        ];

        if (!in_array($statut, $allowed, true)) {
            return $this->json(['message' => 'Statut invalide'], 400);
        }

        $cmd->setStatut($statut);

        if ($statut === Commande::STATUT_ANNULEE) {
            $cmd->setMotifAnnulation($motif ?: 'Annulation');
        } else {
            $cmd->setMotifAnnulation(null);
        }

        $hist = new CommandeStatutHistorique();
        $hist->setCommande($cmd);
        $hist->setStatut($statut);
        $hist->setChangedAt(new \DateTimeImmutable());
        $em->persist($hist);

        $em->flush();

        return $this->json(['message' => 'Statut mis à jour']);
    }
}
