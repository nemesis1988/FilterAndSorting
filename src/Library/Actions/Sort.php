<?php

namespace App\Traits;


use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class Sort
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com> AND Sereda Oleg <zaxodu11@gmail.com>
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
     * @param string $sortRequestField
     * @since 2.0.0
     */
    public function __construct(Builder &$query, Request $request = null, $sortRequestField = 'sort')
    {
        parent::__construct($query, $query->getModel(), $request);
        $this->sortConditions = collect([]);
        $this->sortRequestField = $sortRequestField;
        $this->get();
    }

    /**
     * Устанавливает сортировки в модели.
     *
     * @since 2.0.0
     */
    public function set()
    {
        $this->sortConditions->each(function ($condition, $field) {
            if ($condition->relation) {
                $this->sortRelation($condition, $field);
            } else {
                $this->sortModel($field, $condition->direction);
            }
        });
        return $this->query;
    }

    /**
     * Устанавливает сортировку по реляциям.
     *
     * @since 2.0.0
     * @param Collection $sorted
     * @return Builder
     */
    public function setAsRelation(Collection $sorted)
    {
        $this->sortConditions->each(function ($condition, $field) use ($sorted) {
            if ($condition->relation && !$sorted->contains('relation', $condition->relation)) {
                $this->query->with([$condition->relation => function ($q) use ($field, $condition) {
                    $this->startTransition($q);
                    $this->sortModel($field, $condition->direction);
                    $this->stopTransition();
                }]);
            }
        });

        return $this->query;
    }

    /**
     * Сортиировка по полю внутри реляции.
     *
     * @param $condition
     * @param $fieldName
     * @since 2.0.0
     */
    public function sortRelation($condition, $fieldName)
    {
        $this->query->modelJoin($condition->relation, $fieldName);
        $table_name = $this->detectTableNameFromRelation($condition->relation);
        $this->query->orderBy($table_name . '.' . $fieldName, $condition->direction);
    }

    /**
     * Соритровка по полю внутри модели.
     *
     * @param $field
     * @param $sortDirection
     * @since 2.0.0
     */
    public function sortModel($field, $sortDirection)
    {
        if (in_array($field, $this->getModelAvailableFields($this->model))) {
            $this->query->orderBy($field, $sortDirection);
        }
    }

    /**
     * Возвращает условия сортировки.
     *
     * @return array
     * @since 2.0.0
     */
    public function get()
    {
        $sort = [];
        if ($this->request && $this->request->has($this->sortRequestField)) {
            $sortParts = explode(',', $this->request->get($this->sortRequestField));
            foreach ($sortParts as $part) {
                $this->setSortPartConditions($part);
            }
        }

        return $sort;
    }

    /**
     * Устанавливает условия сортировки для одного блока.
     *
     * @param $part
     * @since 2.0.0
     */
    protected function setSortPartConditions($part)
    {
        $sign = substr(trim($part), 0, 1);
        $sort_direction = 'asc';

        if ($sign == '-') {
            $sort_direction = 'desc';
        }

        $part_arguments = explode('.', trim($part, '-'));

        $field_name = isset($part_arguments[1]) ? $part_arguments[1] : $part_arguments[0];

        $this->sortConditions->put($field_name, (object)[
            'direction' => $sort_direction,
            'relation' => isset($part_arguments[1]) && $this->checkRelation($part_arguments[0]) ? $part_arguments[0] : null
        ]);
    }

}