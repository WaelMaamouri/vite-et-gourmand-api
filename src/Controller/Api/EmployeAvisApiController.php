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

#[Route('/api/employee/avis')]
#[IsGranted('ROLE_EMPLOYE')]
class EmployeAvisApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function pending(AvisRepository $repo): JsonResponse
    {
        $avis = $repo->findBy(['valide' => false], ['createdAt' => 'DESC']);

        $data = array_map(fn(Avis $a) => [
            'id' => $a->getId(),
            'note' => $a->getNote(),
            'commentaire' => $a->getCommentaire(),
            'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i'),
            'menuTitre' => $a->getMenu()->getTitre(),
            'clientEmail' => $a->getUtilisateur()->getEmail(),
            'commandeId' => $a->getCommande()->getId(),
        ], $avis);

        return $this->json($data);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function moderate(Avis $avis, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('valide', $payload)) {
            return $this->json(['message' => 'valide manquant (true/false)'], 400);
        }

        $avis->setValide((bool)$payload['valide']);
        $em->flush();

        return $this->json(['ok' => true, 'valide' => $avis->isValide()]);
    }
}
