<?php

namespace Zhaiyujin\Learning\Providers;

use Illuminate\Support\ServiceProvider;
use Zhaiyujin\Learning\Console\FlushCommand;
use Zhaiyujin\Learning\Console\ImportCommand;
use Zhaiyujin\Learning\EngineManager;

class ScoutServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //合并配置
        $this->mergeConfigFrom(__DIR__.'/../../config/escout.php', 'escout');

        //创建一个搜索引擎驱动
        $this->app->singleton(EngineManager::class, function ($app) {
            return new EngineManager($app);
        });

        //确定laravel应用程序是否正在控制台中运行。
        if ($this->app->runningInConsole()) {
            //注册软件包的自定义Artisan命令
            $this->commands([
                ImportCommand::class,
               // FlushCommand::class,
            ]);
        //配置文件发布
            $this->publishes([
                __DIR__.'/../config/escout.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'escout.php',
            ]);
        }
    }
}
