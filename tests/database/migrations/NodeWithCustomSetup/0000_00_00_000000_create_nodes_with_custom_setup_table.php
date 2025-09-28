<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNodesWithCustomSetupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nodes_with_custom_setup', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\NodeWithCustomSetup::class, 'parent_key')->nullable();
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
        Schema::dropIfExists('nodes_with_custom_setup');
    }
}
