<?php

namespace App\Controller;

use SpotifyWebAPI\Session as SpotifySession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthController extends AbstractController
{
    public function login() {
        $clientId = $this->getParameter('app.spotify.client.id');
        $clientSecret = $this->getParameter('app.spotify.client.secret');
        $spotifySession = new SpotifySession(
            $clientId,
            $clientSecret,
            'http://localhost:8000/menu'
        );
        $options = [
            'scope' => [
                'playlist-read-collaborative',
                'playlist-read-private',
            ],
            'auto_refresh' => true
        ];
        
        header('Location: ' . $spotifySession->getAuthorizeUrl($options));
        exit; 
    }
}