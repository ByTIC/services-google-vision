<?php

/**
 * Inspired From
 * https://github.com/ThijsFeryn/google-cloud-vision-api
 */

namespace ByTIC\Services\Google\Vision;

use Intervention\Image\Image as ImageObj;
use Intervention\Image\ImageManagerStatic as ImageManager;

class Image
{

    protected $_id;

    protected $_name;

    protected $_response;

    /**
     * @var ImageObj
     */
    protected $_resource;

    /**
     * @param $path
     * @return self
     */
    public static function fromFile($path)
    {
        $return = new self();
        $return->setResource(ImageManager::make($path));
        $name = $return->getResource()->basename;
        $return->setName($name);
        $return->setId(sha1($path));

        return $return;
    }

    /**
     * @param $url
     * @return self
     */
    public static function fromUrl($url)
    {
        $return = new self();
        $return->setResource(ImageManager::make($url));
        $name = $return->getResource()->basename;
        $return->setName($name);
        $return->setId(sha1($url));

        return $return;
    }

    /**
     * @param $content
     * @param $name
     * @return self
     */
    public static function fromRaw($content, $name)
    {
        $return = new self();
        $return->setResource(ImageManager::make($content));
        $return->setName($name);
        $return->setId(sha1($name));

        return $return;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    public function generateId()
    {
        return sha1(microtime());
    }

    /**
     * @return ImageObj
     */
    public function getResource()
    {
        return $this->_resource;
    }

    /**
     * @param ImageObj $resource
     */
    public function setResource($resource)
    {
        $this->_resource = $resource;
    }

    public function getContent()
    {
        return (string)$this->getResource()->encode('jpg', 100);
    }

    public function getRequestContent()
    {
        return base64_encode($this->getContent());
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param mixed $response
     */
    public function setResponse($response)
    {
        $this->_response = $response;
    }

    public function drawTextBoundingPoly()
    {
        $response = $this->getResponse();
        if (isset($response->textAnnotations)) {
            foreach ($response->textAnnotations as $annotation) {
                $this->drawAnnotation($annotation);
            }
        }
    }


    public function drawAnnotation($annotation)
    {
        $this->drawTextFromAnnotation($annotation);
        $this->drawBoundingPoly($annotation->boundingPoly);
    }

    public function drawTextFromAnnotation($annotation)
    {
        $x = $annotation->boundingPoly->vertices[3]->x;
        $y = $annotation->boundingPoly->vertices[3]->y;

        $this->getResource()->text($annotation->description, $x, $y, function ($font) {
            $font->file(dirname(__FILE__).DIRECTORY_SEPARATOR.'fonts'.DIRECTORY_SEPARATOR.'OpenSans-Light.ttf');
            $font->size(14);
            $font->color(array(0, 0, 0, 1));
            $font->align('left');
            $font->valign('top');
            $font->angle(-15);
        });
    }

    public function drawBoundingPoly($poly)
    {
        $points = [];

        foreach ($poly->vertices as $point) {
            $points[] = $point->x;
            $points[] = $point->y;
        }

        // draw a filled blue polygon with red border
        $this->getResource()->polygon($points, function ($draw) {
//            $draw->background('#0000ff');
            $draw->border(1, '#ff0000');
        });

    }
}