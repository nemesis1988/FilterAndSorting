<?php

class FilterExpandTest extends TestCase
{
    /** @test * */
    function filter_expand_work_for_eloquent_model()
    {
        $request = $this->setRequest([
            'filter' => '{"id":2}',
            'filterExpand' => '{"viewed.id":{"operation":"in","value":[1,3]}}',
            'expand' => 'posts,viewed',
            'sortExpand' => '-posts.id,-viewed.id'
        ]);

        $user = User::setFilterAndRelationsAndSort($request)->first();
        
        $this->assertNotEquals(
            null,
            $user
        );

        $this->assertEquals(
            'Second post',
            $user->posts->first()->title
        );

        $this->assertEquals(
            'Third post',
            $user->viewed->first()->title
        );

        $this->assertEquals(
            'First post',
            $user->viewed->last()->title
        );

        $this->assertEquals(
            2,
            $user->viewed->count()
        );
    }
}
