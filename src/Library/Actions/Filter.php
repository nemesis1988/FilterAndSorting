<?php
/**
 * Created by PhpStorm.
 * User: jackblack
 * Date: 30.07.16
 * Time: 23:20
 */

namespace Nemesis\FilterAndSorting\Library\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Nemesis\FilterAndSorting\Library\FilterOperation;
use Nemesis\FilterAndSorting\Library\FilterAndSortingFacade;

/**
 * Class Filter
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com> AND Sereda Oleg <zaxodu11@gmail.com>
 */
class Filter extends FilterAndSortingFacade
{
    /**
     * Условия фильтрации.
     *
     * @var Collection
     */
    public $filterConditions = null;

    /**
     * Поле в запросе для анлиза фильтрации.
     *
     * @var string
     */
    private $filterRequestField;

    /**
     * Параметры фильрации по умолчанию.
     *
     * @var array
     */
    private $params;

    /**
     * Конструсктор класс сортировок.
     *
     * @param Builder $query
     * @param Request $request
     * @param array $params
     * @param string $filterRequestField
     */
    public function __construct(Builder &$query, Request $request = null, $params = [], $filterRequestField = 'filter')
    {
        parent::__construct($query, $query->getModel(), $request);
        $this->filterConditions = collect([]);
        $this->filterRequestField = $filterRequestField;
        $this->params = $params;
        $this->get();
    }

    /**
     * Устанавливаем параметры фильтрации.
     */
    public function set()
    {
        $this->filterConditions->each(function ($value, $key) {
            list($relation, $table_name, $field_name) = $this->getFilterFieldParameters($key);
            (new FilterOperation($this->query, $this->getFullFieldLink($table_name, $field_name), $value, $relation))->set();
        });
    }

    /**
     * Устанавливаем параметры фильтрации как для реляции.
     *
     * @param $sortRequestField
     * @return bool
     */
    public function setAsRelation($sortRequestField)
    {
        $sorted = collect([]);
        $sortInstance = new Sort($this->query, $this->request, $sortRequestField);
        $this->filterConditions->each(function ($value, $key) use (&$sorted, $sortInstance) {
            list($relation, $table_name, $field_name) = $this->getFilterFieldParameters($key);
            if ($relation) {
                $sort = $this->checkSortRelation($sortInstance, $relation);
                if ($sort) {
                    $sorted = $sorted->merge($sort);
                }
                $this->filterByRelation($sortInstance, $sort, $relation, $field_name, $table_name, $value);
            }
        });
        return $sorted;
    }

    /**
     * Фильтруем по реляциии.
     *
     * @param Sort $sortInstance
     * @param $sort
     * @param $relation
     * @param $field_name
     * @param $table_name
     * @param $value
     */
    private function filterByRelation(Sort $sortInstance, $sort, $relation, $field_name, $table_name, $value)
    {
        $this->query->with([$relation => function ($query) use ($value, $field_name, $table_name, $relation, $sortInstance, $sort) {
            $queryBuilder = $query->getQuery();
            (new FilterOperation($queryBuilder, $this->getFullFieldLink($table_name, $field_name), $value))->set();
            if ($sort) {
                $this->setSortModelForRelationExpand($sortInstance, $sort, $query);
            }
        }]);
    }

    /**
     * Установим сортировку для expand модели.
     *
     * @param Sort $sortInstance
     * @param Collection $sort
     * @param $query
     */
    private function setSortModelForRelationExpand(Sort $sortInstance, Collection $sort, &$query)
    {
        $sortInstance->startTransition($query);
        $sort->each(function ($condition, $field) use ($sortInstance) {
            $sortInstance->sortModel($field, $condition->direction);
        });
        $sortInstance->stopTransition();
    }


    /**
     * Возвращает параметры для фильтруемого поля.
     *
     * @param $filterField
     * @return array
     * @since 1.0.0
     */
    protected function getFilterFieldParameters($filterField)
    {
        $keys_array = explode('.', $filterField);
        $relation = null;
        if (count($keys_array) == 2 && $this->checkRelation($keys_array[0])) {
            $relation = $keys_array[0];
            $table_name = $this->detectTableNameFromRelation($relation);
            $field_name = $keys_array[1];
        } else {
            $field_name = $keys_array[0];
            $table_name = $this->model->getTable();
        }
        return [$relation, $table_name, $field_name];
    }


    /**
     * Получить условия фильтра.
     *
     * @since 2.0.0
     */
    public function get()
    {
        if (isset($params[$this->filterRequestField]) && is_array($params[$this->filterRequestField])) {
            $this->filterConditions = collect($params[$this->filterRequestField]);
        }

        if ($this->request && $this->request->has($this->filterRequestField)) {
            $this->filterConditions = $this->filterConditions->merge(json_decode($this->request->input($this->filterRequestField), true));
        }
    }

    /**
     * Проверяет реляцию на совпадение и существование соритровки для нее.
     *
     * @param Sort $sort
     * @param $relation
     * @return bool
     */
    private function checkSortRelation(Sort $sort, $relation)
    {
        if ($sort->sortConditions->contains('relation', $relation)) {
            return $sort->sortConditions->where('relation', $relation);
        }
        return false;
    }

    /**
     * Возвращает полный линк поля.
     *
     * @param $table
     * @param $name
     * @return string
     */
    private function getFullFieldLink($table, $name)
    {
        return $table . '.' . $name;
    }
}