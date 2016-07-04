<?php
namespace Nemesis\FilterAndSorting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Class FilterAndSorting
 * @package App\Traits
 *
 * @version 1.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 */
trait FilterAndSorting
{

    /** @var array Список необходимых связей */
    protected $expands = [];

    /**
     * Boot at first.
     */
    public static function bootFilterAndSorting()
    {
        DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    /**
     * установка фильтра, сортировки и подключение вложенных моделей
     *
     * @param Builder $query
     * @param Request|null $request
     * @param array $params
     * @return mixed
     * @since 1.0.0
     */
    public function scopeSetFilterAndRelationsAndSort($query, $request = null, $params = [])
    {
        $query = $this->setFilter($query, $params, $request);
        $this->setExpands($query, $request);
        $query = $this->setSort($query, $request);

        return $query;
    }

    /**
     * Установить значения фильтра
     *
     *
     * @param Builder $query
     * @param array $params астомные параметры для фильтра
     * @param Request $request нужен для выборки по фильтрам с клинта
     * @return mixed
     * @since 1.0.0
     */
    public function setFilter($query, $params, $request = null)
    {
        $filter = [];

        if (isset($params['filter']) && is_array($params['filter'])) {
            $filter = $params['filter'];
        }

        if ($request->input('filter')) {
            $filter = array_merge($filter, json_decode($request->input('filter'), true));
        }

        if ($filter) {
            foreach ($filter as $key => $value) {
                $keys_array = explode('.', $key);
                $relation = null;
                $table_name = null;
                if (count($keys_array) == 2 && in_array($keys_array[0], $this->extraFields())) {
                    $relation = $keys_array[0];
                    $table_name = $this->detectTableNameFromRelation($relation);
                    $field_name = $keys_array[1];
                } else {
                    $field_name = $keys_array[0];
                }

                if ($relation) {
                    if (isset($value['operation']) && $value['operation'] == '<>') {
                        $value['operation'] = '=';
                        $query->whereDoesntHave($relation, function($qu) use ($field_name, $value, $table_name) {
                            $this->addFilterCondition($qu, $field_name, $value, $table_name);
                        });
                    } else {
                        $query->whereHas($relation, function ($qu) use ($field_name, $value, $table_name) {
                            $this->addFilterCondition($qu, $field_name, $value, $table_name);
                        });
                    }
                } else {
                    $this->addFilterCondition($query, $field_name, $value);
                }
            }
        }

        return $query;
    }

    /**
     * Добавление условий к фильтру
     *
     * @param Builder $query
     * @param string $key Поле по которому фильтровать
     * @param string $value значение по которому фильтровать
     * @param string $table_name
     * @return mixed
     * @since 1.0.0
     */
    public function addFilterCondition(&$query, $key, $value, $table_name = null)
    {
        $allow_operations = ['=', '>', '<', '>=', '<=', '<>', 'not in', 'in', 'like'];

        if ($table_name) {
            $key = $table_name . '.' . $key;
        }

        if (is_array($value)) {
            if (isset($value['isNull']) && $value['isNull'] === true) {
                $query->whereNull($key);
            } elseif (isset($value['isNull']) && $value['isNull'] === false) {
                $query->whereNotNull($key);
            }

            $pattern = "/^(\d{2}).(\d{2}).(\d{4})$/";
            if (isset($value['operation']) && in_array(strtolower($value['operation']), $allow_operations) && isset($value['value'])) {
                if (in_array(strtolower($value['operation']), ['in', 'not in']) && is_array($value['value'])) {
                    $query->whereIn($key, $value['value']);
                } elseif (strtolower($value['operation']) == 'like') {
                    $query->where($key, 'like', "%{$value['value']}%");
                } else {
                    $value['value'] = preg_match($pattern, $value['value']) ? (new \DateTime($value['value']))->format("Y-m-d") : $value['value'];
                    $query->where($key, $value['operation'], \DB::raw($value['value']));
                }
            } elseif (isset($value['from']) || isset($value['to'])) {
                if (isset($value['from']) && $value['from']) {
                    $from = preg_match($pattern, $value['from']) ? (new \DateTime($value['from']))->format("Y-m-d") : $value['from'];
                    $query->where($key, '>=', $from);
                }

                if (isset($value['to']) && $value['to']) {
                    $to = preg_match($pattern, $value['to']) ? (new \DateTime($value['to']))->format("Y-m-d") : $value['to'];
                    $query->where($key, '<=', $to);
                }
            }
        } else {
            $query->where($key, $value);
        }

        return $query;
    }

    /**
     * Установить связи
     *
     * @param Builder $query
     * @param Request|null $request
     * @return mixed
     * @since 1.0.0
     */
    public function setExpands(&$query, $request = null)
    {
        $this->expands = $this->getExpands($request);
        if ($this->expands) {
            $query->with($this->expands);
        }

        return $this->expands;
    }

    /**
     * Получить список связей из expand
     *
     * @param Request $request
     * @return array
     * @since 1.0.0
     */
    public function getExpands($request = null)
    {
        $expands = [];

        if ($request && $request->get('expand')) {
            $expands = array_intersect($this->extraFields(), explode(',',$request->get('expand')));
        }

        return $expands;
    }

    /**
     * Задает сортировку
     *
     * @param Builder $query
     * @param Request|null $request
     * @return mixed
     * @since 1.0.0
     */
    public function setSort($query, $request = null)
    {
        if ($request && $request->has('sort')) {
            $sort = $request->get('sort');
            $sign = substr($sort, 0, 1);

            if ($sign == '-') {
                $sort_direction = 'desc';
                $sort = trim($sort, '-');
            } else {
                $sort_direction = 'asc';
            }

            $available_fields = array_keys(DB::getDoctrineSchemaManager()
                ->listTableColumns($query->getModel()->getTable()));

            $sort_arguments = explode('.', $sort);
            $arg_count = count($sort_arguments);
            if ($arg_count == 2) {
                if (in_array($sort_arguments[0], $this->extraFields())) {
                    $query->modelJoin($sort_arguments[0], $sort_arguments[1]);
                    $table_name = $this->detectTableNameFromRelation($sort_arguments[0]);
                    $query->orderBy($table_name . '.' . $sort_arguments[1], $sort_direction);
                }
            } else {
                if (in_array($sort_arguments[0], $available_fields)) {
                    $query->orderBy($sort_arguments[0], $sort_direction);
                }
            }
        }

        return $query;
    }

    /**
     * Определение имени таблицы по имени реляции
     *
     * @param string $relation
     * @return string
     * @since 1.0.0
     */
    protected function detectTableNameFromRelation($relation)
    {
        return $this->$relation()->getRelated()->getTable();
    }

    /**
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $relation_name
     * @param string $sortColumn
     * @param string $operator
     * @param string $type
     * @param bool $where
     * @return \Illuminate\Database\Query\Builder
     * @since 1.0.0
     * 
     * @see http://laravel-tricks.com/tricks/automatic-join-on-eloquent-models-with-relations-setup
     */
    public function scopeModelJoin($query, $relation_name, $sortColumn, $operator = '=', $type = 'left', $where = false)
    {
        $relation = $this->$relation_name();
        $table = $relation->getRelated()->getTable();

        $one = $this->getTable() . '.' . $relation->getForeignKey();
        $two = $table . '.' . $relation->getOtherKey();

        if (method_exists($relation, 'getTable')) {
            $three = $relation->getQualifiedParentKeyName();
            $four = $relation->getForeignKey();
            $query->join($relation->getTable(), $three, $operator, $four, $type, $where);

            $one = $table . '.' . $relation->getRelated()->primaryKey;
            $two = $relation->getOtherKey();
        }

        if (empty($query->columns)) {
            $query->select($this->getTable() . ".*");
        }

        $query->addSelect(new Expression("`$table`.`$sortColumn`"));

        return $query->join($table, $one, $operator, $two, $type, $where);
    }
}