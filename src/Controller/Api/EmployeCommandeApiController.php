<?php

namespace App\Controller\Api;

use App\Document\CommandeEvent;
use App\Entity\Commande;
use App\Entity\CommandeStatutHistorique;
use App\Repository\CommandeRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EMPLOYE')]
#[Route('/api/employe/commandes')]
class EmployeCommandeApiController extends AbstractController
{
    /**
     * GET /api/employe/commandes?statut=...
     */
    #[Route('', name: 'api_employe_commandes_list', methods: ['GET'])]
    public function list(Request $request, CommandeRepository $repo): JsonResponse
    {
        $statut = (string) ($request->query->get('statut') ?? '');
        $criteria = $statut !== '' ? ['statut' => $statut] : [];

        $cmds = $repo->findBy($criteria, ['id' => 'DESC']);

        $data = array_map(fn(Commande $c) => [
            'id' => $c->getId(),
            'statut' => $c->getStatut(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i'),
            'menu' => [
                'id' => $c->getMenu()?->getId(),
                'titre' => $c->getMenu()?->getTitre(),
                'prixMin' => $c->getMenu()?->getPrixMin(),
            ],
            'utilisateur' => [
                'id' => $c->getUtilisateur()?->getId(),
                'nom' => $c->getUtilisateur()?->getNom(),
                'prenom' => $c->getUtilisateur()?->getPrenom(),
                'email' => $c->getUtilisateur()?->getEmail(),
                'gsm' => $c->getUtilisateur()?->getGsm(),
            ],
            'prestation' => [
                'date' => $c->getDatePrestation()?->format('Y-m-d'),
                'heure' => $c->getHeureLivraison()?->format('H:i'),
                'adresse' => $c->getAdressePrestation(),
                'ville' => $c->getVillePrestation(),
                'km' => $c->getKmParcourus(),
                'nbPersonnes' => $c->getNbPersonnes(),
                'prixTotal' => $c->getPrixTotal(),
            ],
            'motifAnnulation' => $c->getMotifAnnulation(),
        ], $cmds);

        return $this->json($data);
    }

    /**
     * PATCH /api/employe/commandes/{id}/statut
     */
    #[Route('/{id}/statut', name: 'api_employe_commandes_update_statut', methods: ['PATCH'])]
    public function updateStatut(
        int $id,
        Request $request,
        CommandeRepository $repo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        DocumentManager $dm
    ): JsonResponse {
        $cmd = $repo->find($id);
        if (!$cmd) return $this->json(['message' => 'Commande introuvable'], 404);

        $data = json_decode($request->getContent(), true) ?? [];
        $statut = (string) ($data['statut'] ?? '');

        $allowed = [
            Commande::STATUT_ACCEPTE,
            Commande::STATUT_PREPARATION,
            Commande::STATUT_LIVRAISON,
            Commande::STATUT_LIVRE,
            Commande::STATUT_ATTENTE_MATERIEL,
            Commande::STATUT_TERMINEE,
        ];

        if (!in_array($statut, $allowed, true)) {
            return $this->json(['message' => 'Statut invalide (employé)'], 400);
        }

        $cmd->setStatut($statut);

        // Historique SQL
        $hist = new CommandeStatutHistorique();
        $hist->setCommande($cmd);
        $hist->setStatut($statut);
        $hist->setChangedAt(new \DateTimeImmutable());
        $em->persist($hist);

        $em->flush();

        // Log Mongo (optionnel mais utile pour les stats)
        try {
            $dm->persist(new CommandeEvent(
                commandeId: (int) $cmd->getId(),
                type: 'status_changed',
                statut: (string) $cmd->getStatut(),
                menuId: $cmd->getMenu()?->getId(),
                menuTitre: $cmd->getMenu()?->getTitre(),
                prixTotal: (float) $cmd->getPrixTotal(),
                userId: $cmd->getUtilisateur()?->getId(),
                details: null
            ));
            $dm->flush();
        } catch (\Throwable) {}

        // ✅ Mail client si terminé (au moins)
        if ($statut === Commande::STATUT_TERMINEE) {
            $this->safeSendMail(
                $mailer,
                (string) $cmd->getUtilisateur()?->getEmail(),
                '✅ Votre commande est terminée',
                "Bonjour,\n\nVotre commande #{$cmd->getId()} est terminée.\nVous pouvez laisser un avis depuis votre espace.\n\nCordialement,\nVite & Gourmand"
            );
        }

        return $this->json(['message' => 'Statut mis à jour']);
    }

    /**
     * PATCH /api/employe/commandes/{id}/annuler
     */
    #[Route('/{id}/annuler', name: 'api_employe_commandes_cancel', methods: ['PATCH'])]
    public function annuler(
        int $id,
        Request $request,
        CommandeRepository $repo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        DocumentManager $dm
    ): JsonResponse {
        $cmd = $repo->find($id);
        if (!$cmd) return $this->json(['message' => 'Commande introuvable'], 404);

        $data = json_decode($request->getContent(), true) ?? [];
        $motif = trim((string) ($data['motif'] ?? ''));
        $contact = strtolower(trim((string) ($data['contact'] ?? 'mail')));

        if ($motif === '') {
            return $this->json(['message' => 'Motif requis'], 400);
        }
        if (!in_array($contact, ['mail', 'telephone'], true)) {
            return $this->json(['message' => 'Contact invalide (mail|telephone)'], 400);
        }

        $cmd->setStatut(Commande::STATUT_ANNULEE);
        $cmd->setMotifAnnulation($motif);

        // Historique SQL
        $hist = new CommandeStatutHistorique();
        $hist->setCommande($cmd);
        $hist->setStatut(Commande::STATUT_ANNULEE);
        $hist->setChangedAt(new \DateTimeImmutable());
        $em->persist($hist);

        $em->flush();

        try {
            $dm->persist(new CommandeEvent(
                commandeId: (int) $cmd->getId(),
                type: 'cancelled',
                statut: (string) $cmd->getStatut(),
                menuId: $cmd->getMenu()?->getId(),
                menuTitre: $cmd->getMenu()?->getTitre(),
                prixTotal: (float) $cmd->getPrixTotal(),
                userId: $cmd->getUtilisateur()?->getId(),
                details: "motif={$motif};contact={$contact}"
            ));
            $dm->flush();
        } catch (\Throwable) {}

        $this->safeSendMail(
            $mailer,
            (string) $cmd->getUtilisateur()?->getEmail(),
            '❌ Annulation de votre commande',
            "Bonjour,\n\nVotre commande #{$cmd->getId()} a été annulée.\nMotif : {$motif}\nMode de contact : {$contact}\n\nCordialement,\nVite & Gourmand"
        );

        return $this->json(['message' => 'Commande annulée']);
    }

    private function safeSendMail(MailerInterface $mailer, string $to, string $subject, string $text): void
    {
        if (trim($to) === '') return;

        try {
            $email = (new Email())
                ->from(new Address('vitegourmand00@gmail.com', 'Vite & Gourmand'))
                ->to($to)
                ->subject($subject)
                ->text($text);

            $mailer->send($email);
        } catch (\Throwable) {
        }
    }
}
