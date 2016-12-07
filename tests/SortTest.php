<?php

use Illuminate\Database\Capsule\Manager as DB;
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

    /** @test **/
    function is_sort_by_relation_works_as_expected()
    {
        $request = $this->setRequest([
            'sort' => '-owner.id'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            3,
            $posts->first()->id
        );

        $this->assertEquals(
            1,
            $posts->last()->id
        );
    }

    /** @test **/
    function is_sort_by_relation_cross_table_works_as_expected()
    {
        $request = $this->setRequest([
            'sort' => '-viewers.id'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            3,
            $posts->first()->id
        );

        $this->assertEquals(
            1,
            $posts->last()->id
        );
    }

    /** @test **/
    function is_sort_by_dotted_relation_works_as_expected()
    {
        $request = $this->setRequest([
            'sort' => '-post.owner.full_name'
        ]);

        $comments = Comment::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            2,
            $comments->first()->id
        );

        $this->assertEquals(
            2,
            $comments->first()->id
        );

        $this->assertEquals(
            1,
            $comments->last()->id
        );
    }
}
