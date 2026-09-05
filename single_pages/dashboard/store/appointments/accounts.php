<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php $selected = $selected ?? null; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong><?= $selected ? t('Edit CalDAV Account') : t('Add CalDAV Account') ?></strong></div>
            <div class="card-body">
                <form method="post" action="<?= h($view->action('save')) ?>">
                    <?= $token->output('appointment_store_account') ?>
                    <input type="hidden" name="id" value="<?= $selected ? (int) $selected->getId() : 0 ?>">
                    <div class="mb-3">
                        <label class="form-label" for="name"><?= t('Name') ?></label>
                        <input class="form-control" id="name" name="name" required value="<?= h($selected ? $selected->getName() : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="baseUri"><?= t('CalDAV Base URL') ?></label>
                        <input class="form-control" id="baseUri" name="baseUri" type="url" required placeholder="https://calendar.example.com/dav/user/" value="<?= h($selected ? $selected->getBaseUri() : '') ?>">
                        <div class="form-text"><?= t('Enter the CalDAV base URL or user principal URL. Calendar URLs may be absolute or relative to this URL.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username"><?= t('Username') ?></label>
                        <input class="form-control" id="username" name="username" autocomplete="username" required value="<?= h($selected ? $selected->getUsername() : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password"><?= t('Password / App Password') ?></label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" <?= $selected ? '' : 'required' ?>>
                        <?php if ($selected): ?><div class="form-text"><?= t('Leave blank to keep the stored password.') ?></div><?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?= !$selected || $selected->isEnabled() ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enabled"><?= t('Enabled') ?></label>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= t('Save') ?></button>
                    <?php if ($selected): ?><a class="btn btn-secondary" href="<?= h($view->action('view')) ?>"><?= t('Cancel') ?></a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th><?= t('Name') ?></th><th><?= t('Username') ?></th><th><?= t('Status') ?></th><th class="text-end"><?= t('Actions') ?></th></tr></thead>
                <tbody>
                <?php if (!$accounts): ?><tr><td colspan="4" class="text-muted"><?= t('No accounts configured.') ?></td></tr><?php endif; ?>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><?= h($account->getName()) ?></td>
                        <td><?= h($account->getUsername()) ?></td>
                        <td><span class="badge <?= $account->isEnabled() ? 'bg-success' : 'bg-secondary' ?>"><?= $account->isEnabled() ? t('Enabled') : t('Disabled') ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= h($view->action('edit', $account->getId())) ?>"><?= t('Edit') ?></a>
                            <form class="d-inline" method="post" action="<?= h($view->action('test', $account->getId())) ?>">
                                <?= $token->output('appointment_store_test_account') ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= t('Test') ?></button>
                            </form>
                            <form class="d-inline" method="post" action="<?= h($view->action('delete', $account->getId())) ?>" onsubmit="return confirm('<?= h(t('Delete this CalDAV account?')) ?>');">
                                <?= $token->output('appointment_store_delete_account') ?>
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
