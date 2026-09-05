<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php
$selected = $selected ?? null;
$selectedSource = $selected ? $selected->getSource() : null;
$productNames = [];
foreach ($products as $product) {
    $productNames[(int) $product->getID()] = $product->getName();
}
?>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><strong><?= t('Configure Appointment Product') ?></strong></div>
            <div class="card-body">
                <?php if (!$sources): ?>
                    <div class="alert alert-warning"><?= t('Create and enable a calendar source first.') ?></div>
                <?php else: ?>
                <form method="post" action="<?= h($view->action('save')) ?>">
                    <?= $token->output('appointment_store_product') ?>
                    <div class="mb-3">
                        <label class="form-label" for="productID"><?= t('Community Store Product') ?></label>
                        <select class="form-select" id="productID" name="productID" required>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= (int) $product->getID() ?>" <?= $selected && $selected->getProductID() === (int) $product->getID() ? 'selected' : '' ?>><?= h($product->getName()) ?> (#<?= (int) $product->getID() ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sourceID"><?= t('Calendar Source') ?></label>
                        <select class="form-select" id="sourceID" name="sourceID" required>
                            <?php foreach ($sources as $source): ?>
                                <option value="<?= (int) $source->getId() ?>" <?= $selectedSource && $selectedSource->getId() === $source->getId() ? 'selected' : '' ?>><?= h($source->getName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="displayMode"><?= t('Frontend Display') ?></label>
                        <?php $mode = $selected ? $selected->getDisplayMode() : 'auto'; ?>
                        <select class="form-select" id="displayMode" name="displayMode">
                            <option value="auto" <?= $mode === 'auto' ? 'selected' : '' ?>><?= t('Automatic') ?></option>
                            <option value="select" <?= $mode === 'select' ? 'selected' : '' ?>><?= t('Select') ?></option>
                            <option value="calendar" <?= $mode === 'calendar' ? 'selected' : '' ?>><?= t('Date picker and time buttons') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reservationMinutes"><?= t('Cart Reservation (minutes)') ?></label>
                        <input class="form-control" id="reservationMinutes" name="reservationMinutes" type="number" min="1" max="1440" value="<?= (int) ($selected ? $selected->getReservationMinutes() : 30) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="minimumBookingNoticeHours"><?= t('Minimum booking notice') ?></label>
                        <?php $minimumBookingNoticeHours = $selected ? $selected->getMinimumBookingNoticeHours() : 24; ?>
                        <select class="form-select" id="minimumBookingNoticeHours" name="minimumBookingNoticeHours">
                            <option value="0" <?= $minimumBookingNoticeHours === 0 ? 'selected' : '' ?>><?= t('No minimum notice') ?></option>
                            <?php foreach ([1, 2, 4, 6, 8, 12, 24, 36, 48, 72] as $hours): ?>
                                <option value="<?= $hours ?>" <?= $minimumBookingNoticeHours === $hours ? 'selected' : '' ?>><?= t('%d hours', $hours) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= t('Appointments starting sooner than this cannot be selected or reserved.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bookingTrigger"><?= t('Final Booking Trigger') ?></label>
                        <?php $trigger = $selected ? $selected->getBookingTrigger() : 'order_placed'; ?>
                        <select class="form-select" id="bookingTrigger" name="bookingTrigger">
                            <option value="order_placed" <?= $trigger === 'order_placed' ? 'selected' : '' ?>><?= t('Order placed') ?></option>
                            <option value="payment_complete" <?= $trigger === 'payment_complete' ? 'selected' : '' ?>><?= t('Payment complete') ?></option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="releaseOnCancel" name="releaseOnCancel" value="1" <?= !$selected || $selected->shouldReleaseOnCancel() ? 'checked' : '' ?>>
                        <label class="form-check-label" for="releaseOnCancel"><?= t('Release appointment when order is cancelled') ?></label>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= t('Save') ?></button>
                    <?php if ($selected): ?><a class="btn btn-secondary" href="<?= h($view->action('view')) ?>"><?= t('Cancel') ?></a><?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="alert alert-info">
            <?= t('Appointment Store creates a required Community Store text option with handle %s. In your product template, render the Appointment Store picker instead of the normal field for this option.', '<code>appointment_slot</code>') ?>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th><?= t('Product') ?></th><th><?= t('Calendar') ?></th><th><?= t('Frontend Display') ?></th><th><?= t('Minimum booking notice') ?></th><th><?= t('Option') ?></th><th class="text-end"><?= t('Actions') ?></th></tr></thead>
                <tbody>
                <?php if (!$configs): ?><tr><td colspan="6" class="text-muted"><?= t('No appointment products configured.') ?></td></tr><?php endif; ?>
                <?php foreach ($configs as $config): ?>
                    <tr class="<?= $config->isEnabled() ? '' : 'text-muted' ?>">
                        <td><?= h($productNames[$config->getProductID()] ?? ('#' . $config->getProductID())) ?></td>
                        <td><?= h($config->getSource() ? $config->getSource()->getName() : t('Missing calendar source')) ?></td>
                        <?php
                        $displayLabels = [
                            \Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig::DISPLAY_AUTO => t('Automatic'),
                            \Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig::DISPLAY_SELECT => t('Select'),
                            \Concrete\Package\AppointmentStore\Entity\AppointmentProductConfig::DISPLAY_CALENDAR => t('Date picker and time buttons'),
                        ];
                        ?>
                        <td><?= h($displayLabels[$config->getDisplayMode()] ?? $config->getDisplayMode()) ?></td>
                        <td><?= $config->getMinimumBookingNoticeHours() > 0 ? h(t('%d hours', $config->getMinimumBookingNoticeHours())) : h(t('No minimum notice')) ?></td>
                        <td><code>pt<?= (int) $config->getProductOptionID() ?></code></td>
                        <td class="text-end">
                            <?php if ($config->isEnabled()): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= h($view->action('edit', $config->getId())) ?>"><?= t('Edit') ?></a>
                                <form class="d-inline" method="post" action="<?= h($view->action('delete', $config->getId())) ?>" onsubmit="return confirm('<?= h(t('Disable appointment booking for this product?')) ?>');">
                                    <?= $token->output('appointment_store_delete_product') ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><?= t('Disable') ?></button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= t('Disabled (history retained)') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
