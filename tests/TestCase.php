<?php

use Illuminate\Database\Capsule\Manager as DB;

abstract class TestCase extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->setUpDatabase();
        $this->migrateTables();
        $this->dataSetUp();
    }

    public function tearDown()
    {
        $this->dropTables();
    }

    /**
     * Set up the database connection.
     */
    protected function setUpDatabase()
    {
        $database = new DB;

        $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $database->bootEloquent();
        $database->setAsGlobal();
        DB::connection()->enableQueryLog();
    }

    /**
     * Migrate tables.
     */
    protected function migrateTables()
    {
        DB::schema()->create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('full_name');
            $table->nullableTimestamps();
        });

        DB::schema()->create('posts', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->string('title');
            $table->nullableTimestamps();
        });

        DB::schema()->create('comments', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->integer('post_id')->unsigned()->index();
            $table->string('text');
            $table->nullableTimestamps();
        });

        DB::schema()->create('post_viewers', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('user_id')->undigned()->index();
            $table->integer('post_id')->unsigned()->index();
        });
    }

    /**
     * Create test data set.
     */
    protected function dataSetUp()
    {
        DB::table('users')->insert([
           [
               'full_name' => 'Ivan Test',
           ],
           [
               'full_name' => 'Jefrey Test'
           ],
           [
               'full_name' => 'Jenna Test'
           ],
        ]);

        DB::table('posts')->insert([
            [
                'user_id' => 1,
                'title' => 'First post',
                'created_at' => \Carbon\Carbon::createFromDate(2017,1,10)
            ],
            [
                'user_id' => 1,
                'title' => 'Second post',
                'created_at' => \Carbon\Carbon::createFromDate(2017,1,15)
            ],
            [
                'user_id' => 3,
                'title' => 'Third post',
                'created_at' => \Carbon\Carbon::createFromDate(2017,1,30)
            ],
        ]);

        DB::table('comments')->insert([
            [
                'user_id' => 1,
                'post_id' => 2,
                'text' => 'Good post test'
            ],
            [
                'user_id' => 2,
                'post_id' => 3,
                'text' => 'Bad post tost'
            ],
        ]);

        DB::table('post_viewers')->insert([
            [
                'user_id' => 1,
                'post_id' => 1
            ],
            [
                'user_id' => 1,
                'post_id' => 2
            ],
            [
                'user_id' => 1,
                'post_id' => 3
            ],
            [
                'user_id' => 2,
                'post_id' => 1
            ],
            [
                'user_id' => 2,
                'post_id' => 3
            ],
            [
                'user_id' => 3,
                'post_id' => 2
            ],
        ]);
    }

    /**
     * Drop tables after all tested.
     */
    protected function dropTables()
    {
        DB::schema()->drop('post_viewers');
        DB::schema()->drop('posts');
        DB::schema()->drop('users');
    }

    /**
     * Set new data to request.
     *
     * @param array $arr
     * @return array|\Illuminate\Http\Request|string
     */
    public function setRequest(array $arr)
    {
        $request = new \Illuminate\Http\Request;
        $request->replace($arr);
        return $request;
    }
}

class User extends \Illuminate\Database\Eloquent\Model
{
    use \Nemesis\FilterAndSorting\FilterAndSorting;

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function viewed()
    {
        return $this->belongsToMany(Post::class, 'post_viewers');
    }

    public function extraFields()
    {
        return ['posts','viewed'];
    }
}

class Post extends \Illuminate\Database\Eloquent\Model
{
    use \Nemesis\FilterAndSorting\FilterAndSorting;

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function viewers()
    {
        return $this->belongsToMany(User::class, 'post_viewers');
    }

    public function extraFields()
    {
        return ['owner','viewers', 'owner.posts'];
    }
}

class Comment extends \Illuminate\Database\Eloquent\Model
{
    use \Nemesis\FilterAndSorting\FilterAndSorting;

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function extraFields()
    {
        return ['owner','post', 'post.owner'];
    }
}