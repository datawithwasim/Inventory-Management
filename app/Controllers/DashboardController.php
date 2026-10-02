<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use App\Models\User;
use Core\Auth;
use Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('app/dashboard', [
            'title' => 'Dashboard',
            'userCount' => User::count(),
            'roleCount' => Role::count(),
            'tenantProblem' => null,
            'u' => Auth::user(),
        ]);
    }
}
