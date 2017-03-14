<?php
/**
 * Created by PhpStorm.
 * User: jackblack
 * Date: 30.07.16
 * Time: 23:20
 */

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
     * @param array   $params
     * @param string  $filterRequestField
     */
    public function __construct(Builder &$query, Request $request = null, $params = [ ], $filterRequestField = 'filter')
    {
        parent::__construct($query, $query->getModel(), $request);
        $this->filterConditions = collect([ ]);
        $this->filterRequestField = $filterRequestField;
        $this->get($params);
    }

    /**
     * Устанавливаем параметры фильтрации.
     * @since 2.0.0
     */
    public function set()
    {
        $this->filterConditions->groupBy('relation')->each(function ($conditions, $relation) {
            if ($relation) {
                (new FilterRelationOperation(
                    $this->query, $relation, $conditions
                ))->set();
            } else {
                //filtration in current table. $relation = null.
                $this->filterByConditions($conditions);
            }
        });
    }

    /**
     * Устанавливаем параметры фильтрации как для реляции.
     *
     * @param       $sortRequestField
     * @param array $params
     *
     * @return Collection
     * @since 2.0.0
     */
    public function setAsRelation($sortRequestField, $params = [ ])
    {
        $sorted = collect([ ]);
        $sortInstance = new Sort($this->query, $this->request, $params, $sortRequestField);
        foreach ($this->filterConditions->groupBy('relation') as $relation => $conditions) {
            if ($relation) {
                $sort = $this->checkSortRelation($sortInstance, $relation);
                if ($sort) {
                    $sorted = $sorted->merge($sort);
                }
                $this->filterByRelation($relation, $conditions, $sortInstance, $sort);
            }
        }

        return $sorted;
    }

    /**
     * Filter current table by conditions.
     *
     * @since 3.2.0
     *
     * @param Collection $conditions
     */
    protected function filterByConditions(Collection $conditions)
    {
        foreach ($conditions as $condition) {
            (new FilterOperation(
                $this->query, $this->getConditionFullPath($condition),
                $condition->value
            ))->set();
        }
    }

    /**
     * Фильтруем по реляциии.
     *
     * @param       $relation
     * @param array $conditions
     * @param Sort  $sortInstance
     * @param       $sort
     */
    protected function filterByRelation($relation, $conditions, Sort $sortInstance = null, $sort = null)
    {
        $this->query->with([ $relation => function ($query) use ($conditions, $sortInstance, $sort) {
            $queryBuilder = $query->getQuery();
            foreach ($conditions as $condition) {
                (new FilterOperation(
                    $queryBuilder,
                    $this->getConditionFullPath($condition),
                    $condition->value
                ))->set();
            }
            if ($sort) {
                $this->setSortModelForRelationExpand($sortInstance, $sort, $query);
            }
        } ]);
    }

    /**
     * Установим сортировку для expand модели.
     *
     * @param Sort       $sortInstance
     * @param Collection $sort
     * @param            $query
     *
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
        $conditions = [ ];
        if (isset($params[ $this->filterRequestField ]) && is_array($params[ $this->filterRequestField ])) {
            $conditions = $params[ $this->filterRequestField ];
        }

        if ($this->request && $this->request->has($this->filterRequestField)) {
            $conditions = $this->mergeConditions($this->request->input($this->filterRequestField), $conditions);
        }

        $this->prepareConditions(collect($conditions));
    }

    /**
     * Сливайет вместе параметры.
     * Только данные "whereIn" операций не затираются.
     *
     * @param $requestConditions
     * @param $params
     *
     * @return mixed
     * @since 3.1.0
     */
    public function mergeConditions($requestConditions, $params)
    {
        $conditions = json_decode($requestConditions, true);
        $filterConditions = $params;
        if (is_array($conditions)) {
            foreach ($conditions as $key => $row) {
                $hasKey = isset($filterConditions[ $key ]);
                if ( ! $hasKey) {
                    $filterConditions[ $key ] = $row;
                } elseif ($hasKey && isset($filterConditions[ $key ]['operation']) && isset($filterConditions[ $key ]['value'])) {
                    $this->mergeOperations($filterConditions, $key, $row);
                }
            }
        }

        return collect($filterConditions);
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
        if (isset($row['operation']) && isset($row['value'])) {
            if (
                $row['operation'] == 'in' &&
                is_array($row['value'])
            ) {
                $conditions[ $index ]['value'] = array_merge($this->filterConditions[ $index ]['value'], $row['value']);
            }
        }
    }

    /**
     * Проверяет реляцию на совпадение и существование соритровки для нее.
     *
     * @param Sort $sort
     * @param      $relation
     *
     * @return bool|Collection
     */
    protected function checkSortRelation(Sort $sort, $relation)
    {
        if ($sort->sortConditions->contains('relation', $relation)) {
            return $sort->sortConditions->where('relation', $relation);
        }

        return null;
    }

    /**
     * Возвращает полную ссылку на поле сортировки.
     *
     * @param $condition
     *
     * @return string
     * @since 2.1.0
     */
    protected function getConditionFullPath($condition)
    {
        return $condition->table . '.' . $condition->field;
    }

    /**
     * Prepare filter conditions to work on.
     *
     * @since 3.2.0
     *
     * @param Collection $conditions
     */
    protected function prepareConditions(Collection $conditions)
    {
        foreach ($conditions as $key => $condition) {
            list($relation, $table_name, $field_name) = $this->getFieldParametersWithExistsCheck($key);
            if ($table_name && $field_name) {
                $this->filterConditions->push((object) [
                    'relation' => $relation,
                    'table'    => $table_name,
                    'field'    => $field_name,
                    'value'    => $condition,
                ]);
            }
        }
    }
}