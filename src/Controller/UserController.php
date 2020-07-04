<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use SpotifyWebAPI\Session as SpotifySession;
use SpotifyWebAPI\SpotifyWebAPI;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;


class UserController extends AbstractController
{
    public function playlists(SessionInterface $session) {
        $api = new SpotifyWebAPI();
        $accessToken = $session->get('accessToken');
        $api->setAccessToken($accessToken);
        $lists = [];
        $next = null;
        $limit = 50;
        $offset = 0;
        $options = [
            'limit'     => $limit,
            'offset'    => $offset
        ];
        $playlists = $api->getMyPlaylists($options);
        $lists = array_merge($lists, $playlists->items);
        while ($playlists->next) {
            $next = parse_url($playlists->next);
            $nextQuery = explode('&', $next['query']);
            $offset = explode('=', $nextQuery[0])[1];
            $limit = explode('=', $nextQuery[1])[1];
            $options = [
                'limit'     => $limit,
                'offset'    => $offset,
            ];
            $playlists = $api->getMyPlaylists($options);
            $lists = array_merge($lists, $playlists->items);
        }
        sort($lists);

        return $this->render('playlists.html.twig', [
            'playlists'     => $playlists,
            'next'          => $next,
            'offset'        => $offset,
            'limit'         => $limit,
            'lists'         => $lists
        ]);
    }

    public function playlistTracks(Request $request, SessionInterface $session) {
        $api = new SpotifyWebAPI();
        $accessToken = $session->get('accessToken');
        $api->setAccessToken($accessToken);

        $lists = [];
        $next = null;
        $limit = 50;
        $offset = 0;
        $options = [
            'limit'     => $limit,
            'offset'    => $offset
        ];

        $getParams = $request->attributes->get('_route_params');
        $playlistId = $getParams['playlistId'];
        $playlistTracks = $api->getPlaylistTracks($playlistId, $options);
        
        $lists = array_merge($lists, $playlistTracks->items);
        while ($playlistTracks->next) {
            $next = parse_url($playlistTracks->next);
            $nextQuery = explode('&', $next['query']);
            $offset = explode('=', $nextQuery[0])[1];
            $limit = explode('=', $nextQuery[1])[1];
            $options = [
                'limit'     => intval($limit),
                'offset'    => intval($offset),
            ];
            $playlistTracks = $api->getPlaylistTracks($playlistId, $options);
            $lists = array_merge($lists, $playlistTracks->items);
        }
        
        $tracks = array_map(function ($e) {
            return $e->track;
        }, $lists);

        $trackIds = array_map(function ($e) {
            return $e->id;
        }, $tracks);

        $trackFeatures = [];
        $audioFeatures = $api->getAudioFeatures($trackIds)->audio_features;
        foreach ($audioFeatures as $feature) {
            $trackFeatures[$feature->id] = $feature;
        }

        $tracks_with_features = [];
        foreach ($tracks as $track) {
            $tracks_with_features[$track->id] = [
                'track'     =>  $track,
                'features'  =>  $trackFeatures[$track->id]
            ];
        }

        return $this->render('playlist_tracks.html.twig', [
            'tracks'    =>  $tracks_with_features
        ]);
    }

    public function menu(SessionInterface $session) {
        $clientId = $this->getParameter('app.spotify.client.id');
        $clientSecret = $this->getParameter('app.spotify.client.secret');
        $spotifySession = new SpotifySession(
            $clientId,
            $clientSecret,
            'http://localhost:8000/menu'
        );
        
        // Request a access token using the code from Spotify
        $spotifySession->requestAccessToken($_GET['code']);
        
        $accessToken = $spotifySession->getAccessToken();
        $refreshToken = $spotifySession->getRefreshToken();
        $session->set('accessToken', $accessToken);
        $session->set('refreshToken', $refreshToken);

        return new Response("<a href='/playlists'>Playlists</a>");
    } 

   }