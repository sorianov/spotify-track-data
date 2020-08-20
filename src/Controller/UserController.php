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
    const SPOTIFY_WEB_API_SONG_LIMIT = 50;
    const SPOTIFY_WEB_API_FEATURE_LIMIT = 100;

    public function playlists(SessionInterface $session) {
        $api = new SpotifyWebAPI();
        $accessToken = $session->get('accessToken');
        $api->setAccessToken($accessToken);
        $lists = [];
        $next = null;
        $limit = self::SPOTIFY_WEB_API_SONG_LIMIT;
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

        $getParams = $request->attributes->get('_route_params');
        $playlistId = $getParams['playlist_id'];

        $next = null;
        $options = [
            'limit'     => self::SPOTIFY_WEB_API_SONG_LIMIT,
            'offset'    => ($request->attributes->getInt('page', 1) - 1) * self::SPOTIFY_WEB_API_SONG_LIMIT
        ];

        $playlistTracks = $api->getPlaylistTracks($playlistId, $options);

        $tracks = array_column($playlistTracks->items, 'track');
        $trackIds = array_column($tracks, 'id');

        $trackFeatures = [];
        $audioFeatures = $api->getAudioFeatures($trackIds)->audio_features;
        foreach ($audioFeatures as $feature) {
            if (is_object($feature)) {
                $trackFeatures[$feature->id] = $feature;
            }
        }

        $tracksWithFeatures = [];
        foreach ($tracks as $track) {
            if (array_key_exists($track->id, $trackFeatures)) {
                $tracksWithFeatures[] = [
                    'track' => $track,
                    'features' => $trackFeatures[$track->id]
                ];
            }
        }
        $totalPages = ceil($playlistTracks->total / self::SPOTIFY_WEB_API_SONG_LIMIT);

        return $this->render('playlist_tracks_cards.html.twig', [
            'tracks'                =>  $tracksWithFeatures,
            'current_page'          =>  $request->attributes->getInt('page', 1),
            'total_pages'           =>  $totalPages,
            'playlist_id'           =>  $request->attributes->getAlnum('playlist_id'),
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