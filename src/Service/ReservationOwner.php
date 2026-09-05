<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Core\Support\Facade\Session;

class ReservationOwner
{
    private const SESSION_KEY = 'appointment_store.reservation_owner';

    public function getToken(): string
    {
        $token = trim((string) Session::get(self::SESSION_KEY));
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);

        return $token;
    }
}
