<?php
namespace Concrete\Package\AppointmentStore\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="AppointmentStoreCalDavAccounts")
 */
class CalDavAccount
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    protected $id;

    /** @ORM\Column(type="string", length=191) */
    protected $name;

    /** @ORM\Column(type="string", length=500) */
    protected $baseUri;

    /** @ORM\Column(type="string", length=191) */
    protected $username;

    /** @ORM\Column(type="text") */
    protected $encryptedPassword;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    protected $enabled = true;

    /** @ORM\Column(type="datetime_immutable") */
    protected $createdAt;

    /** @ORM\Column(type="datetime_immutable") */
    protected $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return (string) $this->name; }
    public function setName(string $value): self { $this->name = $value; return $this->touch(); }
    public function getBaseUri(): string { return (string) $this->baseUri; }
    public function setBaseUri(string $value): self { $this->baseUri = rtrim($value, '/') . '/'; return $this->touch(); }
    public function getUsername(): string { return (string) $this->username; }
    public function setUsername(string $value): self { $this->username = $value; return $this->touch(); }
    public function getEncryptedPassword(): string { return (string) $this->encryptedPassword; }
    public function setEncryptedPassword(string $value): self { $this->encryptedPassword = $value; return $this->touch(); }
    public function isEnabled(): bool { return (bool) $this->enabled; }
    public function setEnabled(bool $value): self { $this->enabled = $value; return $this->touch(); }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this;
    }
}
