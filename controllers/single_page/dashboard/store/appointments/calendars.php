<?php
namespace Concrete\Package\AppointmentStore\Controller\SinglePage\Dashboard\Store\Appointments;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\AppointmentStore\CalDav\ClientFactory;
use Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Entity\CalDavAccount;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;
use Doctrine\ORM\EntityManagerInterface;

class Calendars extends DashboardPageController
{
    public function view()
    {
        $this->loadView();
    }

    public function edit($id)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $source = $em->find(CalendarSource::class, (int) $id);
        if (!$source) {
            $this->error->add(t('Calendar source not found.'));
        }
        $this->loadView($source);
    }

    public function save()
    {
        if (!$this->token->validate('appointment_store_calendar')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $id = (int) $this->post('id');
        $source = $id ? $em->find(CalendarSource::class, $id) : new CalendarSource();
        $account = $em->find(CalDavAccount::class, (int) $this->post('accountID'));
        if (!$source || !$account) {
            $this->error->add(t('A valid CalDAV account is required.'));
            return $this->loadView($source ?: null);
        }

        $name = trim((string) $this->post('name'));
        $freeUri = trim((string) $this->post('freeCalendarUri'));
        $bookedUri = trim((string) $this->post('bookedCalendarUri'));
        $mode = (string) $this->post('bookingMode');
        $timezone = trim((string) $this->post('timezone')) ?: 'UTC';
        if ($name === '' || $freeUri === '') {
            $this->error->add(t('Name and available calendar URL are required.'));
        }
        if (!in_array($mode, [CalendarSource::MODE_CATEGORY, CalendarSource::MODE_MOVE], true)) {
            $this->error->add(t('Invalid booking mode.'));
        }
        if ($mode === CalendarSource::MODE_MOVE && $bookedUri === '') {
            $this->error->add(t('A booked calendar URL is required in two-calendar mode.'));
        }
        if ($mode === CalendarSource::MODE_CATEGORY) {
            if (trim((string) $this->post('freeCategory')) === '' || trim((string) $this->post('bookedCategory')) === '') {
                $this->error->add(t('Free and booked categories are required in category mode.'));
            }
        }
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $e) {
            $this->error->add(t('Invalid timezone.'));
        }
        if ($this->error->has()) {
            return $this->loadView($source);
        }

        $source->setAccount($account)
            ->setName($name)
            ->setFreeCalendarUri($freeUri)
            ->setBookedCalendarUri($bookedUri ?: null)
            ->setBookingMode($mode)
            ->setFreeCategory(trim((string) $this->post('freeCategory')) ?: null)
            ->setBookedCategory(trim((string) $this->post('bookedCategory')) ?: null)
            ->setTimezone($timezone)
            ->setSyncHorizonDays((int) ($this->post('syncHorizonDays') ?: 60))
            ->setEnabled((bool) $this->post('enabled'));
        $em->persist($source);
        $em->flush();
        $this->flash('message', t('Calendar source saved.'));
        return $this->buildRedirect($this->action('view'));
    }

    public function test($id)
    {
        if (!$this->token->validate('appointment_store_test_calendar')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $source = $em->find(CalendarSource::class, (int) $id);
        if (!$source) {
            $this->error->add(t('Calendar source not found.'));
            return $this->loadView();
        }
        try {
            $this->app->make(ClientFactory::class)->create($source->getAccount())->testConnection($source->getFreeCalendarUri());
            $this->flash('message', t('Calendar connection successful.'));
            return $this->buildRedirect($this->action('view'));
        } catch (\Throwable $e) {
            $this->error->add($e->getMessage());
            return $this->loadView($source);
        }
    }

    public function delete($id)
    {
        if (!$this->token->validate('appointment_store_delete_calendar')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $source = $em->find(CalendarSource::class, (int) $id);
        if (!$source) {
            return $this->buildRedirect($this->action('view'));
        }
        if ($em->getRepository(AppointmentProductConfig::class)->count(['source' => $source]) > 0 ||
            $em->getRepository(AppointmentSlot::class)->count(['source' => $source]) > 0) {
            $this->error->add(t('This calendar source is still used by products or synchronized slots. Disable it instead of deleting it.'));
            return $this->loadView($source);
        }
        $em->remove($source);
        $em->flush();
        $this->flash('message', t('Calendar source deleted.'));
        return $this->buildRedirect($this->action('view'));
    }

    private function loadView(?CalendarSource $selected = null)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $this->set('sources', $em->getRepository(CalendarSource::class)->findBy([], ['name' => 'ASC']));
        $this->set('accounts', $em->getRepository(CalDavAccount::class)->findBy([], ['name' => 'ASC']));
        $this->set('selected', $selected);
        $this->set('token', $this->token);
    }
}
