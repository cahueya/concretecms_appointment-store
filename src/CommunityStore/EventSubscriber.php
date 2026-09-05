<?php
namespace Concrete\Package\AppointmentStore\CommunityStore;

use Concrete\Core\Support\Facade\Log;
use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Service\BookingService;
use Concrete\Package\AppointmentStore\Service\ProductOptionService;
use Concrete\Package\AppointmentStore\Service\ReservationService;
use Concrete\Package\AppointmentStore\Service\ReservationOwner;
use Concrete\Package\CommunityStore\Src\CommunityStore\Cart\Cart;
use Doctrine\ORM\EntityManagerInterface;

class EventSubscriber
{
    private $em;
    private $reservations;
    private $bookings;
    private $owner;

    public function __construct(
        EntityManagerInterface $em,
        ReservationService $reservations,
        BookingService $bookings,
        ReservationOwner $owner
    ) {
        $this->em = $em;
        $this->reservations = $reservations;
        $this->bookings = $bookings;
        $this->owner = $owner;
    }

    public function onCartPreAdd($event): void
    {
        $product = $event->getProduct();
        if (!$product) {
            return;
        }
        $config = $this->getConfig((int) $product->getID());
        if (!$config) {
            return;
        }

        $data = (array) $event->getData();
        if ((float) ($data['quantity'] ?? 1) !== 1.0) {
            $event->setErrorMsg(t('Appointment products can only be added with a quantity of one.'));
            return;
        }

        $field = 'pt' . $config->getProductOptionID();
        $slotID = (int) ($data[ProductOptionService::MACHINE_FIELD] ?? 0);
        if ($slotID <= 0) {
            // Backward compatibility for 0.1.0–0.1.2, where the slot ID was the
            // visible text option. Never cast a readable date string to an ID.
            $legacyValue = trim((string) ($data[$field] ?? ''));
            if ($legacyValue !== '' && ctype_digit($legacyValue)) {
                $slotID = (int) $legacyValue;
            }
        }
        if ($slotID <= 0) {
            $event->setErrorMsg(t('Please select an appointment.'));
            return;
        }

        $reservationToken = trim((string) ($data[ProductOptionService::RESERVATION_TOKEN_FIELD] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $reservationToken)) {
            // No-JavaScript / legacy fallback. Modern picker requests carry a
            // side-effect-free owner token minted by the read-only precheck.
            $reservationToken = $this->owner->getToken();
        }

        try {
            // This is the first point at which the appointment is actually
            // blocked. The product-page precheck is intentionally read-only.
            // reserve() runs inside a database transaction with a pessimistic slot
            // lock, so competing cart submissions are resolved atomically here.
            $this->reservations->reserve($config, $slotID, $reservationToken);
            Log::addInfo('Appointment Store cart reservation accepted slot ' . $slotID . '.');
        } catch (\Throwable $e) {
            try {
                $this->reservations->release($slotID, $reservationToken);
            } catch (\Throwable $releaseError) {
            }
            Log::addInfo('Appointment Store cart reservation rejected slot ' . $slotID . ': ' . $e->getMessage());
            $event->setErrorMsg(t('This appointment is no longer available. Please choose another time.'));
        }
    }

    public function onCartGet($event): void
    {
        $data = (array) $event->getData();
        foreach ((array) ($data['cart'] ?? []) as $item) {
            try {
                $selection = $this->selectionFromCartItem($item);
                if ($selection) {
                    [$config, $slotID, $reservationToken] = $selection;
                    $this->reservations->touch($slotID, $reservationToken, $config->getReservationMinutes());
                }
            } catch (\Throwable $e) {
                // Reading/rendering the Community Store cart must never fail just
                // because extending an appointment reservation TTL failed. The
                // reservation itself remains protected by its existing expiry.
                Log::addError('Appointment Store cart reservation refresh failed: ' . $e->getMessage());
            }
        }
    }

