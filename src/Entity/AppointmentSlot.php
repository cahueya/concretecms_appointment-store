<?php
namespace Concrete\Package\AppointmentStore\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="AppointmentStoreSlots", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_appt_slot", columns={"sourceID", "uid", "recurrenceKey"})
 * }, indexes={
 *     @ORM\Index(name="idx_appt_slot_status", columns={"status"}),
 *     @ORM\Index(name="idx_appt_slot_start", columns={"startUTC"}),
 *     @ORM\Index(name="idx_appt_slot_seen", columns={"lastSeenAt"})
 * })
 */
class AppointmentSlot
{
    public const STATUS_FREE = 'free';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_UNAVAILABLE = 'unavailable';

    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="CalendarSource")
     * @ORM\JoinColumn(name="sourceID", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $source;

    /** @ORM\Column(type="string", length=255) */
    protected $uid;

    /** @ORM\Column(type="string", length=255, options={"default": ""}) */
    protected $recurrenceKey = '';

    /** @ORM\Column(type="datetime_immutable") */
    protected $startUTC;

    /** @ORM\Column(type="datetime_immutable") */
    protected $endUTC;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    protected $summary;

    /** @ORM\Column(type="string", length=1000) */
    protected $href;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    protected $etag;

    /** @ORM\Column(type="string", length=32, options={"default": "free"}) */
    protected $status = self::STATUS_FREE;

    /** @ORM\Column(type="datetime_immutable") */
    protected $lastSeenAt;

    /** @ORM\Column(type="string", length=1000, nullable=true) */
    protected $bookedHref;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    protected $bookedEtag;

    public function getId(): ?int { return $this->id; }
    public function getSource(): CalendarSource { return $this->source; }
    public function setSource(CalendarSource $value): self { $this->source = $value; return $this; }
    public function getUid(): string { return (string) $this->uid; }
    public function setUid(string $value): self { $this->uid = $value; return $this; }
    public function getRecurrenceKey(): string { return (string) $this->recurrenceKey; }
    public function setRecurrenceKey(string $value): self { $this->recurrenceKey = $value; return $this; }
    public function getStartUTC(): \DateTimeImmutable { return $this->startUTC; }
    public function setStartUTC(\DateTimeImmutable $value): self { $this->startUTC = $value; return $this; }
    public function getEndUTC(): \DateTimeImmutable { return $this->endUTC; }
    public function setEndUTC(\DateTimeImmutable $value): self { $this->endUTC = $value; return $this; }
    public function getSummary(): ?string { return $this->summary ?: null; }
    public function setSummary(?string $value): self { $this->summary = $value ?: null; return $this; }
    public function getHref(): string { return (string) $this->href; }
    public function setHref(string $value): self { $this->href = $value; return $this; }
    public function getEtag(): ?string { return $this->etag ?: null; }
    public function setEtag(?string $value): self { $this->etag = $value ?: null; return $this; }
    public function getStatus(): string { return (string) $this->status; }
    public function setStatus(string $value): self { $this->status = $value; return $this; }
    public function getLastSeenAt(): \DateTimeImmutable { return $this->lastSeenAt; }
    public function setLastSeenAt(\DateTimeImmutable $value): self { $this->lastSeenAt = $value; return $this; }
    public function getBookedHref(): ?string { return $this->bookedHref ?: null; }
    public function setBookedHref(?string $value): self { $this->bookedHref = $value ?: null; return $this; }
    public function getBookedEtag(): ?string { return $this->bookedEtag ?: null; }
    public function setBookedEtag(?string $value): self { $this->bookedEtag = $value ?: null; return $this; }
}
