<?php

namespace App;

use DomDocument;

class LikeMediaPopulator
{

    /**
     * @param string[] $authHeader
     */
    public function __construct(protected array $authHeader)
    {
    }

    public function populate(array &$likes): void
    {
        $this->tryPopulating($likes);
    }

    public function tryPopulating(array &$likes, int $retries = 1): void
    {
        if ($retries < 0) {
            return;
        }

        $supportedLangs = ['fr', 'en'];

        echo PHP_EOL . 'Getting supported langs ...';
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
        curl_close($supportedLangsCurl);

        echo ' Done !';

        $channelVideos = [];
        $channelAudios = [];

        $channelStorageUrl = 'https://storage.miniggiodev.fr/youtube-likes-recap/channel/';
        $clipApiUrl = 'https://youtube-video-random-clip-api.miniggiodev.fr/';
        $outputClipUrl = $clipApiUrl . 'public/video/';

        foreach ($likes as $index => &$like) {
            echo PHP_EOL . ($index + 1) . '/' . count($likes);
            $channelId = $like['channel_id'];
            $channelVideo = null;

            echo ' Video ?';
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
                curl_close($channelVideoCurl);
            } else {
                $channelVideo = $channelVideos[$channelId];
            }

            $like['channel_video'] = $channelVideo;
            echo ' ' . ($channelVideo !== null ? 'None' : 'Got one') . ' !';

            echo ' Audio ?';
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
                    curl_close($langCurl);
                }
                $channelAudios[$channelId] = $speechToTextAPI . '/' . urlencode($like['channel_name']) . '?lang=' . $lang;
            }

            $like['channel_audio'] = $channelAudios[$channelId];
            echo ' Done !';

            echo ' Clip ?';
            $videoId = $like['youtube_id'];
            $videoClipCurl = curl_init($clipApiUrl . $videoId);
            curl_setopt_array($videoClipCurl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $this->authHeader
            ]);
            curl_exec($videoClipCurl);
            $httpCode = curl_getinfo($videoClipCurl)['http_code'];
            curl_close($videoClipCurl);

            $like['video_clip'] = $httpCode === 204 ? ($outputClipUrl . $videoId) : null;

            echo ' ' . ($like['video_clip'] ? 'Got one' : 'None') . ' !';
        }

        $nextRetryValue = $retries - 1;
        echo PHP_EOL . PHP_EOL . $nextRetryValue . ' retrie(s) left';

        $this->tryPopulating($likes, $nextRetryValue);
    }
}
