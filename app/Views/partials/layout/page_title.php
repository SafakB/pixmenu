<?php
/**
 * Sayfa başlığı + breadcrumb. $title yoksa hiçbir şey basmaz.
 *
 * @var string|null                              $title
 * @var list<array{label: string, url?: string}> $breadcrumbs
 */
if (empty($title)) {
    return;
}
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0"><?= esc($title) ?></h4>
            <?php if (! empty($breadcrumbs)): ?>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <?php foreach ($breadcrumbs as $crumb): ?>
                            <?php if (! empty($crumb['url'])): ?>
                                <li class="breadcrumb-item"><a href="<?= esc($crumb['url'], 'attr') ?>"><?= esc($crumb['label']) ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page"><?= esc($crumb['label']) ?></li>
                            <?php endif ?>
                        <?php endforeach ?>
                    </ol>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>
