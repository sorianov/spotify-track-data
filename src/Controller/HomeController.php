<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

class HomeController
{
    public function index() {
        return new Response("<a href='/login'>Log In</a>");
    }
}