<?php

use Sauladam\ShipmentTracker\DataProviders\DataProviderInterface;

class FileMapperDataProvider implements DataProviderInterface
{
    /**
     * @var
     */
    private $carrier;

    /**
     * @var
     */
    private $fileName;

    /**
     * @var array
     */
    protected $lookup = [];


    /**
     * UrlToFileMatcherClient constructor.
     *
     * @param string       $carrier
     * @param string|array $fileName
     */
    public function __construct($carrier, $fileName)
    {
        $this->carrier = $carrier;

        if (is_array($fileName)) {
            $this->lookup = $fileName;
        } else {
            $this->fileName = $fileName;
        }
    }


    /**
     * Request the given url.
     *
     * @param string $url
     *
     * @return string
     */
    public function get($url)
    {
        return file_get_contents($this->mapToFile($url));
    }


    /**
     * Map the url to a path.
     *
     * @param string $url
     *
     * @return string
     * @throws Exception
     */
    protected function mapToFile($url)
    {
        $basePath = __DIR__ . '/mock/' . $this->carrier . '/';

        if ($this->fileName) {
            return $basePath . $this->fileName;
        }

        if (!array_key_exists($url, $this->lookup)) {
            throw  new Exception("Could not resolve URL [{$url}] to a path.");
        }

        return $basePath . $this->lookup[$url];
    }
}
