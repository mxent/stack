<?php

namespace Mxent\Stack\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class InitCommand extends Command
{
    protected $signature = 'mxent:init {--force}';

    protected $description = 'Convert this project into a modular setup';

    protected $package;

    protected $vendor;

    protected $vendorSnake;

    protected $vendorLower;

    protected $name;

    protected $nameSnake;

    protected $nameLower;

    protected $composerJson;

    protected $packageJson;

    public function handle()
    {

        if (! function_exists('passthru')) {
            $this->components->error('This command requires the passthru function to be enabled');

            return;
        }

        $force = $this->option('force');
        $this->composerJson = json_decode(file_get_contents(base_path('composer.json')), true);
        $this->packageJson = json_decode(file_get_contents(base_path('package.json')), true);

        if (! $force && ($this->composerJson['name'] != 'laravel/laravel' || $this->composerJson['type'] != 'project')) {
            $this->components->error('Please use this command in a fresh Laravel project');

            return;
        }

        $this->components->info('Make sure you do this in a fresh project.');
        $proceed = confirm('Do you want to proceed?');
        if (! $proceed) {
            $this->components->info('Aborted');

            return;
        }

        $this->package = text('What is the name of the package?');
        if (! $this->package) {
            $this->components->error('Package name is required');

            return;
        }

        $packageBits = explode('/', $this->package);
        if (count($packageBits) != 2) {
            $this->components->error('Invalid package name. Please use the format vendor/package-name');

            return;
        }
        $this->setPackageDetails($packageBits);

        $this->processRenames();
        $this->emptyDirectories();
        $this->deleteFiles();
        $this->updateComposerJson();
        $this->updatePackageJson();
        $this->installDependencies();

        $this->components->info('Module '.$this->package.' created');
    }

    protected function setPackageDetails($packageBits)
    {
        $this->package = Str::lower($this->package);
        $this->vendor = Str::studly($packageBits[0]);
        $this->vendorSnake = Str::snake($this->vendor, '-');
        $this->vendorLower = Str::lower($this->vendor);
        $this->name = Str::studly($packageBits[1]);
        $this->nameSnake = Str::snake($this->name, '-');
        $this->nameLower = Str::lower($this->name);
    }

    protected function processRenames()
    {
        $renames = [
            // Add renames if any
        ];

        foreach ($renames as $from => $to) {
            rename(base_path($from), base_path($to));
        }
    }

