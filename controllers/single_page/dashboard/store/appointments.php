<?php
namespace Concrete\Package\AppointmentStore\Controller\SinglePage\Dashboard\Store;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;
use Doctrine\ORM\EntityManagerInterface;

class Appointments extends DashboardPageController
{
    public function view()
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $slotRepository = $em->getRepository(AppointmentSlot::class);
        $this->set('counts', [
            'free' => $slotRepository->count(['status' => AppointmentSlot::STATUS_FREE]),
            'reserved' => $slotRepository->count(['status' => AppointmentSlot::STATUS_RESERVED]),
            'booked' => $slotRepository->count(['status' => AppointmentSlot::STATUS_BOOKED]),
            'unavailable' => $slotRepository->count(['status' => AppointmentSlot::STATUS_UNAVAILABLE]),
        ]);
        $this->set('sources', $em->getRepository(CalendarSource::class)->findBy([], ['name' => 'ASC']));
        $this->set('productCount', $em->getRepository(AppointmentProductConfig::class)->count(['enabled' => true]));
    }
}
