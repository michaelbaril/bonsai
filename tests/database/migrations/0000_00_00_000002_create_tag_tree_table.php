<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTagTreeTable extends Migration
{
    protected $mainTableName = 'tags';
    protected $closureTableName = 'tag_tree';
    protected $mainTableKey = 'id';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->closureTableName, function (Blueprint $table) {
            $table->foreignId('ancestor_id')->constrained($this->mainTableName)->onDelete('cascade');
            $table->foreignId('descendant_id')->constrained($this->mainTableName)->onDelete('cascade');
            $table->unsignedSmallInteger('depth');

            $table->unique(['ancestor_id', 'descendant_id']);
            $table->unique(['descendant_id', 'depth']);
            $table->index('depth');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists($this->closureTableName);
    }
}
