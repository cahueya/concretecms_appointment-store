<?php
namespace Concrete\Package\AppointmentStore\CalDav;

class BookingResult
{
    private $href;
    private $etag;

    public function __construct(string $href, ?string $etag)
    {
        $this->href = $href;
        $this->etag = $etag;
    }

    public function getHref(): string { return $this->href; }
    public function getEtag(): ?string { return $this->etag; }
}
