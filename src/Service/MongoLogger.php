<?php

namespace App\Service;

use App\Document\CommandeEvent;
use Doctrine\ODM\MongoDB\DocumentManager;

class MongoLogger
{
    public function __construct(private DocumentManager $dm) {}

    public function logCommandeEvent(int $commandeId, string $type, string $statut, ?string $details = null): void
    {
        $event = new CommandeEvent($commandeId, $type, $statut, $details);
        $this->dm->persist($event);
        $this->dm->flush();
    }
}
