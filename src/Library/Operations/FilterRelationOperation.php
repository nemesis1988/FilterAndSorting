<?php

namespace Nemesis\FilterAndSorting\Library\Operations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Class FilterRelationOperation
 *
 * @version 1.0.0
 * @date 13.03.17
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class FilterRelationOperation extends FilterOperation
{

    const OPERATION_NEGATIVE_CORRECTION = 1;
    const OPERATION_SEARCH_CORRECTION = 2;

    /**
     * Реляция для операции.
     *
     * @var null
     */
    protected $relation;

    /**
     * @var bool
     */
    public $operationCorrection = false;

    /**
     * @var Collection
     */
    protected $conditions;

    /**
     * Конструктор класса операций фильтра.
     *
     * @param Builder    $query
     * @param            $relation
     * @param            $conditions
     */
    public function __construct(Builder &$query, $relation, $conditions)
    {
        $this->query = $query;
        $this->relation = $relation;
        $this->detectRelationOperations($conditions);
    }


    public function set()
    {
        switch ($this->operationCorrection){
            case static::OPERATION_NEGATIVE_CORRECTION:
                $this->query->whereDoesntHave($this->relation, function ($qu) {
                    $this->setRelationFilter($qu);
                });
                break;
            case static::OPERATION_SEARCH_CORRECTION:
                $this->query->orWhereHas($this->relation, function ($qu) {
                    $qu->where(function($q){
                        $this->setRelationFilter($q);
                    });
                });
                break;
            default:
                $this->query->whereHas($this->relation, function ($qu) {
                    $this->setRelationFilter($qu);
                });
        }
    }

    /**
     * Set relation filter operations.
     *
     * @param $query
     */
    protected function setRelationFilter($query)
    {
        foreach ($this->conditions as $condition) {
            $this->setFilterOperationCLassEnvironment($condition);
            $this->addFilterOperation($query);
        }
    }

    /**
     * Set class environment.
     *
     * @param $environment
     */
    protected function setFilterOperationCLassEnvironment($environment)
    {
        $this->field_name = $environment->field_name;
        $this->operationType = $environment->operationType;
        $this->operation = $environment->operation;
        $this->value = $environment->value;
    }

    /**
     * Detect relation operations.
     *
     * @param $conditions
     */
    protected function detectRelationOperations($conditions)
    {
        $this->conditions = collect([]);
        foreach ($conditions as $condition) {
            $this->conditions->push((object) array_merge([
                'field_name' => $condition->table . '.' . $condition->field
            ], $this->detectOperation($condition->value)));

        }

        //check for negative correction.
        if ($this->conditions->count() == 1 && $this->conditions[0]->operation == '<>') {
            $this->conditions[0]->operation = '=';
            $this->operationCorrection = static::OPERATION_NEGATIVE_CORRECTION;
        }

        //check for search correction.
        if ($this->checkForSearchCorrection($this->conditions)) {
            $this->operationCorrection = static::OPERATION_SEARCH_CORRECTION;
        }
    }

    /**
     * Check if we need to set search correction.
     *
     * @param Collection $conditions
     *
     * @return bool
     */
    protected function checkForSearchCorrection(Collection $conditions)
    {
        $count = $conditions->count();
        $checker = 0;
        foreach ($conditions as $condition) {
            if($condition->operation == 'search'){
                $checker++;
            }
        }

        return $count == $checker;
    }
}