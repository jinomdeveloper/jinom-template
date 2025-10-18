<?php

namespace Jinom\JinomTemplate\Commands;

use Archetype\Facades\LaravelFile;
use Binafy\LaravelStub\Facades\LaravelStub;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Jinom\JinomTemplate\Services\FileManipulator;
use Nette\PhpGenerator\ClassManipulator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Console\Input\InputArgument;

class CreateResourceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'jinom:make-resource {resource} {module} {--table}';

    /**
     * The console command description.
     */
    protected $description = 'Create Resource Template';

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
    public function handle()
    {
        $resource = str($this->argument('resource'))
            ->pascal()
            ->value();

        $module = $this->argument('module');

        if (! $moduleClass = Module::find($module)) {
            throw new Exception('Module not found', 1);
        }

        /**
         * Namespace Variable
         */
        $moduleNamespace = "Modules\\{$moduleClass->getName()}";
        $apiControllerNamespace = "{$moduleNamespace}\\Http\\Controllers\\Api";
        $serviceNamespace = "{$moduleNamespace}\\Services";
        $eventNamespace = "{$moduleNamespace}\\Events\\{$resource}";
        $modelNamespace = "{$moduleNamespace}\\Models\\{$resource}";

        $eventServiceProvider = "{$moduleNamespace}\\Providers\\EventServiceProvider";

        /**
         * Path Variable
         */
        $modulePath = base_path("Modules/$module");
        $apiControllerPath = "{$modulePath}/app/Http/Controllers/Api";
        $servicePath = "{$modulePath}/app/Services";
        $modelsPath = "{$modulePath}/app/Models";
        $eventPath = "{$modulePath}/app/Events/{$resource}";
        $eventServiceProviderPath = "{$modulePath}/app/Providers/EventServiceProvider.php";
        $langPath = "{$modulePath}/lang/en";
        $permissionsPath = "{$modulePath}/config/permissions.php";
        $routesPath = "{$modulePath}/routes";
        $testsPath = "{$modulePath}/tests";

        $this->makeSureFolderExists($apiControllerPath);
        $this->makeSureFolderExists($servicePath);
        $this->makeSureFolderExists($modelsPath);
        $this->makeSureFolderExists($eventPath);
        $this->makeSureFolderExists($langPath);

        $controllerName = "{$resource}Controller";

        /**
         * Creating a Model
         */
        if ($this->confirm('Do you want to generate Model?', true)) {
            $this->createModel($module, $resource, $modelNamespace, $modelsPath);
        }

        /**
         * Creating a Factory
         */
        if ($this->confirm('Do you want to generate Factory?', true)) {
            Artisan::call('module:make-factory', [
                'name' => $resource,
                'module' => $module,
            ]);
        }

        /**
         * Creating a Transformer
         */
        if ($this->confirm('Do you want to generate Transformer?', true)) {
            Artisan::call('module:make-resource', [
                'name' => "{$resource}Resource",
                'module' => $module,
            ]);
        }

        /**
         * Creating a Request
         */
        if ($this->confirm('Do you want to generate Request?', true)) {
            $this->createRequest($module, $resource, $modelNamespace);
        }
        /**
         *  Create Controller by stub
         */
        if ($this->confirm('Do you want to generate Controller?', true)) {
            LaravelStub::from($this->coreModulePath('stubs/controllers/api.stub'))
                ->replaces([
                    'model' => $resource,
                    'model_snake_case' => str($resource)->snake()->value(),
                    'namespace' => $apiControllerNamespace,
                    'module_namespace' => $moduleNamespace,
                ])
                ->name($controllerName)
                ->ext('php')
                ->to($apiControllerPath)
                ->generate();
        }

        /**
         * Create Event by stub
         */
        if ($this->confirm('Do you want to generate Event?', true)) {

            $events = [
                'EventIsCreating',
                'EventIsUpdating',
                'EventWasCreated',
                'EventWasUpdated',
            ];

            /**
             * Register Event to EventServiceProvider
             */
            $class = ClassType::from($eventServiceProvider);
            $manipulator = new ClassManipulator($class);

            $listenProperty = $manipulator->inheritProperty('listen', true);

            $listenPropertyValue = $listenProperty->getValue();

            foreach ($events as $key => $event) {
                LaravelStub::from($this->coreModulePath("stubs/events/{$event}.stub"))
                    ->replaces([
                        'class' => $eventName = str($event)->replace('Event', $resource)->value(),
                        'model' => $resource,
                        'model_snake_case' => str($resource)->snake()->value(),
                        'namespace' => $eventNamespace,
                        'model_namespace' => $modelNamespace,
                    ])
                    ->name($eventName)
                    ->ext('php')
                    ->to($eventPath)
                    ->generate();

                $listenPropertyValue[$eventNamespace.'\\'.$eventName = str($event)->replace('Event', $resource)->value()] = [];
            }

            $file = new PhpFile;
            $listenProperty->setValue($listenPropertyValue);
            $namespace = $file->addNamespace($class->getNamespace()->getName());
            $namespace->add($class);
            file_put_contents($eventServiceProviderPath, (string) $file);
        }
        /**
         * Create Eloquent Service
         */
        if ($this->confirm('Do you want to generate Eloquent Service?', true)) {
            LaravelStub::from($this->coreModulePath('stubs/services/EloquentService.stub'))
                ->replaces([
                    'model' => $resource,
                    'model_snake_case' => str($resource)->snake()->value(),
                    'namespace' => $serviceNamespace,
                    'module' => $module,
                    'class' => $class = "Eloquent{$resource}Service",
                ])
                ->name($class)
                ->ext('php')
                ->to($servicePath)
                ->generate();
        }

        /**
         * Create a Translation
         */
        if ($this->confirm('Do you want to generate Translation?', true)) {
            LaravelStub::from($this->coreModulePath('stubs/resources/lang.stub'))
                ->replaces([
                    'model' => $resource,
                    'model_lowercase' => str($resource)->snake()->explode('_')->join(' '),
                ])
                ->name(str($resource)->snake()->value())
                ->ext('php')
                ->to($langPath)
                ->generate();
        }
        /**
         * Generate a permission
         */
        if ($this->confirm('Do you want to generate Permission?', true)) {
            $this->generatePermissions($module, $resource, $permissionsPath);
        }
        /**
         * Register to api routes
         */
        if ($this->confirm('Do you want to register a api route?', true)) {
            $this->registerToApiRoutes($module, $resource, $routesPath, $apiControllerNamespace.'\\'.$controllerName);
        }
        /**
         * Create TestCase
         */
        if ($this->confirm('Do you want to generate Api Testcase?', true)) {

            $this->createApiTestCase($module, $resource, $testsPath);
        }

        $this->info("Resource for {$resource} has been generated!");
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['resource', null, InputArgument::REQUIRED, 'Generated Resource.', null],
            ['module', null, InputArgument::REQUIRED, 'Existing Module', null],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [];
    }

    public function coreModulePath($path)
    {
        $corePath = __DIR__.'/../../';
        $path = str($path)->startsWith('/') ? $path : "/$path";

        return "{$corePath}{$path}";
    }

    public function makeSureFolderExists($path)
    {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    public function makeSureFileExists($path)
    {
        // Cek dulu apakah filenya sudah ada, jika iya, tidak perlu melakukan apa-apa.
        if (file_exists($path)) {
            return;
        }

        // Ambil path direktorinya saja
        $directory = dirname($path);

        // Cek apakah direktorinya ada, jika tidak, buat secara rekursif.
        // Parameter ketiga `true` sangat penting agar bisa membuat folder bersarang.
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        // Setelah direktori dipastikan ada, buat file kosongnya.
        touch($path);
    }

    public function generatePermissions($module, $resource, $permissionsPath)
    {
        $this->makeSureFileExists($permissionsPath);

        file_put_contents($permissionsPath, "<?php\n\nreturn [];");
        // Load existing permissions
        $permissions = include $permissionsPath;

        $model = str($resource)->snake()->value();
        $module = str($module)->lower()->value();

        // Add new permission array
        $newPermissions = [
            $model => [
                'index' => "{$module}::{$model}.list resource",
                'create' => "{$module}::{$model}.create resource",
                'edit' => "{$module}::{$model}.edit resource",
                'destroy' => "{$module}::{$model}.destroy resource",
            ],
        ];

        // Merge with existing permissions
        $permissions = array_merge($permissions, $newPermissions);

        // Generate new file content
        $content = "<?php\n\nreturn [\n";
        foreach ($permissions as $key => $permissionArray) {
            $content .= "    '$module.$key' => [\n";
            foreach ($permissionArray as $permissionKey => $permissionValue) {
                $content .= "        '$permissionKey' => '$permissionValue',\n";
            }
            $content .= "    ],\n";
        }
        $content .= "];\n";

        // Write back to file
        file_put_contents($permissionsPath, $content);

        foreach ($newPermissions as $key => $permissions) {
            foreach ($permissions as $key => $permission2) {
                $p = config('jinom-template.permission_model')::findOrCreate(strtolower("{$module}.{$model}.{$key}"), 'web');
                $p->save();
            }
        }
    }

    public function registerToApiRoutes($module, $resource, $routesPath, $controllerClass)
    {
        $resourceLowercase = str($resource)->snake()->value();
        $module = str($module)->snake()->value();

        $text = "\nRoute::prefix('v1/{$resourceLowercase}s')";
        $text .= "\n\t->middleware('auth:sanctum')";
        $text .= "\n\t->name('{$module}.{$resourceLowercase}.')";
        $text .= "\n\t->group(function () {";
        $text .= "\n\t\tRoute::get('/', [{$controllerClass}::class, 'index'])\n\t\t\t->middleware('can:{$module}.{$resourceLowercase}.index')\n\t\t\t->name('index');";

        $text .= "\n\t\tRoute::get('/{{$resourceLowercase}}', [{$controllerClass}::class, 'show'])\n\t\t\t->middleware('can:{$module}.{$resourceLowercase}.index')\n\t\t\t->name('show');";

        $text .= "\n\t\tRoute::delete('/{{$resourceLowercase}}', [{$controllerClass}::class, 'destroy'])\n\t\t\t->middleware('can:{$module}.{$resourceLowercase}.destroy')\n\t\t\t->name('destroy');";

        $text .= "\n\t\tRoute::post('/', [{$controllerClass}::class, 'store'])\n\t\t\t->middleware('can:{$module}.{$resourceLowercase}.create')\n\t\t\t->name('store');";

        $text .= "\n\t\tRoute::put('/{{$resourceLowercase}}', [{$controllerClass}::class, 'update'])\n\t\t\t->middleware('can:{$module}.{$resourceLowercase}.edit')\n\t\t\t->name('update');";

        $text .= "\n\t});";

        // $text = "\nRoute::apiResource('{$resourceLowercase}s', {$controllerClass}::class)->names('{$module}.$resourceLowercase');";

        file_put_contents("{$routesPath}/api.php", $text, FILE_APPEND);

        $webRoute = "\nRoute::get('/{$module}s/{$resourceLowercase}s', function () {\n\treturn view('app');\n})->name('$module.{$resourceLowercase}.index');";

        file_put_contents("{$routesPath}/web.php", $webRoute, FILE_APPEND);
    }

    public function createApiTestCase($module, $resource, $testsPath)
    {
        LaravelStub::from($this->coreModulePath('stubs/tests/ApiTest.stub'))
            ->replaces([
                'module' => $module,
                'model' => $resource,
                'model_snake_case' => str($resource)->snake()->value(),
            ])
            ->name("Api{$resource}Test")
            ->ext('php')
            ->to($testsPath.'/Feature')
            ->generate();
    }

    public function createModel($module, $resource, $modelNamespace, $modelsPath)
    {
        $newModelPath = $modelsPath.'/'.$resource.'.php';

        LaravelStub::from($this->coreModulePath('stubs/model/model.stub'))
            ->replaces([
                'model' => $resource,
                'module' => $module,
            ])
            ->name($resource)
            ->ext('php')
            ->to($modelsPath)
            ->generate();

        if ($this->option('table')) {
            $columns = Schema::getColumnListing((new $modelNamespace)->getTable());

            $columns = collect($columns)->filter(function ($col) {
                return ! in_array($col, [
                    'created_at',
                    'updated_at',
                    'id',
                ]);
            })->toArray();

            $laravelfile = LaravelFile::load($newModelPath);

            foreach ($columns as $key => $col) {
                $laravelfile->add()->fillable($col);
            }

            $laravelfile->save();
        }
    }

    public function createRequest($module, $resource, $modelNamespace)
    {
        $requestsPath = module_path($module, "app/Http/Requests/{$resource}");

        $this->makeSureFolderExists($requestsPath);
        $requests = [
            "Create{$resource}Request",
            "Update{$resource}Request",
        ];

        foreach ($requests as $key => $request) {
            Artisan::call('module:make-request', [
                'name' => "{$resource}\\$request",
                'module' => $module,
            ]);

            if ($this->option('table')) {
                $file_manipulator = new FileManipulator($requestsPath.'/'.$request.'.php');

                $columns = Schema::getColumns(app($modelNamespace)->getTable());

                $validationRules = $file_manipulator->generateValidationRulesByColumnsScheme($columns);

                $file_manipulator->addValidationRules($validationRules);
                $file_manipulator->save();
            }
        }
    }
}
