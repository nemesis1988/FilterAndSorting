<?php
namespace Nemesis\FilterAndSorting;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Nemesis\FilterAndSorting\Library\Actions\Sort;
use Nemesis\FilterAndSorting\Library\Actions\Expand;
use Nemesis\FilterAndSorting\Library\Actions\Filter;

/**
 * Class FilterAndSorting
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
trait FilterAndSorting
{

    /**
     * Возвращает массив вожных реляций. Если нужны реляции - ПЕРЕОПРЕДЕЛИТЬ ФУНКЦИЮ В МОДЕЛИ.
     *
     * @return array
     * @since 1.0.0
     */
    protected function extraFields()
    {
        return [];
    }

    /**
     * Установка фильтра, сортировки и подключение вложенных моделей
     *
     * @param Builder $query
     * @param Request|null $request
     * @param array $params
     * @return mixed
     * @since 1.0.0
     */
    public function scopeSetFilterAndRelationsAndSort($query, $request = null, $params = [])
    {
        (new Expand($query, $request, 'expand'))->set();
        (new Filter($query, $request, $params, 'filter'))->set();
        (new Sort($query, $request, 'sort'))->set();
        $sorted = (new Filter($query, $request, $params, 'filterExpand'))->setAsRelation('sortExpand');
        (new Sort($query, $request, 'sortExpand'))->setAsRelation($sorted);
        return $query;
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
     * @since 1.0.5
     *
     * @see http://laravel-tricks.com/tricks/automatic-join-on-eloquent-models-with-relations-setup
     */
    public function scopeModelJoin($query, $relation_name, $sortColumn, $operator = '=', $type = 'left', $where = false)
    {
        $relation = $this->$relation_name();
        $table = $relation->getRelated()->getTable();

        list($one, $two) = $this->checkCrossTableRelation($query, $relation, $operator, $type, $where);

        if (empty($query->columns)) {
            $query->select($this->getTable() . ".*");
        }

        $query->addSelect(new Expression("$table.$sortColumn"));

        return $query->join($table, $one, $operator, $two, $type, $where);
    }

    /**
     * Проверяет реляцию на кросстабличность, например - много к многим, и добавляет промежуточную таблицу к джоину.
     * Возвращает конечные данные подключения джоина.
     *
     * @param $query
     * @param $relation
     * @param $operator
     * @param $type
     * @param $where
     * @return array
     * @since 1.0.5
     */
    protected function checkCrossTableRelation($query, $relation, $operator, $type, $where)
    {
        $one = $relation->getQualifiedParentKeyName();
        $two = $relation->getForeignKey();
        if (method_exists($relation, 'getTable')) {
            $three = $relation->getQualifiedParentKeyName();
            $four = $relation->getForeignKey();

            $query->join($relation->getTable(), $three, $operator, $four, $type, $where);

            $one = $relation->getRelated()->getTable() . '.' . $relation->getRelated()->primaryKey;
            $two = $relation->getOtherKey();
        }
        return [$one, $two];
    }

}