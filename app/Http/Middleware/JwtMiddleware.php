<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\UserCredential;
use App\AdminUser;
use App\User;
use App\SuperAdmin;
use App\Reseller;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class JwtMiddleware
{   
    /**
    * The header name.
    *
    * @var string
    */
    protected $header = 'authorization';
    /**
     * The header prefix.
     *
     * @var string
     */
    protected $prefix = 'bearer';
     /**
     * Custom parameters.
     *
     * @var \Symfony\Component\HttpFoundation\ParameterBag
     *
     * @api
     */
    public $attributes;
    protected function fromAltHeaders(Request $request)
    {
        return $request->server->get('HTTP_AUTHORIZATION') ?: $request->server->get('REDIRECT_HTTP_AUTHORIZATION');
    }
    public function handle( $request, Closure $next, $guard = null)
    {
        $header = $request->headers->get($this->header) ?: $this->fromAltHeaders($request);
        if ($header && preg_match('/'.$this->prefix.'\s*(.*)\b/i', $header, $matches))
        {
            $token=$matches[1];
        }
        else
        {
            $token=false;
        }
        if(!$token) {
            // Unauthorized response if token not there
            return response()->json([
                'error' => 'Token not provided.'
            ], 401);
        }
        try {
            $credentials = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        } catch(ExpiredException $e) {
            return response()->json([
                'error' => 'Provided token is expired.'
            ], 400);
        } catch(Exception $e) {
            return response()->json([
                'error' => 'An error while decoding token.'
            ], 400);
        }
        if($credentials->type == env('PUBLIC_USER'))
        {
            $user = User::where('email_id', $credentials->sub)->where('company_id', $credentials->company_id)->first();
        }
        elseif($credentials->type == env('SUPER_ADMIN'))
        {
            $user = SuperAdmin::where('username', $credentials->sub)->first();
        }
        elseif($credentials->type == env('RESELLER_USER'))
        {
            $user = Reseller::where('username', $credentials->sub)->first();
        }
        else
        {
            $user = AdminUser::where('username', $credentials->sub)->first();
        }
        // Now let's put the user in the request class so that you can grab it from there
        $request->auth = $user; 
        $request->attributes->add(['scope' => $credentials->scope]);
        return $next($request);
    }
}
