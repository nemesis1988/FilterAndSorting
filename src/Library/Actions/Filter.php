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
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 * @author Sereda Oleg <zaxodu11@gmail.com>
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
        $this->get($params);
    }

    /**
     * Устанавливаем параметры фильтрации.
     * @since 2.0.0
     */
    public function set()
    {
        $this->filterConditions->each(function ($value, $key) {
            list($relation, $table_name, $field_name) = $this->getFieldParameters($key);
            (new FilterOperation($this->query, $this->getFullFieldLink($table_name, $field_name), $value, $relation))->set();
        });
    }

    /**
     * Устанавливаем параметры фильтрации как для реляции.
     *
     * @param $sortRequestField
     * @return bool
     * @since 2.0.0
     */
    public function setAsRelation($sortRequestField)
    {
        $sorted = collect([]);
        $sortInstance = new Sort($this->query, $this->request, $sortRequestField);
        $this->filterConditions->each(function ($value, $key) use (&$sorted, $sortInstance) {
            list($relation, $table_name, $field_name) = $this->getFieldParameters($key);
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
     * @since 2.0.0
     */
    private function setSortModelForRelationExpand(Sort $sortInstance, Collection $sort, &$query)
    {
        $sortInstance->startTransition($query);
        $sort->each(function ($condition) use ($sortInstance) {
            $sortInstance->sortModel($condition);
        });
        $sortInstance->stopTransition();
    }


    /**
     * Получить условия фильтра.
     *
     * @since 2.0.0
     *
     * @param $params
     */
    public function get($params)
    {
        if (isset($params[ $this->filterRequestField ]) && is_array($params[ $this->filterRequestField ])) {
            $this->filterConditions = collect($params[ $this->filterRequestField ]);
        }

        if ($this->request && $this->request->has($this->filterRequestField)) {
            $this->mergeConditions($this->request->input($this->filterRequestField));
        }
        dd($this->filterConditions);
    }

    /**
     * Сливайет вместе параметры.
     * Только данные "whereIn" операций не затираются.
     *
     * @param $requestConditions
     *
     * @return mixed
     * @since 3.1.0
     */
    public function mergeConditions($requestConditions)
    {
        $conditions = json_decode($requestConditions, true);
        $filterConditions = $this->filterConditions->toArray();
        if(is_array($conditions)) {
            foreach ($conditions as $key => $row) {
                $hasKey = isset($filterConditions[$key]);
                if (!$hasKey) {
                    $filterConditions[$key] = $row;
                }elseif($hasKey && isset($filterConditions[$key]['operation']) && isset($filterConditions[$key]['value'])){
                    $this->mergeOperations($filterConditions, $key, $row);
                }
            }
        }
        $this->filterConditions = collect($filterConditions);
    }

    /**
     * Мержит операции с дополнением параметров.
     * TODO Если станет много операция для мерджа - использовать switch-case структуру.
     *
     * @param $conditions
     * @param $index
     * @param $row
     *
     * @since 3.1.0
     */
    protected function mergeOperations(&$conditions, $index, $row)
    {
        if(isset($row['operation']) && isset($row['value'])){
            if(
                $row['operation'] == 'in' &&
                is_array($row['value'])
            ){
                $conditions[$index]['value'] = array_merge($this->filterConditions[$index]['value'], $row['value']);
            }

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