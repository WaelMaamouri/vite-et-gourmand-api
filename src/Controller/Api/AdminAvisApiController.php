<?php

namespace App\Controller\Api;

use App\Entity\Avis;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/avis')]
#[IsGranted('ROLE_ADMIN')]
class AdminAvisApiController extends AbstractController
{
    #[Route('/pending', name: 'api_admin_avis_pending', methods: ['GET'])]
    public function pending(AvisRepository $repo): JsonResponse
    {
        $avis = $repo->findBy(['valide' => false], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn(Avis $a) => $this->toArray($a), $avis));
    }

    #[Route('', name: 'api_admin_avis_list', methods: ['GET'])]
    public function list(AvisRepository $repo): JsonResponse
    {
        $avis = $repo->findBy([], ['createdAt' => 'DESC']);
        return $this->json(array_map(fn(Avis $a) => $this->toArray($a), $avis));
    }

    #[Route('/{id}', name: 'api_admin_avis_validate', methods: ['PATCH'])]
    public function validateAvis(
    int $id,
    Request $request,
    AvisRepository $repo,
    EntityManagerInterface $em
    ): JsonResponse {
    $avis = $repo->find($id);

    if (!$avis) {
        return $this->json(['message' => 'Avis introuvable'], 404);
    }

    $data = json_decode($request->getContent(), true) ?? [];

    if (!array_key_exists('valide', $data)) {
        return $this->json(['message' => 'Champ "valide" requis'], 400);
    }

    if ($data['valide'] === true) {
        $avis->setValide(true);
        $em->flush();

        return $this->json(['message' => 'Avis validé']);
    }

    $em->remove($avis);
    $em->flush();

    return $this->json(['message' => 'Avis refusé']);
}


    #[Route('/{id}', name: 'api_admin_avis_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteAvis(int $id, AvisRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $avis = $repo->find($id);
        if (!$avis) return $this->json(['message' => 'Avis introuvable'], 404);

        $em->remove($avis);
        $em->flush();

        return $this->json(['message' => 'Avis supprimé']);
    }

    private function toArray(Avis $a): array
    {
        return [
            'id' => $a->getId(),
            'note' => $a->getNote(),
            'commentaire' => $a->getCommentaire(),
            'valide' => $a->isValide(),
            'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i'),
            'menu' => [
                'id' => $a->getMenu()?->getId(),
                'titre' => $a->getMenu()?->getTitre(),
            ],
            'utilisateur' => [
                'id' => $a->getUtilisateur()?->getId(),
                'nom' => $a->getUtilisateur()?->getNom(),
                'prenom' => $a->getUtilisateur()?->getPrenom(),
                'email' => $a->getUtilisateur()?->getEmail(),
            ],
        ];
    }
}
