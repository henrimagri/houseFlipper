<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class ItbiAuthorizeCommand extends Command
{
    protected $signature = 'itbi:authorize {email : Conta autorizada a manter a base pública compartilhada} {--revoke}';

    protected $description = 'Concede ou revoga a permissão de importar a base ITBI compartilhada';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Conta não encontrada.');

            return self::FAILURE;
        }
        $permission = Permission::findOrCreate('itbi.import', 'web');
        if ($this->option('revoke')) {
            $user->revokePermissionTo($permission);
        } else {
            $user->givePermissionTo($permission);
        }
        $this->info('Permissão atualizada.');

        return self::SUCCESS;
    }
}
