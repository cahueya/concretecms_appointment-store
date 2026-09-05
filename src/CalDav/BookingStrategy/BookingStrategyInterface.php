<?php
namespace Concrete\Package\AppointmentStore\CalDav\BookingStrategy;

use Concrete\Package\AppointmentStore\CalDav\BookingResult;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;

interface BookingStrategyInterface
{
    public function book(CalendarSource $source, AppointmentSlot $slot): BookingResult;
    public function cancel(CalendarSource $source, AppointmentSlot $slot): BookingResult;
}
