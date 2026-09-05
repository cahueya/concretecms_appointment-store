<?php
namespace Concrete\Package\AppointmentStore\CalDav\BookingStrategy;

use Concrete\Package\AppointmentStore\CalDav\BookingResult;
use Concrete\Package\AppointmentStore\CalDav\CalDavException;
use Concrete\Package\AppointmentStore\CalDav\ClientFactory;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;

class CategoryBookingStrategy implements BookingStrategyInterface
{
    private $clients;

    public function __construct(ClientFactory $clients)
    {
        $this->clients = $clients;
    }

    public function book(CalendarSource $source, AppointmentSlot $slot): BookingResult
    {
        $free = $source->getFreeCategory();
        $booked = $source->getBookedCategory();
        if (!$booked) {
            throw new CalDavException(t('A booked category is required for category booking mode.'));
        }

        $client = $this->clients->create($source->getAccount());
        $resource = $client->fetchCalendar($slot->getHref(), $slot->getEtag());
        $calendar = $resource['calendar'];
        foreach ($calendar->select('VEVENT') as $event) {
            $categories = $client->readCategories($event);
            $categories = $this->removeCategory($categories, $free);
            if (!$client->containsCategory($categories, $booked)) {
                $categories[] = $booked;
            }
            $client->writeCategories($event, $categories);
        }
        $etag = $client->putCalendar($slot->getHref(), $calendar, $slot->getEtag());
        return new BookingResult($slot->getHref(), $etag);
    }

    public function cancel(CalendarSource $source, AppointmentSlot $slot): BookingResult
    {
        $free = $source->getFreeCategory();
        $booked = $source->getBookedCategory();
        if (!$free) {
            throw new CalDavException(t('An available category is required to release category-based bookings.'));
        }

        $client = $this->clients->create($source->getAccount());
        $href = $slot->getBookedHref() ?: $slot->getHref();
        $resource = $client->fetchCalendar($href, $slot->getBookedEtag());
        $calendar = $resource['calendar'];
        foreach ($calendar->select('VEVENT') as $event) {
            $categories = $client->readCategories($event);
            $categories = $this->removeCategory($categories, $booked);
            if (!$client->containsCategory($categories, $free)) {
                $categories[] = $free;
            }
            $client->writeCategories($event, $categories);
        }
        $etag = $client->putCalendar($href, $calendar, $slot->getBookedEtag());
        return new BookingResult($href, $etag);
    }

    /** @param string[] $categories @return string[] */
    private function removeCategory(array $categories, ?string $category): array
    {
        if (!$category) {
            return $categories;
        }
        return array_values(array_filter($categories, static function ($candidate) use ($category) {
            return strcasecmp($candidate, $category) !== 0;
        }));
    }
}
