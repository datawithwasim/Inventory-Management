<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\DB;
use Core\Migrator;
use Core\Seeder;
use Core\Validator;

/** One-time web installer for hosts without a terminal (e.g. cPanel). Locks itself when done. */
final class InstallController extends Controller
{
    public static function installed(): bool
    {
        return is_file(ROOT . '/storage/installed.lock');
    }

    private function guard(): void
    {
        if (self::installed()) $this->notFound();
    }

    private function requirements(): array
    {
        return [
            'PHP 8.1 or newer (you have ' . PHP_VERSION . ')' => PHP_VERSION_ID >= 80100,
            'PDO MySQL extension' => extension_loaded('pdo_mysql'),
            'mbstring extension' => extension_loaded('mbstring'),
            'storage/ folder is writable' => is_writable(ROOT . '/storage'),
            'Can create the .env file' => is_file(ROOT . '/.env') ? is_writable(ROOT . '/.env') : is_writable(ROOT),
        ];
    }

    public function show(): void
    {
        $this->guard();
        $req = $this->requirements();
        $this->view('install/form', [
            'title' => 'Install', 'wide' => true,
            'requirements' => $req, 'ready' => !in_array(false, $req, true),
        ], 'layouts/auth');
    }

    public function run(): void
    {
        $this->guard();
        if (in_array(false, $this->requirements(), true)) {
            flash('danger', 'Fix the requirements below first.');
            redirect('install');
        }
        $d = $this->input();
        $errors = Validator::check($d, [
            'app_name' => 'required|max:80', 'db_host' => 'required|max:190', 'db_port' => 'required|int',
            'db_name' => 'required|max:64', 'db_user' => 'required|max:64',
            'admin_name' => 'required|max:120', 'admin_email' => 'required|email|max:190', 'admin_password' => 'required|min:8',
        ]);
        foreach (['app_name', 'db_host', 'db_name', 'db_user', 'db_pass'] as $k) {
            if (preg_match('/["\r\n]/', (string)($d[$k] ?? ''))) $errors[$k] = "Field $k contains characters that are not allowed.";
        }
        if ($errors) {
            foreach ($errors as $m) flash('danger', $m);
            with_old($d);
            redirect('install');
        }

        $db = ['host' => trim($d['db_host']), 'port' => trim($d['db_port']), 'name' => trim($d['db_name']),
            'user' => trim($d['db_user']), 'pass' => (string)($d['db_pass'] ?? '')];
        $GLOBALS['config']['db'] = $db;
        try {
            DB::pdo();
        } catch (\Throwable $e) {
            flash('danger', 'Could not connect to the database. Check host, name, user and password. (' . $e->getMessage() . ')');
            with_old($d);
            redirect('install');
        }

        try {
            Migrator::run();
            Seeder::run(trim($d['admin_name']), strtolower(trim($d['admin_email'])), $d['admin_password']);
        } catch (\Throwable $e) {
            flash('danger', 'Setting up the database failed: ' . $e->getMessage());
            with_old($d);
            redirect('install');
        }

        $env = implode("\n", [
            'APP_NAME="' . trim($d['app_name']) . '"',
            'APP_ENV=production',
            'APP_DEBUG=false',
            'DB_HOST="' . $db['host'] . '"',
            'DB_PORT=' . $db['port'],
            'DB_NAME="' . $db['name'] . '"',
            'DB_USER="' . $db['user'] . '"',
            'DB_PASS="' . $db['pass'] . '"',
            'MAIL_DRIVER=mail',
            'MAIL_FROM="noreply@' . preg_replace('/^www\./', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]) . '"',
            '',
        ]);
        if (file_put_contents(ROOT . '/.env', $env) === false) {
            flash('danger', 'Could not write the .env file. Check folder permissions.');
            redirect('install');
        }
        @chmod(ROOT . '/.env', 0640);
        file_put_contents(ROOT . '/storage/installed.lock', date('c'));

        flash('success', 'Installation complete. Sign in as Super Admin to create your first company.');
        redirect('admin/login');
    }
}
