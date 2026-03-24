<?php

namespace App\Controller\Api;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur API pour la gestion des menus côté administrateur.
 */

#[Route('/api/admin/menus')]
#[IsGranted('ROLE_ADMIN')]
class AdminMenuApiController extends AbstractController
{
    #[Route('', name: 'api_admin_menus_list', methods: ['GET'])]
    public function list(MenuRepository $repo): JsonResponse
    {
        $menus = $repo->findBy([], ['id' => 'DESC']);

        $data = array_map(fn(Menu $m) => [
            'id' => $m->getId(),
            'titre' => $m->getTitre(),
            'description' => $m->getDescription(),
            'prixMin' => $m->getPrixMin(),
            'nbPersonnesMin' => $m->getNbPersonnesMin(),
            'theme' => $m->getTheme(),
            'regime' => $m->getRegime(),
            'image' => $m->getImage(),
            'conditions' => $m->getConditions(),
        ], $menus);

        return $this->json($data);
    }

    /**
     * Upload d’une image de menu (multipart, champ « file »).
     * Réponse : { "path": "/uploads/menus/...." } pour le champ image du menu.
     */
    #[Route('/upload', name: 'api_admin_menus_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['message' => 'Fichier « file » manquant ou invalide.'], 400);
        }

        $maxBytes = 5 * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            return $this->json(['message' => 'Fichier trop volumineux (max 5 Mo).'], 413);
        }

        $mime = (string) $file->getMimeType();
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return $this->json(['message' => 'Type non autorisé (JPEG, PNG, WebP, GIF).'], 415);
        }

        $ext = $allowed[$mime];
        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;

        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDir = $projectDir . '/public/uploads/menus';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return $this->json(['message' => 'Impossible de créer le dossier d’upload.'], 500);
        }

        try {
            $file->move($targetDir, $safeName);
        } catch (FileException) {
            return $this->json(['message' => 'Impossible d’enregistrer le fichier.'], 500);
        }

        $path = '/uploads/menus/' . $safeName;

        return $this->json(['path' => $path], 201);
    }

    #[Route('', name: 'api_admin_menus_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $titre = trim((string)($data['titre'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $prixMin = (string)($data['prixMin'] ?? '');
        $nbPersonnesMin = (int)($data['nbPersonnesMin'] ?? 0);

        if ($titre === '' || $description === '' || $prixMin === '' || $nbPersonnesMin <= 0) {
            return $this->json(['message' => 'Champs obligatoires manquants.'], 400);
        }

        $menu = new Menu();
        $menu->setTitre($titre);
        $menu->setDescription($description);
        $menu->setPrixMin($prixMin);
        $menu->setNbPersonnesMin($nbPersonnesMin);
        $menu->setTheme($data['theme'] ?? null);
        $menu->setRegime($data['regime'] ?? null);
        $menu->setImage($data['image'] ?? null);
        $menu->setConditions($data['conditions'] ?? null);

        $em->persist($menu);
        $em->flush();

        return $this->json(['message' => 'Menu créé', 'id' => $menu->getId()], 201);
    }

    #[Route('/{id}', name: 'api_admin_menus_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, MenuRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $menu = $repo->find($id);
        if (!$menu) return $this->json(['message' => 'Menu introuvable'], 404);

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['titre'])) $menu->setTitre(trim((string)$data['titre']));
        if (isset($data['description'])) $menu->setDescription(trim((string)$data['description']));
        if (isset($data['prixMin'])) $menu->setPrixMin((string)$data['prixMin']);
        if (isset($data['nbPersonnesMin'])) $menu->setNbPersonnesMin((int)$data['nbPersonnesMin']);
        if (array_key_exists('theme', $data)) $menu->setTheme($data['theme']);
        if (array_key_exists('regime', $data)) $menu->setRegime($data['regime']);
        if (array_key_exists('image', $data)) $menu->setImage($data['image']);
        if (array_key_exists('conditions', $data)) $menu->setConditions($data['conditions']);

        $em->flush();

        return $this->json(['message' => 'Menu mis à jour']);
    }

    #[Route('/{id}', name: 'api_admin_menus_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, MenuRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $menu = $repo->find($id);
        if (!$menu) return $this->json(['message' => 'Menu introuvable'], 404);

        $em->remove($menu);
        $em->flush();

        return $this->json(['message' => 'Menu supprimé']);
    }
}
