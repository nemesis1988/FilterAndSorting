<?php
/**
 * Created by PhpStorm.
 * User: jackblack
 * Date: 30.07.16
 * Time: 22:22
 */

namespace Nemesis\FilterAndSorting\Library;

use Illuminate\Http\Request;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

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
        'query' => null,
        'request' => null
    ];

    /**
     * Конструсктор класса фасада.
     *
     * @param Builder $query
     * @param Request $request
     * @param Model $model
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
     * @param Model $model
     * @return array
     * @since 2.0.0
     */
    public function getModelAvailableFields(Model $model)
    {
        return array_keys(DB::getDoctrineSchemaManager()
            ->listTableColumns($model->getTable()));
    }

    /**
     * Определение имени таблицы по имени реляции
     *
     * @param string $relation
     * @return string
     * @since 2.0.0
     */
    protected function detectTableNameFromRelation($relation)
    {
        return $this->model->$relation()->getRelated()->getTable();
    }

    /**
     * Проверяет наличие реляции.
     *
     * @param $relation
     * @return bool
     * @since 2.0.0
     */
    protected function checkRelation($relation)
    {
        return in_array($relation, $this->model->extraFields());
    }

    /**
     * Начинает транзакцию
     *
     * @param  $query
     * @param  $request
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