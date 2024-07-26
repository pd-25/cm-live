<?php

if ( ! function_exists('config_path'))
{
    /**
     * Get the configuration path.
     *
     * @param  string $path
     * @return string
     */
    function config_path($path = '')
    {
        return rtrim(app()->basePath() . '/config' . ($path ? '/' . $path : $path));
    }
    
    if(!function_exists('public_path'))
    {
            /**
            * Return the path to public dir
            * @param null $path
            * @return string
            */
            function public_path($path=null)
            {
                    return rtrim(app()->basePath('public/'.$path), '/');
            }
    }
    if(!function_exists('storage_path'))
    {
            /**
            * Return the path to storage dir
            * @param null $path
            * @return string
            */
            function storage_path($path=null)
            {
                    return app()->storagePath($path);
            }
    }
    if(!function_exists('database_path'))
    {
            /**
            * Return the path to database dir
            * @param null $path
            * @return string
            */
            function database_path($path=null)
            {
                    return app()->databasePath($path);
            }
    }   
}