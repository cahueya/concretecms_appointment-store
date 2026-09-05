<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Package\AppointmentStore\CalDav\ClientFactory;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;
use Concrete\Package\AppointmentStore\Entity\CalendarSource;
use Doctrine\ORM\EntityManagerInterface;

class SlotSyncService
{
    private $em;
    private $clients;

    public function __construct(EntityManagerInterface $em, ClientFactory $clients)
    {
        $this->em = $em;
        $this->clients = $clients;
    }

    /** @return array{seen:int,created:int,updated:int,unavailable:int} */
    public function sync(CalendarSource $source): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $to = $now->modify('+' . $source->getSyncHorizonDays() . ' days');
        $client = $this->clients->create($source->getAccount());
        $records = $client->listFreeSlots($source, $now, $to);
        $repository = $this->em->getRepository(AppointmentSlot::class);

        $stats = ['seen' => count($records), 'created' => 0, 'updated' => 0, 'unavailable' => 0];
        foreach ($records as $record) {
            /** @var AppointmentSlot|null $slot */
            $slot = $repository->findOneBy([
                'source' => $source,
                'uid' => $record['uid'],
                'recurrenceKey' => $record['recurrenceKey'],
            ]);
            if (!$slot) {
                $slot = (new AppointmentSlot())
                    ->setSource($source)
                    ->setUid($record['uid'])
                    ->setRecurrenceKey($record['recurrenceKey'])
                    ->setStatus(AppointmentSlot::STATUS_FREE);
                $this->em->persist($slot);
                ++$stats['created'];
            } else {
                ++$stats['updated'];
                if ($slot->getStatus() === AppointmentSlot::STATUS_UNAVAILABLE) {
                    $slot->setStatus(AppointmentSlot::STATUS_FREE);
                }
            }

            $slot->setStartUTC($record['start'])
                ->setEndUTC($record['end'])
                ->setSummary($record['summary'])
                ->setHref($record['href'])
                ->setEtag($record['etag'])
                ->setLastSeenAt($now);
        }
        $this->em->flush();

        $qb = $this->em->createQueryBuilder();
        $stale = $qb->select('s')
            ->from(AppointmentSlot::class, 's')
            ->where('s.source = :source')
            ->andWhere('s.status = :status')
            ->andWhere('s.startUTC >= :from AND s.startUTC < :to')
            ->andWhere('(s.lastSeenAt < :seen OR s.lastSeenAt IS NULL)')
            ->setParameter('source', $source)
            ->setParameter('status', AppointmentSlot::STATUS_FREE)
            ->setParameter('from', $now)
            ->setParameter('to', $to)
            ->setParameter('seen', $now)
            ->getQuery()->getResult();
        foreach ($stale as $slot) {
            $slot->setStatus(AppointmentSlot::STATUS_UNAVAILABLE);
            ++$stats['unavailable'];
        }

        $source->setLastSyncedAt($now)->setLastSyncError(null);
        $this->em->flush();
        return $stats;
    }
}
