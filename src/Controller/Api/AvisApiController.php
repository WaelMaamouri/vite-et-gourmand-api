<?php

namespace App\Controller\Api;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\User;
use App\Repository\AvisRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/avis')]
class AvisApiController extends AbstractController
{
    /**
     * GET /api/avis
     */
    #[Route('', name: 'api_avis_list', methods: ['GET'])]
    public function list(AvisRepository $repo): JsonResponse
    {
        $avis = $repo->findBy(['valide' => true], ['createdAt' => 'DESC']);

        $data = array_map(static fn(Avis $a) => [
            'id' => $a->getId(),
            'note' => $a->getNote(),
            'commentaire' => $a->getCommentaire(),
            'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i'),
            'menu' => [
                'id' => $a->getMenu()?->getId(),
                'titre' => $a->getMenu()?->getTitre(),
            ],
            'utilisateur' => [
                'prenom' => $a->getUtilisateur()?->getPrenom(),
                'nom' => $a->getUtilisateur()?->getNom(),
            ],
        ], $avis);

        return $this->json($data);
    }

    /**
     * POST /api/avis
     */
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'api_avis_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        AvisRepository $avisRepo,
        CommandeRepository $commandeRepo
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];

        $commandeId = (int)($payload['commandeId'] ?? 0);
        $note = (int)($payload['note'] ?? 0);
        $commentaire = trim((string)($payload['commentaire'] ?? ''));

        $missing = [];
        if ($commandeId <= 0) $missing[] = 'commandeId';
        if ($note < 1 || $note > 5) $missing[] = 'note';
        if ($commentaire === '') $missing[] = 'commentaire';

        if ($missing) {
            return $this->json([
                'message' => 'Champs invalides',
                'missing' => $missing,
            ], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        /** @var Commande|null $commande */
        $commande = $commandeRepo->find($commandeId);
        if (!$commande) {
            return $this->json(['message' => 'Commande introuvable'], 404);
        }

        if ($commande->getUtilisateur()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Accès interdit à cette commande'], 403);
        }

        if ($commande->getStatut() !== Commande::STATUT_TERMINEE) {
            return $this->json(['message' => 'Avis autorisé uniquement si commande terminée'], 400);
        }

        $deja = $avisRepo->findOneBy(['commande' => $commande]);
        if ($deja) {
            return $this->json(['message' => 'Un avis existe déjà pour cette commande'], 409);
        }

        $menu = $commande->getMenu();
        if (!$menu) {
            return $this->json(['message' => 'Menu manquant sur la commande'], 400);
        }

        $avis = new Avis();
        $avis->setUtilisateur($user);
        $avis->setCommande($commande);
        $avis->setMenu($menu);
        $avis->setNote($note);
        $avis->setCommentaire($commentaire);
        $avis->setValide(false); 
        $avis->setCreatedAt(new \DateTimeImmutable());

        $em->persist($avis);
        $em->flush();

        return $this->json([
            'message' => 'Avis envoyé (en attente de validation)',
            'id' => $avis->getId()
        ], 201);
    }
}
