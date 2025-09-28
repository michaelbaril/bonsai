<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSoftDeletableNodeTreeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soft_deletable_node_tree', function (Blueprint $table) {
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\SoftDeletableNode::class, 'ancestor_id')->constrained('soft_deletable_nodes')->onDelete('cascade');
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\SoftDeletableNode::class, 'descendant_id')->constrained('soft_deletable_nodes')->onDelete('cascade');
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
        Schema::dropIfExists('soft_deletable_node_tree');
    }
}
