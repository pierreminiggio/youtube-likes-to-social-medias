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

        $channelVideos = [];

        $channelStorageUrl = 'https://storage.miniggiodev.fr/youtube-likes-recap/channel/';

        foreach ($likes as &$like) {
            $channelId = $like['channel_id'];

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

                    $channelVideos[$channelId] = $channelStorageUrl . $channelId . '/' . $videos[array_rand($videos)];
                } else {
                    $channelVideos[$channelId] = 'test';
                    // TODO make placeholder video
                }
            }

            $like['channel_video'] = $channelVideos[$channelId];
            var_dump($like['channel_video']);
        }
    }
}
