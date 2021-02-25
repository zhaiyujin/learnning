<?php

namespace Zhaiyujin\Learning\Engines;

use Zhaiyujin\Learning\Builder;
use Elasticsearch\Client as Elastic;
use Illuminate\Database\Eloquent\Collection;

class ElasticsearchEngine extends Engine
{
    /**
     * Elastic client.
     *
     * @var Elastic
     */
    protected $elastic;

    /**
     * Create a new engine instance.
     *
     * @param  \Elasticsearch\Client  $elastic
     * @return void
     */
    public function __construct(Elastic $elastic)
    {
        $this->elastic = $elastic;
    }


    public function save($models){

        if ($models->isEmpty()) {
            return;
        }

        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'update' => [
                    '_id' => $model->getScoutKey(),
                    '_index' => $model->searchableAs(),
                    // '_type' => "_doc"//get_class($model),
                ]
            ];
            $params['body'][] = [
                'doc' => $model->toSearchableArray(),
                'doc_as_upsert' => true
            ];
        });

        $this->elastic->bulk($params);
    }


    /**
     * Update the given model in the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function update($models)
    {

        if ($models->isEmpty()) {
            return;
        }

        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'update' => [
                    '_id' => $model->getScoutKey(),
                    '_index' => $model->searchableAs(),
                   // '_type' => "_doc"//get_class($model),
                ]
            ];
            $params['body'][] = [
                'doc' => $model->toSearchableArray(),
                'doc_as_upsert' => true
            ];
        });

        $this->elastic->bulk($params);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $model->searchableAs(),
                    '_type' => "_doc"//get_class($model),
                ]
            ];
        });

        $this->elastic->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {

        $result = $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

        //$result['nbPages'] = $result['hits']['total'] / $perPage;
        $result['nbPages'] = $result['hits']['total']['value'] / $perPage;

        return $result;
    }

    /**
     *在引擎上执行给定的搜索。
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function searchAll($models)
    {
        return $this->performSearch($models);
    }


    /**
     * 在引擎上执行给定的搜索。
     *
     * @param  Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {


     $bool=[
         'should'=> [
             ['match' => ["content.pinyin" => "*{$builder->query}*"]],
             ['match' => ["title.pinyin" => "*{$builder->query}*"]]
         ],
       //  'should'=> [ 'query_string' => ['query' => "*{$builder->query}*"]],

     ];
        $bool2=[
            'must'=> [ 'query_string' => ['query' => "*{$builder->query}*"]],

        ];

        $bool3=[
            'must'=>[
                ['multi_match'=>[
                    "type"=> "most_fields",
                    "query"=> "*{$builder->query}*",
                    //"type"=>"best_fields",
                    "fields"=>[
                        //"content",
                        "title",
                        "title.pinyin",
                        "content.pinyin",
                    ],
                    "minimum_should_match"=> "-20%"
                    //"operator"=>"or",
                    //"minimum_should_match"=> "80%"
                ]]
               /* ['match' => ["content.pinyin" => "*{$builder->query}*"]],
                ["match"=>[ 'query_string' => ['query' => "*{$builder->query}*"]]],
                */
                ],
           // "must"=>[]

        ];
        
        $index=$builder->model->searchIndex();
        $params = [
            'index' => $index,//$builder->model->searchableAs(),
            'type' => "_doc",//get_class($builder->model),
            'body' => [
                "size"=>100,
                'query' => [
                    "bool"=>$bool3,
                    /*'bool' => [
                       // 'must' => [ 'query_string' => ['query' => "*{$builder->query}*"]],//[[ 'query_string' => ['query' => "*{$builder->query}*"]]],
                        'must'=>  $must,                         // [ 'query_string' => ['query' => "*{$builder->query}*"]],

                        'should'=> [ 'query_string' => ['query' => "*{$builder->query}*"]],
                    ]*/
                ],
                "highlight"=>[
                    "boundary_chars"=>".,!? \t\n，。！？",
                    "pre_tags" => ["<font color='red'>"],
                    "post_tags" => ["</font>"],
                    "fields"=> [
                    "title" =>[ "number_of_fragments" => 0],//new \stdClass(),
                        "title.pinyin" =>[
                        "number_of_fragments" => 0
                        ],

                ]]
            ],
            "search_type"=>$builder->model->searchType()
        ];

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elastic,
                $builder->query,
                $params
            );
        }

        return $this->elastic->search($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value)) {
                return ['terms' => [$key => $value]];
            }

            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }

    protected function is_chinese($s){
        $allen = preg_match("/^[^\x80-\xff]+$/", $s);   //判断是否是英文
        $allcn = preg_match("/^[".chr(0xa1)."-".chr(0xff)."]+$/",$s);  //判断是否是中文
        if($allen){
            return false;
        }else{
            if($allcn){
                return true;
            }else{
                return true;
            }
        }
    }


    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {

        if ($results['hits']['total'] === 0) {
            return $model->newCollection();
        }

        $keys = collect($results['hits']['hits'])->pluck('_id')->values()->all();

        $modelIdPositions = array_flip($keys);

        return $model->getScoutModelsByIds(
            $builder,
            $keys
        )->filter(function ($model) use ($keys) {
            return in_array($model->getScoutKey(), $keys);
        })->sortBy(function ($model) use ($modelIdPositions) {
            return $modelIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        //return $results['hits']['total']['value'];
        return $results['hits']['total']['value'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $model->newQuery()
            ->orderBy($model->getKeyName())
            ->unsearchable();
    }

    /**
     * Generates the sort if theres any.
     *
     * @param  Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return null;
        }

        return collect($builder->orders)->map(function ($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }
}
