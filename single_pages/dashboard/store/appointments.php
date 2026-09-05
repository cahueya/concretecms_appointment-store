<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<div class="ccm-dashboard-header-buttons">
    <a class="btn btn-primary" href="<?= h((string) \Concrete\Core\Support\Facade\Url::to('/dashboard/system/automation/tasks')) ?>">
        <?= t('Open Tasks') ?>
    </a>
</div>

<?php
$statusLabels = [
    'free' => t('Available'),
    'reserved' => t('Reserved'),
    'booked' => t('Booked'),
    'unavailable' => t('Unavailable'),
];
?>
<div class="row g-3 mb-4">
    <?php foreach ($counts as $status => $count): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small"><?= h($statusLabels[$status] ?? ucfirst($status)) ?></div>
                    <div class="fs-2 fw-semibold"><?= (int) $count ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= t('Calendar Sources') ?></strong>
        <span class="badge bg-secondary"><?= count($sources) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th><?= t('Source') ?></th><th><?= t('Last Sync') ?></th><th><?= t('Status') ?></th></tr></thead>
            <tbody>
            <?php if (!$sources): ?>
                <tr><td colspan="3" class="text-muted"><?= t('No calendar sources configured yet.') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($sources as $source): ?>
                <tr>
                    <td><?= h($source->getName()) ?></td>
                    <td><?= $source->getLastSyncedAt() ? h($source->getLastSyncedAt()->format('Y-m-d H:i:s')) . ' UTC' : '—' ?></td>
                    <td>
                        <?php if ($source->getLastSyncError()): ?>
                            <span class="badge bg-danger"><?= t('Error') ?></span>
                            <div class="small text-danger mt-1"><?= h($source->getLastSyncError()) ?></div>
                        <?php elseif ($source->isEnabled()): ?>
                            <span class="badge bg-success"><?= t('Enabled') ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= t('Disabled') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted mb-0"><?= t('%d Community Store product(s) currently use Appointment Store.', (int) $productCount) ?></p>
