<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="RHC API Documentation",
 *     description="API documentation for the RHC backend built with Laravel",
 *     @OA\Contact(
 *         email="support@rhc.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 *
 * @OA\Server(
 *     url="https://api.rhc.com/api",
 *     description="Production Server"
 * )
 *
 * @OA\PathItem(
 *     path="/", 
 *     description="Root path of the API"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
