<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUnconstrainedNodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('unconstrained_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\UnconstrainedNode::class, 'parent_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('unconstrained_nodes');
    }
}
