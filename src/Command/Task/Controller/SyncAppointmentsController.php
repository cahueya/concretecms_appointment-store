<?php
namespace Concrete\Package\AppointmentStore\Command\Task\Controller;

use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\CommandTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Package\AppointmentStore\Command\SyncAppointmentsCommand;

class SyncAppointmentsController extends AbstractController
{
    public function getName(): string
    {
        return t('Synchronize Appointment Slots');
    }

    public function getDescription(): string
    {
        return t('Synchronizes available CalDAV events and releases expired cart reservations.');
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        return new CommandTaskRunner(
            $task,
            new SyncAppointmentsCommand(),
            t('Appointment slots synchronized successfully.')
        );
    }
}
