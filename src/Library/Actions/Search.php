<?php

namespace Nemesis\FilterAndSorting\Library\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nemesis\FilterAndSorting\Library\FilterAndSortingFacade;
use Nemesis\FilterAndSorting\Library\FilterOperation;

/**
 * Class Filter
 * @package App\Traits
 *
 * @version 2.1.0
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class Search extends FilterAndSortingFacade
{

    /**
     * Условия фильтрации.
     *
     * @var Collection
     */
    public $searchConditions = null;

    /**
     * Поле в запросе для анлиза фильтрации.
     *
     * @var string
     */
    private $searchRequestField;

    /**
     * Конструсктор класс сортировок.
     *
     * @param Builder $query
     * @param Request $request
     * @param array   $params
     * @param string  $searchRequestField
     */
    public function __construct(Builder &$query, Request $request = null, $params = [ ], $searchRequestField = 'search')
    {
        parent::__construct($query, $query->getModel(), $request);
        $this->searchRequestField = $searchRequestField;
        $this->searchConditions = collect([ ]);
        $this->get($params);
    }

    /**
     * Устанавливаем параметры фильтрации.
     */
    public function set()
    {
        if ( ! empty($this->searchConditions['fields']) && ! empty($this->searchConditions['query'])) {
            $params = [
                'operation' => 'search',
                'value'     => $this->searchConditions['query'],
            ];
            //create hard where query
            $this->query->where(function ($query) use ($params) {
                foreach (explode('|', $this->searchConditions['fields']) as $field) {
                    list($relation, $table_name, $field_name) = $this->getFieldParameters($field);
                    (new FilterOperation($query, $this->getFullFieldLink($table_name, $field_name), $params, $relation))->set();
                }
            });
        }
    }

    /**
     * Получить условия фильтра.
     *
     * @since 2.0.0
     *
     * @param array $params
     */
    public function get($params = [ ])
    {
        if ($this->request && $this->request->has($this->searchRequestField)) {
            $this->searchConditions = collect(json_decode($this->request->input($this->searchRequestField)));
        }
    }

    /**
     * Возвращает полный линк поля.
     *
     * @param $table
     * @param $name
     *
     * @return string
     */
    private function getFullFieldLink($table, $name)
    {
        return $table . '.' . $name;
    }
}