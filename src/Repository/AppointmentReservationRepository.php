<?php
namespace Concrete\Package\AppointmentStore\Repository;

use Concrete\Package\AppointmentStore\Entity\AppointmentReservation;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ORM lookup service for appointment reservations.
 *
 * This is intentionally not registered as a Doctrine custom entity repository.
 * Package upgrades must not depend on refreshed Doctrine repositoryClass metadata.
 */
class AppointmentReservationRepository
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function findOneBySlotID(
        int $slotID,
        bool $forUpdate = false,
        ?EntityManagerInterface $em = null
    ): ?AppointmentReservation {
        $manager = $em ?: $this->em;

        $query = $manager->createQueryBuilder()
            ->select('r')
            ->from(AppointmentReservation::class, 'r')
            ->innerJoin('r.slot', 's')
            ->where('s.id = :slotID')
            ->setParameter('slotID', $slotID)
            ->setMaxResults(1)
            ->getQuery();

        if ($forUpdate) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        $reservation = $query->getOneOrNullResult();

        return $reservation instanceof AppointmentReservation ? $reservation : null;
    }
}
