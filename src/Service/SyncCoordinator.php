<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Package\AppointmentStore\Entity\CalendarSource;
use Doctrine\ORM\EntityManagerInterface;

class SyncCoordinator
{
    private $em;
    private $sync;
    private $reservations;

    public function __construct(EntityManagerInterface $em, SlotSyncService $sync, ReservationService $reservations)
    {
        $this->em = $em;
        $this->sync = $sync;
        $this->reservations = $reservations;
    }

    /** @return array{sources:int,seen:int,created:int,updated:int,unavailable:int,released:int,errors:string[]} */
    public function run(): array
    {
        $totals = [
            'sources' => 0,
            'seen' => 0,
            'created' => 0,
            'updated' => 0,
            'unavailable' => 0,
            'released' => $this->reservations->releaseExpired(),
            'errors' => [],
        ];
        $sources = $this->em->getRepository(CalendarSource::class)->findBy(['enabled' => true]);
        foreach ($sources as $source) {
            if (!$source->getAccount()->isEnabled()) {
                continue;
            }
            ++$totals['sources'];
            try {
                $stats = $this->sync->sync($source);
                foreach (['seen', 'created', 'updated', 'unavailable'] as $key) {
                    $totals[$key] += $stats[$key];
                }
            } catch (\Throwable $e) {
                $source->setLastSyncedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->setLastSyncError($e->getMessage());
                $this->em->flush();
                $totals['errors'][] = $source->getName() . ': ' . $e->getMessage();
            }
        }
        return $totals;
    }
}
