<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNodesWithUuidTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nodes_with_uuid', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\NodeWithUuid::class, 'parent_id')->nullable();
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
        Schema::dropIfExists('nodes_with_uuid');
    }
}
