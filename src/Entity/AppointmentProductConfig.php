<?php
namespace Concrete\Package\AppointmentStore\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="AppointmentStoreProductConfigs", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_appt_product", columns={"productID"})
 * })
 */
class AppointmentProductConfig
{
    public const DISPLAY_AUTO = 'auto';
    public const DISPLAY_SELECT = 'select';
    public const DISPLAY_CALENDAR = 'calendar';
    public const TRIGGER_ORDER_PLACED = 'order_placed';
    public const TRIGGER_PAYMENT_COMPLETE = 'payment_complete';

    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    protected $id;

    /** @ORM\Column(type="integer") */
    protected $productID;

    /**
     * @ORM\ManyToOne(targetEntity="CalendarSource")
     * @ORM\JoinColumn(name="sourceID", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     */
    protected $source;

    /** @ORM\Column(type="integer") */
    protected $productOptionID;

    /** @ORM\Column(type="string", length=32, options={"default": "auto"}) */
    protected $displayMode = self::DISPLAY_AUTO;

    /** @ORM\Column(type="integer", options={"default": 30}) */
    protected $reservationMinutes = 30;

    /** @ORM\Column(type="integer", options={"default": 24}) */
    protected $minimumBookingNoticeHours = 24;

    /** @ORM\Column(type="string", length=32, options={"default": "order_placed"}) */
    protected $bookingTrigger = self::TRIGGER_ORDER_PLACED;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    protected $releaseOnCancel = true;

    /** @ORM\Column(type="boolean", nullable=true) */
    protected $originalAllowQuantity;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    protected $enabled = true;

    public function getId(): ?int { return $this->id; }
    public function getProductID(): int { return (int) $this->productID; }
    public function setProductID(int $value): self { $this->productID = $value; return $this; }
    public function getSource(): ?CalendarSource { return $this->source ?: null; }
    public function setSource(CalendarSource $value): self { $this->source = $value; return $this; }
    public function getProductOptionID(): int { return (int) $this->productOptionID; }
    public function setProductOptionID(int $value): self { $this->productOptionID = $value; return $this; }
    public function getDisplayMode(): string { return (string) $this->displayMode; }
    public function setDisplayMode(string $value): self { $this->displayMode = $value; return $this; }
    public function getReservationMinutes(): int { return (int) $this->reservationMinutes; }
    public function setReservationMinutes(int $value): self { $this->reservationMinutes = max(1, $value); return $this; }
    public function getMinimumBookingNoticeHours(): int { return max(0, (int) $this->minimumBookingNoticeHours); }
    public function setMinimumBookingNoticeHours(int $value): self { $this->minimumBookingNoticeHours = max(0, $value); return $this; }
    public function getBookingTrigger(): string { return (string) $this->bookingTrigger; }
    public function setBookingTrigger(string $value): self { $this->bookingTrigger = $value; return $this; }
    public function shouldReleaseOnCancel(): bool { return (bool) $this->releaseOnCancel; }
    public function setReleaseOnCancel(bool $value): self { $this->releaseOnCancel = $value; return $this; }
    public function getOriginalAllowQuantity(): ?bool { return $this->originalAllowQuantity !== null ? (bool) $this->originalAllowQuantity : null; }
    public function setOriginalAllowQuantity(?bool $value): self { $this->originalAllowQuantity = $value; return $this; }
    public function isEnabled(): bool { return (bool) $this->enabled; }
    public function setEnabled(bool $value): self { $this->enabled = $value; return $this; }
}
