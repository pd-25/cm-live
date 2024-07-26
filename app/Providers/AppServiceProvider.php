<?php

namespace App\Providers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('uuid', function ($attribute, $value, $parameters, $validator) {
            return (bool) preg_match('/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/', $value);
        });
        Validator::extend("emails", function($attribute, $value, $parameters) {
            $rules = [
                'email' => 'required|email',
            ];
            foreach ($value as $email) {
                $data = [
                    'email' => $email
                ];
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    return false;
                }
            }
            return true;
        });
        Validator::extend("mobiles", function($attribute, $value, $parameters) {
            $rules = [
                'mobile' => 'required|numeric:10',
            ];
            foreach ($value as $mobile) {
                $data = [
                    'mobile' => $mobile
                ];
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    return false;
                }
            }
            return true;
        });
    }
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('mailer', function ($app) {
            $app->configure('services');
            return $app->loadComponent('mail', 'Illuminate\Mail\MailServiceProvider', 'mailer');
        });
       
    }

    }
   
