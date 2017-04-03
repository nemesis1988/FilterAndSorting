<?php
namespace Nemesis\FilterAndSorting;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Nemesis\FilterAndSorting\Library\Actions\Expand;
use Nemesis\FilterAndSorting\Library\Actions\Filter;
use Nemesis\FilterAndSorting\Library\Actions\Search;
use Nemesis\FilterAndSorting\Library\Actions\Sort;

/**
 * Class FilterAndSorting
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com> AND Sereda Oleg <zaxodu11@gmail.com>
 */
trait FilterAndSorting
{

    /**
     * Возвращает массив вожных реляций. Если нужны реляции - ПЕРЕОПРЕДЕЛИТЬ ФУНКЦИЮ В МОДЕЛИ.
     *
     * @return array
     * @since 1.0.0
     */
    public function extraFields()
    {
        return [];
    }

    /**
     * Boot at first.
     */
    public static function bootFilterAndSorting()
    {
        if (env('APP_ENV', 'testing') == 'testing') {
            Manager::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        } else {
            \DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        }
    }

    /**
     * Установка фильтра, сортировки и подключение вложенных моделей
     *
     * @param Builder      $query
     * @param Request|null $request
     * @param array        $params
     *
     * @return mixed
     * @since 1.0.0
     */
    public function scopeSetFilterAndRelationsAndSort($query, $request = null, $params = [ ])
    {
        (new Expand($query, $request, $params, 'expand'))->set();
        (new Filter($query, $request, $params, 'filter'))->set();
        (new Sort($query, $request, $params, 'sort'))->set();
        $sorted = (new Filter($query, $request, $params, 'filterExpand'))->setAsRelation('sortExpand', $params);
        (new Sort($query, $request, $params, 'sortExpand'))->setAsRelation($sorted);
        (new Search($query, $request, $params, 'search'))->set();

        return $query;
    }

    /**
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string                             $relation_name
     * @param string                             $sortColumn
     * @param string                             $operator
     * @param string                             $type
     * @param bool                               $where
     *
     * @return \Illuminate\Database\Query\Builder
     * @since 1.0.5
     *
     * @see http://laravel-tricks.com/tricks/automatic-join-on-eloquent-models-with-relations-setup
     */
    public function scopeModelJoin($query, $relation_name, $sortColumn, $operator = '=', $type = 'left', $where = false)
    {
        if (str_contains($relation_name, '.')) {
            $relationParts = explode('.', $relation_name);
            $lastIndex = count($relationParts) - 1;
            $model = $this;
            foreach ($relationParts as $index => $relation) {
                $model = $this->simpleModelJoin($query, $model, $relation, $index == $lastIndex ? $sortColumn : null, $operator, $type, $where);
            }
        } else {
            $this->simpleModelJoin($query, $this, $relation_name, $sortColumn, $operator, $type, $where);
        }
    }

    protected function simpleModelJoin($query, Model $model, $relation_name, $sortColumn, $operator = '=', $type = 'left', $where = false)
    {
        $relation = $model->$relation_name();
        $table = $relation->getRelated()->getTable();

        list($one, $two) = $this->checkCrossTableRelation($query, $relation, $operator, $type, $where);

        if (empty($query->columns)) {
            $query->select($this->getTable() . ".*");
        }
        if ( ! empty($sortColumn)) {
            $query->addSelect(new Expression("`$table`.`$sortColumn`"));
        }

        $query->join($table, $one, $operator, $two, $type, $where);

        return $relation->getRelated();
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
     *
     * @return array
     * @since 1.0.5
     */
    protected function checkCrossTableRelation($query, $relation, $operator, $type, $where)
    {
        if (method_exists($relation, 'getTable')) {
            $three = $relation->getQualifiedParentKeyName();
            $four = $relation->getForeignKey();

            $query->join($relation->getTable(), $three, $operator, $four, $type, $where);

            $one = $relation->getRelated()->getTable() . '.' . $relation->getRelated()->primaryKey;
            $two = $relation->getOtherKey();
        } else {
            if(method_exists($relation, 'getQualifiedOtherKeyName') && method_exists($relation, 'getQualifiedForeignKey')) {
                $one = $relation->getQualifiedOtherKeyName();
                $two = $relation->getQualifiedForeignKey();
            }else{
                $one = $relation->getQualifiedParentKeyName();
                $two = $relation->getForeignKey();
            }
        }

        return [ $one, $two ];
    }

}