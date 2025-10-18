<?php

namespace Jinom\JinomTemplate\Services;

use Illuminate\Support\Arr;
use Nwidart\Modules\Module;

class PermissionManager
{
    /**
     * @var Module
     */
    private $module;

    public function __construct()
    {
        $this->module = app('modules');
    }

    public static function make()
    {
        return new static;
    }

    /**
     * Get the permissions from all the enabled modules
     *
     * @return array
     */
    public function all()
    {
        $permissions = [];
        foreach ($this->module->allEnabled() as $enabledModule) {
            $configuration = config(strtolower($enabledModule->getName()).'.permissions');
            if ($configuration) {
                $permissions[$enabledModule->getName()] = $configuration;
            }
        }

        return $permissions;
    }

    public static function buildPermissionRequest($default = 1)
    {
        $permissionsManager = app(PermissionManager::class);

        return static::buildPermissionList($permissionsManager->all(), default: $default);
    }

    public static function buildPermissionList(array $permissionsConfig, $model = null, $default = null): array
    {
        $list = [];

        if ($permissionsConfig === null) {
            return $list;
        }

        if ($model === null) {
            $model = new static;
        }

        foreach ($permissionsConfig as $mainKey => $subPermissions) {
            foreach ($subPermissions as $key => $permissionGroup) {
                foreach ($permissionGroup as $lastKey => $description) {
                    $list[strtolower("$key").'.'.$lastKey] = static::current_permission_value_for_roles($model, strtolower("$key"), $lastKey, $default);
                }
            }
        }

        return $list;
    }

    public static function current_permission_value_for_roles($model, $permissionTitle, $permissionAction, $default = null)
    {

        if ($model === null) {
            return -1;
        }

        if ($default !== null) {
            return $default;
        }

        $permissions = $model->permissions->mapWithKeys(function ($item, $key) {
            return [$item->name => true];
        })->toArray();

        $value = Arr::get($permissions, "$permissionTitle.$permissionAction");
        if ($value === true) {
            return 1;
        }

        return -1;
    }

    /**
     * Return a correctly type casted permissions array
     *
     * @return array
     */
    public function clean($permissions)
    {
        if (! $permissions) {
            return [];
        }
        $cleanedPermissions = [];
        foreach ($permissions as $permissionName => $checkedPermission) {
            if ($this->getState($checkedPermission) !== null) {
                $cleanedPermissions[$permissionName] = $this->getState($checkedPermission);
            }
        }

        return $cleanedPermissions;
    }

    /**
     * @return bool
     */
    protected function getState($checkedPermission)
    {
        if ($checkedPermission === '1' || $checkedPermission === 1) {
            return true;
        }

        if ($checkedPermission === '-1' || $checkedPermission === -1) {
            return false;
        }

        return null;
    }

    /**
     * Are all of the permissions passed of false value?
     *
     * @param  array  $permissions  Permissions array
     * @return bool
     */
    public function permissionsAreAllFalse(array $permissions)
    {
        $uniquePermissions = array_unique($permissions);

        if (count($uniquePermissions) > 1) {
            return false;
        }

        $uniquePermission = reset($uniquePermissions);

        $cleanedPermission = $this->getState($uniquePermission);

        return $cleanedPermission === false;
    }
}
