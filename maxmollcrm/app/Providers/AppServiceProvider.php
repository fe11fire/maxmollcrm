<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        Validator::extend('multi_date_format', function ($attribute, $value, $parameters, $validator) {

            $ok = true;

            $result = [];

            foreach ($parameters as $parameter) {
                $result[] = $validator->validateDateFormat($attribute, $value, [$parameter]);
            }

            if (!in_array(true, $result)) {
                $ok = false;
                $validator->setCustomMessages(['multi_date_format' => 'Wrong format']);
            }

            return $ok;
        });
    }
}
