<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Entity\AppointmentReservation;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Repository\AppointmentReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    private $em;
    private $reservationRepository;

    public function __construct(
        EntityManagerInterface $em,
        AppointmentReservationRepository $reservationRepository
    ) {
        $this->em = $em;
        $this->reservationRepository = $reservationRepository;
    }

    public function reserve(AppointmentProductConfig $config, int $slotID, string $sessionID): AppointmentReservation
    {
        return $this->em->transactional(function (EntityManagerInterface $em) use ($config, $slotID, $sessionID) {
            /** @var AppointmentSlot|null $slot */
            $slot = $em->find(AppointmentSlot::class, $slotID, LockMode::PESSIMISTIC_WRITE);
            if (!$slot || $slot->getSource()->getId() !== $config->getSource()->getId()) {
                throw new \RuntimeException(t('This appointment does not belong to this product.'));
            }
            $now = $this->now();
            $minimumNoticeHours = $config->getMinimumBookingNoticeHours();
            $cutoff = $minimumNoticeHours > 0 ? $now->modify('+' . $minimumNoticeHours . ' hours') : $now;
            if ($slot->getStartUTC() < $cutoff) {
                throw new \RuntimeException(t('This appointment can no longer be booked because the minimum booking notice has passed.'));
            }

            /** @var AppointmentReservation|null $reservation */
            $reservation = $this->reservationRepository->findOneBySlotID($slotID, true, $em);
            if ($reservation && $reservation->getOrderID() === null && $reservation->getExpiresAt() <= $now) {
                $em->remove($reservation);
                $this->restoreAvailabilityStatus($slot);
                // Flush the DELETE before a replacement reservation can be INSERTed.
                // Doctrine may otherwise schedule INSERT before DELETE and hit the
                // unique slotID constraint even though the old reservation expired.
                $em->flush();
                $reservation = null;
            }

            $expiresAt = $now->modify('+' . $config->getReservationMinutes() . ' minutes');
            if ($reservation) {
                if ($reservation->getOrderID() !== null) {
                    throw new \RuntimeException(t('This appointment is already assigned to an order.'));
                }
                if ($reservation->getProductConfig()->getId() !== $config->getId()) {
                    throw new \RuntimeException(t('This appointment is reserved for a different product.'));
                }
                if (!hash_equals($reservation->getSessionID(), $sessionID)) {
                    throw new \RuntimeException(t('This appointment is currently reserved by another customer.'));
                }
                $reservation->setExpiresAt($expiresAt);
                $slot->setStatus(AppointmentSlot::STATUS_RESERVED);
                $em->flush();
                return $reservation;
            }

            if ($slot->getStatus() !== AppointmentSlot::STATUS_FREE) {
                throw new \RuntimeException(t('This appointment is no longer available.'));
            }

            $reservation = (new AppointmentReservation())
                ->setSlot($slot)
                ->setProductConfig($config)
                ->setSessionID($sessionID)
                ->setCreatedAt($now)
                ->setExpiresAt($expiresAt);
            $slot->setStatus(AppointmentSlot::STATUS_RESERVED);
            $em->persist($reservation);
            $em->flush();
            return $reservation;
        });
    }

    public function touch(int $slotID, string $sessionID, int $minutes): void
    {
        if ($slotID <= 0 || $sessionID === '') {
            return;
        }

        // `slot` is a Doctrine association, so query it with an entity reference
        // instead of relying on scalar association coercion. This path runs from
        // Community Store's CART_GET event and must remain safe on every cart read.
        $slot = $this->em->getReference(AppointmentSlot::class, $slotID);
        $reservation = $this->reservationRepository->findOneBySlotID($slotID);
        if ($reservation && $reservation->getOrderID() === null && hash_equals($reservation->getSessionID(), $sessionID)) {
            $reservation->setExpiresAt($this->now()->modify('+' . max(1, $minutes) . ' minutes'));
            $this->em->flush();
        }
    }

    public function release(int $slotID, string $sessionID): void
    {
        $this->em->transactional(function (EntityManagerInterface $em) use ($slotID, $sessionID) {
            $slot = $em->find(AppointmentSlot::class, $slotID, LockMode::PESSIMISTIC_WRITE);
            if (!$slot) {
                return;
            }
            $reservation = $this->reservationRepository->findOneBySlotID($slotID, true, $em);
            if ($reservation && $reservation->getOrderID() === null && hash_equals($reservation->getSessionID(), $sessionID)) {
                $em->remove($reservation);
                if ($slot->getStatus() === AppointmentSlot::STATUS_RESERVED) {
                    $this->restoreAvailabilityStatus($slot);
                }
                $em->flush();
            }
        });
    }

    public function releaseAllForSession(string $sessionID): int
    {
        $qb = $this->em->createQueryBuilder();
        $reservations = $qb->select('r')
            ->from(AppointmentReservation::class, 'r')
            ->where('r.sessionID = :sessionID')
            ->andWhere('r.orderID IS NULL')
            ->setParameter('sessionID', $sessionID)
            ->getQuery()->getResult();
        $count = 0;
        foreach ($reservations as $reservation) {
            $slot = $reservation->getSlot();
            if ($slot->getStatus() === AppointmentSlot::STATUS_RESERVED) {
                $this->restoreAvailabilityStatus($slot);
            }
            $this->em->remove($reservation);
            ++$count;
        }
        if ($count) {
            $this->em->flush();
        }
        return $count;
    }

    public function releaseForOrder(int $orderID): int
    {
        return $this->em->transactional(function (EntityManagerInterface $em) use ($orderID) {
            $reservations = $em->getRepository(AppointmentReservation::class)->findBy(['orderID' => $orderID]);
            $count = 0;
            foreach ($reservations as $reservation) {
                $slot = $em->find(AppointmentSlot::class, $reservation->getSlot()->getId(), LockMode::PESSIMISTIC_WRITE);
                if ($slot && $slot->getStatus() === AppointmentSlot::STATUS_RESERVED) {
                    $this->restoreAvailabilityStatus($slot);
                }
                $em->remove($reservation);
                ++$count;
            }
            if ($count) {
                $em->flush();
            }
            return $count;
        });
    }

    public function releaseExpired(): int
    {
        $now = $this->now();
        $qb = $this->em->createQueryBuilder();
        $reservations = $qb->select('r')
            ->from(AppointmentReservation::class, 'r')
            ->where('r.expiresAt <= :now')
            ->andWhere('r.orderID IS NULL')
            ->setParameter('now', $now)
            ->getQuery()->getResult();

        $count = 0;
        foreach ($reservations as $reservation) {
            $slot = $reservation->getSlot();
            if ($slot->getStatus() === AppointmentSlot::STATUS_RESERVED) {
                $this->restoreAvailabilityStatus($slot);
            }
            $this->em->remove($reservation);
            ++$count;
        }
        if ($count) {
            $this->em->flush();
        }
        return $count;
    }

    private function restoreAvailabilityStatus(AppointmentSlot $slot): void
    {
        $sourceLastSync = $slot->getSource()->getLastSyncedAt();
        $lastSeen = $slot->getLastSeenAt();
        if ($sourceLastSync && $lastSeen && $sourceLastSync > $lastSeen) {
            $slot->setStatus(AppointmentSlot::STATUS_UNAVAILABLE);
            return;
        }
        $slot->setStatus(AppointmentSlot::STATUS_FREE);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
