<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {email} {password} {--name=Platform Admin}';

    protected $description = 'Create (or reset) a platform super admin who can manage all tenants and role permissions';

    public function handle(): int
    {
        $user = User::withTrashed()->firstOrNew(['email' => $this->argument('email')]);
        $user->forceFill(['name' => $user->name ?: $this->option('name'), 'password' => $this->argument('password'), 'is_super_admin' => true, 'tenant_id' => null, 'is_owner' => false, 'deleted_at' => null])->save();
        $this->info("Super admin ready: {$user->email}");

        return 0;
    }
}
