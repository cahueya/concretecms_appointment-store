<?php
namespace Concrete\Package\AppointmentStore\Service;

use Concrete\Core\Localization\Localization;
use Concrete\Package\AppointmentStore\Entity\AppointmentSlot;

class SlotFormatter
{
    public function format(AppointmentSlot $slot): string
    {
        $timezoneName = $slot->getSource()->getTimezone();
        $timezone = new \DateTimeZone($timezoneName);
        $start = $slot->getStartUTC()->setTimezone($timezone);
        $end = $slot->getEndUTC()->setTimezone($timezone);

        if (class_exists(\IntlDateFormatter::class)) {
            $locale = Localization::activeLocale() ?: 'en_US';
            $dateFormatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE,
                $timezoneName
            );
            $timeFormatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::SHORT,
                $timezoneName
            );

            $startDate = $dateFormatter->format($start);
            $endDate = $dateFormatter->format($end);
            $startTime = $timeFormatter->format($start);
            $endTime = $timeFormatter->format($end);

            if ($startDate !== false && $endDate !== false && $startTime !== false && $endTime !== false) {
                if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
                    return $startDate . ' · ' . $startTime . '–' . $endTime;
                }

                return $startDate . ' ' . $startTime . ' – ' . $endDate . ' ' . $endTime;
            }
        }

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return $start->format('Y-m-d') . ' · ' . $start->format('H:i') . '–' . $end->format('H:i');
        }

        return $start->format('Y-m-d H:i') . ' – ' . $end->format('Y-m-d H:i');
    }
}
