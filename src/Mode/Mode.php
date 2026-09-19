<?php

namespace Talal\LabelPrinter\Mode;

use Talal\LabelPrinter\Command\CommandInterface;

abstract class Mode
{
    /**
     * @var array
     */
    protected $commands = [];
    
    /**
     * @var resource
     */
    protected $resource;

    /**
     * @param resource $resource
     */
    public function setResource($resource)
    {
        if (! is_resource($resource)) {
            throw new \InvalidArgumentException('An invalid resource has been provided.');
        }

        $this->resource = $resource;
    }

    /**
     * Closes the connection resource.
     */
    public function closeResource()
    {
        fclose($this->resource);
    }

    /**
     * @param CommandInterface $command
     */
    public function addCommand(CommandInterface $command)
    {
        $this->commands[] = $command;
    }

    /**
     * @return CommandInterface[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * @param string    $data
     * @return string
     */
    public function sendCommand($data)
    {
        if (function_exists('stream_set_blocking')) {
            @stream_set_blocking($this->resource, true);
        }

        $length = strlen($data);
        $offset = 0;
        $blockedWrites = 0;

        while ($offset < $length) {
            $written = @fwrite($this->resource, substr($data, $offset, 4096));

            if ($written === false || $written === 0) {
                if (++$blockedWrites > 100) {
                    throw new \RuntimeException('Printer did not accept all data.');
                }

                usleep(100000);
                continue;
            }

            $offset += $written;
            $blockedWrites = 0;
        }

        fflush($this->resource);

        return $data;
    }

    /**
     * Send the print-label command
     *
     * @return void
     */
    abstract public function process();
}
