<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUnconstrainedNodeTreeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('unconstrained_node_tree', function (Blueprint $table) {
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\UnconstrainedNode::class, 'ancestor_id');
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\UnconstrainedNode::class, 'descendant_id');
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
        Schema::dropIfExists('unconstrained_node_tree');
    }
}
