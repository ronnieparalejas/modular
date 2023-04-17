<?php

namespace InterNACHI\Modular\Console\Commands\Make;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Commands\MakeCommand;
use Livewire\LivewireComponentsFinder;

if (class_exists(MakeCommand::class)) {
    class MakeLivewire extends MakeCommand
    {
        use Modularize;

        public function getAliases(): array
        {
            return ['make:livewire', 'livewire:make'];
        }

        public function handle()
        {
            if ($module = $this->module()) {
                Config::set('livewire.view_path', $module->path('resources/views/livewire'));

                $app = $this->getLaravel();

                $defaultManifestPath = $app['livewire']->isRunningServerless()
                    ? '/tmp/storage/bootstrap/cache/livewire-components.php'
                    : $app->bootstrapPath('cache/livewire-components.php');

                $componentsFinder = new LivewireComponentsFinder(
                    new Filesystem(),
                    Config::get('livewire.manifest_path') ?? $defaultManifestPath,
                    $module->path('src/Http/Livewire')
                );

                $app->instance(LivewireComponentsFinder::class, $componentsFinder);
            }

            parent::handle();
        }

        protected function createClass($force = false, $inline = false)
        {
            if ($module = $this->module()) {
                $classPath = $module->path('src/Http/Livewire/'.$this->getModuleName().'.php');

                if (File::exists($classPath) && ! $force) {
                    $this->line("<options=bold,reverse;fg=red> WHOOPS-IE-TOOTLES </> 😳 \n");
                    $this->line("<fg=red;options=bold>Class already exists:</> {$this->parser->relativeClassPath()}");

                    return false;
                }

                $this->ensureDirectoryExists($classPath);

                File::put($classPath, $this->classContents($inline));

                return $classPath;
            }

            return parent::createClass($force, $inline);
        }

        protected function classContents($inline = false)
        {
            $stubName = $inline ? 'livewire.inline.stub' : 'livewire.stub';

            $template = file_get_contents(__DIR__.'/stubs/'.$stubName);

            if ($inline) {
                $template = preg_replace('/\[quote\]/', $this->parser->wisdomOfTheTao(), $template);
            }

            return preg_replace(
                ['/\[namespace\]/', '/\[class\]/', '/\[view\]/'],
                [$this->parser->classNamespace(), $this->parser->className(), $this->getViewName()],
                $template
            );
        }

        protected function getViewName(): string
        {
            return $this->module()->name.'::'.$this->viewName();
        }

        protected function getModuleName(): string
        {
            return Str::of($this->argument('name'))
                    ->split('/[.\/(\\\\)]+/')
                    ->map([Str::class, 'studly'])
                    ->join(DIRECTORY_SEPARATOR);
        }

        protected function viewName(): string
        {
            return Str::of($this->getModuleName())
            ->explode('/')
            ->filter()
            ->map([Str::class, 'kebab'])
            ->implode('.');
        }
    }
}
