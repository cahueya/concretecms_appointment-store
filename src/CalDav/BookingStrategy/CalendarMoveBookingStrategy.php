<?php
namespace Concrete\Package\AppointmentStore\CalDav\BookingStrategy;

use Concrete\Package\AppointmentStore\CalDav\BookingResult;
use Concrete\Package\AppointmentStore\CalDav\CalDavException;
use Concrete\Package\AppointmentStore\CalDav\ClientFactory;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;

class CalendarMoveBookingStrategy implements BookingStrategyInterface
{
    private $clients;

    public function __construct(ClientFactory $clients)
    {
        $this->clients = $clients;
    }

    public function book(CalendarSource $source, AppointmentSlot $slot): BookingResult
    {
        if (!$source->getBookedCalendarUri()) {
            throw new CalDavException(t('A booked calendar is required for move booking mode.'));
        }

        $client = $this->clients->create($source->getAccount());
        $resource = $client->fetchCalendar($slot->getHref(), $slot->getEtag());
        $destination = $client->childUri($source->getBookedCalendarUri(), $slot->getHref());
        $destinationEtag = $client->putCalendar($destination, $resource['calendar'], null, true);

        try {
            $client->deleteResource($slot->getHref(), $slot->getEtag());
        } catch (\Throwable $e) {
            try {
                $client->deleteResource($destination, $destinationEtag);
            } catch (\Throwable $rollbackError) {
                // Preserve the original error. The duplicate is safer than silently losing the event.
            }
            throw $e;
        }

        return new BookingResult($destination, $destinationEtag);
    }

    public function cancel(CalendarSource $source, AppointmentSlot $slot): BookingResult
    {
        $bookedHref = $slot->getBookedHref();
        if (!$bookedHref) {
            throw new CalDavException(t('The booked CalDAV resource is unknown.'));
        }

        $client = $this->clients->create($source->getAccount());
        $resource = $client->fetchCalendar($bookedHref, $slot->getBookedEtag());
        $restoredEtag = $client->putCalendar($slot->getHref(), $resource['calendar'], null, true);

        try {
            $client->deleteResource($bookedHref, $slot->getBookedEtag());
        } catch (\Throwable $e) {
            try {
                $client->deleteResource($slot->getHref(), $restoredEtag);
            } catch (\Throwable $rollbackError) {
            }
            throw $e;
        }

        return new BookingResult($slot->getHref(), $restoredEtag);
    }
}
