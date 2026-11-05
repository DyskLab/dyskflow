<?php

add_action('cms.body_open', function () {
    $message = plugin_setting('hello-bar', 'message', 'Welcome to our site');
    $link = plugin_setting('hello-bar', 'link');

    if (! $message) {
        return;
    }

    echo '<div style="padding:8px 16px;text-align:center;font-size:14px;background:var(--primary);color:var(--primary-foreground)">';
    echo $link ? '<a href="'.e($link).'">'.e($message).'</a>' : e($message);
    echo '</div>';
});
