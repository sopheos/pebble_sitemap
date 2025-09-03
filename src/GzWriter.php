<?php

namespace Pebble\Sitemap;

/**
 * Create sitemap files
 */
class GzWriter
{
    /**
     * @var resource
     */
    private $stream = null;
    private ?string $filename = null;

    // -------------------------------------------------------------------------

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function isOpen(): bool
    {
        return $this->stream !== null;
    }

    public function open()
    {
        if (! $this->stream) {
            $this->stream = gzopen($this->filename, 'w') ?: null;
        }
    }

    public function close()
    {
        if ($this->stream) {
            gzclose($this->stream);
            $this->stream = null;
        }
    }

    public function write(string $content)
    {
        gzwrite($this->stream, $content);
    }
}
