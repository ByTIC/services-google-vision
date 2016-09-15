<?php

define('BASE_PATH', dirname(__FILE__).DIRECTORY_SEPARATOR);

require '../vendor/autoload.php';

$api = new ByTIC\Services\Google\Vision\Api("AIzaSyAdXpA3muIEaOU8mCi4lWyHP8gveHRL0xQ");
$api->addFeature($api::FEATURE_TEXT_DETECTION, 100);

$image = \ByTIC\Services\Google\Vision\Image::fromFile(BASE_PATH.'photos\IMG_0820.JPG');
$api->addImage($image);


$image = \ByTIC\Services\Google\Vision\Image::fromFile(BASE_PATH.'photos\IMG_0821.JPG');
$api->addImage($image);

$result = $api->request(10);

foreach ($result as $key => $image) {
    $image->drawTextBoundingPoly();
    echo $image->getResource()->response();
    die();
}