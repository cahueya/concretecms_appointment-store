<?php
namespace Concrete\Package\AppointmentStore\Controller\Frontend;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Controller\Controller;
use Concrete\Core\Validation\CSRF\Token;
use Concrete\Package\AppointmentStore\Service\AvailabilityService;
use Symfony\Component\HttpFoundation\JsonResponse;

class Reservation extends Controller
{
    /**
     * Read-only add-to-cart precheck.
     *
     * This endpoint deliberately does NOT reserve the appointment. It only gives
     * the product page a friendly conflict check immediately before Community
     * Store submits the cart request. The actual atomic reservation happens in
     * on_community_store_cart_pre_add.
     */
    public function preflight(): JsonResponse
    {
        /** @var Token $token */
        $token = $this->app->make(Token::class);
        $submittedToken = (string) $this->request->request->get('ccm_token', '');
        if (!$token->validate('community_store', $submittedToken)) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'csrf',
                'message' => t('Unable to verify appointment availability. Please try again.'),
            ], 403);
        }

        $productID = (int) $this->request->request->get('productID', 0);
        $slotID = (int) $this->request->request->get('slotID', 0);
        if ($productID <= 0 || $slotID <= 0) {
            return $this->conflict(AvailabilityService::STATE_UNAVAILABLE);
        }

        /** @var AvailabilityService $availability */
        $availability = $this->app->make(AvailabilityService::class);
        $state = $availability->getSlotState($productID, $slotID);
        if ($state !== AvailabilityService::STATE_AVAILABLE) {
            return $this->conflict($state);
        }

        // The token is only a cart-reservation owner identifier. Creating it has
        // no side effect and does not block the slot. CART_PRE_ADD will use it if
        // the subsequent Community Store cart submission succeeds.
        return new JsonResponse([
            'ok' => true,
            'reservationToken' => bin2hex(random_bytes(32)),
        ]);
    }

    private function conflict(string $reason): JsonResponse
    {
        if ($reason === AvailabilityService::STATE_RESERVED) {
            $message = t('This appointment is currently reserved as part of an active booking process. If you already added it to your cart, you can continue your booking there. Otherwise, it may become available again if the current booking is not completed.');
        } elseif ($reason === AvailabilityService::STATE_MINIMUM_NOTICE) {
            $message = t('This appointment can no longer be booked because the minimum booking notice has passed.');
        } else {
            $reason = AvailabilityService::STATE_UNAVAILABLE;
            $message = t('The selected appointment is no longer available. Please choose another available appointment.');
        }

        return new JsonResponse([
            'ok' => false,
            'conflict' => true,
            'reason' => $reason,
            'message' => $message,
        ], 409);
    }
}
