<?php
/**
 * Created by PhpStorm.
 * User: jackblack
 * Date: 30.07.16
 * Time: 22:22
 */

namespace Nemesis\FilterAndSorting\Library;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Class FilterAndSortingFacade
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class FilterAndSortingFacade
{

    /**
     * @var Builder
     */
    public $query;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var Model
     */
    public $model;

    /**
     * Транзакция, для запоминания при работой с реляциями.
     *
     * @var array
     */
    private $transition = [
        'query'   => null,
        'request' => null,
    ];

    /**
     * Конструсктор класса фасада.
     *
     * @param Builder $query
     * @param Request $request
     * @param Model   $model
     */
    public function __construct(Builder $query, Model $model, Request $request = null)
    {

        $this->query = $query;
        $this->request = $request;
        $this->model = $model;
    }

    /**
     * Возвращает доступные в модели ключи.
     *
     * @param mixed $model
     *
     * @return array
     * @since 2.0.0
     */
    public function getModelAvailableFields($model)
    {
        $tableName = $model;
        if ($model instanceof Model) {
            $tableName = $model->getTable();
        }

        return array_keys(DB::getDoctrineSchemaManager()
            ->listTableColumns($tableName));
    }

    /**
     * Определение имени таблицы по имени реляции
     *
     * @param string $relationString
     *
     * @return string
     * @since 2.0.0
     */
    public function detectTableNameFromRelation($relationString)
    {
        $keys = explode('.', $relationString);
        $relatedModel = $this->model;
        foreach ($keys as $relation) {
            if (method_exists($relatedModel, $relation)) {
                $relatedModel = $relatedModel->$relation()->getRelated();
            }
        }

        return $relatedModel->getTable();
    }

    /**
     * Проверяет наличие реляции.
     *
     * @param $keys
     *
     * @return bool
     * @since 2.0.0
     */
    public function checkRelation(array $keys)
    {
        $detectRelation = null;
        $i = 0;
        $relation = $keys[ $i ];
        do {
            if (in_array($relation, $this->model->extraFields())) {
                $detectRelation = $relation;
            } elseif ($i != count($keys) - 1) {//this is not last iteration.
                return null;
            }
            $i++;
            if ( ! empty($keys[ $i ])) {
                $relation .= ".$keys[$i]";
            }
        } while ($i < count($keys));

        return $detectRelation;
    }

    /**
     * Возвращает параметры для фильтруемого поля.
     *
     * @param      $filterField
     *
     * @param bool $checkExists
     *
     * @return array
     * @since 1.0.0
     */
    public function getFieldParameters($filterField, $checkExists = false)
    {
        $keys_array = explode('.', $filterField);
        $relation = $this->checkRelation($keys_array);
        if (count($keys_array) >= 2 && $relation) {
            $field_name = last($keys_array);
            $table_name = $this->detectTableNameFromRelation($relation);
        } else {
            $field_name = $filterField;
            $table_name = $this->model->getTable();
        }

        if ($checkExists && ! in_array($field_name, $this->getModelAvailableFields($table_name))) {
            $field_name = null;
            $table_name = null;
        }

        return [ $relation, $table_name, $field_name ];
    }

    /**
     * Возвращает параметры для фильтруемого поля c проверкой наличия его в таблице назанчения.
     *
     * @param      $filterField
     *
     * @return array
     * @since 2.1.0
     */
    public function getFieldParametersWithExistsCheck($filterField)
    {
        return $this->getFieldParameters($filterField, true);
    }

    /**
     * Начинает транзакцию
     *
     * @param  $query
     * @param  $request
     *
     * @since 2.0.0
     */
    public function startTransition($query = false, $request = false)
    {
        $this->transition['query'] = $this->query;
        $this->transition['request'] = $this->request;
        if ($query !== false) {
            $this->query = $query;
            $this->model = $query->getModel();
        }
        if ($request !== false) {
            $this->request = $request;
        }
    }

    /**
     * Останавливает транзакцию.
     *
     * @since 2.0.0
     */
    public function stopTransition()
    {
        $this->request = $this->transition['request'];
        $this->query = $this->transition['query'];
        $this->model = $this->query->getModel();

    }

}