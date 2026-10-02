<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Core\Audit;
use Core\Auth;
use Core\Controller;

final class ProfileController extends Controller
{
    public function show(): void
    {
        $this->view('app/profile/show', ['title' => 'My profile', 'me' => Auth::user()]);
    }

    public function update(): void
    {
        $me = Auth::user();
        $d = $this->validate(['name' => 'required|max:120', 'email' => 'required|email|max:190'], 'profile');
        $email = strtolower(trim($d['email']));
        if (User::emailTaken($email, (int)$me['id'])) {
            flash('danger', 'That email is already in use.');
            redirect('profile');
        }
        User::update((int)$me['id'], ['name' => trim($d['name']), 'email' => $email]);
        flash('success', 'Profile updated.');
        redirect('profile');
    }

    public function password(): void
    {
        $me = Auth::user();
        $d = $this->input();
        if (!password_verify((string)($d['current_password'] ?? ''), $me['password_hash'])) {
            flash('danger', 'Current password is incorrect.');
            redirect('profile');
        }
        $pw = (string)($d['password'] ?? '');
        if (strlen($pw) < 8) {
            flash('danger', 'New password must be at least 8 characters.');
            redirect('profile');
        }
        if ($pw !== ($d['password_confirm'] ?? '')) {
            flash('danger', 'New passwords do not match.');
            redirect('profile');
        }
        User::update((int)$me['id'], ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
        Audit::log('password_change', 'user', (int)$me['id']);
        flash('success', 'Password changed.');
        redirect('profile');
    }
}
