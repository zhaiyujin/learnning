<?php

namespace Zhaiyujin\Learning;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Zhaiyujin\Learning\Engines\ElasticsearchEngine;
use Zhaiyujin\Learning\Console\FlushCommand;
use Zhaiyujin\Learning\Console\ImportCommand;
use Zhaiyujin\Learning\EngineManager;

class EscoutServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //合并配置
        $this->mergeConfigFrom(__DIR__ . '/../config/escout.php', 'escout');

        //创建一个搜索引擎驱动
        $this->app->singleton(EngineManager::class, function ($app) {
            return new EngineManager($app);
        });

        $this->app->singleton(Search::class, function ($app) {
            return new Search($app);
        });

        //确定laravel应用程序是否正在控制台中运行。
        if ($this->app->runningInConsole()) {
            //注册软件包的自定义Artisan命令
            $this->commands([
                ImportCommand::class,
                FlushCommand::class,
            ]);
            //配置文件发布
            $this->publishes([
                __DIR__ . '/../config/escout.php' => $this->app['path.config'] . DIRECTORY_SEPARATOR . 'escout.php',
            ]);

        }
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->ensureElasticClientIsInstalled();

        resolve(\Zhaiyujin\Learning\EngineManager::class)->extend('elasticsearch', function () {
            return new ElasticsearchEngine(
                ClientBuilder::create()
                    ->setHosts(config('escout.elasticsearch.hosts'))
                    ->build()
            );
        });
    }

    /**
     * Ensure the Elastic API client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureElasticClientIsInstalled()
    {
        if (class_exists(ClientBuilder::class)) {
            return;
        }

        throw new Exception('Please install the Elasticsearch PHP client: elasticsearch/elasticsearch.');
    }

}
