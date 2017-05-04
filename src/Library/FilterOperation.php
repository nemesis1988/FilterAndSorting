<?php
/**
 * Created by PhpStorm.
 * User: jackblack
 * Date: 30.07.16
 * Time: 23:38
 */

namespace Nemesis\FilterAndSorting\Library;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class FilterOperation
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class FilterOperation
{
    /**
     * @var Builder
     */
    public $query;

    /**
     * @var string
     */
    public $operation = '=';
    public $operationCorrection = false;
    public $operationType = false;

    /**
     * Значение для операции.
     *
     * @var mixed
     */
    public $value = '';

    /**
     * Реляция для операции.
     *
     * @var null
     */
    private $relation;

    /**
     * Поле для операции.
     *
     * @var string
     */
    private $field_name;

    /**
     * Доступные операции.
     *
     * @var array
     */
    private $allowedOperations = ['=', '>', '<', '>=', '<=', '<>', 'not in', 'in', 'like'];
    private $allowedDateOperations = ['=', '>', '<', '>=', '<=', '<>'];

    /** @var regex Шаблон определения даты для фильтрации */
    private $datePattern = "/^(\d{2}).(\d{2}).(\d{4})$/";

    /**
     * Конструктор класса операций фильтра.
     *
     * @param Builder $query
     * @param $field_name
     * @param $condition
     * @param null $relation
     */
    public function __construct(Builder &$query, $field_name, $condition, $relation = null)
    {
        $this->query = $query;
        $this->relation = $relation;
        $this->field_name = $field_name;
        $this->detectOperation($condition);
    }

    /**
     * Установим операцию.
     */
    public function set()
    {
        if ($this->relation) {
            if ($this->operationCorrection) {
                $this->query->whereDoesntHave($this->relation, function ($qu) {
                    $this->addFilterOperation($qu);
                });
            } else {
                $this->query->whereHas($this->relation, function ($qu) {
                    $this->addFilterOperation($qu);
                });
            }
        } else {
            $this->addFilterOperation($this->query);
        }
    }

    /**
     * Добавление условий к фильтру.
     *
     * @param Builder $query
     * @return mixed
     * @since 1.0.0
     */
    protected function addFilterOperation(Builder &$query)
    {
        dump($this->value);
        dump($this->checkSettingForSearchOnValue($this->value));
        $this->isNullOperation($query);
        if ($this->operationType == 'operation' && $this->checkSettingForSearchOnValue($this->value)) {
            $this->filterAllowedOperations($query);
        } elseif ($this->operationType == 'date_range') {
            $this->filterByDateRange($query);
        } elseif (is_string($this->value)) {
            $query->where($this->field_name, $this->value);
        }

        return $query;
    }

    /**
     * Фильтрация по разрешенным операциями.
     *
     * @param $query
     * @since 2.0.0
     */
    private function filterAllowedOperations(&$query)
    {
        switch ($this->operation) {
            case 'in':
                if (is_array($this->value)) {
                    $query->whereIn($this->field_name, $this->value);
                }
                break;
            case 'not in':
                if (is_array($this->value)) {
                    $query->whereNotIn($this->field_name, $this->value);
                }
                break;
            case 'like':
                if (is_string($this->value)) {
                    $query->where($this->field_name, 'like', "%{$this->value}%");
                }
                break;
            default:
                $query->where($this->field_name, $this->operation, $this->getDateValue($this->value));

        }
    }

    /**
     * Фильтрация по датам
     *
     * @param Builder $query
     * @since 2.0.0
     */
    private function filterByDateRange(Builder &$query)
    {
        if ($this->value['from']['value']) {
            $query->where($this->field_name, $this->value['from']['operation'], $this->value['from']['value']);
        }

        if ($this->value['to']['value']) {
            $query->where($this->field_name, $this->value['to']['operation'], $this->value['to']['value']);
        }
    }

    /**
     * @param Builder $query
     * @since 2.0.0
     */
    private function isNullOperation(Builder &$query)
    {
        if (isset($this->value['isNull']) && $this->value['isNull'] === true) {
            $query->whereNull($this->field_name);
        } elseif (isset($this->value['isNull']) && $this->value['isNull'] === false) {
            $query->whereNotNull($this->field_name);
        }
    }


    /**
     * Определим условия операции.
     *
     * @param $condition
     * @since 2.0.0
     */
    private function detectOperation($condition)
    {
        if (isset($condition['operation'])) {
            if (in_array($condition['operation'], $this->allowedOperations) !== false) {
                $this->operationType = 'operation';
                $this->operation = strtolower($condition['operation']);
                if ($this->relation && $this->operation == '<>') {
                    $this->operation = '=';
                    $this->operationCorrection = true;
                }
                if (isset($condition['value'])) {
                    $this->value = $condition['value'];
                }
            }
        } elseif (isset($condition['from']) || isset($condition['to'])) {
            $this->operationType = 'date_range';
            $this->value = [
                'from' => $this->getDateCondition(isset($condition['from']) ? $condition['from'] : null, '>='),
                'to' => $this->getDateCondition(isset($condition['to']) ? $condition['to'] : null, '<='),
            ];
        } else {
            $this->operationType = 'simple';
            $this->value = $condition;
        }
    }

    /**
     * Возвращает отформатированное условия для поиска по дате.
     *
     * @param $dateCondition
     * @param string $defaultOperation
     * @return array
     * @since 2.0.0
     */
    private function getDateCondition($dateCondition, $defaultOperation = '<=')
    {
        if (is_array($dateCondition)) {
            $operation = isset($dateCondition['operation']) ? $dateCondition['operation'] : $defaultOperation;
            return [
                'value' => $this->getDateValue(isset($dateCondition['value']) ? $dateCondition['value'] : null),
                'operation' => $this->checkDateOperation($operation) ? $operation : $defaultOperation
            ];
        } else {
            return [
                'value' => $this->getDateValue($dateCondition),
                'operation' => $defaultOperation
            ];
        }
    }

    /**
     * Проверяет операцию для даты на существование.
     *
     * @param $operation
     * @return bool
     */
    private function checkDateOperation($operation)
    {
        return in_array($operation, $this->allowedDateOperations);
    }

    /**
     * Возвращает дату в удобном формате.
     *
     * @param $date
     * @return string
     * @since 2.0.0
     */
    private function getDateValue($date)
    {
        return preg_match($this->datePattern, $date) ? (new \DateTime($date))->format("Y-m-d") : $date;
    }

    /**
     * Проверка настроек на возможность искать по пустым строкам
     *
     * @param $value
     * @return bool
     */
    private function checkSettingForSearchOnValue($value)
    {
        if (!$value) {
            return (config('filter-and-sorting.search_by_operation_empty_value'));
        }

        return true;
    }

}