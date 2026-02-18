<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthApiController extends AbstractController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'JSON invalide'], 400);
        }

        $prenom = trim((string)($data['prenom'] ?? ''));
        $nom = trim((string)($data['nom'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        $adresse = trim((string)($data['adresse'] ?? ''));
        $ville = trim((string)($data['ville'] ?? ''));

        $codePostal = $data['codePostal'] ?? $data['code_postal'] ?? null;
        $gsm = trim((string)($data['gsm'] ?? ''));

        if (!$prenom || !$nom || !$email || !$password || !$adresse || !$ville || !$codePostal || !$gsm) {
            return $this->json(['message' => 'Champs manquants'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Email invalide'], 400);
        }

        $codePostalInt = (int)$codePostal;
        if ($codePostalInt <= 0) {
            return $this->json(['message' => 'Code postal invalide'], 400);
        }

        if ($users->findOneBy(['email' => $email])) {
            return $this->json(['message' => 'Email déjà utilisé'], 409);
        }

        $user = new User();
        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setAdresse($adresse);
        $user->setVille($ville);
        $user->setCodePostal($codePostalInt);
        $user->setGsm($gsm);

        $user->setRoles(['ROLE_USER']);

        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json(['message' => 'Utilisateur créé'], 201);
    }
}
