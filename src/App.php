<?php

namespace App;

use DomDocument;

class App
{
    public function run(): void
    {
        $config = require
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
            . 'config.php'
        ;
        $token = $config['apiToken'];
        
        $likeCurl = curl_init('https://old.miniggiodev.fr/api/getVideoableLikes.php');

        curl_setopt_array($likeCurl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json' , 'Authorization: Bearer ' . $token]
        ]);

        $likeCurlResponse = curl_exec($likeCurl);

        if ($likeCurlResponse === false) {
            return;
        }

        $likes = json_decode($likeCurlResponse, true);

        if ($likes === null) {
            return;
        }

        $supportedLangs = ['fr', 'en'];

        $speechToTextAPI = 'https://gtts-api.miniggiodev.fr';
        $supportedLangsCurl = curl_init($speechToTextAPI);
        curl_setopt($supportedLangsCurl, CURLOPT_RETURNTRANSFER, true);
        $supportedLangsCurlResponse = curl_exec($supportedLangsCurl);

        if (! empty($supportedLangsCurlResponse)) {
            $supportedLangsCurlJsonResponse = json_decode($supportedLangsCurlResponse, true);

            if (! empty($supportedLangsCurlJsonResponse) && ! empty($supportedLangsCurlJsonResponse['langs'])) {
                $supportedLangs = $supportedLangsCurlJsonResponse['langs'];
            }
        }

        $channelVideos = [];
        $channelAudios = [];

        $channelStorageUrl = 'https://storage.miniggiodev.fr/youtube-likes-recap/channel/';

        foreach ($likes as &$like) {
            $channelId = $like['channel_id'];

            $channelVideo = null;

            if (! in_array($channelId, array_keys($channelVideos))) {
                $channelVideoCurl = curl_init($channelStorageUrl . $channelId . '/');
                curl_setopt($channelVideoCurl, CURLOPT_RETURNTRANSFER, true);
                $channelVideoCurlResponse = curl_exec($channelVideoCurl);
                $httpCode = curl_getinfo($channelVideoCurl)['http_code'];

                if ($httpCode === 200) {
                    $dom = new DomDocument();
                    $dom->loadHTML($channelVideoCurlResponse);
                    $links = $dom->getElementsByTagName('a');

                    $videos = [];
                    foreach ($links as $link) {
                        $content = $link->textContent;
                        $videoExt = '.webm';
                        if (str_contains($content, $videoExt)) {
                            $videos[] = substr($content, 0, - strlen($videoExt));
                        }
                    }

                    $channelVideo = $channelStorageUrl . $channelId . '/' . $videos[array_rand($videos)];
                    $channelVideos[$channelId] = $channelVideo;
                }
            } else {
                $channelVideo = $channelVideos[$channelId];
            }

            $like['channel_video'] = $channelVideo;

            if (! in_array($channelId, array_keys($channelAudios))) {
                $lang = 'en';

                if (! empty($like['channel_country'])) {
                    $langCurl = curl_init('https://country-to-lang-api.miniggiodev.fr/' . $like['channel_country']);
                    curl_setopt($langCurl, CURLOPT_RETURNTRANSFER, true);
                    $curlResponse = curl_exec($langCurl);

                    if (! empty($curlResponse)) {
                        $jsonResponse = json_decode($curlResponse, true);
                        if (! empty($jsonResponse) && $jsonResponse['lang']) {
                            $fetchedLang = $jsonResponse['lang'];

                            if (in_array($fetchedLang, $supportedLangs)) {
                                $lang = $fetchedLang;
                            }
                        }
                    }
                }
                $channelAudios[$channelId] = $speechToTextAPI . '/' . urlencode($like['channel_name']) . '?lang=' . $lang;
            }

            $like['channel_audio'] = $channelAudios[$channelId];
        }
    }
}
