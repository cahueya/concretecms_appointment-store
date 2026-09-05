<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php $selected = $selected ?? null; ?>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><strong><?= $selected ? t('Edit Calendar Source') : t('Add Calendar Source') ?></strong></div>
            <div class="card-body">
                <?php if (!$accounts): ?>
                    <div class="alert alert-warning"><?= t('Create a CalDAV account first.') ?></div>
                <?php else: ?>
                <form method="post" action="<?= h($view->action('save')) ?>">
                    <?= $token->output('appointment_store_calendar') ?>
                    <input type="hidden" name="id" value="<?= $selected ? (int) $selected->getId() : 0 ?>">
                    <div class="mb-3">
                        <label class="form-label" for="accountID"><?= t('CalDAV Account') ?></label>
                        <select class="form-select" id="accountID" name="accountID" required>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= (int) $account->getId() ?>" <?= $selected && $selected->getAccount()->getId() === $account->getId() ? 'selected' : '' ?>><?= h($account->getName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="name"><?= t('Name') ?></label>
                        <input class="form-control" id="name" name="name" required value="<?= h($selected ? $selected->getName() : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="freeCalendarUri"><?= t('Available Calendar URL') ?></label>
                        <input class="form-control" id="freeCalendarUri" name="freeCalendarUri" required placeholder="Calendar/personal/" value="<?= h($selected ? $selected->getFreeCalendarUri() : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bookingMode"><?= t('Booking Mode') ?></label>
                        <select class="form-select" id="bookingMode" name="bookingMode">
                            <option value="category" <?= !$selected || $selected->getBookingMode() === 'category' ? 'selected' : '' ?>><?= t('Categories in one calendar') ?></option>
                            <option value="move" <?= $selected && $selected->getBookingMode() === 'move' ? 'selected' : '' ?>><?= t('Two calendars (free → booked)') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bookedCalendarUri"><?= t('Booked Calendar URL') ?></label>
                        <input class="form-control" id="bookedCalendarUri" name="bookedCalendarUri" placeholder="Calendar/booked/" value="<?= h($selected ? ($selected->getBookedCalendarUri() ?: '') : '') ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="freeCategory"><?= t('Available Category') ?></label>
                            <input class="form-control" id="freeCategory" name="freeCategory" value="<?= h($selected ? ($selected->getFreeCategory() ?: '') : 'AVAILABLE') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="bookedCategory"><?= t('Booked Category') ?></label>
                            <input class="form-control" id="bookedCategory" name="bookedCategory" value="<?= h($selected ? ($selected->getBookedCategory() ?: '') : 'BOOKED') ?>">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-7">
                            <label class="form-label" for="timezone"><?= t('Timezone') ?></label>
                            <input class="form-control" id="timezone" name="timezone" required value="<?= h($selected ? $selected->getTimezone() : date_default_timezone_get()) ?>">
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="syncHorizonDays"><?= t('Sync Horizon (days)') ?></label>
                            <input class="form-control" id="syncHorizonDays" name="syncHorizonDays" type="number" min="1" max="730" value="<?= (int) ($selected ? $selected->getSyncHorizonDays() : 60) ?>">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?= !$selected || $selected->isEnabled() ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enabled"><?= t('Enabled') ?></label>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= t('Save') ?></button>
                    <?php if ($selected): ?><a class="btn btn-secondary" href="<?= h($view->action('view')) ?>"><?= t('Cancel') ?></a><?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th><?= t('Source') ?></th><th><?= t('Mode') ?></th><th><?= t('Last Sync') ?></th><th class="text-end"><?= t('Actions') ?></th></tr></thead>
                <tbody>
                <?php if (!$sources): ?><tr><td colspan="4" class="text-muted"><?= t('No calendar sources configured.') ?></td></tr><?php endif; ?>
                <?php foreach ($sources as $source): ?>
                    <tr>
                        <td>
                            <strong><?= h($source->getName()) ?></strong><br>
                            <small class="text-muted"><?= h($source->getAccount()->getName()) ?></small>
                            <?php if ($source->getLastSyncError()): ?><div class="small text-danger"><?= h($source->getLastSyncError()) ?></div><?php endif; ?>
                        </td>
                        <td><?= $source->getBookingMode() === 'move' ? t('Two calendars') : t('Categories') ?></td>
                        <td><?= $source->getLastSyncedAt() ? h($source->getLastSyncedAt()->format('Y-m-d H:i')) . ' UTC' : '—' ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= h($view->action('edit', $source->getId())) ?>"><?= t('Edit') ?></a>
                            <form class="d-inline" method="post" action="<?= h($view->action('test', $source->getId())) ?>">
                                <?= $token->output('appointment_store_test_calendar') ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= t('Test') ?></button>
                            </form>
                            <form class="d-inline" method="post" action="<?= h($view->action('delete', $source->getId())) ?>" onsubmit="return confirm('<?= h(t('Delete this calendar source?')) ?>');">
                                <?= $token->output('appointment_store_delete_calendar') ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= t('Delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
