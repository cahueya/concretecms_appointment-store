<?php
namespace Concrete\Package\AppointmentStore\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="AppointmentStoreCalendarSources", indexes={
 *     @ORM\Index(name="idx_appt_source_enabled", columns={"enabled"})
 * })
 */
class CalendarSource
{
    public const MODE_CATEGORY = 'category';
    public const MODE_MOVE = 'move';

    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="CalDavAccount")
     * @ORM\JoinColumn(name="accountID", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     */
    protected $account;

    /** @ORM\Column(type="string", length=191) */
    protected $name;

    /** @ORM\Column(type="string", length=500) */
    protected $freeCalendarUri;

    /** @ORM\Column(type="string", length=500, nullable=true) */
    protected $bookedCalendarUri;

    /** @ORM\Column(type="string", length=32, options={"default": "category"}) */
    protected $bookingMode = self::MODE_CATEGORY;

    /** @ORM\Column(type="string", length=191, nullable=true) */
    protected $freeCategory;

    /** @ORM\Column(type="string", length=191, nullable=true) */
    protected $bookedCategory;

    /** @ORM\Column(type="string", length=64, options={"default": "UTC"}) */
    protected $timezone = 'UTC';

    /** @ORM\Column(type="integer", options={"default": 60}) */
    protected $syncHorizonDays = 60;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    protected $enabled = true;

    /** @ORM\Column(type="datetime_immutable", nullable=true) */
    protected $lastSyncedAt;

    /** @ORM\Column(type="text", nullable=true) */
    protected $lastSyncError;

    public function getId(): ?int { return $this->id; }
    public function getAccount(): CalDavAccount { return $this->account; }
    public function setAccount(CalDavAccount $value): self { $this->account = $value; return $this; }
    public function getName(): string { return (string) $this->name; }
    public function setName(string $value): self { $this->name = $value; return $this; }
    public function getFreeCalendarUri(): string { return (string) $this->freeCalendarUri; }
    public function setFreeCalendarUri(string $value): self { $this->freeCalendarUri = $value; return $this; }
    public function getBookedCalendarUri(): ?string { return $this->bookedCalendarUri ?: null; }
    public function setBookedCalendarUri(?string $value): self { $this->bookedCalendarUri = $value ?: null; return $this; }
    public function getBookingMode(): string { return (string) $this->bookingMode; }
    public function setBookingMode(string $value): self { $this->bookingMode = $value; return $this; }
    public function getFreeCategory(): ?string { return $this->freeCategory ?: null; }
    public function setFreeCategory(?string $value): self { $this->freeCategory = $value ?: null; return $this; }
    public function getBookedCategory(): ?string { return $this->bookedCategory ?: null; }
    public function setBookedCategory(?string $value): self { $this->bookedCategory = $value ?: null; return $this; }
    public function getTimezone(): string { return $this->timezone ?: 'UTC'; }
    public function setTimezone(string $value): self { $this->timezone = $value; return $this; }
    public function getSyncHorizonDays(): int { return (int) $this->syncHorizonDays; }
    public function setSyncHorizonDays(int $value): self { $this->syncHorizonDays = max(1, $value); return $this; }
    public function isEnabled(): bool { return (bool) $this->enabled; }
    public function setEnabled(bool $value): self { $this->enabled = $value; return $this; }
    public function getLastSyncedAt(): ?\DateTimeImmutable { return $this->lastSyncedAt; }
    public function setLastSyncedAt(?\DateTimeImmutable $value): self { $this->lastSyncedAt = $value; return $this; }
    public function getLastSyncError(): ?string { return $this->lastSyncError ?: null; }
    public function setLastSyncError(?string $value): self { $this->lastSyncError = $value ?: null; return $this; }
}
