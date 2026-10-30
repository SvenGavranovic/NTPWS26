<?php

declare(strict_types=1);

/*
 * Popis svih ruta aplikacije na jednom mjestu.
 * Treći argument određuje tko smije pristupiti (provjerava Router).
 *
 * Napomena: korisnički račun je pod /racun/..., jer bi "prijava" (login)
 * inače bila zamijenjena s "prijave" (prijave komunalnih problema).
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\AdminController;
use App\Controllers\AdminPrijavaController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\HomeController;
use App\Controllers\NewsAdminController;
use App\Controllers\NewsController;
use App\Controllers\PrijavaController;
use App\Controllers\ProfileController;
use App\Controllers\UserAdminController;
use App\Controllers\ZadatakController;

$samoAdmin = ['uloge' => ['admin']];
$urednistvo = ['uloge' => ['urednik', 'admin']];

// Javno
$router->get('/',             [HomeController::class, 'index']);
$router->get('/o-projektu',   [HomeController::class, 'about']);
$router->get('/vijesti',      [NewsController::class, 'index']);
$router->get('/vijesti/{id}', [NewsController::class, 'show']);

// Korisnički račun
$router->get('/racun/prijava',      [AuthController::class, 'loginForm'],    ['gost' => true]);
$router->post('/racun/prijava',     [AuthController::class, 'login'],        ['gost' => true]);
$router->get('/racun/registracija', [AuthController::class, 'registerForm'], ['gost' => true]);
$router->post('/racun/registracija',[AuthController::class, 'register'],     ['gost' => true]);
$router->post('/racun/odjava',      [AuthController::class, 'logout'],       ['auth' => true]);

$router->get('/racun/profil',   [ProfileController::class, 'show'],           ['auth' => true]);
$router->post('/racun/profil',  [ProfileController::class, 'update'],         ['auth' => true]);
$router->post('/racun/lozinka', [ProfileController::class, 'changePassword'], ['auth' => true]);

// Prijave problema (svaki prijavljeni korisnik; tko smije vidjeti koju
// prijavu provjerava PrijavaService::canView)
$router->get('/prijave/nova',                  [PrijavaController::class, 'create'], ['auth' => true]);
$router->post('/prijave',                      [PrijavaController::class, 'store'],  ['auth' => true]);
$router->get('/prijave/moje',                  [PrijavaController::class, 'index'],  ['auth' => true]);
$router->get('/prijave/{id}',                  [PrijavaController::class, 'show'],   ['auth' => true]);
$router->get('/prijave/{id}/slike/{slikaId}',  [PrijavaController::class, 'image'],  ['auth' => true]);

// Radnik: kanban i radnje nad VLASTITIM zadacima ({id} = id prijave)
$router->get('/radnik/zadaci',               [ZadatakController::class, 'index'],  ['uloge' => ['radnik']]);
$router->post('/radnik/zadaci/{id}/zapocni', [ZadatakController::class, 'start'],  ['uloge' => ['radnik']]);
$router->post('/radnik/zadaci/{id}/zavrsi',  [ZadatakController::class, 'submit'], ['uloge' => ['radnik']]);

// CMS: vijesti (urednik samo svoje – provjera u NewsAdminController, admin sve)
$router->get('/admin/vijesti',              [NewsAdminController::class, 'index'],   $urednistvo);
$router->get('/admin/vijesti/nova',         [NewsAdminController::class, 'create'],  $urednistvo);
$router->post('/admin/vijesti',             [NewsAdminController::class, 'store'],   $urednistvo);
$router->get('/admin/vijesti/{id}/uredi',   [NewsAdminController::class, 'edit'],    $urednistvo);
$router->post('/admin/vijesti/{id}',        [NewsAdminController::class, 'update'],  $urednistvo);
$router->post('/admin/vijesti/{id}/obrisi', [NewsAdminController::class, 'destroy'], $urednistvo);

// Administracija (samo admin)
$router->get('/admin', [AdminController::class, 'index'], $samoAdmin);

$router->get('/admin/prijave',                [AdminPrijavaController::class, 'index'],   $samoAdmin);
$router->post('/admin/prijave/{id}/dodjela',  [AdminPrijavaController::class, 'assign'],  $samoAdmin);
$router->post('/admin/prijave/{id}/hitnost',  [AdminPrijavaController::class, 'hitnost'], $samoAdmin);
$router->post('/admin/prijave/{id}/odobri',   [AdminPrijavaController::class, 'approve'], $samoAdmin);
$router->post('/admin/prijave/{id}/vrati',    [AdminPrijavaController::class, 'reject'],  $samoAdmin);

$router->get('/admin/kategorije',              [CategoryController::class, 'index'],   $samoAdmin);
$router->post('/admin/kategorije',             [CategoryController::class, 'store'],   $samoAdmin);
$router->post('/admin/kategorije/{id}',        [CategoryController::class, 'update'],  $samoAdmin);
$router->post('/admin/kategorije/{id}/obrisi', [CategoryController::class, 'destroy'], $samoAdmin);

$router->get('/admin/korisnici',       [UserAdminController::class, 'index'],  $samoAdmin);
$router->post('/admin/korisnici/{id}', [UserAdminController::class, 'update'], $samoAdmin);
