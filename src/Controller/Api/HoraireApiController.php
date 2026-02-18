<?php

namespace App\Controller\Api;

use App\Entity\Horaire;
use App\Repository\HoraireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
/**
 * API des horaires
 */
#[Route('/api/horaires')]
class HoraireApiController extends AbstractController
{

    #[Route('', name: 'api_horaires_list', methods: ['GET'])]
    public function list(HoraireRepository $repo): JsonResponse
    {
        $items = $repo->findAll();

        $data = array_map(fn(Horaire $h) => [
            'id' => $h->getId(),
            'jour' => $h->getJour(),
            'ouverture' => $h->getOuverture()?->format('H:i'),
            'fermeture' => $h->getFermeture()?->format('H:i'),
        ], $items);

        return $this->json($data);
    }

    /**
     * Ajoute ou met à jour un horaire (upsert = update ou insert).
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('', name: 'api_horaires_upsert', methods: ['PUT'])]
    public function upsert(Request $request, HoraireRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $jour = trim($data['jour'] ?? '');
        $ouverture = (string)($data['ouverture'] ?? '');
        $fermeture = (string)($data['fermeture'] ?? '');

        if (!$jour || !$ouverture || !$fermeture) {
            return $this->json(['message' => 'Champs manquants'], 400);
        }

        $ouv = \DateTime::createFromFormat('H:i', $ouverture);
        $ferm = \DateTime::createFromFormat('H:i', $fermeture);
        if (!$ouv || !$ferm) {
            return $this->json(['message' => 'Format heure invalide (HH:MM)'], 400);
        }

        $horaire = $repo->findOneBy(['jour' => $jour]);
        if (!$horaire) {
            $horaire = new Horaire();
            $horaire->setJour($jour);
            $em->persist($horaire);
        }

        $horaire->setOuverture($ouv);
        $horaire->setFermeture($ferm);

        $em->flush();

        return $this->json(['message' => 'Horaire enregistré']);
    }
}
