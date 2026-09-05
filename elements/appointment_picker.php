<?php
/** @var int $productID */
defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Package\AppointmentStore\Service\AvailabilityService;
use Concrete\Package\AppointmentStore\Service\ProductOptionService;

$productID = isset($productID) ? (int) $productID : 0;
if ($productID <= 0) {
    return;
}

/** @var AvailabilityService $availability */
$availability = app(AvailabilityService::class);
$config = $availability->getConfig($productID);
if (!$config) {
    return;
}

$dayStates = $availability->getDayStates($productID);
$days = array_keys(array_filter($dayStates, static function (string $state): bool {
    return $state === AvailabilityService::STATE_AVAILABLE;
}));
$reservedDays = array_keys(array_filter($dayStates, static function (string $state): bool {
    return $state === AvailabilityService::STATE_RESERVED;
}));
$calendarDays = array_keys($dayStates);
$fieldName = 'pt' . $config->getProductOptionID();
$fieldID = 'appointment-store-slot-' . $productID . '-' . $config->getId();
$dateFieldID = 'appointment-store-date-' . $productID . '-' . $config->getId();
$displayMode = $config->getDisplayMode();
?>
<div
    class="appointment-store-picker"
    data-product-id="<?= $productID ?>"
    data-display-mode="<?= h($displayMode) ?>"
    data-days="<?= h(json_encode(array_values($days), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
    data-reserved-days="<?= h(json_encode(array_values($reservedDays), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
    data-slots-url="<?= h(URL::to('/appointment_store/availability/slots')) ?>"
    data-preflight-url="<?= h(URL::to('/appointment_store/reservation/preflight')) ?>"
    data-machine-field="<?= h(ProductOptionService::MACHINE_FIELD) ?>"
    data-reservation-token-field="<?= h(ProductOptionService::RESERVATION_TOKEN_FIELD) ?>"
    data-empty-text="<?= h(t('No appointments are available on this date.')) ?>"
    data-reserved-title="<?= h(t('Appointment currently being booked')) ?>"
    data-reserved-text="<?= h(t('This appointment is currently reserved as part of an active booking process. If you already added it to your cart, you can continue your booking there. Otherwise, it may become available again if the current booking is not completed.')) ?>"
    data-reserved-summary-text="<?= h(t('Some appointments are currently reserved in active booking processes and may become available again if those bookings are not completed.')) ?>"
    data-unavailable-title="<?= h(t('Appointment no longer available')) ?>"
    data-minimum-notice-title="<?= h(t('Appointment can no longer be booked')) ?>"
    data-minimum-notice-text="<?= h(t('This appointment can no longer be booked because the minimum booking notice has passed.')) ?>"
    data-error-text="<?= h(t('Unable to load appointments.')) ?>"
    data-choose-text="<?= h(t('Please choose an appointment.')) ?>"
    data-times-text="<?= h(t('Available times')) ?>"
    data-conflict-text="<?= h(t('The selected appointment is no longer available. Please choose another available appointment.')) ?>"
    data-refreshed-text="<?= h(t('Availability has been refreshed.')) ?>"
    data-choose-another-text="<?= h(t('Choose another appointment')) ?>"
    data-verify-error-text="<?= h(t('Unable to verify appointment availability. Please try again.')) ?>"
>

    <div class="appointment-store-conflict alert alert-warning d-none mb-3" role="alert" aria-live="assertive">
        <div class="fw-semibold appointment-store-conflict-title"></div>
        <div class="appointment-store-conflict-message mt-1"></div>
        <div class="small mt-1 appointment-store-conflict-refreshed"></div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-3 appointment-store-conflict-action"></button>
    </div>

    <div class="store-product-option-group form-group mb-3 appointment_slot appointment-store-select-wrap">
        <label for="<?= h($fieldID) ?>" class="store-product-option-group-label form-label">
            <?= t('Appointment') ?>
        </label>
        <select
            id="<?= h($fieldID) ?>"
            name="<?= h($fieldName) ?>"
            class="store-product-option store-product-option-entry form-control form-select appointment-store-select"
            required="required"
            <?= $days ? '' : 'disabled="disabled"' ?>
        >
            <option value=""><?= $days ? t('Please choose an appointment') : t('No appointments are currently available') ?></option>
            <?php foreach ($days as $day): ?>
                <?php $slots = $availability->getSlots($productID, $day); ?>
                <?php if ($slots): ?>
                    <optgroup label="<?= h($day) ?>">
                        <?php foreach ($slots as $slot): ?>
                            <option
                                value="<?= (int) $slot['id'] ?>"
                                data-display="<?= h($slot['display']) ?>"
                            >
                                <?= h($day . ' · ' . $slot['start'] . '–' . $slot['end'] . ($slot['label'] ? ' · ' . $slot['label'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <input type="hidden" class="appointment-store-display-value" value="">
        <input type="hidden" class="appointment-store-machine-value" name="<?= h(ProductOptionService::MACHINE_FIELD) ?>" value="">
        <input type="hidden" class="appointment-store-reservation-token" name="<?= h(ProductOptionService::RESERVATION_TOKEN_FIELD) ?>" value="">
    </div>

    <div class="appointment-store-date-picker d-none mb-3">
        <label for="<?= h($dateFieldID) ?>" class="store-product-option-group-label form-label">
            <?= t('Appointment date') ?>
        </label>
        <input
            id="<?= h($dateFieldID) ?>"
            type="text"
            class="form-control appointment-store-date"
            autocomplete="off"
            <?= $calendarDays ? '' : 'disabled="disabled"' ?>
        >
        <div class="appointment-store-times mt-3" aria-live="polite"></div>
    </div>

    <div class="appointment-store-status small text-muted mb-3" aria-live="polite"></div>
</div>
