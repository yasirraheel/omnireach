<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('android_apk_versions', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->nullable()->index();
            $table->string('version', 30)->unique();
            $table->string('file_name');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('android_apk_versions');
    }
};
