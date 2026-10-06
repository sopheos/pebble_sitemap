<?php

use Pebble\Sitemap\GzWriter;
use PHPUnit\Framework\TestCase;

class GzWriterTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/pebble_sitemap_' . uniqid() . '.gz';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    // -------------------------------------------------------------------------
    // Nominal
    // -------------------------------------------------------------------------

    public function testOpenWriteCloseProducesAGzipFile()
    {
        $writer = new GzWriter($this->file);
        $writer->open();
        $writer->write('hello ');
        $writer->write('world');
        $writer->close();

        self::assertSame($this->file, $writer->filename());
        self::assertSame('hello world', gzdecode(file_get_contents($this->file)));
    }

    public function testIsOpenFollowsOpenAndClose()
    {
        $writer = new GzWriter($this->file);
        self::assertFalse($writer->isOpen());

        $writer->open();
        self::assertTrue($writer->isOpen());

        $writer->close();
        self::assertFalse($writer->isOpen());

        $writer->close();
        self::assertFalse($writer->isOpen());
    }

    public function testOpenTruncatesAnExistingFile()
    {
        file_put_contents($this->file, gzencode('old content'));

        $writer = new GzWriter($this->file);
        $writer->open();
        $writer->write('new');
        $writer->close();

        self::assertSame('new', gzdecode(file_get_contents($this->file)));
    }

    public function testWriteBeforeOpenThrowsATypeError()
    {
        $this->expectException(TypeError::class);
        (new GzWriter($this->file))->write('x');
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testFailedOpenIsSilentAndWriteThrowsATypeError()
    {
        // BUG: open() turns a gzopen() failure into a null stream without
        // throwing; the error only surfaces at write() as gzwrite(null).
        $writer = new GzWriter(sys_get_temp_dir() . '/pebble_sitemap_missing_' . uniqid() . '/x.gz');
        @$writer->open();

        self::assertFalse($writer->isOpen());

        $this->expectException(TypeError::class);
        $writer->write('x');
    }
}
