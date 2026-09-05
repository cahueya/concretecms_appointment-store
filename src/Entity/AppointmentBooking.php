<?php
namespace Concrete\Package\AppointmentStore\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="AppointmentStoreBookings", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_appt_booking_order_item", columns={"orderID", "orderItemID"})
 * }, indexes={
 *     @ORM\Index(name="idx_appt_booking_order", columns={"orderID"})
 * })
 */
class AppointmentBooking
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="AppointmentSlot")
     * @ORM\JoinColumn(name="slotID", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     */
    protected $slot;

    /**
     * @ORM\ManyToOne(targetEntity="AppointmentProductConfig")
     * @ORM\JoinColumn(name="productConfigID", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     */
    protected $productConfig;

    /** @ORM\Column(type="integer") */
    protected $orderID;

    /** @ORM\Column(type="integer") */
    protected $orderItemID;

    /** @ORM\Column(type="datetime_immutable") */
    protected $bookedAt;

    /** @ORM\Column(type="datetime_immutable", nullable=true) */
    protected $cancelledAt;

    public function getId(): ?int { return $this->id; }
    public function getSlot(): AppointmentSlot { return $this->slot; }
    public function setSlot(AppointmentSlot $value): self { $this->slot = $value; return $this; }
    public function getProductConfig(): AppointmentProductConfig { return $this->productConfig; }
    public function setProductConfig(AppointmentProductConfig $value): self { $this->productConfig = $value; return $this; }
    public function getOrderID(): int { return (int) $this->orderID; }
    public function setOrderID(int $value): self { $this->orderID = $value; return $this; }
    public function getOrderItemID(): int { return (int) $this->orderItemID; }
    public function setOrderItemID(int $value): self { $this->orderItemID = $value; return $this; }
    public function getBookedAt(): \DateTimeImmutable { return $this->bookedAt; }
    public function setBookedAt(\DateTimeImmutable $value): self { $this->bookedAt = $value; return $this; }
    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }
    public function setCancelledAt(?\DateTimeImmutable $value): self { $this->cancelledAt = $value; return $this; }
}
