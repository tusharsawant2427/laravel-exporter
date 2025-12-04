<?php

namespace LaravelExporter\Concerns;

/**
 * Remember the chunk offset during chunked imports
 *
 * Similar to Maatwebsite\Excel\Concerns\RemembersChunkOffset
 */
trait RemembersChunkOffset
{
    protected int $chunkOffset = 0;

    /**
     * Set the current chunk offset
     */
    public function setChunkOffset(int $offset): void
    {
        $this->chunkOffset = $offset;
    }

    /**
     * Get the current chunk offset
     */
    public function getChunkOffset(): int
    {
        return $this->chunkOffset;
    }
}
