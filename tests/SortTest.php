<?php

class SortTest extends TestCase
{
    /** @test **/
    function asc_sort_work_for_eloquent_model()
    {
        $request = $this->setRequest([
            'sort' => 'id'
        ]);

        $users = User::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $users->first()->id
        );

        $this->assertEquals(
            3,
            $users->last()->id
        );
    }

    /** @test **/
    function desc_sort_work_for_eloquent_model()
    {
        $request = $this->setRequest([
            'sort' => '-id'
        ]);

        $users = User::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            3,
            $users->first()->id
        );

        $this->assertEquals(
            1,
            $users->last()->id
        );
    }
}
