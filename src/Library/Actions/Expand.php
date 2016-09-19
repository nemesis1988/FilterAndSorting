<?php

namespace Nemesis\FilterAndSorting\Library\Actions;


use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Nemesis\FilterAndSorting\Library\FilterAndSortingFacade;

/**
 * Class Expand
 * @package App\Traits
 *
 * @version 2.0.0
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 * @author Sereda Oleg <zaxodu11@gmail.com>
 */
class Expand extends FilterAndSortingFacade
{
    /**
     * @var Collection
     */
    public $expands = null;

    /**
     * @var string
     */
    private $expandRequestField;


    /**
     * Конструсктор класс сортировок.
     *
     * @param Builder $query
     * @param Request $request
     * @param string $expandRequestField
     */
    public function __construct(Builder &$query, Request $request = null, $expandRequestField = 'sort'){
        parent::__construct($query, $query->getModel(), $request);
        $this->expands = collect([]);
        $this->expandRequestField = $expandRequestField;
        $this->get();
    }

    /**
     * Установить связи.
     *
     * @return mixed
     * @since 1.0.0
     */
    public function set()
    {
        if (!$this->expands->isEmpty()) {
            $this->query->with($this->expands->all());
        }

        return $this->expands;
    }

    /**
     * Получить список связей из expand.
     *
     * @return array
     * @since 1.0.0
     */
    protected function get()
    {
        if ($this->request && $this->request->has('expand')) {
            $this->expands = collect(array_intersect($this->model->extraFields(), explode(',', $this->request->get('expand'))));
        }
    }
}