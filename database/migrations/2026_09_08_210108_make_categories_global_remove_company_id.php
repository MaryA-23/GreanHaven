<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | MERGE EXACT DUPLICATE CATEGORY NAMES
        |--------------------------------------------------------------------------
        |
        | Because categories previously belonged to individual companies,
        | different companies may have created categories with the same name.
        |
        | Example:
        |
        | Company A -> Vegetables
        | Company B -> Vegetables
        |
        | We keep the first category and move products from duplicate
        | categories into the first category.
        |
        */

        $categories = DB::table('categories')
            ->orderBy('id')
            ->get();

        $masterCategories = [];

        foreach ($categories as $category) {

            /*
            |--------------------------------------------------------------------------
            | Normalize name for duplicate checking
            |--------------------------------------------------------------------------
            */

            $normalizedName = Str::lower(
                trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        $category->name
                    )
                )
            );


            /*
            |--------------------------------------------------------------------------
            | First occurrence becomes master
            |--------------------------------------------------------------------------
            */

            if (!isset($masterCategories[$normalizedName])) {

                $masterCategories[$normalizedName] = $category->id;

                DB::table('categories')
                    ->where('id', $category->id)
                    ->update([
                        'name' => trim($category->name),
                    ]);

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate found
            |--------------------------------------------------------------------------
            */

            $masterCategoryId =
                $masterCategories[$normalizedName];


            /*
            |--------------------------------------------------------------------------
            | Move products to master category
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('products')) {

                DB::table('products')
                    ->where(
                        'category_id',
                        $category->id
                    )
                    ->update([
                        'category_id' =>
                            $masterCategoryId,
                    ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Keep an image if master category has none
            |--------------------------------------------------------------------------
            */

            $masterCategory = DB::table('categories')
                ->where(
                    'id',
                    $masterCategoryId
                )
                ->first();

            if (
                $masterCategory &&
                empty($masterCategory->image) &&
                !empty($category->image)
            ) {
                DB::table('categories')
                    ->where(
                        'id',
                        $masterCategoryId
                    )
                    ->update([
                        'image' => $category->image,
                    ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Delete duplicate category
            |--------------------------------------------------------------------------
            */

            DB::table('categories')
                ->where(
                    'id',
                    $category->id
                )
                ->delete();
        }


        /*
        |--------------------------------------------------------------------------
        | REMOVE COMPANY OWNERSHIP
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                'categories',
                'company_id'
            )
        ) {

            Schema::table(
                'categories',
                function (Blueprint $table) {

                    $table->dropForeign([
                        'company_id',
                    ]);

                    $table->dropColumn(
                        'company_id'
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | MAKE CATEGORY NAME UNIQUE
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'categories',
            function (Blueprint $table) {

                $table->unique(
                    'name',
                    'categories_name_unique'
                );
            }
        );
    }


    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | REMOVE UNIQUE INDEX
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'categories',
            function (Blueprint $table) {

                $table->dropUnique(
                    'categories_name_unique'
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | RESTORE COMPANY_ID
        |--------------------------------------------------------------------------
        |
        | We can restore the column, but old company ownership cannot be
        | reconstructed automatically after categories become global.
        |
        */

        Schema::table(
            'categories',
            function (Blueprint $table) {

                $table
                    ->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->nullOnDelete();
            }
        );
    }
};