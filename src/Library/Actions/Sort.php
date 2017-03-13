<?php

namespace Nemesis\FilterAndSorting\Library\Actions;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nemesis\FilterAndSorting\Library\FilterAndSortingFacade;

/**
 * Class Sort
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class Sort extends FilterAndSortingFacade
{

    /**
     * @var Collection
     */
    public $sortConditions = null;

    /**
     * @var string
     */
    private $sortRequestField;


    /**
     * Конструсктор класс сортировок.
     *
     * @param Builder $query
     * @param Request $request
     * @param array   $params
     * @param string  $sortRequestField
     *
     * @since 2.0.0
     */
    public function __construct(Builder &$query, Request $request = null, $params = [ ], $sortRequestField = 'sort')
    {
        parent::__construct($query, $query->getModel(), $request);
        $this->sortConditions = collect([ ]);
        $this->sortRequestField = $sortRequestField;
        $this->get($params);
    }

    /**
     * Устанавливает сортировки в модели.
     *
     * @since 2.0.0
     */
    public function set()
    {
        $this->sortConditions->each(function ($condition) {
            if ($condition->relation) {
                $this->sortRelation($condition);
            } else {
                $this->sortModel($condition);
            }
        });

        return $this->query;
    }

    /**
     * Устанавливает сортировку по реляциям.
     *
     * @since 2.0.0
     *
     * @param Collection $sorted
     *
     * @return Builder
     */
    public function setAsRelation(Collection $sorted)
    {
        $this->sortConditions->each(function ($condition) use ($sorted) {
            if ($condition->relation && ! $sorted->contains('relation', $condition->relation)) {
                $this->query->with([ $condition->relation => function ($q) use ($condition) {
                    $this->startTransition($q);
                    $this->sortModel($condition);
                    $this->stopTransition();
                } ]);
            }
        });

        return $this->query;
    }

    /**
     * Сортиировка по полю внутри реляции.
     *
     * @param $condition
     *
     * @since 2.0.0
     */
    public function sortRelation($condition)
    {
        $this->query->modelJoin($condition->relation, $condition->field);
        $this->sortModel($condition);
    }

    /**
     * Соритровка по полю внутри модели.
     *
     * @param $condition
     *
     * @since 2.0.0
     */
    public function sortModel($condition)
    {
        $this->query->orderBy($this->getConditionFullPath($condition), $condition->direction);
    }

    /**
     * Возвращает условия сортировки.
     *
     * @param array $params
     *
     * @return array
     * @since 2.0.0
     */
    public function get($params = [ ])
    {
        if ($this->request && $this->request->has($this->sortRequestField)) {
            $sortParts = explode(',', $this->request->get($this->sortRequestField));
            foreach ($sortParts as $part) {
                $this->setSortPartConditions($part);
            }
        }

        if (isset($params[ $this->sortRequestField ])) {
            $sortParts = explode(',', $params[ $this->sortRequestField ]);
            foreach ($sortParts as $part) {
                $this->setSortPartConditions($part);
            }
        }

        $this->sortConditions = $this->sortConditions->unique(function ($condition) {
            return $condition->relation . $condition->table . $condition->field;
        });

    }

    /**
     * Устанавливает условия сортировки для одного блока.
     *
     * @param $part
     *
     * @since 2.0.0
     */
    protected function setSortPartConditions($part)
    {
        $sign = substr(trim($part), 0, 1);
        $sort_direction = 'asc';

        if ($sign == '-') {
            $sort_direction = 'desc';
        }

        list($relation, $table_name, $field_name) = $this->getFieldParametersWithExistsCheck(trim($part, '-'));

        if ($table_name && $field_name) {
            $this->sortConditions->push((object) [
                'table'     => $table_name,
                'field'     => $field_name,
                'direction' => $sort_direction,
                'relation'  => $relation,
            ]);
        }
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

}