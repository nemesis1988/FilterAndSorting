<?php
namespace Nemesis\FilterAndSorting;

use Illuminate\Support\ServiceProvider;

/**
 * Class FilterAndSortingServiceProvider
 *
 * @package Nemesis\FilterAndSorting
 *
 * @author Bondarenko Kirill <bondarenko.kirill@gmail.com>
 */
class FilterAndSortingServiceProvider extends ServiceProvider
{

    /**
     * @inheritdoc
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('filter-and-sorting.php'),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function register()
    {
        //
    }
}