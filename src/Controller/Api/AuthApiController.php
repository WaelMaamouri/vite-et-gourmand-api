<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Auth
 */
#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    private const PWD_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $prenom = trim((string)($data['prenom'] ?? ''));
        $nom = trim((string)($data['nom'] ?? ''));
        $gsm = trim((string)($data['gsm'] ?? ($data['telephone'] ?? '')));
        $adresse = trim((string)($data['adresse'] ?? ''));
        $codePostal = (int)($data['codePostal'] ?? 0);
        $ville = trim((string)($data['ville'] ?? ''));

        $missing = [];
        if ($prenom === '') $missing[] = 'prenom';
        if ($nom === '') $missing[] = 'nom';
        if ($email === '') $missing[] = 'email';
        if ($password === '') $missing[] = 'password';
        if ($gsm === '') $missing[] = 'gsm';
        if ($adresse === '') $missing[] = 'adresse';
        if ($codePostal <= 0) $missing[] = 'codePostal';
        if ($ville === '') $missing[] = 'ville';

        if ($missing) {
            return $this->json([
                'message' => 'Champs obligatoires manquants.',
                'missing' => $missing
            ], 400);
        }

        if (!preg_match(self::PWD_REGEX, $password)) {
            return $this->json([
                'message' => 'Mot de passe invalide (10+ caractères, 1 maj, 1 min, 1 chiffre, 1 spécial).'
            ], 400);
        }

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['message' => 'Email déjà utilisé.'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setGsm($gsm);
        $user->setAdresse($adresse);
        $user->setCodePostal($codePostal);
        $user->setVille($ville);

        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setIsVerified(false);

        $em->persist($user);
        $em->flush();

        // Mail de bienvenue
        try {
            $emailMessage = (new Email())
                ->from(new Address('vitegourmand00@gmail.com', 'Vite & Gourmand'))
                ->to($user->getEmail())
                ->subject('✅ Bienvenue sur Vite & Gourmand')
                ->text(
                    "Bonjour {$user->getPrenom()},\n\n" .
                    "Votre compte a bien été créé.\n\n" .
                    "À très bientôt,\nVite & Gourmand"
                );

            $mailer->send($emailMessage);
        } catch (\Throwable) {
            // pas bloquant
        }

        return $this->json(['message' => 'Inscription réussie'], 201);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(Security $security): JsonResponse
    {
        $u = $security->getUser();
        if (!$u || !($u instanceof User)) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        return $this->json([
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'prenom' => $u->getPrenom(),
            'nom' => $u->getNom(),
            'roles' => $u->getRoles(),
        ]);
    }

    /**
     * Demande de réinitialisation :
     */
    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim((string)($data['email'] ?? ''));

        if ($email === '') {
            return $this->json(['message' => 'Email requis'], 400);
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        // Réponse neutre même si email inconnu (bonne pratique)
        if (!$user) {
            return $this->json(['message' => 'Si le compte existe, un email a été envoyé.'], 200);
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $em->flush();

        $resetLink = 'http://127.0.0.1:5500/reset-password?token=' . $token;

        try {
            $mail = (new Email())
                ->from(new Address('vitegourmand00@gmail.com', 'Vite & Gourmand'))
                ->to($user->getEmail())
                ->subject('🔐 Réinitialisation de votre mot de passe')
                ->text(
                    "Bonjour {$user->getPrenom()},\n\n" .
                    "Cliquez sur ce lien pour réinitialiser votre mot de passe :\n{$resetLink}\n\n" .
                    "Ce lien expire dans 1 heure.\n\n" .
                    "Vite & Gourmand"
                );

            $mailer->send($mail);
        } catch (\Throwable) {
            // pas bloquant
        }

        return $this->json(['message' => 'Si le compte existe, un email a été envoyé.'], 200);
    }

    /**
     * Réinitialisation :
     * POST { "token": "...", "password": "..." }
     */
    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $token = trim((string)($data['token'] ?? ''));
        $newPassword = (string)($data['password'] ?? '');

        if ($token === '' || $newPassword === '') {
            return $this->json(['message' => 'Token et password requis'], 400);
        }

        if (!preg_match(self::PWD_REGEX, $newPassword)) {
            return $this->json([
                'message' => 'Mot de passe invalide (10+ caractères, 1 maj, 1 min, 1 chiffre, 1 spécial).'
            ], 400);
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['resetToken' => $token]);
        if (!$user) {
            return $this->json(['message' => 'Token invalide'], 400);
        }

        $exp = $user->getResetTokenExpiresAt();
        if (!$exp || $exp < new \DateTimeImmutable()) {
            return $this->json(['message' => 'Token expiré'], 400);
        }

        $user->setPassword($hasher->hashPassword($user, $newPassword));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour'], 200);
    }

    #[Route('/logout', name: '_logout_api', methods: ['POST'])]
    public function logout(): Response
    {
        throw new \LogicException('This should never be reached.');
    }
}
