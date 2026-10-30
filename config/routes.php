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
use App\Controllers\HomeController;
use App\Controllers\NewsController;
use App\Controllers\ProfileController;

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

// Administracija
$router->get('/admin', [AdminController::class, 'index'], ['uloge' => ['admin']]);
