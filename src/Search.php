<?php


namespace Zhaiyujin\Learning;
use Zhaiyujin\Learning\EsSearch;

class Search
{
    use EsSearch;
    public $index="";
    public function searchType(){
        return "dfs_query_then_fetch";
    }
    //搜索索引
    public function index($index){
        $this->index=$index;
        return $this;
    }
    public function searchIndex(){
        return $this->index;
    }
    public function esearch($string){

        return $this->isearch($string,$this);

    }
    /**
     * Get the Scout engine for the model.
     *
     * @return mixed
     */
    public function searchableUsing()
    {
        return app(EngineManager::class)->engine();
    }
}
