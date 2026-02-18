<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/employes')]
#[IsGranted('ROLE_ADMIN')]
class AdminEmployeApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();

        $employes = array_filter($users, fn(User $u) => in_array('ROLE_EMPLOYE', $u->getRoles(), true));

        $data = array_map(fn(User $u) => [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'prenom' => $u->getPrenom(),
            'nom' => $u->getNom(),
            'roles' => $u->getRoles(),
            'actif' => method_exists($u, 'isActif') ? $u->isActif() : true,
        ], $employes);

        return $this->json($data);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = trim((string)($data['email'] ?? ''));
        $prenom = trim((string)($data['prenom'] ?? ''));
        $nom = trim((string)($data['nom'] ?? ''));

        if ($email === '' || $prenom === '' || $nom === '') {
            return $this->json(['message' => 'email, prenom, nom obligatoires'], 400);
        }

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['message' => 'Email déjà utilisé'], 409);
        }

        $tmpPassword = bin2hex(random_bytes(8));

        $u = new User();
        $u->setEmail($email);
        $u->setPrenom($prenom);
        $u->setNom($nom);
        $u->setRoles(['ROLE_EMPLOYE']);
        if (method_exists($u, 'setActif')) $u->setActif(true);

        $u->setPassword($hasher->hashPassword($u, $tmpPassword));

        $em->persist($u);
        $em->flush();

        // ✅ mail “compte créé” sans afficher le mdp
        try {
            $mailer->send(
                (new Email())
                    ->from('no-reply@vite-gourmand.test')
                    ->to($email)
                    ->subject('Votre compte employé a été créé')
                    ->text(
                        "Bonjour {$prenom},\n\n" .
                        "Votre compte employé a été créé.\n" .
                        "Pour définir votre mot de passe, utilisez la fonctionnalité \"Mot de passe oublié\".\n\n" .
                        "Vite & Gourmand"
                    )
            );
        } catch (\Throwable) {}

        return $this->json(['message' => 'Employé créé', 'id' => $u->getId()], 201);
    }

    #[Route('/{id}/disable', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function disable(int $id, EntityManagerInterface $em): JsonResponse
    {
        $u = $em->getRepository(User::class)->find($id);
        if (!$u) return $this->json(['message' => 'Utilisateur introuvable'], 404);

        if (!in_array('ROLE_EMPLOYE', $u->getRoles(), true)) {
            return $this->json(['message' => 'Seuls les employés peuvent être désactivés ici'], 400);
        }

        if (!method_exists($u, 'setActif')) {
            return $this->json(['message' => 'Ajoute le champ "actif" sur User pour désactiver'], 500);
        }

        $u->setActif(false);
        $em->flush();

        return $this->json(['message' => 'Employé désactivé']);
    }
}
