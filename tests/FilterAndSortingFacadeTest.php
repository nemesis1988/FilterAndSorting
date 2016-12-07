<?php

class FilterAndSortingFacadeTest extends TestCase
{
    /** @test * */
    function is_get_table_name_from_relation_works_as_expected()
    {
        $model = new Post();
        $facade = new \Nemesis\FilterAndSorting\Library\FilterAndSortingFacade($model->newQuery(), $model);

        $table = $facade->detectTableNameFromRelation('owner.posts');
        $this->assertEquals('posts', $table);

        $table = $facade->detectTableNameFromRelation('owner');
        $this->assertEquals('users', $table);

        $table = $facade->detectTableNameFromRelation('owne');
        $this->assertEquals('posts', $table);
    }

    /** @test **/
    function is_check_relation_works_as_expected()
    {
        $model = new Post();
        $facade = new \Nemesis\FilterAndSorting\Library\FilterAndSortingFacade($model->newQuery(), $model);

        $relation = $facade->checkRelation(['owner', 'post']);
        $this->assertEquals('owner', $relation);

        $relation = $facade->checkRelation(['owner', 'posts']);
        $this->assertEquals('owner.posts', $relation);
    }

    /** @test **/
    function is_get_field_parameters_works_as_expected()
    {
        $model = new Post();
        $facade = new \Nemesis\FilterAndSorting\Library\FilterAndSortingFacade($model->newQuery(), $model);

        list($relation, $table_name, $field_name) = $facade->getFieldParameters('owner.posts.title');
        $this->assertEquals($relation, 'owner.posts');
        $this->assertEquals($table_name, 'posts');
        $this->assertEquals($field_name, 'title');

        list($relation, $table_name, $field_name) = $facade->getFieldParameters('owner.post.title');
        $this->assertEquals($relation, null);
        $this->assertEquals($table_name, 'posts');
        $this->assertEquals($field_name, 'owner.post.title');
    }

    /** @test **/
    function is_get_field_parameters_with_exists_check_works_as_expected()
    {
        $model = new Post();
        $facade = new \Nemesis\FilterAndSorting\Library\FilterAndSortingFacade($model->newQuery(), $model);

        list($relation, $table_name, $field_name) = $facade->getFieldParametersWithExistsCheck('title');
        $this->assertEquals($relation, null);
        $this->assertEquals($table_name, 'posts');
        $this->assertEquals($field_name, 'title');

        list($relation, $table_name, $field_name) = $facade->getFieldParametersWithExistsCheck('owner.posts.title');
        $this->assertEquals($relation, 'owner.posts');
        $this->assertEquals($table_name, 'posts');
        $this->assertEquals($field_name, 'title');

        list($relation, $table_name, $field_name) = $facade->getFieldParametersWithExistsCheck('posts.title');
        $this->assertEquals($relation, null);
        $this->assertEquals($table_name, null);
        $this->assertEquals($field_name, null);

        list($relation, $table_name, $field_name) = $facade->getFieldParametersWithExistsCheck('owner.posts.full_name');
        $this->assertEquals($relation, 'owner.posts');
        $this->assertEquals($table_name, null);
        $this->assertEquals($field_name, null);

        list($relation, $table_name, $field_name) = $facade->getFieldParametersWithExistsCheck('owner.post.full_name');
        $this->assertEquals($relation, null);
        $this->assertEquals($table_name, null);
        $this->assertEquals($field_name, null);

        list($relation, $table_name, $field_name) = $facade->getFieldParametersWithExistsCheck('owne.full_name');
        $this->assertEquals($relation, null);
        $this->assertEquals($table_name, null);
        $this->assertEquals($field_name, null);
    }
}
