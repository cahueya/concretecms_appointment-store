<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Doctrine\ORM\EntityManagerInterface;

class AvailabilityService
{
    public const STATE_AVAILABLE = 'available';
    public const STATE_RESERVED = 'reserved';
    public const STATE_MINIMUM_NOTICE = 'minimum_notice';
    public const STATE_UNAVAILABLE = 'unavailable';

    private $em;
    private $reservations;
    private $formatter;

    public function __construct(EntityManagerInterface $em, ReservationService $reservations, SlotFormatter $formatter)
    {
        $this->em = $em;
        $this->reservations = $reservations;
        $this->formatter = $formatter;
    }

    public function getConfig(int $productID): ?AppointmentProductConfig
    {
        $config = $this->em->getRepository(AppointmentProductConfig::class)->findOneBy(['productID' => $productID]);
        if (!$config || !$config->isEnabled() || !$config->getSource()->isEnabled() || !$config->getSource()->getAccount()->isEnabled()) {
            return null;
        }
        return $config;
    }

    /**
     * Return the current reason a slot can or cannot be booked.
     *
     * This is read-only apart from releasing already-expired temporary holds. It
     * deliberately does not create or extend a reservation; CART_PRE_ADD remains
     * the authoritative atomic reservation point.
     */
    public function getSlotState(int $productID, int $slotID): string
    {
        $this->reservations->releaseExpired();
        $config = $this->getConfig($productID);
        if (!$config || $slotID <= 0) {
            return self::STATE_UNAVAILABLE;
        }

        /** @var AppointmentSlot|null $slot */
        $slot = $this->em->find(AppointmentSlot::class, $slotID);
        if (!$slot || $slot->getSource()->getId() !== $config->getSource()->getId()) {
            return self::STATE_UNAVAILABLE;
        }

        if ($slot->getEndUTC() <= $slot->getStartUTC()) {
            return self::STATE_UNAVAILABLE;
        }

        if ($slot->getStartUTC() < $this->bookingCutoff($config)) {
            return self::STATE_MINIMUM_NOTICE;
        }

        if ($slot->getStatus() === AppointmentSlot::STATUS_FREE) {
            return self::STATE_AVAILABLE;
        }
        if ($slot->getStatus() === AppointmentSlot::STATUS_RESERVED) {
            return self::STATE_RESERVED;
        }

        return self::STATE_UNAVAILABLE;
    }

    /**
     * Read-only availability check used by the product-page UX before the real
     * Community Store add-to-cart request.
     */
    public function isSlotAvailable(int $productID, int $slotID): bool
    {
        return $this->getSlotState($productID, $slotID) === self::STATE_AVAILABLE;
    }

    /**
     * Day-level state for the calendar.
     *
     * A day is available when at least one slot is free. It is reserved only when
     * there are no free slots but at least one valid slot is temporarily held by
     * a booking process. Booked/unavailable slots are intentionally not exposed as
     * calendar days.
     *
     * @return array<string,string>
     */
    public function getDayStates(int $productID): array
    {
        $this->reservations->releaseExpired();
        $config = $this->getConfig($productID);
        if (!$config) {
            return [];
        }

        $tz = new \DateTimeZone($config->getSource()->getTimezone());
        $states = [];
        foreach ($this->getCalendarSlots($config) as $slot) {
            $day = $slot->getStartUTC()->setTimezone($tz)->format('Y-m-d');
            $state = $slot->getStatus() === AppointmentSlot::STATUS_FREE
                ? self::STATE_AVAILABLE
                : self::STATE_RESERVED;

            // A free slot always wins over a temporary hold on the same day.
            if (!isset($states[$day]) || $state === self::STATE_AVAILABLE) {
                $states[$day] = $state;
            }
        }

        ksort($states);
        return $states;
    }

    /** @return string[] */
    public function getDays(int $productID): array
    {
        return array_keys(array_filter($this->getDayStates($productID), static function (string $state): bool {
            return $state === self::STATE_AVAILABLE;
        }));
    }

    /** @return string[] */
    public function getReservedDays(int $productID): array
    {
        return array_keys(array_filter($this->getDayStates($productID), static function (string $state): bool {
            return $state === self::STATE_RESERVED;
        }));
    }

    /** @return array<int,array<string,mixed>> */
    public function getSlots(int $productID, string $date): array
    {
        return $this->getSlotsWithState($productID, $date)['slots'];
    }

    /**
     * Return selectable slots together with the reason for an empty result.
     *
     * @return array{slots:array<int,array<string,mixed>>,state:string}
     */
    public function getSlotsWithState(int $productID, string $date): array
    {
        $this->reservations->releaseExpired();
        $config = $this->getConfig($productID);
        if (!$config || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['slots' => [], 'state' => self::STATE_UNAVAILABLE];
        }

        $tz = new \DateTimeZone($config->getSource()->getTimezone());
        $result = [];
        $hasReserved = false;

        foreach ($this->getCalendarSlots($config) as $slot) {
            // Filter on the same local-date representation used by getDayStates().
            if ($slot->getStartUTC()->setTimezone($tz)->format('Y-m-d') !== $date) {
                continue;
            }

            if ($slot->getStatus() === AppointmentSlot::STATUS_RESERVED) {
                $hasReserved = true;
                continue;
            }

            if ($slot->getStatus() !== AppointmentSlot::STATUS_FREE) {
                continue;
            }

            $result[] = [
                'id' => $slot->getId(),
                'start' => $slot->getStartUTC()->setTimezone($tz)->format('H:i'),
                'end' => $slot->getEndUTC()->setTimezone($tz)->format('H:i'),
                'label' => $slot->getSummary(),
                'display' => $this->formatter->format($slot),
            ];
        }

        if ($result) {
            return ['slots' => $result, 'state' => self::STATE_AVAILABLE];
        }

        return [
            'slots' => [],
            'state' => $hasReserved ? self::STATE_RESERVED : self::STATE_UNAVAILABLE,
        ];
    }

    /** @return AppointmentSlot[] */
    private function getCalendarSlots(AppointmentProductConfig $config): array
    {
        $slots = $this->em->getRepository(AppointmentSlot::class)->createQueryBuilder('s')
            ->where('s.source = :source')
            ->andWhere('(s.status = :free OR s.status = :reserved)')
            ->andWhere('s.startUTC >= :cutoff')
            ->setParameter('source', $config->getSource())
            ->setParameter('free', AppointmentSlot::STATUS_FREE)
            ->setParameter('reserved', AppointmentSlot::STATUS_RESERVED)
            ->setParameter('cutoff', $this->bookingCutoff($config))
            ->orderBy('s.startUTC', 'ASC')
            ->getQuery()->getResult();

        // Keep malformed-event field-to-field datetime validation out of DQL for
        // compatibility with the Doctrine/DBAL versions supported by Concrete CMS 9.
        return array_values(array_filter($slots, static function (AppointmentSlot $slot): bool {
            return $slot->getEndUTC() > $slot->getStartUTC();
        }));
    }

    private function bookingCutoff(AppointmentProductConfig $config): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hours = $config->getMinimumBookingNoticeHours();
        return $hours > 0 ? $now->modify('+' . $hours . ' hours') : $now;
    }
}
