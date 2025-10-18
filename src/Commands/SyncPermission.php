<?php

namespace Jinom\JinomTemplate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Jinom\JinomTemplate\Services\PermissionManager;

class SyncPermission extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'jinom:sync-permission';

    /**
     * The console command description.
     */
    protected $description = 'Sync Permission';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle() {
        $permissionManager = new PermissionManager;

        $modules = $permissionManager->all();

        foreach ($modules as $module => $permissions) {
            foreach ($permissions as $permission => $permissions2) {
                foreach ($permissions2 as $permission2 => $translateable) {
                    $d = config('jinom-template.permission_model')::findOrCreate(strtolower("{$permission}.{$permission2}"), 'web');
                    $d->save();
                }
            }
        }
    }
}
