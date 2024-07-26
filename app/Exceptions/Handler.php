<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Response;
class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    { 
        //To handle the token provided with authorization header
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException) {
              return response()->json(['token_not_provided'], $e->getStatusCode()); 
        }
        else if ($e instanceof Tymon\JWTAuth\Exceptions\TokenExpiredException) {
            return response()->json(['token_expired'], $e->getStatusCode());
        } 
        else if ($e instanceof Tymon\JWTAuth\Exceptions\TokenInvalidException) {
            return response()->json(['token_invalid'], $e->getStatusCode());
        }
        else if($e instanceof Tymon\JWTAuth\Exceptions\JWTException){
            return response()->json(['token_absent'], $e->getStatusCode());
        }
        
        return parent::render($request, $e);
    } 
}
