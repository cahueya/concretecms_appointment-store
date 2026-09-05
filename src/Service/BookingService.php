<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Package\AppointmentStore\CalDav\BookingStrategy\BookingStrategyFactory;
use Concrete\Package\AppointmentStore\Entity\AppointmentBooking;
use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Entity\AppointmentReservation;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Repository\AppointmentReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class BookingService
{
    private $em;
    private $strategies;
    private $reservations;
    private $reservationRepository;

    public function __construct(
        EntityManagerInterface $em,
        BookingStrategyFactory $strategies,
        ReservationService $reservations,
        AppointmentReservationRepository $reservationRepository
    ) {
        $this->em = $em;
        $this->strategies = $strategies;
        $this->reservations = $reservations;
        $this->reservationRepository = $reservationRepository;
    }

    public function handleOrder($order, string $trigger, ?string $sessionID = null): int
    {
        $count = 0;
        $orderID = (int) $order->getOrderID();

        foreach ($order->getOrderItems() as $item) {
            $config = $this->em->getRepository(AppointmentProductConfig::class)->findOneBy([
                'productID' => (int) $item->getProductID(),
            ]);
            if (!$config || $config->getBookingTrigger() !== $trigger) {
                continue;
            }

            $slotID = $this->resolveSlotID($item, $orderID);
            if (!$slotID) {
                continue;
            }

            $this->bookItem($config, $slotID, $orderID, (int) $item->getID(), $sessionID);
            ++$count;
        }

        return $count;
    }

    /**
     * Bind the machine-readable cart slot IDs to the freshly created order items.
     *
     * Community Store stores the human-readable appointment text as the actual
     * OrderItemOption value. The internal slot ID travels through the cart as a
     * non-product-option attribute and is captured here before the cart is cleared.
     *
     * @param object $order
     * @param array<int,array<string,mixed>> $cart
     */
    public function bindOrderReservations($order, array $cart, ?string $sessionID = null): int
    {
        $selections = [];

        foreach ($cart as $cartItem) {
            $productID = (int) ($cartItem['product']['pID'] ?? 0);
            if ($productID <= 0) {
                continue;
            }

            $config = $this->em->getRepository(AppointmentProductConfig::class)->findOneBy([
                'productID' => $productID,
            ]);
            if (!$config) {
                continue;
            }

            $attributes = (array) ($cartItem['productAttributes'] ?? []);
            $field = 'pt' . $config->getProductOptionID();
            $visibleValue = (string) ($attributes[$field] ?? '');
            $slotID = (int) ($attributes[ProductOptionService::MACHINE_FIELD] ?? 0);
            $reservationToken = trim((string) ($attributes[ProductOptionService::RESERVATION_TOKEN_FIELD] ?? ''));
            if (!preg_match('/^[a-f0-9]{64}$/', $reservationToken)) {
                $reservationToken = $sessionID ?: '';
            }

            if ($slotID <= 0) {
                // Legacy/no-JavaScript carts from 0.1.0–0.1.2 stored the numeric
                // slot ID directly in the visible text option. Never cast a
                // human-readable date such as "2026-09-30 ..." to an integer.
                $legacyValue = trim($visibleValue);
                if ($legacyValue !== '' && ctype_digit($legacyValue)) {
                    $slotID = (int) $legacyValue;
                }
            }
            if ($slotID <= 0) {
                continue;
            }

            $selections[] = [
                'productID' => $productID,
                'visibleValue' => $visibleValue,
                'slotID' => $slotID,
                'config' => $config,
                'reservationToken' => $reservationToken,
                'used' => false,
            ];
        }

        $count = 0;
        $orderID = (int) $order->getOrderID();

        foreach ($order->getOrderItems() as $item) {
            $productID = (int) $item->getProductID();
            $visibleValue = $this->extractAppointmentOptionValue($item);
            $match = null;

            // First match both product and the exact visible option value. This also
            // supports multiple appointments for the same product in one cart.
            foreach ($selections as $index => $selection) {
                if ($selection['used'] || $selection['productID'] !== $productID) {
                    continue;
                }
                if ($visibleValue !== null && $selection['visibleValue'] === $visibleValue) {
                    $match = $index;
                    break;
                }
            }

            // Backward-compatible fallback if an old cart/order has no readable value.
            if ($match === null) {
                foreach ($selections as $index => $selection) {
                    if (!$selection['used'] && $selection['productID'] === $productID) {
                        $match = $index;
                        break;
                    }
                }
            }

            if ($match === null) {
                continue;
            }

            $selections[$match]['used'] = true;
            /** @var AppointmentProductConfig $config */
            $config = $selections[$match]['config'];
            $slotID = (int) $selections[$match]['slotID'];
            $orderItemID = (int) $item->getID();

            $existing = $this->em->getRepository(AppointmentBooking::class)->findOneBy([
                'orderID' => $orderID,
                'orderItemID' => $orderItemID,
            ]);
            if ($existing && !$existing->getCancelledAt()) {
                continue;
            }

            $reservationToken = (string) $selections[$match]['reservationToken'];
            $this->bindReservationToOrder($config, $slotID, $orderID, $orderItemID, $reservationToken !== '' ? $reservationToken : $sessionID);
            ++$count;
        }

        return $count;
    }

    public function cancelOrder($order): int
    {
        $orderID = (int) $order->getOrderID();
        $count = $this->reservations->releaseForOrder($orderID);
        $bookings = $this->em->getRepository(AppointmentBooking::class)->findBy(['orderID' => $orderID]);
        foreach ($bookings as $booking) {
            if ($booking->getCancelledAt() || !$booking->getProductConfig()->shouldReleaseOnCancel()) {
                continue;
            }
            $cancelled = $this->em->transactional(function (EntityManagerInterface $em) use ($booking) {
                $slot = $em->find(AppointmentSlot::class, $booking->getSlot()->getId(), LockMode::PESSIMISTIC_WRITE);
                if (!$slot || $slot->getStatus() !== AppointmentSlot::STATUS_BOOKED) {
                    return false;
                }
                $strategy = $this->strategies->forSource($slot->getSource());
                $result = $strategy->cancel($slot->getSource(), $slot);
                $slot->setStatus(AppointmentSlot::STATUS_FREE)
                    ->setEtag($result->getEtag())
                    ->setBookedHref(null)
                    ->setBookedEtag(null)
                    ->setLastSeenAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                $booking->setCancelledAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                $em->flush();
                return true;
            });
            if ($cancelled) {
                ++$count;
            }
        }
        return $count;
    }

    private function bookItem(AppointmentProductConfig $config, int $slotID, int $orderID, int $orderItemID, ?string $sessionID): void
    {
        $existing = $this->em->getRepository(AppointmentBooking::class)->findOneBy([
            'orderID' => $orderID,
            'orderItemID' => $orderItemID,
        ]);
        if ($existing && !$existing->getCancelledAt()) {
            return;
        }

        $this->em->transactional(function (EntityManagerInterface $em) use ($config, $slotID, $orderID, $orderItemID, $sessionID) {
            $slot = $em->find(AppointmentSlot::class, $slotID, LockMode::PESSIMISTIC_WRITE);
            if (!$slot || $slot->getSource()->getId() !== $config->getSource()->getId()) {
                throw new \RuntimeException(t('The appointment selected for this order is invalid.'));
            }
            if (in_array($slot->getStatus(), [AppointmentSlot::STATUS_BOOKED, AppointmentSlot::STATUS_UNAVAILABLE], true)) {
                throw new \RuntimeException(t('The appointment is no longer available.'));
            }

            $reservation = $this->reservationRepository->findOneBySlotID($slotID, true, $em);
            if ($reservation) {
                if ($reservation->getProductConfig()->getId() !== $config->getId()) {
                    throw new \RuntimeException(t('The appointment reservation belongs to a different product configuration.'));
                }
                if ($reservation->getOrderID() !== null && $reservation->getOrderID() !== $orderID) {
                    throw new \RuntimeException(t('The appointment belongs to another order.'));
                }
                if ($reservation->getOrderID() === null) {
                    if (!$sessionID || !hash_equals($reservation->getSessionID(), $sessionID)) {
                        throw new \RuntimeException(t('The appointment reservation could not be verified for this order.'));
                    }
                }
            }

            $strategy = $this->strategies->forSource($slot->getSource());
            $result = $strategy->book($slot->getSource(), $slot);
            $slot->setStatus(AppointmentSlot::STATUS_BOOKED)
                ->setBookedHref($result->getHref())
                ->setBookedEtag($result->getEtag());

            if ($reservation) {
                $em->remove($reservation);
            }
            $booking = (new AppointmentBooking())
                ->setSlot($slot)
                ->setProductConfig($config)
                ->setOrderID($orderID)
                ->setOrderItemID($orderItemID)
                ->setBookedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $em->persist($booking);
            $em->flush();
        });
    }

    private function bindReservationToOrder(AppointmentProductConfig $config, int $slotID, int $orderID, int $orderItemID, ?string $sessionID): void
    {
        $this->em->transactional(function (EntityManagerInterface $em) use ($config, $slotID, $orderID, $orderItemID, $sessionID) {
            $slot = $em->find(AppointmentSlot::class, $slotID, LockMode::PESSIMISTIC_WRITE);
            if (!$slot || $slot->getSource()->getId() !== $config->getSource()->getId()) {
                throw new \RuntimeException(t('The appointment selected for this order is invalid.'));
            }
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $reservation = $this->reservationRepository->findOneBySlotID($slotID, true, $em);
            if (!$reservation) {
                if ($slot->getStatus() !== AppointmentSlot::STATUS_FREE) {
                    throw new \RuntimeException(t('The appointment is no longer available.'));
                }
                $reservation = (new AppointmentReservation())
                    ->setSlot($slot)
                    ->setProductConfig($config)
                    ->setSessionID($sessionID ?: ('order-' . $orderID))
                    ->setCreatedAt($now);
                $em->persist($reservation);
                $slot->setStatus(AppointmentSlot::STATUS_RESERVED);
            } elseif ($reservation->getProductConfig()->getId() !== $config->getId()) {
                throw new \RuntimeException(t('The appointment reservation belongs to a different product configuration.'));
            } elseif ($reservation->getOrderID() === null) {
                if (!$sessionID || !hash_equals($reservation->getSessionID(), $sessionID)) {
                    throw new \RuntimeException(t('The appointment reservation could not be verified for this order.'));
                }
            } elseif ($reservation->getOrderID() !== $orderID) {
                throw new \RuntimeException(t('The appointment belongs to another order.'));
            }
            $reservation->setOrderID($orderID)
                ->setOrderItemID($orderItemID)
                ->setExpiresAt($now->modify('+60 minutes'));
            $em->flush();
        });
    }

    private function resolveSlotID($item, int $orderID): ?int
    {
        $orderItemID = (int) $item->getID();

        $reservation = $this->em->getRepository(AppointmentReservation::class)->findOneBy([
            'orderID' => $orderID,
            'orderItemID' => $orderItemID,
        ]);
        if ($reservation) {
            return (int) $reservation->getSlot()->getId();
        }

        $booking = $this->em->getRepository(AppointmentBooking::class)->findOneBy([
            'orderID' => $orderID,
            'orderItemID' => $orderItemID,
        ]);
        if ($booking) {
            return (int) $booking->getSlot()->getId();
        }

        // Legacy orders (0.1.0–0.1.2) stored the slot ID directly in the visible option.
        $value = $this->extractAppointmentOptionValue($item);
        if ($value !== null && ctype_digit(trim($value))) {
            $slotID = (int) $value;
            return $slotID > 0 ? $slotID : null;
        }

        return null;
    }

    private function extractAppointmentOptionValue($item): ?string
    {
        foreach ((array) $item->getProductOptions() as $option) {
            if (($option['oioHandle'] ?? '') === ProductOptionService::HANDLE) {
                return isset($option['oioValue']) ? (string) $option['oioValue'] : '';
            }
        }

        return null;
    }
}
