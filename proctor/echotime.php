<?php
    list($usec, $timestamp) = explode(" ", microtime());
    print '{"st":'.(($timestamp)*1000+round(floatval($usec)*1000)).'}';
?>