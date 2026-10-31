<?php

declare(strict_types=1);

/*
 * Rute aplikacije. Treći argument određuje tko smije pristupiti.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\AdminController;
use App\Controllers\Api\KategorijeApiController;
use App\Controllers\Api\PrijaveApiController;
use App\Controllers\Api\PrometApiController;
use App\Controllers\Api\RadoviApiController;
use App\Controllers\Api\SesijaApiController;
use App\Controllers\Api\VijestiApiController;
use App\Controllers\AdminPrijavaController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\HomeController;
use App\Controllers\KartaController;
use App\Controllers\NewsAdminController;
use App\Controllers\NewsController;
use App\Controllers\PrijavaController;
use App\Controllers\ProfileController;
use App\Controllers\PrometAdminController;
use App\Controllers\RadAdminController;
use App\Controllers\UserAdminController;
use App\Controllers\ZadatakController;

$samoAdmin = ['uloge' => ['admin']];
$urednistvo = ['uloge' => ['urednik', 'admin']];

// Javno
$router->get('/',             [HomeController::class, 'index']);
$router->get('/o-projektu',   [HomeController::class, 'about']);
$router->get('/karta',        [KartaController::class, 'index']);
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

// Prijave problema
$router->get('/prijave/nova',                  [PrijavaController::class, 'create'], ['auth' => true]);
$router->post('/prijave',                      [PrijavaController::class, 'store'],  ['auth' => true]);
$router->get('/prijave/moje',                  [PrijavaController::class, 'index'],  ['auth' => true]);
$router->get('/prijave/{id}',                  [PrijavaController::class, 'show'],   ['auth' => true]);
$router->get('/prijave/{id}/slike/{slikaId}',  [PrijavaController::class, 'image'],  ['auth' => true]);

// Radnik ({id} = id prijave)
$router->get('/radnik/zadaci',               [ZadatakController::class, 'index'],  ['uloge' => ['radnik']]);
$router->post('/radnik/zadaci/{id}/zapocni', [ZadatakController::class, 'start'],  ['uloge' => ['radnik']]);
$router->post('/radnik/zadaci/{id}/zavrsi',  [ZadatakController::class, 'submit'], ['uloge' => ['radnik']]);

// CMS: vijesti
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

$router->get('/admin/radovi',              [RadAdminController::class, 'index'],   $samoAdmin);
$router->get('/admin/radovi/novi',         [RadAdminController::class, 'create'],  $samoAdmin);
$router->post('/admin/radovi',             [RadAdminController::class, 'store'],   $samoAdmin);
$router->get('/admin/radovi/{id}/uredi',   [RadAdminController::class, 'edit'],    $samoAdmin);
$router->post('/admin/radovi/{id}',        [RadAdminController::class, 'update'],  $samoAdmin);
$router->post('/admin/radovi/{id}/obrisi', [RadAdminController::class, 'destroy'], $samoAdmin);

$router->get('/admin/promet',                       [PrometAdminController::class, 'index'],  $samoAdmin);
$router->post('/admin/promet/sakrij',               [PrometAdminController::class, 'hide'],   $samoAdmin);
$router->post('/admin/promet/skriveni/{id}/vrati',  [PrometAdminController::class, 'unhide'], $samoAdmin);

$router->get('/admin/kategorije',              [CategoryController::class, 'index'],   $samoAdmin);
$router->post('/admin/kategorije',             [CategoryController::class, 'store'],   $samoAdmin);
$router->post('/admin/kategorije/{id}',        [CategoryController::class, 'update'],  $samoAdmin);
$router->post('/admin/kategorije/{id}/obrisi', [CategoryController::class, 'destroy'], $samoAdmin);

$router->get('/admin/korisnici',       [UserAdminController::class, 'index'],  $samoAdmin);
$router->post('/admin/korisnici/{id}', [UserAdminController::class, 'update'], $samoAdmin);

// REST API (JSON)
$api = '/api/v1';
$router->get("{$api}/sesija",     [SesijaApiController::class, 'show']);
$router->post("{$api}/sesija",    [SesijaApiController::class, 'store']);
$router->delete("{$api}/sesija",  [SesijaApiController::class, 'destroy'], ['auth' => true]);

$router->get("{$api}/vijesti",         [VijestiApiController::class, 'index']);
$router->post("{$api}/vijesti",        [VijestiApiController::class, 'store'],   $urednistvo);
$router->get("{$api}/vijesti/{id}",    [VijestiApiController::class, 'show']);
$router->patch("{$api}/vijesti/{id}",  [VijestiApiController::class, 'update'],  $urednistvo);
$router->delete("{$api}/vijesti/{id}", [VijestiApiController::class, 'destroy'], $urednistvo);

$router->get("{$api}/kategorije", [KategorijeApiController::class, 'index']);

$router->get("{$api}/prijave",        [PrijaveApiController::class, 'index']);
$router->post("{$api}/prijave",       [PrijaveApiController::class, 'store'],  ['auth' => true]);
$router->get("{$api}/prijave/{id}",   [PrijaveApiController::class, 'show'],   ['auth' => true]);
$router->patch("{$api}/prijave/{id}", [PrijaveApiController::class, 'update'], ['auth' => true]);

$router->get("{$api}/radovi", [RadoviApiController::class, 'index']);
$router->get("{$api}/promet", [PrometApiController::class, 'show']);
