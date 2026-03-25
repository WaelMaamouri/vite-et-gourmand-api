<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MailService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthApiController extends AbstractController
{
    private const RESET_TOKEN_TTL_HOURS = 1;

    public function __construct(
        #[Autowire('%app.front_app_url%')]
        private readonly string $frontAppUrl,
        #[Autowire('%app.mailer_from_email%')]
        private readonly string $mailerFromEmail,
    ) {
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        MailService $mailService,
        LoggerInterface $logger
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

        try {
            if ($users->findOneBy(['email' => $email])) {
                return $this->json(['message' => 'Email déjà utilisé'], 409);
            }
        } catch (\Throwable $e) {
            $logger->error('Inscription : lecture email en base — ' . $e->getMessage(), ['exception' => $e]);

            return $this->json(['message' => 'Impossible de créer le compte pour le moment. Réessayez plus tard.'], 503);
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

        try {
            $em->persist($user);
            $em->flush();
        } catch (\Throwable $e) {
            for ($ex = $e; $ex !== null; $ex = $ex->getPrevious()) {
                if ($ex instanceof UniqueConstraintViolationException) {
                    return $this->json(['message' => 'Email déjà utilisé'], 409);
                }
            }
            $logger->error('Inscription : échec en base — ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->json(['message' => 'Impossible de créer le compte pour le moment. Réessayez plus tard.'], 503);
        }

        // Toute erreur mail (DSN manquant, Brevo, etc.) : le compte existe déjà — ne pas renvoyer 500.
        try {
            $mailService->sendRegistrationConfirmation($user->getEmail() ?? '', (string) $user->getPrenom());
        } catch (\Throwable $e) {
            $logger->warning('Email de confirmation inscription non envoyé : ' . $e->getMessage(), [
                'email' => $user->getEmail(),
                'exception' => $e,
            ]);
        }

        return $this->json(['message' => 'Utilisateur créé'], 201);
    }

    #[Route('/api/auth/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): JsonResponse {
        $logger->info('forgot-password endpoint called');
        try {
            $data = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->json(['message' => 'JSON invalide'], 400);
        }

        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Email invalide'], 400);
        }

        $generic = ['message' => 'Si le compte existe, un email a été envoyé.'];

        $user = $users->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return $this->json($generic);
        }

        $base = rtrim($this->frontAppUrl, '/');
        if ($base === '') {
            $logger->error('FRONT_APP_URL est vide : impossible de générer le lien de réinitialisation.');
            return $this->json(['message' => 'Configuration serveur incomplète (FRONT_APP_URL).'], 503);
        }

        $rawToken = bin2hex(random_bytes(32));
        $user->setResetToken($rawToken);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+' . self::RESET_TOKEN_TTL_HOURS . ' hours'));
        $em->flush();

        $link = $base . '/reset-password?token=' . rawurlencode($rawToken);
        $body = "Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable "
            . self::RESET_TOKEN_TTL_HOURS . " h) :\n\n{$link}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n\n— Vite & Gourmand\n";

        $message = (new Email())
            ->from($this->mailerFromEmail)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe — Vite & Gourmand')
            ->text($body);

        try {
            $mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            $logger->error('Échec envoi email reset password : ' . $e->getMessage());

            return $this->json(['message' => 'Envoi de l’email impossible pour le moment. Réessayez plus tard.'], 503);
        }

        return $this->json($generic);
    }

    #[Route('/api/auth/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->json(['message' => 'JSON invalide'], 400);
        }

        $token = trim((string)($data['token'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($token === '' || $password === '') {
            return $this->json(['message' => 'Token et mot de passe requis'], 400);
        }

        if (!self::isPasswordStrong($password)) {
            return $this->json([
                'message' => 'Mot de passe invalide (10+ caractères, majuscule, minuscule, chiffre, caractère spécial).',
            ], 400);
        }

        $user = $users->findOneBy(['resetToken' => $token]);
        if (!$user instanceof User) {
            return $this->json(['message' => 'Lien invalide ou expiré'], 400);
        }

        $expires = $user->getResetTokenExpiresAt();
        if ($expires === null || $expires < new \DateTimeImmutable()) {
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $em->flush();

            return $this->json(['message' => 'Lien invalide ou expiré'], 400);
        }

        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour']);
    }

    /** Même règle que le front (resetPasswordPage.js). */
    private static function isPasswordStrong(string $password): bool
    {
        return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/', $password);
    }
}
