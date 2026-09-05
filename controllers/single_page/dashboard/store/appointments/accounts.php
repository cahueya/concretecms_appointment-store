<?php
namespace Concrete\Package\AppointmentStore\Controller\SinglePage\Dashboard\Store\Appointments;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\AppointmentStore\CalDav\ClientFactory;
use Concrete\Package\AppointmentStore\Entity\CalDavAccount;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;
use Concrete\Package\AppointmentStore\Security\CredentialCipher;
use Doctrine\ORM\EntityManagerInterface;

class Accounts extends DashboardPageController
{
    public function view()
    {
        $this->loadView();
    }

    public function edit($id)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $account = $em->find(CalDavAccount::class, (int) $id);
        if (!$account) {
            $this->error->add(t('CalDAV account not found.'));
        }
        $this->loadView($account);
    }

    public function save()
    {
        if (!$this->token->validate('appointment_store_account')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }

        $em = $this->app->make(EntityManagerInterface::class);
        $id = (int) $this->post('id');
        $account = $id ? $em->find(CalDavAccount::class, $id) : new CalDavAccount();
        if (!$account) {
            $this->error->add(t('CalDAV account not found.'));
            return $this->loadView();
        }

        $name = trim((string) $this->post('name'));
        $baseUri = trim((string) $this->post('baseUri'));
        $username = trim((string) $this->post('username'));
        $password = (string) $this->post('password');
        if ($name === '' || $baseUri === '' || $username === '') {
            $this->error->add(t('Name, CalDAV base URL and username are required.'));
        }
        if (!$id && $password === '') {
            $this->error->add(t('A password or app password is required.'));
        }
        if ($this->error->has()) {
            return $this->loadView($account);
        }

        $account->setName($name)
            ->setBaseUri($baseUri)
            ->setUsername($username)
            ->setEnabled((bool) $this->post('enabled'));
        if ($password !== '') {
            $account->setEncryptedPassword($this->app->make(CredentialCipher::class)->encrypt($password));
        }
        $em->persist($account);
        $em->flush();
        $this->flash('message', t('CalDAV account saved.'));
        return $this->buildRedirect($this->action('view'));
    }

    public function test($id)
    {
        if (!$this->token->validate('appointment_store_test_account')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $account = $em->find(CalDavAccount::class, (int) $id);
        if (!$account) {
            $this->error->add(t('CalDAV account not found.'));
            return $this->loadView();
        }
        try {
            $this->app->make(ClientFactory::class)->create($account)->testConnection();
            $this->flash('message', t('CalDAV connection successful.'));
            return $this->buildRedirect($this->action('view'));
        } catch (\Throwable $e) {
            $this->error->add($e->getMessage());
            return $this->loadView($account);
        }
    }

    public function delete($id)
    {
        if (!$this->token->validate('appointment_store_delete_account')) {
            $this->error->add(t('Invalid security token.'));
            return $this->loadView();
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $account = $em->find(CalDavAccount::class, (int) $id);
        if (!$account) {
            return $this->buildRedirect($this->action('view'));
        }
        if ($em->getRepository(CalendarSource::class)->count(['account' => $account]) > 0) {
            $this->error->add(t('This account is still used by one or more calendar sources.'));
            return $this->loadView($account);
        }
        $em->remove($account);
        $em->flush();
        $this->flash('message', t('CalDAV account deleted.'));
        return $this->buildRedirect($this->action('view'));
    }

    private function loadView(?CalDavAccount $selected = null)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $this->set('accounts', $em->getRepository(CalDavAccount::class)->findBy([], ['name' => 'ASC']));
        $this->set('selected', $selected);
        $this->set('token', $this->token);
    }
}
