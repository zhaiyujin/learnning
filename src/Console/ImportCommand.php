<?php

namespace Zhaiyujin\Learning\Console;

use \Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Scout\Events\ModelsImported;

class ImportCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'escout:import
            {model : 要批量导入的模型的类名}
            {--c|chunk= : 一次导入的记录数（默认为配置值）: `escout.chunk.searchable`)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '将给定模型导入搜索索引';

    /**
     * Execute the console command.
     * 注入事件实例,并注册侦听器的订阅者
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        $class = $this->argument('model');

        $model = new $class;
        //注册呢一个事件ModelsImported,事件监听者是闭包
        $events->listen(ModelsImported::class, function ($event) use ($class) {
            //事件处理,通过事件获取构造传入的模型获取最后一条数据的主键
            $key = $event->models->last()->getScoutKey();
            //将信息输出到控制台
            $this->line('<comment>Imported ['.$class.'] models up to ID:</comment> '.$key);
        });
        //通过模型来批量提交索引Searchable的实例
        $model::makeAllSearchable($this->option('chunk'));
        //从调度程序中删除一组侦听器。
        $events->forget(ModelsImported::class);
        //结束
        $this->info('All ['.$class.'] records have been imported.');
    }
}