    protected function emptyDirectories()
    {
        $empty = [
            'app/Models',
            'app/Providers',
            'config',
            'database/migrations',
            'database/seeders',
            'database/factories',
            'routes',
            'resources/views',
            'resources/js',
            'resources/css',
        ];

        foreach ($empty as $path) {
            if (! is_dir(base_path($path))) {
                continue;
            }

            $files = scandir(base_path($path));
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $fullPath = $path.'/'.$file;

                if (is_file(base_path($fullPath))) {
                    unlink(base_path($fullPath));
                }
            }
        }
    }

    protected function deleteFiles()
    {
        $deletes = [
            'database/database.sqlite',
        ];

        foreach ($deletes as $path) {
            if (file_exists(base_path($path))) {
                unlink(base_path($path));
            }
        }
    }

    protected function updateComposerJson()
    {
        $replaces = $this->getReplaces();

        if (! isset($this->composerJson['extra']['laravel']['providers'])) {
            $this->composerJson['extra']['laravel']['providers'] = [];
        }
        $this->composerJson['name'] = $this->package;
        $this->composerJson['type'] = 'library';
        $this->composerJson['description'] = 'A skeleton module created using mxent/stack.';
        $this->composerJson['keywords'] = [$this->vendorSnake, $this->nameSnake];
        $this->composerJson['extra']['laravel']['providers'][] = $this->vendor.'\\'.$this->name.'\\Providers\\'.$this->name.'ServiceProvider';
        $this->composerJson['autoload']['psr-4'][$this->vendor.'\\'.$this->name.'\\'] = 'app/';
        unset($this->composerJson['autoload']['psr-4']['App\\']);

        $composerJsonClean = json_encode($this->composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents(base_path('composer.json'), $composerJsonClean);

        $this->recursiveStubs(__DIR__.'/../../stubs/react', base_path(), $replaces);
        $this->recursiveReplace(base_path(), $replaces);
    }

    protected function updatePackageJson()
    {
        if (! isset($this->packageJson['workspaces'])) {
            $this->packageJson['workspaces'] = [];
        }
        if (! in_array('vendor/'.$this->vendorSnake.'/*', $this->packageJson['workspaces'])) {
            $this->packageJson['workspaces'][] = 'vendor/'.$this->vendorSnake.'/*';
        }

        $this->packageJson['scripts']['test'] = 'vitest run';

        if (! isset($this->packageJson['lint-staged'])) {
            $this->packageJson['lint-staged'] = [];
        }
        $this->packageJson['lint-staged']['**/*.{ts,js,tsx,jsx}'] = ['prettier --write', 'eslint --fix'];

        $packageJsonClean = json_encode($this->packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents(base_path('package.json'), $packageJsonClean);
    }

    protected function installDependencies()
    {
        $composerRequires = [
            'inertiajs/inertia-laravel' => '2.x-dev',
        ];

        $npmDevInstalls = [
            '@inertiajs/react' => 'next',
            '@vitejs/plugin-react' => null,
            'react' => null,
            'react-dom' => null,
            '@types/node' => null,
            '@types/react' => null,
            '@types/react-dom' => null,
            '@commitlint/cli' => null,
            '@commitlint/config-conventional' => null,
            'husky' => null,
            'lint-staged' => null,
            'prettier' => null,
            'eslint' => null,
            'globals' => null,
            '@eslint/js' => null,
            'typescript-eslint' => null,
            'eslint-plugin-react' => null,
            'vitest' => null,
        ];

        $npmInstalls = [
            // Add npm installs if any
        ];

        $npmUninstalls = [
            'axios',
        ];

        $passThru = [];

        $allComposerRequires = [];
        foreach ($composerRequires as $packageName => $version) {
            $allComposerRequires[] = $packageName.($version ? ':'.$version : '');
        }

        $allNpmUninstalls = [];
        foreach ($npmUninstalls as $packageName) {
            $allNpmUninstalls[] = $packageName;
        }
        $allNpmDevInstalls = [];
        foreach ($npmDevInstalls as $packageName => $version) {
            $allNpmDevInstalls[] = $packageName.($version ? '@'.$version : '');
        }
        $allNpmInstalls = [];
        foreach ($npmInstalls as $packageName => $version) {
            $allNpmInstalls[] = $packageName.($version ? '@'.$version : '');
        }

        $passThru[] = 'composer require '.implode(' ', $allComposerRequires);
        $passThru[] = 'npm uninstall '.implode(' ', $allNpmUninstalls);
        $passThru[] = 'npm install --save-dev '.implode(' ', $allNpmDevInstalls);
        $passThru[] = 'npm install '.implode(' ', $allNpmInstalls);
        $passThru[] = 'echo "export default { extends: [\'@commitlint/config-conventional\'] };" > commitlint.config.js';
        $passThru[] = 'git init';
        $passThru[] = 'npx husky init';
        $passThru[] = 'echo "npx --no -- commitlint --edit \$1" > .husky/commit-msg';
        $passThru[] = 'echo "npm test && vendor/bin/pest && vendor/bin/pint && npx lint-staged && git add -A ." > .husky/pre-commit';
        $passThru[] = 'npm install';
        $passThru[] = 'npm run build';
        passthru(implode(' && ', $passThru));
    }

    private function getReplaces()
    {
        return [
            'VendorName' => $this->vendor,
            'vendor-name' => $this->vendorSnake,
            'vendorname' => $this->vendorLower,
            'ModuleName' => $this->name,
            'module-name' => $this->nameSnake,
            'modulename' => $this->nameLower,
            'App\\' => $this->vendor.'\\'.$this->name.'\\',
            'AppServiceProvider' => $this->name.'ServiceProvider',
        ];
    }

    private function recursiveStubs($path, $destination, $replaces)
    {
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $fullPath = $path.'/'.$file;
            $fullDestination = $destination.'/'.str_replace('.stub', '', $file);
            $fullDestination = str_replace(array_keys($replaces), array_values($replaces), $fullDestination);

            if (is_dir($fullPath)) {
                if (! is_dir($fullDestination)) {
                    mkdir($fullDestination);
                }

                $this->recursiveStubs($fullPath, $fullDestination, $replaces);
            } else {
                $contents = file_get_contents($fullPath);

                foreach ($replaces as $search => $replace) {
                    $contents = str_replace($search, $replace, $contents);
                }

                file_put_contents($fullDestination, $contents);
            }
        }
    }

    private function recursiveReplace($path, $replaces)
    {
        $excludes = [
            'vendor',
            'node_modules',
        ];

        $gitignores = base_path('.gitignore');
        $gitignores = file_exists($gitignores) ? file($gitignores) : [];

        $files = scandir($path);

        foreach ($files as $file) {
            if ($file == '.' || $file == '..' || in_array($file, $excludes) || in_array($file, $gitignores) || substr($file, 0, 1) == '.') {
                continue;
            }

            $fullPath = $path.'/'.$file;

            if (is_dir($fullPath)) {
                $this->recursiveReplace($fullPath, $replaces);
            } else {
                $contents = file_get_contents($fullPath);
                $isJson = in_array(pathinfo($fullPath, PATHINFO_EXTENSION), ['json', 'lock']);

                foreach ($replaces as $search => $replace) {
                    if ($isJson) {
                        $search = str_replace('\\', '\\\\', $search);
                        $replace = str_replace('\\', '\\\\', $replace);
                    }

                    $contents = str_replace($search, $replace, $contents);
                }

                file_put_contents($fullPath, $contents);
            }
        }
    }
}
