<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\SqlServerBuilder;

class CreateNodeWithUuidTreeTable extends Migration
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

        Schema::create('node_with_uuid_tree', function (Blueprint $table) use ($shouldAddConstraints) {
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\NodeWithUuid::class, 'ancestor_id')
                ->when($shouldAddConstraints, function ($foreignId) {
                    $foreignId->constrained('nodes_with_uuid')->onDelete('cascade');
                });
            $table->foreignIdFor(\Baril\Bonsai\Tests\Models\NodeWithUuid::class, 'descendant_id')
                ->when($shouldAddConstraints, function ($foreignId) {
                    $foreignId->constrained('nodes_with_uuid')->onDelete('cascade');
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
        Schema::dropIfExists('node_with_uuid_tree');
    }
}
