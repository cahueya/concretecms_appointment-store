<?php
namespace Concrete\Package\AppointmentStore\CalDav;

use Concrete\Package\AppointmentStore\Entity\CalendarSource;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
use Sabre\VObject\Reader;

class Client
{
    private $baseUri;
    private $http;

    public function __construct(string $baseUri, string $username, string $password)
    {
        $this->baseUri = rtrim($baseUri, '/') . '/';
        $this->http = new HttpClient([
            'auth' => [$username, $password],
            'http_errors' => false,
            'connect_timeout' => 8,
            'timeout' => 25,
            'headers' => [
                'User-Agent' => 'Concrete-Appointment-Store/0.1',
            ],
        ]);
    }

    public function testConnection(?string $uri = null): void
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/><d:displayname/></d:prop></d:propfind>';
        $response = $this->request('PROPFIND', $uri ?: $this->baseUri, [
            'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
            'body' => $body,
        ]);
        if (!in_array($response->getStatusCode(), [200, 207], true)) {
            throw new CalDavException(t('CalDAV connection test failed with HTTP %s.', $response->getStatusCode()));
        }
    }

    /**
     * Returns non-recurring VEVENTs in the requested time range.
     * Recurring master events (RRULE) are deliberately ignored in v0.1.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listFreeSlots(CalendarSource $source, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $fromValue = $from->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
        $toValue = $to->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
            . '<d:prop><d:getetag/><c:calendar-data/></d:prop>'
            . '<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">'
            . '<c:time-range start="' . $fromValue . '" end="' . $toValue . '"/>'
            . '</c:comp-filter></c:comp-filter></c:filter>'
            . '</c:calendar-query>';

        $response = $this->request('REPORT', $source->getFreeCalendarUri(), [
            'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
            'body' => $body,
        ]);
        if ($response->getStatusCode() !== 207) {
            throw new CalDavException(t('CalDAV calendar query failed with HTTP %s.', $response->getStatusCode()));
        }

        $document = new \DOMDocument();
        if (!$document->loadXML((string) $response->getBody(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new CalDavException(t('The CalDAV server returned invalid XML.'));
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('d', 'DAV:');
        $xpath->registerNamespace('c', 'urn:ietf:params:xml:ns:caldav');

        $result = [];
        foreach ($xpath->query('//d:response') as $responseNode) {
            $hrefNode = $xpath->query('./d:href', $responseNode)->item(0);
            $etagNode = $xpath->query('.//d:getetag', $responseNode)->item(0);
            $dataNode = $xpath->query('.//c:calendar-data', $responseNode)->item(0);
            if (!$hrefNode || !$dataNode || trim($dataNode->textContent) === '') {
                continue;
            }

            $href = $this->resolveUri(trim($hrefNode->textContent));
            $etag = $etagNode ? trim($etagNode->textContent) : null;
            try {
                $calendar = Reader::read($dataNode->textContent);
            } catch (\Throwable $e) {
                continue;
            }

            $events = $calendar->select('VEVENT');
            // v0.1 intentionally supports one standalone VEVENT per CalDAV resource only.
            // Moving or categorizing a multi-event resource could otherwise affect an entire series.
            if (count($events) !== 1) {
                continue;
            }
            foreach ($events as $event) {
                // Moving/tagging a recurring master would affect the entire series.
                if (isset($event->RRULE) || !isset($event->UID) || !isset($event->DTSTART)) {
                    continue;
                }
                $rawStart = trim((string) $event->DTSTART);
                if (preg_match('/^\\d{8}$/', $rawStart)) {
                    continue; // all-day events are not appointment slots
                }

                try {
                    $startValue = $event->DTSTART->getDateTime();
                    $start = $startValue instanceof \DateTimeImmutable
                        ? $startValue
                        : \DateTimeImmutable::createFromMutable($startValue);
                    if (isset($event->DTEND)) {
                        $endValue = $event->DTEND->getDateTime();
                        $end = $endValue instanceof \DateTimeImmutable
                            ? $endValue
                            : \DateTimeImmutable::createFromMutable($endValue);
                    } elseif (isset($event->DURATION)) {
                        $end = $start->add(new \DateInterval((string) $event->DURATION));
                    } else {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                // Reject malformed appointment windows. A slot that ends at or before
                // its start cannot be booked and should never reach the storefront.
                if ($end <= $start) {
                    continue;
                }

                $categories = $this->readCategories($event);
                if ($source->getBookingMode() === CalendarSource::MODE_CATEGORY && $source->getFreeCategory()) {
                    if (!$this->containsCategory($categories, $source->getFreeCategory())) {
                        continue;
                    }
                }

                $result[] = [
                    'uid' => trim((string) $event->UID),
                    'recurrenceKey' => isset($event->{'RECURRENCE-ID'}) ? trim((string) $event->{'RECURRENCE-ID'}) : '',
                    'start' => $start->setTimezone(new \DateTimeZone('UTC')),
                    'end' => $end->setTimezone(new \DateTimeZone('UTC')),
                    'summary' => isset($event->SUMMARY) ? trim((string) $event->SUMMARY) : null,
                    'href' => $href,
                    'etag' => $etag,
                ];
            }
        }

        return $result;
    }

    /** @return array{calendar:mixed,etag:?string} */
    public function fetchCalendar(string $href, ?string $ifMatch = null): array
    {
        $headers = [];
        if ($ifMatch) {
            $headers['If-Match'] = $ifMatch;
        }
        $response = $this->request('GET', $href, ['headers' => $headers]);
        if ($response->getStatusCode() === 412) {
            throw new BookingConflictException(t('The calendar event changed after it was synchronized.'));
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new CalDavException(t('Unable to read calendar event (HTTP %s).', $response->getStatusCode()));
        }
        try {
            $calendar = Reader::read((string) $response->getBody());
        } catch (\Throwable $e) {
            throw new CalDavException(t('The calendar event could not be parsed.'), 0, $e);
        }
        return ['calendar' => $calendar, 'etag' => $response->getHeaderLine('ETag') ?: null];
    }

    public function putCalendar(string $href, $calendar, ?string $ifMatch = null, bool $ifNoneMatch = false): ?string
    {
        $headers = ['Content-Type' => 'text/calendar; charset=utf-8'];
        if ($ifMatch) {
            $headers['If-Match'] = $ifMatch;
        }
        if ($ifNoneMatch) {
            $headers['If-None-Match'] = '*';
        }
        $response = $this->request('PUT', $href, [
            'headers' => $headers,
            'body' => $calendar->serialize(),
        ]);
        if (in_array($response->getStatusCode(), [409, 412], true)) {
            throw new BookingConflictException(t('The destination calendar event already exists or changed.'));
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new CalDavException(t('Unable to write calendar event (HTTP %s).', $response->getStatusCode()));
        }
        $etag = $response->getHeaderLine('ETag');
        return $etag !== '' ? $etag : $this->readEtag($href);
    }

    public function deleteResource(string $href, ?string $ifMatch = null): void
    {
        $headers = [];
        if ($ifMatch) {
            $headers['If-Match'] = $ifMatch;
        }
        $response = $this->request('DELETE', $href, ['headers' => $headers]);
        if ($response->getStatusCode() === 412) {
            throw new BookingConflictException(t('The calendar event changed before it could be deleted.'));
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new CalDavException(t('Unable to delete calendar event (HTTP %s).', $response->getStatusCode()));
        }
    }

    public function childUri(string $collectionUri, string $sourceHref): string
    {
        $path = (string) parse_url($sourceHref, PHP_URL_PATH);
        $filename = basename($path);
        if ($filename === '' || $filename === '/' || $filename === '.') {
            throw new CalDavException(t('Unable to determine the CalDAV event filename.'));
        }
        return rtrim($this->resolveUri($collectionUri), '/') . '/' . $filename;
    }

    public function resolveUri(string $uri): string
    {
        if (preg_match('#^https?://#i', $uri)) {
            return $uri;
        }
        $base = parse_url($this->baseUri);
        if (strpos($uri, '/') === 0) {
            $scheme = $base['scheme'] ?? 'https';
            $host = $base['host'] ?? '';
            $port = isset($base['port']) ? ':' . $base['port'] : '';
            return $scheme . '://' . $host . $port . $uri;
        }
        return $this->baseUri . ltrim($uri, '/');
    }

    /** @return string[] */
    public function readCategories($event): array
    {
        $categories = [];
        foreach ($event->select('CATEGORIES') as $property) {
            foreach (explode(',', (string) $property) as $category) {
                $category = trim($category);
                if ($category !== '') {
                    $categories[] = $category;
                }
            }
        }
        return array_values(array_unique($categories));
    }

    /** @param string[] $categories */
    public function writeCategories($event, array $categories): void
    {
        unset($event->CATEGORIES);
        $categories = array_values(array_filter(array_unique(array_map('trim', $categories))));
        if ($categories) {
            $event->add('CATEGORIES', implode(',', $categories));
        }
    }

    /** @param string[] $categories */
    public function containsCategory(array $categories, string $needle): bool
    {
        foreach ($categories as $category) {
            if (strcasecmp($category, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    private function readEtag(string $href): ?string
    {
        $response = $this->request('GET', $href);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return null;
        }
        $etag = $response->getHeaderLine('ETag');
        return $etag !== '' ? $etag : null;
    }

    private function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        try {
            return $this->http->request($method, $this->resolveUri($uri), $options);
        } catch (\Throwable $e) {
            throw new CalDavException(t('CalDAV request failed: %s', $e->getMessage()), 0, $e);
        }
    }
}
