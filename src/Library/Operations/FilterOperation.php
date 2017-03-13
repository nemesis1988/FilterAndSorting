<?php
/**
 * Created by PhpStorm.
 * User: jackblack
 * Date: 30.07.16
 * Time: 23:38
 */

namespace Nemesis\FilterAndSorting\Library\Operations;

use Carbon\Carbon;
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
    public $operationType = false;

    /**
     * Значение для операции.
     *
     * @var mixed
     */
    public $value = '';

    /**
     * Поле для операции.
     *
     * @var string
     */
    protected $field_name;

    /**
     * Доступные операции.
     *
     * @var array
     */
    protected $allowedOperations = [ '=', '>', '<', '>=', '<=', '<>', 'not in', 'in', 'like', 'search' ];
    protected $allowedDateOperations = [ '=', '>', '<', '>=', '<=', '<>' ];


    /**
     * Конструктор класса операций фильтра.
     *
     * @param Builder $query
     * @param         $field_name
     * @param         $condition
     */
    public function __construct(Builder &$query, $field_name, $condition)
    {
        $this->query = $query;
        $this->field_name = $field_name;
        $this->detectOperation($condition);
    }

    /**
     * Установим операцию.
     */
    public function set()
    {
        $this->addFilterOperation($this->query);
    }

    /**
     * Добавление условий к фильтру.
     *
     * @param Builder $query
     *
     * @return mixed
     * @since 1.0.0
     */
    protected function addFilterOperation(Builder &$query)
    {
        $this->isNullOperation($query);
        if ($this->operationType == 'operation' && ! empty($this->value)) {
            $this->filterAllowedOperations($query);
        } elseif ($this->operationType == 'date_range') {
            $this->filterByDateRange($query);
        } elseif (is_string($this->value) || is_numeric($this->value)) {
            $query->where($this->field_name, $this->value);
        }

        return $query;
    }

    /**
     * Фильтрация по разрешенным операциями.
     *
     * @param $query
     *
     * @since 2.0.0
     */
    protected function filterAllowedOperations(&$query)
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
            case 'search':
                if (is_string($this->value)) {
                    $query->orWhere($this->field_name, 'like', "%{$this->value}%");
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
     *
     * @since 2.0.0
     */
    protected function filterByDateRange(Builder &$query)
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
     *
     * @since 2.0.0
     */
    protected function isNullOperation(Builder &$query)
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
     *
     * @since 2.0.0
     * @return array
     */
    protected function detectOperation($condition)
    {
        $this->operation = '=';
        if (isset($condition['operation'])) {
            $operation = strtolower($condition['operation']);
            if (in_array($operation, $this->allowedOperations) !== false) {
                $this->operationType = 'operation';
                $this->operation = $operation;
                if (isset($condition['value'])) {
                    $this->value = $condition['value'];
                }
            }
        } elseif (isset($condition['from']) || isset($condition['to'])) {
            $this->operationType = 'date_range';
            $this->value = [
                'from' => $this->getDateCondition(isset($condition['from']) ? $condition['from'] : null, '>='),
                'to'   => $this->getDateCondition(isset($condition['to']) ? $condition['to'] : null, '<=', ' 23:59:59'),
            ];
        } else {
            $this->operationType = 'simple';
            $this->value = $condition;
        }

        return [
            'operationType' => $this->operationType,
            'operation'     => $this->operation,
            'value'         => $this->value,
        ];
    }

    /**
     * Возвращает отформатированное условия для поиска по дате.
     *
     * @param        $dateCondition
     * @param string $defaultOperation
     *
     * @param string $time
     *
     * @return array
     * @since 2.0.0
     */
    protected function getDateCondition($dateCondition, $defaultOperation = '<=', $time = ' 00:00:00')
    {
        if (is_array($dateCondition)) {
            $operation = isset($dateCondition['operation']) ? $dateCondition['operation'] : $defaultOperation;

            return [
                'value'     => $this->getDateValue(isset($dateCondition['value']) ? $dateCondition['value'] : null, $time),
                'operation' => $this->checkDateOperation($operation) ? $operation : $defaultOperation,
            ];
        } else {
            return [
                'value'     => $this->getDateValue($dateCondition, $time),
                'operation' => $defaultOperation,
            ];
        }
    }

    /**
     * Проверяет операцию для даты на существование.
     *
     * @param $operation
     *
     * @return bool
     */
    protected function checkDateOperation($operation)
    {
        return in_array($operation, $this->allowedDateOperations);
    }

    /**
     * Возвращает дату в удобном формате.
     *
     * @param $date
     * @param $time
     *
     * @return string
     * @since 2.0.0
     */
    protected function getDateValue($date, $time = false)
    {
        try {
            $date = Carbon::parse($date);
            if ($time) {
                $date->setTimeFromTimeString($time);

                return $date->toDateTimeString();
            }

            return $this->checkIfTimeNeeded($date) ? $date->toDateTimeString() : $date->toDateString();
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Determine if date need time expression.
     *
     * @param $date
     *
     * @return int
     */
    protected function checkIfTimeNeeded($date)
    {
        return preg_match('/\s(\d{2}):(\d{2})(:\d{2})?$/', $date);
    }

}