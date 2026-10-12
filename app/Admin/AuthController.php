<?php
declare(strict_types=1);

namespace App\Admin;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

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

    /* ---------- forgotten password ---------- */

    // Reset requests live in the same table as company users', with the address prefixed "admin:" so a token can never cross over.
    private const PREFIX = 'admin:';

    public function showForgot(): void
    {
        $this->view('auth/admin_forgot', ['title' => 'Super Admin'], 'layouts/auth');
    }

    public function forgot(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $admin = DB::one('SELECT id, email FROM super_admins WHERE email = ? AND is_active = 1', [$email]);
        $key = self::PREFIX . $email;
        $recent = DB::val('SELECT 1 FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)', [$key]);
        if ($admin && !$recent) {
            DB::run('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL', [$key]);
            $token = bin2hex(random_bytes(32));
            DB::insert('password_resets', ['email' => $key, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
            $link = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : '') . url('admin/reset-password/' . $token);
            send_mail($email, 'Reset your Super Admin password', "Use this link within 1 hour to choose a new Super Admin password:\n$link\n\nIf you did not ask for this, ignore this mail.");
            Audit::log('admin_password_reset_requested', 'super_admin', (int)$admin['id'], null, null, 'admin', (int)$admin['id']);
        }
        // Same answer whether or not the address exists.
        flash('success', 'If that email belongs to a Super Admin, a reset link has been sent.');
        redirect('admin/forgot-password');
    }

    private function validReset(string $token): ?array
    {
        return DB::one('SELECT * FROM password_resets WHERE token_hash = ? AND email LIKE ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token), self::PREFIX . '%']);
    }

    public function showReset(string $token): void
    {
        if (!$this->validReset($token)) {
            flash('danger', 'This reset link is invalid or has expired.');
            redirect('admin/forgot-password');
        }
        $this->view('auth/admin_reset', ['token' => $token, 'title' => 'Super Admin'], 'layouts/auth');
    }

    public function reset(string $token): void
    {
        $row = $this->validReset($token);
        if (!$row) {
            flash('danger', 'This reset link is invalid or has expired.');
            redirect('admin/forgot-password');
        }
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 8) {
            flash('danger', 'Password must be at least 8 characters.');
            redirect('admin/reset-password/' . $token);
        }
        if ($pw !== ($_POST['password_confirm'] ?? '')) {
            flash('danger', 'Passwords do not match.');
            redirect('admin/reset-password/' . $token);
        }
        $email = substr((string)$row['email'], strlen(self::PREFIX));
        DB::transaction(function () use ($row, $pw, $email) {
            DB::run('UPDATE super_admins SET password_hash = ? WHERE email = ?', [password_hash($pw, PASSWORD_DEFAULT), $email]);
            DB::run('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL', [$row['email']]);
            DB::run('DELETE FROM login_attempts WHERE email = ?', [$row['email']]);
        });
        $a = DB::one('SELECT id FROM super_admins WHERE email = ?', [$email]);
        if ($a) Audit::log('admin_password_reset', 'super_admin', (int)$a['id'], null, null, 'admin', (int)$a['id']);
        flash('success', 'Password updated. You can sign in now.');
        redirect('admin/login');
    }
}
