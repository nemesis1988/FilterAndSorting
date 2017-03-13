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
            'Jefrey Test',
            $user->full_name
        );

        $this->assertEquals(
            2,
            $user->id
        );

        $this->assertEmpty(
            $user->posts
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

    /** @test **/
    function it_filter_expand_on_multiple_conditions()
    {
        $request = $this->setRequest([
            'filterExpand' => '{"posts.created_at":{"operation":">=","value":"2017-01-10"}, "posts.created_at":{"operation":"<=","value":"2017-01-15"}}',
            'expand' => 'posts',
            'sortExpand' => '-posts.id'
        ]);

        $users = User::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(1, $users[0]->posts->count());
        $this->assertEquals(0, $users[2]->posts->count());

        $request = $this->setRequest([
            'filterExpand' => '{"posts.created_at":{"from":"2017-01-10","to":"2017-01-15"}}',
            'expand' => 'posts',
            'sortExpand' => '-posts.id'
        ]);

        $users = User::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(2, $users[0]->posts->count());
        $this->assertEquals(2, $users[0]->posts[0]->id);
        $this->assertEquals(0, $users[2]->posts->count());

        $request = $this->setRequest([
            'filterExpand' => '{"posts.created_at":{"operation":">=","value":"2017-01-10"}, "posts.created_at":{"operation":"<=","value":"2017-01-30"}}',
            'expand' => 'posts',
            'sortExpand' => '-posts.id'
        ]);

        $users = User::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(2, $users[0]->posts->count());
        $this->assertEquals(0, $users[2]->posts->count());

        $request = $this->setRequest([
            'filterExpand' => '{"posts.created_at":{"operation":">=","value":"2017-01-10"}, "posts.created_at":{"operation":"<=","value":"2017-01-30 23:59"}}',
            'expand' => 'posts',
            'sortExpand' => '-posts.id'
        ]);

        $users = User::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(2, $users[0]->posts->count());
        $this->assertEquals(1, $users[2]->posts->count());

    }
}
