# youtube-likes-to-social-medias

Every repos involved in this project : 

Main cron script : https://github.com/pierreminiggio/youtube-likes-to-social-medias

Things the cron script uses :

Getting my likes from Youtube API (cron from my old website) : https://github.com/pierreminiggio/old.miniggiodev.fr/blob/main/test/twitter/youtubescraping.php

Get likes for today's video : https://github.com/pierreminiggio/old.miniggiodev.fr/blob/main/api/getVideoableLikes.php

Getting Google Traduction's voice mp3 from text : https://gtts-api.miniggiodev.fr (https://github.com/pierreminiggio/gtts-api)

Some prerecorded clips : https://storage.miniggiodev.fr/youtube-likes-recap/

Getting random clips from videos : https://youtube-video-random-clip-api.miniggiodev.fr (https://github.com/pierreminiggio/youtube-video-random-clip-api)

Getting lang from Youtube channels' coutries (to adapt the Google Trad's voice) : https://country-to-lang-api.miniggiodev.fr (https://github.com/pierreminiggio/country-to-lang-api)

The remotion project : https://github.com/pierreminiggio/youtube-likes-recap-video-maker (Right now called using the CLI, looking to call it through a Github action instead)

Posting to Youtube, using Heropost & Youtube API : https://github.com/pierreminiggio/heropost-and-youtube-api-based-video-poster

Find Youtube channels' twitter handles (to tag them on Twitter that I liked their stuff) : https://twitter-handle-finder-api.miniggiodev.fr (https://github.com/pierreminiggio/youtube-channel-twitter-handle-finder-api)

Mark liked videos as videoed : https://github.com/pierreminiggio/old.miniggiodev.fr/blob/main/api/setAsVideoed.php

Migration
```sql
CREATE TABLE `channel_storage`.`like_recap` ( `id` INT NOT NULL AUTO_INCREMENT , `tweet_id` VARCHAR(255) NOT NULL , `tweet_date` DATETIME NOT NULL , `tweet_content` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL , `video_link` VARCHAR(255) NULL , `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP , PRIMARY KEY (`id`)) ENGINE = InnoDB;
```