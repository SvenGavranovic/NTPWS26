<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

/** Registracija, prijava i odjava. */
final class AuthController
{
    public function loginForm(): void
    {
        View::render('auth/login', ['title' => 'Prijava']);
    }

    public function login(): void
    {
        $email = post('email');
        $result = (new AuthService())->attemptLogin($email, post('lozinka'), $_SERVER['REMOTE_ADDR'] ?? '');

        if (!$result['ok']) {
            // 401 neispravni podaci, 429 previše pokušaja
            if (!empty($result['blokirano'])) {
                header('Retry-After: ' . (AuthService::PROZOR_MINUTA * 60));
            }
            View::render('auth/login', [
                'title' => 'Prijava',
                'greska' => $result['greska'],
                'old' => ['email' => $email],
            ], empty($result['blokirano']) ? 401 : 429);
            return;
        }

        Auth::login($result['korisnik']);
        Session::flash('success', 'Uspješno ste se prijavili.');

        redirect(match (true) {
            Auth::hasRole('admin')   => '/admin',
            Auth::hasRole('urednik') => '/admin/vijesti',
            Auth::hasRole('radnik')  => '/radnik/zadaci',
            default                  => '/',
        });
    }

    public function registerForm(): void
    {
        View::render('auth/register', ['title' => 'Registracija']);
    }

    public function register(): void
    {
        // samo poznata polja
        $input = [
            'ime'             => post('ime'),
            'prezime'         => post('prezime'),
            'email'           => post('email'),
            'lozinka'         => post('lozinka'),
            'lozinka_potvrda' => post('lozinka_potvrda'),
        ];

        $errors = (new AuthService())->register($input);

        if ($errors !== []) {
            View::render('auth/register', [
                'title' => 'Registracija',
                'errors' => $errors,
                'old' => array_intersect_key($input, array_flip(['ime', 'prezime', 'email'])),
            ], 422);
            return;
        }

        Session::flash('success', 'Registracija je uspjela. Sada se možete prijaviti.');
        redirect('/racun/prijava');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('info', 'Odjavljeni ste.');
        redirect('/');
    }
}