    public function onCartPreRemove($event): void
    {
        $data = (array) $event->getData();
        $instance = $data['cartItem'] ?? null;
        if ($instance === null) {
            return;
        }
        $cart = Cart::getCart();
        if (!isset($cart[$instance])) {
            return;
        }
        $selection = $this->selectionFromCartItem($cart[$instance]);
        if ($selection) {
            [, $slotID, $reservationToken] = $selection;
            $this->reservations->release($slotID, $reservationToken);
        }
    }

    public function onCartPreClear($event): void
    {
        foreach (Cart::getCart() as $item) {
            $selection = $this->selectionFromCartItem($item);
            if ($selection) {
                [, $slotID, $reservationToken] = $selection;
                $this->reservations->release($slotID, $reservationToken);
            }
        }
    }

    public function onCartPostAdd($event): void
    {
        // CART_POST_ADD is dispatched even when Community Store rejects the item
        // after our CART_PRE_ADD reservation (for example because another required
        // option is invalid). Release that cart reservation immediately.
        if (!$event->getError()) {
            return;
        }
        $data = (array) $event->getData();
        $slotID = (int) ($data[ProductOptionService::MACHINE_FIELD] ?? 0);
        $reservationToken = trim((string) ($data[ProductOptionService::RESERVATION_TOKEN_FIELD] ?? ''));
        if ($slotID > 0 && preg_match('/^[a-f0-9]{64}$/', $reservationToken)) {
            $this->reservations->release($slotID, $reservationToken);
        }
    }

    public function onOrderCreated($event): void
    {
        try {
            $this->bookings->bindOrderReservations(
                $event->getOrder(),
                Cart::getCart()
            );
        } catch (\Throwable $e) {
            Log::addError('Appointment Store could not bind the appointment reservation to the order: ' . $e->getMessage());
            throw $e;
        }
    }

    public function onOrderPlaced($event): void
    {
        try {
            $this->bookings->handleOrder(
                $event->getOrder(),
                AppointmentProductConfig::TRIGGER_ORDER_PLACED
            );
        } catch (\Throwable $e) {
            Log::addError('Appointment Store booking failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function onPaymentComplete($event): void
    {
        try {
            $this->bookings->handleOrder(
                $event->getOrder(),
                AppointmentProductConfig::TRIGGER_PAYMENT_COMPLETE
            );
        } catch (\Throwable $e) {
            Log::addError('Appointment Store payment-complete booking failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function onOrderCancelled($event): void
    {
        try {
            $this->bookings->cancelOrder($event->getOrder());
        } catch (\Throwable $e) {
            // Never hide an order cancellation because the remote calendar is temporarily unavailable.
            Log::addError('Appointment Store could not release a cancelled booking: ' . $e->getMessage());
        }
    }

    private function getConfig(int $productID): ?AppointmentProductConfig
    {
        $config = $this->em->getRepository(AppointmentProductConfig::class)->findOneBy([
            'productID' => $productID,
            'enabled' => true,
        ]);
        if (!$config || !$config->getSource()->isEnabled() || !$config->getSource()->getAccount()->isEnabled()) {
            return null;
        }
        return $config;
    }

    /** @return array{0:AppointmentProductConfig,1:int,2:string}|null */
    private function selectionFromCartItem(array $item): ?array
    {
        $productID = (int) ($item['product']['pID'] ?? 0);
        $config = $productID ? $this->getConfig($productID) : null;
        if (!$config) {
            return null;
        }
        $field = 'pt' . $config->getProductOptionID();
        $slotID = (int) ($item['productAttributes'][ProductOptionService::MACHINE_FIELD] ?? 0);
        if ($slotID <= 0) {
            $legacyValue = trim((string) ($item['productAttributes'][$field] ?? ''));
            if ($legacyValue !== '' && ctype_digit($legacyValue)) {
                $slotID = (int) $legacyValue;
            }
        }
        if ($slotID <= 0) {
            return null;
        }
        $reservationToken = trim((string) ($item['productAttributes'][ProductOptionService::RESERVATION_TOKEN_FIELD] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $reservationToken)) {
            $reservationToken = $this->owner->getToken();
        }
        return [$config, $slotID, $reservationToken];
    }

}
