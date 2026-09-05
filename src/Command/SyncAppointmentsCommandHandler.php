<?php
namespace Concrete\Package\AppointmentStore\Command;

use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Support\Facade\Log;
use Concrete\Package\AppointmentStore\Service\SyncCoordinator;

class SyncAppointmentsCommandHandler
{
    private $coordinator;

    public function __construct(SyncCoordinator $coordinator)
    {
        $this->coordinator = $coordinator;
    }

    public function __invoke(SyncAppointmentsCommand $command)
    {
        $result = $this->coordinator->run();
        Log::addInfo(sprintf(
            'Appointment Store sync: %d source(s), %d seen, %d created, %d updated, %d unavailable, %d expired reservation(s) released.',
            $result['sources'],
            $result['seen'],
            $result['created'],
            $result['updated'],
            $result['unavailable'],
            $result['released']
        ));
        if ($result['errors']) {
            throw new UserMessageException(implode("\n", $result['errors']));
        }
    }
}
