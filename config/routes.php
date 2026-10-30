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
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\HomeController;
use App\Controllers\NewsAdminController;
use App\Controllers\NewsController;
use App\Controllers\ProfileController;
use App\Controllers\UserAdminController;

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

// CMS: vijesti (urednik samo svoje – provjera u NewsAdminController, admin sve)
$router->get('/admin/vijesti',              [NewsAdminController::class, 'index'],   $urednistvo);
$router->get('/admin/vijesti/nova',         [NewsAdminController::class, 'create'],  $urednistvo);
$router->post('/admin/vijesti',             [NewsAdminController::class, 'store'],   $urednistvo);
$router->get('/admin/vijesti/{id}/uredi',   [NewsAdminController::class, 'edit'],    $urednistvo);
$router->post('/admin/vijesti/{id}',        [NewsAdminController::class, 'update'],  $urednistvo);
$router->post('/admin/vijesti/{id}/obrisi', [NewsAdminController::class, 'destroy'], $urednistvo);

// Administracija (samo admin)
$router->get('/admin', [AdminController::class, 'index'], $samoAdmin);

$router->get('/admin/kategorije',              [CategoryController::class, 'index'],   $samoAdmin);
$router->post('/admin/kategorije',             [CategoryController::class, 'store'],   $samoAdmin);
$router->post('/admin/kategorije/{id}',        [CategoryController::class, 'update'],  $samoAdmin);
$router->post('/admin/kategorije/{id}/obrisi', [CategoryController::class, 'destroy'], $samoAdmin);

$router->get('/admin/korisnici',       [UserAdminController::class, 'index'],  $samoAdmin);
$router->post('/admin/korisnici/{id}', [UserAdminController::class, 'update'], $samoAdmin);
