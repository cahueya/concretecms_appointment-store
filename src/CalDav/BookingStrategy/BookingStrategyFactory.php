<?php
namespace Concrete\Package\AppointmentStore\CalDav\BookingStrategy;

use Concrete\Package\AppointmentStore\Entity\CalendarSource;

class BookingStrategyFactory
{
    private $category;
    private $move;

    public function __construct(CategoryBookingStrategy $category, CalendarMoveBookingStrategy $move)
    {
        $this->category = $category;
        $this->move = $move;
    }

    public function forSource(CalendarSource $source): BookingStrategyInterface
    {
        return $source->getBookingMode() === CalendarSource::MODE_MOVE ? $this->move : $this->category;
    }
}
