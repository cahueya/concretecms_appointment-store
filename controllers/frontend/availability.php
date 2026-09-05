<?php
namespace Concrete\Package\AppointmentStore\Controller\Frontend;

use Concrete\Core\Controller\Controller;
use Concrete\Package\AppointmentStore\Service\AvailabilityService;
use Symfony\Component\HttpFoundation\JsonResponse;

class Availability extends Controller
{
    public function days(): JsonResponse
    {
        $productID = (int) $this->request->query->get('productID', 0);
        $service = $this->app->make(AvailabilityService::class);
        $states = $service->getDayStates($productID);

        return new JsonResponse([
            'days' => array_keys(array_filter($states, static function (string $state): bool {
                return $state === AvailabilityService::STATE_AVAILABLE;
            })),
            'reservedDays' => array_keys(array_filter($states, static function (string $state): bool {
                return $state === AvailabilityService::STATE_RESERVED;
            })),
            'dayStates' => $states,
        ]);
    }

    public function slots(): JsonResponse
    {
        $productID = (int) $this->request->query->get('productID', 0);
        $date = (string) $this->request->query->get('date', '');
        $service = $this->app->make(AvailabilityService::class);
        return new JsonResponse($service->getSlotsWithState($productID, $date));
    }
}
