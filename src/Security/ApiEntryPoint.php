<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Point d'entrée de sécurité pour l'API.
 */
class ApiEntryPoint implements AuthenticationEntryPointInterface
{
    /**
     * Méthode exécutée quand l'utilisateur n'est pas authentifié.
     */
    public function start(Request $request, \Throwable $authException = null): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Non authentifié (API).',
        ], 401);
    }
}
