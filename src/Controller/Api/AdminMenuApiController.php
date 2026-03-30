<?php

namespace App\Controller\Api;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use App\Service\CloudinaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/menus')]
#[IsGranted('ROLE_ADMIN')]
class AdminMenuApiController extends AbstractController
{
    private CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    private function getCloudinaryImageUrl(?string $cloudinaryId): ?string
    {
        if (!$cloudinaryId) {
            return null;
        }

        $cloudName = 'dw7arnugc';
        return "https://res.cloudinary.com/{$cloudName}/image/upload/{$cloudinaryId}";
    }

    private function toPublicImageUrl(Request $request, ?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return $request->getSchemeAndHttpHost() . '/' . ltrim($image, '/');
    }

    private function extractImageField(array $data): ?string
    {
        $value = $data['image'] ?? $data['imageUrl'] ?? null;
        return is_string($value) ? trim($value) : null;
    }

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
            'entrees' => $m->getEntrees(),
            'plats' => $m->getPlats(),
            'desserts' => $m->getDesserts(),
            'image' => $m->getImage() ?: null,
            'imageUrl' => $this->getCloudinaryImageUrl($m->getImage()),
            'conditions' => $m->getConditions(),
        ], $menus);

        return $this->json($data);
    }

    #[Route('/upload', name: 'api_admin_menus_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['message' => 'Invalid file.'], 400);
        }

        try {
            $result = $this->cloudinaryService->upload($file, 'menus');
            $publicId = (string) ($result['public_id'] ?? '');
            $secureUrl = (string) ($result['secure_url'] ?? '');

            if ($publicId === '' || $secureUrl === '') {
                return $this->json([
                    'message' => 'Unexpected upload response from media provider.',
                ], 502);
            }

            return $this->json([
                'publicId' => $publicId,
                'url' => $secureUrl,
                'path' => $secureUrl,
                'image' => $publicId,
                'imageUrl' => $secureUrl,
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }
    }

    #[Route('', name: 'api_admin_menus_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $validated = $this->validateMenuData($data);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $menu = new Menu();
        $menu->setTitre($validated['titre']);
        $menu->setDescription($validated['description']);
        $menu->setPrixMin($validated['prixMin']);
        $menu->setNbPersonnesMin($validated['nbPersonnesMin']);
        $menu->setTheme($validated['theme'] ?? null);
        $menu->setRegime($validated['regime'] ?? null);
        $menu->setEntrees($validated['entrees'] ?? null);
        $menu->setPlats($validated['plats'] ?? null);
        $menu->setDesserts($validated['desserts'] ?? null);
        $menu->setImage($this->extractImageField($data));
        $menu->setConditions($validated['conditions'] ?? null);

        $em->persist($menu);
        $em->flush();

        return $this->json([
            'message' => 'Menu created',
            'id' => $menu->getId(),
            'titre' => $menu->getTitre(),
            'description' => $menu->getDescription(),
            'prixMin' => $menu->getPrixMin(),
            'nbPersonnesMin' => $menu->getNbPersonnesMin(),
            'theme' => $menu->getTheme(),
            'regime' => $menu->getRegime(),
            'entrees' => $menu->getEntrees(),
            'plats' => $menu->getPlats(),
            'desserts' => $menu->getDesserts(),
            'image' => $menu->getImage() ?: null,
            'imageUrl' => $this->getCloudinaryImageUrl($menu->getImage()),
            'conditions' => $menu->getConditions(),
        ], 201);
    }

    private function validateMenuData(array $data): array|JsonResponse
    {
        $titre = trim((string)($data['titre'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $prixMin = (string)($data['prixMin'] ?? '');
        $nbPersonnesMin = (int)($data['nbPersonnesMin'] ?? 0);

        if ($titre === '' || $description === '' || $prixMin === '' || $nbPersonnesMin <= 0) {
            return $this->json(['message' => 'Missing required fields.'], 400);
        }

        return [
            'titre' => $titre,
            'description' => $description,
            'prixMin' => $prixMin,
            'nbPersonnesMin' => $nbPersonnesMin,
            'theme' => $data['theme'] ?? null,
            'regime' => $data['regime'] ?? null,
            'conditions' => $data['conditions'] ?? null,
        ];
    }

    #[Route('/{id}', name: 'api_admin_menus_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, MenuRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $menu = $repo->find($id);
        if (!$menu) {
            return $this->json(['message' => 'Menu not found.'], 404);
        }

        $data = json_decode((string) $request->getContent(), true) ?? [];
        $this->applyMenuUpdates($menu, $data);

        $em->flush();

        return $this->json([
            'message' => 'Menu updated',
            'image' => $menu->getImage() ?: null,
            'imageUrl' => $this->getCloudinaryImageUrl($menu->getImage()),
        ]);
    }

    private function applyMenuUpdates(Menu $menu, array $data): void
    {
        if (isset($data['titre'])) {
            $menu->setTitre(trim((string)$data['titre']));
        }
        if (isset($data['description'])) {
            $menu->setDescription(trim((string)$data['description']));
        }
        if (isset($data['prixMin'])) {
            $menu->setPrixMin((string)$data['prixMin']);
        }
        if (isset($data['nbPersonnesMin'])) {
            $menu->setNbPersonnesMin((int)$data['nbPersonnesMin']);
        }
        if (array_key_exists('theme', $data)) {
            $menu->setTheme($data['theme']);
        }
        if (array_key_exists('regime', $data)) {
            $menu->setRegime($data['regime']);
        }
        if (array_key_exists('entrees', $data)) {
            $menu->setEntrees($data['entrees']);
        }
        if (array_key_exists('plats', $data)) {
            $menu->setPlats($data['plats']);
        }
        if (array_key_exists('desserts', $data)) {
            $menu->setDesserts($data['desserts']);
        }
        if (array_key_exists('image', $data) || array_key_exists('imageUrl', $data)) {
            $menu->setImage($this->extractImageField($data));
        }
        if (array_key_exists('conditions', $data)) {
            $menu->setConditions($data['conditions']);
        }
    }

    #[Route('/{id}', name: 'api_admin_menus_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, MenuRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $menu = $repo->find($id);
        if (!$menu) {
            return $this->json(['message' => 'Menu not found.'], 404);
        }

        $em->remove($menu);
        $em->flush();

        return $this->json(['message' => 'Menu deleted']);
    }
}
