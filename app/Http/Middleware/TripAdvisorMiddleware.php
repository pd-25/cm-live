<?php
namespace App\Http\Middleware;
use Closure;
use Exception;
class TripAdvisorMiddleware
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
    protected $prefix = 'basic';
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
            $auth=base64_decode($matches[1]);
        }
        else
        {
            $auth=false;
        }
        if(!$auth) {
            // Unauthorized response if token not there
            return response()->json(array('status'=>'Error','message'=>'Invalid Credentials!')); 
        }
        
        if($auth!='Tripadvisor:@B00k1ng#'){
            return response()->json(array('status'=>'Error','message'=>'Invalid Credentials!')); 
        }
        return $next($request);
    }
}
