<?php
/**
 * @var int $status
 * @var string $message
 */
?>
<div class="text-center py-5">
    <p class="display-4 fw-bold text-secondary"><?= (int) $status ?></p>
    <p class="lead"><?= e($message) ?></p>
    <a href="<?= e(url('/')) ?>" class="btn btn-primary">Natrag na početnu</a>
</div>
