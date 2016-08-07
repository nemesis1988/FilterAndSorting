<?php

class ExpandTest extends TestCase
{
    /** @test * */
    function expand_work_for_eloquent_model()
    {
        $request = $this->setRequest([
            'filter' => '{"id":1}',
            'expand' => 'posts,viewed',
            'sortExpand' => 'posts.id'
        ]);

        $user = User::setFilterAndRelationsAndSort($request)->first();

        $this->assertNotEquals(
            null,
            $user
        );

        $this->assertArrayHasKey(
            'posts',
            $user
        );

        $this->assertEquals(
            'First post',
            $user->posts->first()->title
        );

        $this->assertArrayHasKey(
            'viewed',
            $user
        );
    }
}
