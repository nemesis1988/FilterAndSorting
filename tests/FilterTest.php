<?php

class FilterTest extends TestCase
{
    /** @test **/
    function it_basic_filter_work_for_eloquent_model()
    {
        $request = $this->setRequest([
            'filter' => '{"id":1,"title":"First post"}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $posts->first()->id
        );
    }

    /** @test **/
    function equal_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":"=","value":2},"title":{"operation":"=","value":"Second post"}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            2,
            $posts->first()->id
        );
    }

    /** @test **/
    function gt_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":">","value":2}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            3,
            $posts->first()->id
        );
    }

    /** @test **/
    function gte_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":">=","value":2}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            2,
            $posts->count()
        );

        $this->assertEquals(
            3,
            $posts->last()->id
        );
    }

    /** @test **/
    function lt_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":"<","value":2}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $posts->first()->id
        );
    }

    /** @test **/
    function lte_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":"<=","value":2}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            2,
            $posts->count()
        );

        $this->assertEquals(
            2,
            $posts->last()->id
        );
    }

    /** @test **/
    function not_equal_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":"<>","value":2}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            2,
            $posts->count()
        );

        $this->assertEquals(
            3,
            $posts->last()->id
        );
    }

    /** @test **/
    function not_in_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":"not in","value":[2,3]}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $posts->count()
        );

        $this->assertEquals(
            1,
            $posts->first()->id
        );
    }

    /** @test **/
    function in_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"operation":"in","value":[1,3]}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            2,
            $posts->count()
        );

        $this->assertEquals(
            1,
            $posts->first()->id
        );

        $this->assertEquals(
            3,
            $posts->last()->id
        );
    }

    /** @test **/
    function like_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"title":{"operation":"like","value":"post"}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            3,
            $posts->count()
        );

        $request = $this->setRequest([
            'filter' => '{"title":{"operation":"like","value":"First "}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $posts->count()
        );

        $this->assertEquals(
            1,
            $posts->first()->id
        );
    }

    /** @test **/
    function from_to_operation_test()
    {
        $request = $this->setRequest([
            'filter' => '{"id":{"from":3}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $posts->count()
        );

        $this->assertEquals(
            3,
            $posts->first()->id
        );

        $request = $this->setRequest([
            'filter' => '{"id":{"from":1,"to":3}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            3,
            $posts->count()
        );

        $this->assertEquals(
            1,
            $posts->first()->id
        );

        $this->assertEquals(
            3,
            $posts->last()->id
        );

        $request = $this->setRequest([
            'filter' => '{"id":{"from":{"operation":">","value":1},"to":{"operation":"<","value":3}}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $posts->count()
        );

        $this->assertEquals(
            2,
            $posts->first()->id
        );
    }

    /** @test **/
    function is_relations_filter_works_as_expected()
    {
        $request = $this->setRequest([
            'filter' => '{"owner.posts.title":{"operation":"in","value":["Second post","Third post"]}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            3,
            $posts->count()
        );

        $request = $this->setRequest([
            'filter' => '{"owner.posts.title":{"operation":"like","value":"thir"}}'
        ]);

        $posts = Post::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(
            1,
            $posts->count()
        );
    }
}
