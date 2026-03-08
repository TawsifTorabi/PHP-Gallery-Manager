<?php

$profiles = [

    "default" => [
        "content_selector" => "//body",
        "link_selector" => "//a",
        "media_selectors" => [
            "//img",
            "//video/source",
            "//audio/source"
        ]
    ],

    "instagram.com" => [
        "content_selector" => "//div[@class='cPost_contentWrap']",
        "link_selector" => "//div[@class='cPost_contentWrap']//a",
        "media_selectors" => [
            "//div[@class='cPost_contentWrap']//img"
        ]
    ],

];