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
 * Contrôleur API pour la gestion des horaires côté administrateur.
 */

#[Route('/api/admin/horaires')]
#[IsGranted('ROLE_ADMIN')]
class AdminHoraireApiController extends AbstractController
{
    #[Route('', name: 'api_admin_horaires_list', methods: ['GET'])]
    public function list(HoraireRepository $repo): JsonResponse
    {
        $horaires = $repo->findBy([], ['id' => 'ASC']);

        $data = array_map(fn(Horaire $h) => [
            'id' => $h->getId(),
            'jour' => $h->getJour(),
            'ouverture' => $h->getOuverture()?->format('H:i'),
            'fermeture' => $h->getFermeture()?->format('H:i'),
        ], $horaires);

        return $this->json($data);
    }

    #[Route('', name: 'api_admin_horaires_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $jour = trim((string)($data['jour'] ?? ''));
        $ouverture = (string)($data['ouverture'] ?? '');
        $fermeture = (string)($data['fermeture'] ?? '');

        if ($jour === '' || $ouverture === '' || $fermeture === '') {
            return $this->json(['message' => 'Champs obligatoires manquants.'], 400);
        }

        $o = \DateTime::createFromFormat('H:i', $ouverture);
        $f = \DateTime::createFromFormat('H:i', $fermeture);

        if (!$o || !$f) return $this->json(['message' => 'Heure invalide.'], 400);

        $h = new Horaire();
        $h->setJour($jour);
        $h->setOuverture($o);
        $h->setFermeture($f);

        $em->persist($h);
        $em->flush();

        return $this->json(['message' => 'Horaire créé', 'id' => $h->getId()], 201);
    }

    #[Route('/{id}', name: 'api_admin_horaires_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, HoraireRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $h = $repo->find($id);
        if (!$h) return $this->json(['message' => 'Horaire introuvable'], 404);

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['jour'])) $h->setJour(trim((string)$data['jour']));

        if (isset($data['ouverture'])) {
            $o = \DateTime::createFromFormat('H:i', (string)$data['ouverture']);
            if (!$o) return $this->json(['message' => 'Ouverture invalide'], 400);
            $h->setOuverture($o);
        }

        if (isset($data['fermeture'])) {
            $f = \DateTime::createFromFormat('H:i', (string)$data['fermeture']);
            if (!$f) return $this->json(['message' => 'Fermeture invalide'], 400);
            $h->setFermeture($f);
        }

        $em->flush();

        return $this->json(['message' => 'Horaire mis à jour']);
    }

    #[Route('/{id}', name: 'api_admin_horaires_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, HoraireRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $h = $repo->find($id);
        if (!$h) return $this->json(['message' => 'Horaire introuvable'], 404);

        $em->remove($h);
        $em->flush();

        return $this->json(['message' => 'Horaire supprimé']);
    }
}
