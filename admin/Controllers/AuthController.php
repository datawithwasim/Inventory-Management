<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Auth;
use Core\Controller;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/admin_login', ['title' => 'Super Admin'], 'layouts/auth');
    }

    public function login(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $error = Auth::attemptAdmin($email, (string)($_POST['password'] ?? ''));
        if ($error) {
            flash('danger', $error);
            with_old(['email' => $email]);
            redirect('admin/login');
        }
        redirect('admin');
    }

    public function logout(): void
    {
        Auth::logoutAdmin();
        redirect('admin/login');
    }
}
