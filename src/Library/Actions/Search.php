<?php

namespace Nemesis\FilterAndSorting\Library\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nemesis\FilterAndSorting\Library\FilterAndSortingFacade;
use Nemesis\FilterAndSorting\Library\Operations\FilterOperation;
use Nemesis\FilterAndSorting\Library\Operations\FilterRelationOperation;

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
        if ( ! $this->searchConditions->isEmpty()) {
            //create hard where query
            $this->query->where(function ($query) {
                foreach ($this->searchConditions->groupBy('relation') as $relation => $conditions) {
                    if ($relation) {
                        (new FilterRelationOperation($query, $relation, $conditions))->set();
                    } else {
                        foreach ($conditions as $condition) {
                            (new FilterOperation($query, $this->getConditionFullPath($condition), $condition->value))->set();
                        }
                    }
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
            $search = json_decode($this->request->input($this->searchRequestField));
            if ( ! empty($search->fields) && ! empty($search->query)) {
                foreach (explode('|', $search->fields) as $field) {
                    list($relation, $table_name, $field_name) = $this->getFieldParametersWithExistsCheck($field);
                    if ($table_name && $field_name) {
                        $this->searchConditions->push((object) [
                            'relation' => $relation,
                            'table'    => $table_name,
                            'field'    => $field_name,
                            'value'    => [
                                'operation' => 'search',
                                'value'     => $search->query,
                            ],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Возвращает полный линк поля.
     *
     * @param $condition
     *
     * @return string
     */
    private function getConditionFullPath($condition)
    {
        return $condition->table . '.' . $condition->field;
    }
}