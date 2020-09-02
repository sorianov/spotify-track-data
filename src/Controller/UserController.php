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
    const CARDS_PER_ROW = 2;

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

    /**
     * Retrieve tracks from a playlist using Spotify's API
     *
     * @param string $accessToken Access token given by Spotify API.
     * @param string $playlistId  ID of the playlist to fetch tracks for.
     * @param int    $currentPage 'Page' of the playlist. Will be used to calculate playlist offset.
     * @return array
     */
    private function playlistTracks($accessToken, $playlistId, $currentPage) {
        $api = new SpotifyWebAPI();
        $api->setAccessToken($accessToken);

        $next = null;
        $options = [
            'limit'     => self::SPOTIFY_WEB_API_SONG_LIMIT,
            'offset'    => ($currentPage - 1) * self::SPOTIFY_WEB_API_SONG_LIMIT
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

        return [
            'tracks'        =>  $tracksWithFeatures,
            'total_pages'   =>  $totalPages,
        ];
    }

    public function playlistTracksCards(Request $request, SessionInterface $session)
    {
        $accessToken = $session->get('accessToken');
        $currentPage = $request->attributes->getInt('page', 1);
        $playlistId  = $request->attributes->getAlnum('playlist_id');

        $trackData = $this->playlistTracks($accessToken, $playlistId, $currentPage);

        return $this->render('playlist_tracks_cards.html.twig', [
            'cards_per_row'         =>  self::CARDS_PER_ROW,
            'tracks'                =>  $trackData['tracks'],
            'current_page'          =>  $request->attributes->getInt('page', 1),
            'total_pages'           =>  $trackData['total_pages'],
            'playlist_id'           =>  $request->attributes->getAlnum('playlist_id'),
        ]);
    }

    public function playlistTracksTable(Request $request, SessionInterface $session)
    {
        $accessToken = $session->get('accessToken');
        $currentPage = $request->attributes->getInt('page', 1);
        $playlistId  = $request->attributes->getAlnum('playlist_id');

        $trackData = $this->playlistTracks($accessToken, $playlistId, $currentPage);

        return $this->render('playlist_tracks.html.twig', [
            'tracks'                =>  $trackData['tracks'],
            'current_page'          =>  $request->attributes->getInt('page', 1),
            'total_pages'           =>  $trackData['total_pages'],
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

        return new Response('<a href="/playlists">Playlists</a>');
    } 

   }