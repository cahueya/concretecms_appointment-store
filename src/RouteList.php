<?php
namespace Concrete\Package\AppointmentStore;

use Concrete\Core\Routing\RouteListInterface;

class RouteList implements RouteListInterface
{
    public function loadRoutes($router)
    {
        $router->get(
            '/appointment_store/availability/days',
            '\\Concrete\\Package\\AppointmentStore\\Controller\\Frontend\\Availability::days'
        );
        $router->get(
            '/appointment_store/availability/slots',
            '\\Concrete\\Package\\AppointmentStore\\Controller\\Frontend\\Availability::slots'
        );
        $router->post(
            '/appointment_store/reservation/preflight',
            '\\Concrete\\Package\\AppointmentStore\\Controller\\Frontend\\Reservation::preflight'
        );
    }
}
