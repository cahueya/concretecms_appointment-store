<?php
namespace Concrete\Package\AppointmentStore\CalDav;

use Concrete\Package\AppointmentStore\Entity\CalDavAccount;
use Concrete\Package\AppointmentStore\Security\CredentialCipher;

class ClientFactory
{
    private $cipher;

    public function __construct(CredentialCipher $cipher)
    {
        $this->cipher = $cipher;
    }

    public function create(CalDavAccount $account): Client
    {
        return new Client(
            $account->getBaseUri(),
            $account->getUsername(),
            $this->cipher->decrypt($account->getEncryptedPassword())
        );
    }
}
