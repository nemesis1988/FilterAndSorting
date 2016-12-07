<?php

class SortExpandTest extends TestCase
{
    /** @test * */
    function sort_expand_work_for_eloquent_model()
    {
        $request = $this->setRequest([
            'filter' => '{"id":2}',
            'expand' => 'posts,viewed',
            'sortExpand' => '-posts.id,-viewed.id'
        ]);

        $user = User::setFilterAndRelationsAndSort($request)->first();

        $this->assertNotEquals(
            null,
            $user
        );

        $this->assertEquals(
            'Third post',
            $user->viewed->first()->title
        );

        $this->assertEquals(
            'First post',
            $user->viewed->last()->title
        );
    }
}
