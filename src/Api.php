<?php

/**
 * Inspired From
 * https://github.com/ThijsFeryn/google-cloud-vision-api
 */

namespace ByTIC\Services\Google\Vision;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Promise;

class Api
{
    /**
     * @var GuzzleClientInterface
     */
    protected $guzzleClient;
    /**
     * @var string
     */
    protected $apiEndpoint = "https://vision.googleapis.com/v1";
    /**
     * @var string
     */
    protected $apiUrl;
    /**
     * @var string[]
    /**
     * @var string[]
     */
    protected $images = [];
    /**
     * @var string[]
     */
    protected $features = [];
    /**
     * @var string
     */
    protected $apiKey;

    const FEATURE_LABEL_DETECTION = 'LABEL_DETECTION';
    const FEATURE_TEXT_DETECTION = 'TEXT_DETECTION';
    const FEATURE_FACE_DETECTION = 'FACE_DETECTION';
    const FEATURE_LANDMARK_DETECTION = 'LANDMARK_DETECTION';
    const FEATURE_LOGO_DETECTION = 'LOGO_DETECTION';
    const FEATURE_SAFE_SEARCH_DETECTION = 'SAFE_SEARCH_DETECTION';
    const FEATURE_IMAGE_PROPERTIES = 'IMAGE_PROPERTIES';
    /**
     * @var string[]
     */
    protected $availableFeatures = [
        'LABEL_DETECTION',
        'TEXT_DETECTION',
        'FACE_DETECTION',
        'LANDMARK_DETECTION',
        'LOGO_DETECTION',
        'SAFE_SEARCH_DETECTION',
        'IMAGE_PROPERTIES',
    ];

    public function __construct($apiKey, GuzzleClientInterface $guzzleClient = null)
    {
        $this->apiKey = (string)$apiKey;
        $this->apiUrl = $this->apiEndpoint."/images:annotate?key=".$this->apiKey;
        if ($guzzleClient != null) {
            $this->guzzleClient = $guzzleClient;
        } else {
            $this->guzzleClient = new GuzzleClient();
        }
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $this->apiEndpoint."/images:annotate?key=".$this->apiKey;

        return $this;
    }

    /**
     * @param Image $image
     * @return $this
     * @throws Exception
     */
    public function addImage($image)
    {
        $name = $image->getName();
        $id = $image->getId();

        if (isset($this->images[$id])) {
            throw new Exception("Image '{$name}' already added");
        }

        $this->images[$id] = $image;

        return $this;
    }

    public function addRawImage($image, $name)
    {
        $image = Image::fromRaw($image, $name);
        $this->addImage($image);

        return $image;
    }

    public function addImageByFilename($filename)
    {
        $image = Image::fromFile($filename);
        $this->addImage($image);

        return $image;
    }

    public function addImageByUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new Exception("'{$url}' is not a valid URL");
        }
        $image = Image::fromUrl($url);
        $this->addImage($image);

        return $this;
    }

    public function addFeature($feature, $maxResults = 1)
    {
        if (!in_array($feature, $this->availableFeatures)) {
            throw new Exception("Feature '{$feature}' does not exist");
        }
        $this->features[$feature] = $maxResults;

        return $this;
    }

    protected function checkRequirements()
    {
        if (empty($this->apiKey)) {
            throw new Exception("API Key cannot be empty");
        }

        if (empty($this->features)) {
            throw new Exception("Features cannot be empty");
        }

        if (empty($this->images) && empty($this->imageFilenames) && empty($this->imageUrls)) {
            throw new Exception("Images cannot by empty");
        }
    }

    protected function yieldAsyncPromisesForPostRequests($batchSize)
    {
        foreach ($this->createRequests($batchSize) as $key => $request) {
            yield $key => $this->guzzleClient->postAsync($this->apiUrl,
                ['headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($request)]);
        }
    }

    /**
     * @return Image[]
     */
    protected function getImages()
    {
        return $this->images;
    }

    protected function createRequests($batchSize = 10)
    {
        $features = [];
        $requests = [];
        $counter = 0;
        $ids = [];
        foreach ($this->features as $feature => $maxResults) {
            $features[] = ['type' => $feature, 'maxResults' => $maxResults];
        }
        foreach ($this->getImages() as $id => $image) {
            if ($counter == $batchSize) {
                $counter = 0;
                $keys = base64_encode(json_encode($ids));
                yield $keys => ['requests' => $requests];
                $requests = [];
                $ids = [];
            }
            $requests[] = [
                'image' => ['content' => $image->getRequestContent()],
                'features' => $features,
            ];
            $ids[] = $id;
            $counter++;
        }
        $keys = base64_encode(json_encode($ids));
        yield $keys => ['requests' => $requests];
    }

    protected function processResponse($index, $data)
    {
        $indexes = json_decode(base64_decode($index));
        foreach ($data->responses as $key => $response) {
            yield $indexes[$key] => $response;
        }
    }

    /**
     * @param int $batchSize
     * @return \Generator|Image[]
     */
    public function request($batchSize = 10)
    {
        $this->checkRequirements();
        $results = Promise\unwrap($this->yieldAsyncPromisesForPostRequests($batchSize));
        foreach ($results as $index => $result) {
            $processedResponse = $this->processResponse($index, json_decode($result->getBody()->getContents()));
            foreach ($processedResponse as $key => $response) {
                $image = $this->images[$key];
                $image->setResponse($response);
                yield $key => $image;
            }
        }
    }
}