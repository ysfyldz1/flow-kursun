<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Diske yazmadan, akış halinde sıkıştırmasız (store) ZIP üretir.
 * Tüm girdiler ZIP64 biçimindedir; 4 GB üstü dosya ve arşivler desteklenir.
 * Sıkıştırma olmadığı için arşiv boyutu önceden bilinir (Content-Length).
 */
final class ZipStream
{
    private const LOCAL_HEADER = 30;
    private const LOCAL_EXTRA = 20;      // ZIP64 extra: tag(2) + size(2) + 2 x 8 bayt
    private const DESCRIPTOR = 24;       // imza(4) + crc(4) + 2 x 8 bayt
    private const CENTRAL_HEADER = 46;
    private const CENTRAL_EXTRA = 28;    // ZIP64 extra: tag(2) + size(2) + 3 x 8 bayt
    private const END_RECORDS = 56 + 20 + 22; // ZIP64 EOCD + ZIP64 locator + EOCD

    private int $offset = 0;
    private array $entries = [];
    private ?array $current = null;
    private int $dosTime;
    private int $dosDate;

    public function __construct()
    {
        $t = getdate();
        $this->dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $this->dosDate = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
    }

    /** @param array<array{string,int}> $files [[ad, boyut], ...] */
    public static function predictSize(array $files): int
    {
        $total = self::END_RECORDS;
        foreach ($files as [$name, $size]) {
            $n = strlen($name);
            $total += self::LOCAL_HEADER + $n + self::LOCAL_EXTRA + $size + self::DESCRIPTOR
                + self::CENTRAL_HEADER + $n + self::CENTRAL_EXTRA;
        }
        return $total;
    }

    public function beginFile(string $name): void
    {
        $this->current = ['name' => $name, 'offset' => $this->offset, 'size' => 0, 'hash' => hash_init('crc32b')];
        $this->out(pack('VvvvvvVVVvv', 0x04034b50, 45, 0x0808, 0, $this->dosTime, $this->dosDate, 0, 0xFFFFFFFF, 0xFFFFFFFF, strlen($name), self::LOCAL_EXTRA)
            . $name
            . pack('vvPP', 0x0001, 16, 0, 0));
    }

    public function write(string $data): void
    {
        hash_update($this->current['hash'], $data);
        $this->current['size'] += strlen($data);
        $this->out($data);
    }

    public function endFile(): void
    {
        $e = $this->current;
        $e['crc'] = hexdec(hash_final($e['hash']));
        unset($e['hash']);
        $this->out(pack('VVPP', 0x08074b50, $e['crc'], $e['size'], $e['size']));
        $this->entries[] = $e;
        $this->current = null;
        flush();
    }

    public function finish(): void
    {
        $cdStart = $this->offset;
        foreach ($this->entries as $e) {
            $this->out(pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 45, 45, 0x0808, 0, $this->dosTime, $this->dosDate, $e['crc'], 0xFFFFFFFF, 0xFFFFFFFF, strlen($e['name']), self::CENTRAL_EXTRA, 0, 0, 0, 0, 0xFFFFFFFF)
                . $e['name']
                . pack('vvPPP', 0x0001, 24, $e['size'], $e['size'], $e['offset']));
        }
        $cdSize = $this->offset - $cdStart;
        $zip64End = $this->offset;
        $count = count($this->entries);
        $this->out(pack('VPvvVVPPPP', 0x06064b50, 44, 45, 45, 0, 0, $count, $count, $cdSize, $cdStart));
        $this->out(pack('VVPV', 0x07064b50, 0, $zip64End, 1));
        $this->out(pack('VvvvvVVv', 0x06054b50, 0, 0, 0xFFFF, 0xFFFF, 0xFFFFFFFF, 0xFFFFFFFF, 0));
        flush();
    }

    private function out(string $bytes): void
    {
        echo $bytes;
        $this->offset += strlen($bytes);
    }
}
