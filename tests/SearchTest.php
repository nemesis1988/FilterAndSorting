<?php

class SearchTest extends TestCase
{
    /** @test * */
    function is_search_action_works_as_expected()
    {
        $request = $this->setRequest([
            'search' => '{"query":"test","fields":"post.title|text|owner.full_name"}'
        ]);

        $comments = Comment::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(2, $comments->count());

        $request = $this->setRequest([
            'search' => '{"query":"tos","fields":"post.title|text|owner.full_name"}'
        ]);

        $comments = Comment::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(1, $comments->count());

        $request = $this->setRequest([
            'search' => '{"query":"post","fields":"post.title|text|owner.full_name"}'
        ]);

        $comments = Comment::setFilterAndRelationsAndSort($request)->get();

        $this->assertEquals(2, $comments->count());
    }
}
