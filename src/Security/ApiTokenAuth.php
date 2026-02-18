<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Classe utilitaire pour authentifier un utilisateur via un token API.
 */
class ApiTokenAuth
{
    /**
     * Récupère l'utilisateur à partir du header Authorization.
     */
    public static function getUserFromRequest(Request $request, EntityManagerInterface $em): ?User
    {
        $auth = $request->headers->get('Authorization', '');

        if (!preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return null;
        }

        $token = trim($m[1]);

        if ($token === '') return null;

        return $em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
    }
}
