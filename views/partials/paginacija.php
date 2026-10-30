<?php
/** @var \App\Core\Paginator $paginator */
if (!$paginator->hasPages()) {
    return;
}
?>
<nav aria-label="Straničenje" class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item<?= $paginator->page === 1 ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($paginator->url($paginator->page - 1)) ?>">&laquo; Prethodna</a>
        </li>
        <?php for ($i = 1; $i <= $paginator->pages; $i++): ?>
            <li class="page-item<?= $i === $paginator->page ? ' active' : '' ?>">
                <a class="page-link" href="<?= e($paginator->url($i)) ?>"
                    <?= $i === $paginator->page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item<?= $paginator->page === $paginator->pages ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($paginator->url($paginator->page + 1)) ?>">Sljedeća &raquo;</a>
        </li>
    </ul>
</nav>
