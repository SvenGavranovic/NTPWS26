<?php
/**
 * Podizbornik administracije. Urednik vidi samo vijesti; ostale stavke
 * su skrivene, ali ih ionako štiti router (403).
 */

use App\Core\Auth;

$stavke = Auth::hasRole('admin')
    ? ['/admin' => 'Pregled', '/admin/prijave' => 'Prijave', '/admin/vijesti' => 'Vijesti', '/admin/kategorije' => 'Kategorije', '/admin/korisnici' => 'Korisnici']
    : ['/admin/vijesti' => 'Moje vijesti'];
$trenutna = request_path();
?>
<ul class="nav nav-tabs mb-4">
    <?php foreach ($stavke as $putanja => $naziv):
        $aktivna = $trenutna === $putanja || ($putanja !== '/admin' && str_starts_with($trenutna, $putanja . '/')); ?>
        <li class="nav-item">
            <a class="nav-link<?= $aktivna ? ' active' : '' ?>" href="<?= e(url($putanja)) ?>"
                <?= $aktivna ? 'aria-current="page"' : '' ?>><?= e($naziv) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
