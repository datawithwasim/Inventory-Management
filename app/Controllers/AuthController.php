<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class AuthController extends Controller
{
    public function home(): void
    {
        redirect(Auth::check() ? 'dashboard' : 'login');
    }

    public function showLogin(): void
    {
        $this->view('auth/login', [], 'layouts/auth');
    }

    public function login(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $error = Auth::attempt($email, (string)($_POST['password'] ?? ''));
        if ($error) {
            flash('danger', $error);
            with_old(['email' => $email]);
            redirect('login');
        }
        redirect('dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('login');
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot', [], 'layouts/auth');
    }

    public function forgot(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $user = DB::one('SELECT id, email FROM users WHERE email = ? AND is_active = 1', [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            DB::insert('password_resets', [
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            ]);
            $link = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : '')
                . url('reset-password/' . $token);
            send_mail($email, 'Reset your password', "Use this link within 1 hour to reset your password:\n$link");
        }
        // Same answer whether or not the email exists.
        flash('success', 'If that email is registered, a reset link has been sent.');
        redirect('forgot-password');
    }

    private function validReset(string $token): ?array
    {
        return DB::one(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token)]);
    }

    public function showReset(string $token): void
    {
        if (!$this->validReset($token)) {
            flash('danger', 'This reset link is invalid or has expired.');
            redirect('forgot-password');
        }
        $this->view('auth/reset', ['token' => $token], 'layouts/auth');
    }

    public function reset(string $token): void
    {
        $row = $this->validReset($token);
        if (!$row) {
            flash('danger', 'This reset link is invalid or has expired.');
            redirect('forgot-password');
        }
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 8) {
            flash('danger', 'Password must be at least 8 characters.');
            redirect('reset-password/' . $token);
        }
        if ($pw !== ($_POST['password_confirm'] ?? '')) {
            flash('danger', 'Passwords do not match.');
            redirect('reset-password/' . $token);
        }
        DB::transaction(function () use ($row, $pw) {
            DB::run('UPDATE users SET password_hash = ? WHERE email = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['email']]);
            DB::run('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$row['id']]);
            DB::run('DELETE FROM login_attempts WHERE email = ?', [$row['email']]);
        });
        $u = DB::one('SELECT id, tenant_id FROM users WHERE email = ?', [$row['email']]);
        Audit::log('password_reset', 'user', (int)$u['id'], null, (int)$u['tenant_id'], 'user', (int)$u['id']);
        flash('success', 'Password updated. You can sign in now.');
        redirect('login');
    }
}
