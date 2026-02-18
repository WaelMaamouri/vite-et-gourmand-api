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

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminApiController extends AbstractController
{
    #[Route('/ping', name: 'api_admin_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['ok' => true]);
    }
}