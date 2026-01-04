<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\SqlServerBuilder;

class CreateNodeWithCustomSetupClosuresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schema = Schema::getFacadeRoot();
        $shouldAddConstraints = !($schema instanceof SqlServerBuilder);

        Schema::create('node_with_custom_setup_closures', function (Blueprint $table) use ($shouldAddConstraints) {
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\NodeWithCustomSetup::class, 'ancestor_id')
                ->when($shouldAddConstraints, function ($foreignId) {
                    $foreignId->constrained('nodes_with_custom_setup')->onDelete('cascade');
                });
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\NodeWithCustomSetup::class, 'descendant_id')
                ->when($shouldAddConstraints, function ($foreignId) {
                    $foreignId->constrained('nodes_with_custom_setup')->onDelete('cascade');
                });
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
        Schema::dropIfExists('node_with_custom_setup_closures');
    }
}
