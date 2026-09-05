<?php
namespace Concrete\Package\AppointmentStore\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="AppointmentStoreReservations", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_appt_reservation_slot", columns={"slotID"})
 * }, indexes={
 *     @ORM\Index(name="idx_appt_reservation_session", columns={"sessionID"}),
 *     @ORM\Index(name="idx_appt_reservation_expiry", columns={"expiresAt"}),
 *     @ORM\Index(name="idx_appt_reservation_order", columns={"orderID"})
 * })
 */
class AppointmentReservation
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    protected $id;

    /**
     * @ORM\OneToOne(targetEntity="AppointmentSlot")
     * @ORM\JoinColumn(name="slotID", referencedColumnName="id", nullable=false, unique=true, onDelete="CASCADE")
     */
    protected $slot;

    /**
     * @ORM\ManyToOne(targetEntity="AppointmentProductConfig")
     * @ORM\JoinColumn(name="productConfigID", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     */
    protected $productConfig;

    /**
     * Opaque reservation-owner token stored in Concrete's session. The database
     * column keeps its historical sessionID name for upgrade compatibility.
     *
     * @ORM\Column(type="string", length=191)
     */
    protected $sessionID;

    /** @ORM\Column(type="datetime_immutable") */
    protected $createdAt;

    /** @ORM\Column(type="datetime_immutable") */
    protected $expiresAt;

    /** @ORM\Column(type="integer", nullable=true) */
    protected $orderID;

    /** @ORM\Column(type="integer", nullable=true) */
    protected $orderItemID;

    public function getId(): ?int { return $this->id; }
    public function getSlot(): AppointmentSlot { return $this->slot; }
    public function setSlot(AppointmentSlot $value): self { $this->slot = $value; return $this; }
    public function getProductConfig(): AppointmentProductConfig { return $this->productConfig; }
    public function setProductConfig(AppointmentProductConfig $value): self { $this->productConfig = $value; return $this; }
    public function getSessionID(): string { return (string) $this->sessionID; }
    public function setSessionID(string $value): self { $this->sessionID = $value; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $value): self { $this->createdAt = $value; return $this; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $value): self { $this->expiresAt = $value; return $this; }
    public function getOrderID(): ?int { return $this->orderID !== null ? (int) $this->orderID : null; }
    public function setOrderID(?int $value): self { $this->orderID = $value; return $this; }
    public function getOrderItemID(): ?int { return $this->orderItemID !== null ? (int) $this->orderItemID : null; }
    public function setOrderItemID(?int $value): self { $this->orderItemID = $value; return $this; }
}
