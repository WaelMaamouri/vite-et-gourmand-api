<?php

namespace App\Controller\Api;

use App\Document\CommandeEvent;
use App\Entity\Commande;
use App\Entity\CommandeStatutHistorique;
use App\Entity\Menu;
use App\Entity\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API Commande 
 */
#[Route('/api/commandes')]
class CommandeApiController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'api_commandes_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, DocumentManager $dm): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $menuId = (int)($data['menuId'] ?? 0);
        $datePrestation = (string)($data['datePrestation'] ?? '');
        $heureLivraison = (string)($data['heureLivraison'] ?? '');
        $adressePrestation = trim((string)($data['adressePrestation'] ?? ''));
        $villePrestation = trim((string)($data['villePrestation'] ?? ''));
        $kmParcourus = (float)($data['kmParcourus'] ?? 0);
        $nbPersonnes = (int)($data['nbPersonnes'] ?? 0);

        $missing = [];
        if ($menuId <= 0) $missing[] = 'menuId';
        if ($datePrestation === '') $missing[] = 'datePrestation';
        if ($heureLivraison === '') $missing[] = 'heureLivraison';
        if ($adressePrestation === '') $missing[] = 'adressePrestation';
        if ($villePrestation === '') $missing[] = 'villePrestation';
        if ($nbPersonnes <= 0) $missing[] = 'nbPersonnes';

        $villeNorm = mb_strtolower(trim($villePrestation));
        if ($villeNorm !== 'bordeaux' && $kmParcourus <= 0) {
            $missing[] = 'kmParcourus';
        }

        if ($missing) {
            return $this->json(['message' => 'Champs manquants', 'missing' => $missing], 400);
        }

        $menu = $em->getRepository(Menu::class)->find($menuId);
        if (!$menu) {
            return $this->json(['message' => 'Menu introuvable'], 404);
        }

        $dateObj = \DateTime::createFromFormat('Y-m-d', $datePrestation) ?: null;
        $timeObj = \DateTime::createFromFormat('H:i', $heureLivraison) ?: null;
        if (!$dateObj || !$timeObj) {
            return $this->json(['message' => 'Date/heure invalide (formats attendus: Y-m-d et H:i)'], 400);
        }

        $minMenu = (int)($menu->getNbPersonnesMin() ?? 0);
        $prixMin = (float)($menu->getPrixMin() ?? 0);

        if ($minMenu <= 0 || $prixMin <= 0) {
            return $this->json(['message' => 'Menu invalide (prixMin / nbPersonnesMin).'], 400);
        }

        if ($nbPersonnes < $minMenu) {
            return $this->json([
                'message' => "Nombre de personnes insuffisant (min: {$minMenu})."
            ], 400);
        }

        $prixMenuBrut = ($prixMin / $minMenu) * $nbPersonnes;

        $remise = 0.0;
        $prixMenuNet = $prixMenuBrut;
        if ($nbPersonnes >= ($minMenu + 5)) {
            $remise = $prixMenuBrut * 0.10;
            $prixMenuNet = $prixMenuBrut - $remise;
        }

        $prixLivraison = 0.0;
        if ($villeNorm !== 'bordeaux') {
            $prixLivraison = 5 + 0.59 * max(0, $kmParcourus);
        }

        $prixTotal = round($prixMenuNet + $prixLivraison, 2);

        // Création commande SQL
        $cmd = new Commande();
        $cmd->setUtilisateur($user);
        $cmd->setMenu($menu);
        $cmd->setDatePrestation($dateObj);
        $cmd->setHeureLivraison($timeObj);
        $cmd->setAdressePrestation($adressePrestation);
        $cmd->setVillePrestation($villePrestation);
        $cmd->setKmParcourus($kmParcourus);
        $cmd->setNbPersonnes($nbPersonnes);
        $cmd->setPrixTotal(number_format($prixTotal, 2, '.', ''));
        $cmd->setStatut(Commande::STATUT_EN_ATTENTE);
        $cmd->setCreatedAt(new \DateTimeImmutable());

        $em->persist($cmd);

        // 8) Historique statut
        $hist = new CommandeStatutHistorique();
        $hist->setCommande($cmd);
        $hist->setStatut(Commande::STATUT_EN_ATTENTE);
        $hist->setChangedAt(new \DateTimeImmutable());
        $em->persist($hist);

        $em->flush();

        // 9) Event Mongo (utile pour stats)
        $details = json_encode([
            'prixMenuBrut' => round($prixMenuBrut, 2),
            'remise' => round($remise, 2),
            'prixMenuNet' => round($prixMenuNet, 2),
            'prixLivraison' => round($prixLivraison, 2),
            'kmParcourus' => round($kmParcourus, 2),
            'ville' => $villePrestation,
        ], JSON_UNESCAPED_UNICODE);

        $event = new CommandeEvent(
            commandeId: $cmd->getId(),
            type: "created",
            statut: $cmd->getStatut(),
            menuId: $cmd->getMenu()?->getId(),
            menuTitre: $cmd->getMenu()?->getTitre(),
            prixTotal: (float)$cmd->getPrixTotal(),
            userId: $cmd->getUtilisateur()?->getId(),
            details: $details
        );

        $dm->persist($event);
        $dm->flush();

        return $this->json([
            'message' => 'Demande envoyée',
            'id' => $cmd->getId(),
            'pricing' => [
                'prixMenuBrut' => round($prixMenuBrut, 2),
                'remise' => round($remise, 2),
                'prixMenuNet' => round($prixMenuNet, 2),
                'prixLivraison' => round($prixLivraison, 2),
                'total' => round($prixTotal, 2),
            ],
        ], 201);
    }
}
