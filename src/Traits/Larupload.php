<?php

namespace Mostafaznv\Larupload\Traits;

use stdClass;
use Exception;
use Mostafaznv\Larupload\Models\LaruploadFFMpegQueue;
use Mostafaznv\Larupload\Storage\Attachment;

trait Larupload
{
    /**
     * Holds the hash value for the current LARUPLOAD_NULL constant
     *
     * @var string
     */
    protected static $laruploadNull;

    /**
     * Uploaded flag to prevent infinite loop
     *
     * @var bool
     */
    protected static $uploaded = false;

    /**
     * All of the model's current file attachments
     *
     * @var array
     */
    protected $attachedFiles = [];

    /**
     * Boot the Larupload trait for the model
     * Register eloquent event handlers
     *
     */
    public static function bootLarupload()
    {
        static::$laruploadNull = crc32(time());

        if (!defined('LARUPLOAD_NULL')) {
            define('LARUPLOAD_NULL', static::$laruploadNull);
        }

        static::saved(function($model) {
            if (!self::$uploaded) {
                self::$uploaded = true;

                foreach ($model->attachedFiles as $name => $attachedFile) {
                    $model = $attachedFile->saved($model);
                }

                $model->save();
            }
        });

        static::deleted(function($model) {
            foreach ($model->attachedFiles as $name => $attachedFile) {
                if ($model->forceDeleting) {
                    $attachedFile->deleted($model);
                }
            }
        });
    }

    /**
     * Add a new file attachment type to the list of available attachments
     * This function acts as a quasi constructor for this trait
     *
     * @param string $name
     * @param array $options
     * @throws Exception
     */
    public function hasUploadFile(string $name, array $options = []): void
    {
        $folder = self::getTable();
        $attachment = new Attachment($name, $folder, $options);

        $this->attachedFiles[$name] = $attachment;
    }

    /**
     * Assign file and cover to registered fields
     *
     * @param $key
     * @param $file
     * @param null $cover
     *
     * @return bool
     */
    public function setUploadedFile(string $key, $file, $cover = null): bool
    {
        if (array_key_exists($key, $this->attachedFiles)) {
            if ($file) {
                static::$uploaded = false;

                $attachedFile = $this->attachedFiles[$key];
                $attachedFile->setUploadedFile($file, $cover);
            }

            return true;
        }

        return false;
    }

    /**
     * Handle the dynamic setting of attachment objects
     *
     * @param string $key
     * @param mixed $value
     */
    public function setAttribute($key, $value)
    {
        if (!$this->setUploadedFile($key, $value)) {
            parent::setAttribute($key, $value);
        }
    }

    /**
     * Handle the dynamic retrieval of attachment objects
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attachedFiles)) {
            return $this->getFiles($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Get all of the current attributes and attachment objects on the model
     *
     * @return mixed
     */
    /*public function getAttributes()
    {
        return array_merge(parent::getAttributes(), $this->attachedFiles);
    }*/

    /**
     * Remove attachment from get dirty array
     * Fix for getAttributes()
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = parent::getDirty();

        return array_filter($dirty, function($var) {
            return !($var instanceof Attachment);
        });
    }

    /**
     * Get URL for specified style of attachable field
     *
     * @param string $name
     * @param string $style
     * @return null|string
     */
    public function laruploadUrl(string $name, string $style = 'original')
    {
        if (array_key_exists($name, $this->attachedFiles) and $this->attributes['id']) {
            return $this->attachedFiles[$name]->url($this, $style);
        }

        return null;
    }

    /**
     * Get URL for specified style of attachable field
     *
     * @param string $name
     * @param string $style
     * @return null|string
     */
    public function url(string $name, string $style = 'original')
    {
        return $this->laruploadUrl($name, $style);
    }

    /**
     * Download specified style of attachable field
     *
     * @param string $name
     * @param string $style
     * @return null|string
     */
    public function laruploadDownload(string $name, string $style = 'original')
    {
        if (array_key_exists($name, $this->attachedFiles) and $this->attributes['id']) {
            return $this->attachedFiles[$name]->download($this, $style);
        }

        return null;
    }

    /**
     * Download specified style of attachable field
     *
     * @param string $name
     * @param string $style
     * @return null|string
     */
    public function download(string $name, string $style = 'original')
    {
        return $this->laruploadDownload($name, $style);
    }

    /**
     * Get All styles (original, cover and ...) for attached field
     *
     * @param string $name
     * @return object|null
     */
    public function getFiles(string $name = null)
    {
        if ($name) {
            if (isset($this->attachedFiles[$name])) {
                return $this->attachedFiles[$name]->getFiles($this);
            }

            return null;
        }
        else {
            $files = new stdClass();
            foreach ($this->attachedFiles as $name => $attachedFile) {
                $files->{$name} = $attachedFile->getFiles($this);
            }

            return $files;
        }
    }

    /**
     * Get meta data as an array or object
     *
     * @param string $name
     * @param string $key
     * @return object|string|integer|null
     */
    public function meta(string $name, string $key = null)
    {
        if (array_key_exists($name, $this->attachedFiles) and $this->attributes['id']) {
            return $this->attachedFiles[$name]->getMeta($this, $key);
        }

        return ($key) ? null : new stdClass();
    }

    /**
     * Retrieve latest status log for ffmpeg queue process
     *
     * @return mixed
     */
    public function laruploadQueue()
    {
        return $this->hasOne(LaruploadFFMpegQueue::class, 'record_id')->where('record_class', self::class)->orderBy('id', 'desc');
    }

    /**
     * Retrieve all status logs for ffmpeg queue process
     *
     * @return mixed
     */
    public function laruploadQueues()
    {
        return $this->hasMany(LaruploadFFMpegQueue::class, 'record_id')->where('record_class', self::class)->orderBy('id', 'desc');
    }
}
